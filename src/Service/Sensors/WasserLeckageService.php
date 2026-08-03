<?php

declare(strict_types=1);

namespace PbdKn\ContaoContaohabBundle\Service\Sensors;

use Doctrine\DBAL\Connection;
use PbdKn\ContaoContaohabBundle\Model\SensorModel;
use PbdKn\ContaoContaohabBundle\Service\LoggerService;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class WasserLeckageService implements SensorFetcherInterface
{
    private const KEYS = [
        'VLV','BAT','FLO','BAR','CEL','PRF','SRN','VER','WIP','WGW','MAC1','EIP','EGW','MAC2','WFS','WFR',
        'ALA','WRN','NOT','ALM','ALW','ALN','VOL','CND','WTI','CEN','DSV','DRP','DTT','DTC','DOM','DST','DMA',
        'MM','DBD','DBT','DPL','DCM','AMA','ALD','SLP','SLE','SLV','SLT','SLF','SOF','SLO','SMF',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly Connection $connection,
        private readonly LoggerService $logger,
    ) {
    }

    public function supports(SensorModel $sensor): bool
    {
        return strtolower((string) $sensor->sensorSource) === 'wasserleckage';
    }

    public function fetch(SensorModel $sensor): ?array
    {
        return $this->fetchArr([$sensor])[(string) $sensor->sensorID] ?? null;
    }

    public function fetchArr(array $sensors, ?string $date = null, array $fetchedValues = []): ?array
    {
        if ($sensors === []) {
            return [];
        }

        try {
            $settings = $this->connection->fetchAssociative(
                'SELECT * FROM tl_coh_sensorcollector_settings ORDER BY id ASC LIMIT 1'
            );
            if (!$settings) {
                throw new \RuntimeException('Keine Sensorcollector-Einstellungen vorhanden.');
            }

            $mode = (string) ($settings['wasserLeckageAccess'] ?? 'local');
            if ($mode === 'disabled') {
                $this->logger->debugMe('Wasserleckage-Zugriff ist deaktiviert.');

                return [];
            }
            if (!in_array($mode, ['local', 'raspberry'], true)) {
                throw new \RuntimeException("Unbekannte Wasserleckage-Zugriffsart '$mode'.");
            }

            $deviceUrl = $this->normalizeDeviceUrl((string) $sensors[0]->geraeteUrl);
            $data = $mode === 'raspberry'
                ? $this->requestViaRaspberry($settings, $deviceUrl)
                : $this->requestLocal($deviceUrl, max(1, (int) ($settings['wasserLeckageRequestTimeout'] ?? 15)));

            $result = [];
            foreach ($sensors as $sensor) {
                $sensorId = (string) $sensor->sensorID;
                $localId = strtoupper(trim((string) $sensor->sensorLokalId));
                if ($localId === '') {
                    $this->logger->Error("WasserLeckage: sensorLokalId fehlt fuer SensorID '$sensorId'.");
                    continue;
                }

                if ($localId === 'ALL') {
                    $value = $data;
                    $unit = (string) $sensor->sensorEinheit;
                    $type = (string) $sensor->sensorValueType;
                } elseif ($localId === 'SMALL' || str_contains($localId, ':')) {
                    $keys = $localId === 'SMALL' ? self::KEYS : explode(':', $localId);
                    $value = $this->selectValues($data, $keys, $sensorId);
                    if ($value === null) {
                        continue;
                    }
                    $unit = (string) $sensor->sensorEinheit;
                    $type = (string) $sensor->sensorValueType;
                } elseif (!preg_match('/^[A-Z][A-Z0-9]*$/', $localId)) {
                    $this->logger->Error("WasserLeckage: Ungueltiger SYR-Schluessel '$localId' fuer SensorID '$sensorId'.");
                    continue;
                } elseif (!array_key_exists($localId, $data)) {
                    $this->logger->Error("WasserLeckage: SYR-Wert '$localId' fuer SensorID '$sensorId' fehlt.");
                    continue;
                } else {
                    [$value, $unit, $type] = $this->convert($localId, $data[$localId]);
                    $unit = $unit !== '' ? $unit : (string) $sensor->sensorEinheit;
                    $type = $type !== '' ? $type : (string) $sensor->sensorValueType;
                }

                $result[$sensorId] = [
                    'sensorID' => $sensorId,
                    'sensorValue' => $value,
                    'sensorEinheit' => $unit,
                    'sensorValueType' => $type,
                    'sensorSource' => (string) $sensor->sensorSource,
                    'outputMode' => (string) $sensor->outputMode,
                ];
            }

            return $result;
        } catch (\Throwable $error) {
            $this->logger->Error('WasserLeckage: ' . $error->getMessage());

            return [];
        }
    }

    private function requestLocal(string $deviceUrl, int $timeout): array
    {
        $raw = $this->requestSyr($deviceUrl, 'all', $timeout);
        $data = [];

        if ($raw !== []) {
            foreach ($raw as $payloadKey => $value) {
                if (!is_string($payloadKey) || !str_starts_with($payloadKey, 'get')) {
                    continue;
                }
                $key = strtoupper(substr($payloadKey, 3));
                if ($key !== '') {
                    $data[$key] = $value;
                }
            }
        } else {
            foreach (self::KEYS as $key) {
                $data[$key] = $this->singleValue(
                    $this->requestSyr($deviceUrl, strtolower($key), $timeout),
                    $key
                );
            }
        }

        // Diese Werte sind in /get/all fehlerhaft und werden immer separat gelesen.
        foreach (['ALM', 'ALW', 'ALN'] as $key) {
            $data[$key] = $this->singleValue(
                $this->requestSyr($deviceUrl, strtolower($key), $timeout),
                $key
            );
        }

        return $data;
    }

    private function requestViaRaspberry(array $settings, string $deviceUrl): array
    {
        $baseUrl = rtrim(trim((string) ($settings['wasserLeckageRaspberryBaseUrl'] ?? '')), '/');
        $path = '/' . ltrim(trim((string) ($settings['wasserLeckageRaspberryPath'] ?? '/api/coh/wasserleckage.php')), '/');
        if ($baseUrl === '') {
            throw new \RuntimeException('Raspberry-Basis-URL fuer Wasserleckage fehlt.');
        }

        $options = [
            'headers' => [
                'Accept' => 'application/json',
                'X-COH-TOKEN' => (string) ($settings['wasserLeckageRaspberryToken'] ?? ''),
            ],
            'query' => ['deviceUrl' => $deviceUrl],
            'timeout' => max(1, (int) ($settings['wasserLeckageRequestTimeout'] ?? 15)),
        ];

        // MyFRITZ kann per IPv6 auf der FRITZ!Box statt auf der IPv4-Portfreigabe landen.
        // Den Hostnamen deshalb dynamisch auf seinen aktuellen A-Record aufloesen.
        $host = (string) parse_url($baseUrl, PHP_URL_HOST);
        if ($host !== '' && filter_var($host, FILTER_VALIDATE_IP) === false) {
            $ipv4 = gethostbyname($host);
            if ($ipv4 !== $host && filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                $options['resolve'] = [$host => $ipv4];
            }
        }

        $response = $this->httpClient->request('GET', $baseUrl . $path, $options);
        $payload = $response->toArray(false);
        if ($response->getStatusCode() !== 200 || empty($payload['ok']) || !is_array($payload['data'] ?? null)) {
            throw new \RuntimeException('Ungueltige Raspberry-Wasserleckage-Antwort: ' . json_encode($payload, JSON_UNESCAPED_UNICODE));
        }

        return $payload['data'];
    }

    private function requestSyr(string $deviceUrl, string $command, int $timeout): array
    {
        $response = $this->httpClient->request(
            'GET',
            $deviceUrl . '/trio/get/' . rawurlencode($command),
            ['timeout' => $timeout]
        );
        $payload = $response->toArray(false);
        if ($response->getStatusCode() !== 200 || !is_array($payload)) {
            throw new \RuntimeException("SYR-Abruf '$command' lieferte HTTP {$response->getStatusCode()}.");
        }

        return $payload;
    }

    /**
     * @param list<string> $keys
     */
    private function selectValues(array $data, array $keys, string $sensorId): ?array
    {
        $selection = [];
        foreach ($keys as $key) {
            $key = strtoupper(trim($key));
            if ($key === '' || !preg_match('/^[A-Z][A-Z0-9]*$/', $key)) {
                $this->logger->Error("WasserLeckage: Ungueltiger SYR-Schluessel '$key' fuer SensorID '$sensorId'.");

                return null;
            }
            if (!array_key_exists($key, $data)) {
                $this->logger->Error("WasserLeckage: SYR-Wert '$key' fuer SensorID '$sensorId' fehlt.");
                continue;
            }
            $selection[$key] = $data[$key];
        }

        return $selection;
    }

    private function singleValue(array $payload, string $key): mixed
    {
        foreach (['get' . $key, $key] as $payloadKey) {
            if (array_key_exists($payloadKey, $payload)) {
                return $payload[$payloadKey];
            }
        }

        return count($payload) === 1 ? reset($payload) : null;
    }

    private function normalizeDeviceUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            throw new \RuntimeException('geraeteUrl fehlt.');
        }
        if (!preg_match('~^https?://~i', $url)) {
            $url = 'http://' . $url;
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            throw new \RuntimeException("Ungueltige geraeteUrl '$url'.");
        }
        $authority = (!empty($parts['user'])
            ? rawurlencode((string) $parts['user']) . (!empty($parts['pass']) ? ':' . rawurlencode((string) $parts['pass']) : '') . '@'
            : '')
            . (string) $parts['host'] . ':' . (int) ($parts['port'] ?? 5333);

        return strtolower((string) $parts['scheme']) . '://' . $authority . rtrim((string) ($parts['path'] ?? ''), '/');
    }

    private function convert(string $localId, mixed $value): array
    {
        return match ($localId) {
            'BAT' => [(float) $value / 100, 'V', 'float'],
            'CEL' => [(float) $value / 10, '°C', 'float'],
            'BAR' => [(float) $value / 1000, 'Bar', 'float'],
            'VOL' => [(float) $value, 'l', 'float'],
            'VLV' => [(int) $value, '', 'int'],
            default => [$value, '', ''],
        };
    }
}
