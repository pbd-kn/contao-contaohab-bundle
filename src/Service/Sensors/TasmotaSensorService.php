<?php

declare(strict_types=1);

namespace PbdKn\ContaoContaohabBundle\Service\Sensors;

use Doctrine\DBAL\Connection;
use PbdKn\ContaoContaohabBundle\Model\SensorModel;
use PbdKn\ContaoContaohabBundle\Service\LoggerService;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class TasmotaSensorService implements SensorFetcherInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly Connection $connection,
        private readonly LoggerService $logger,
    ) {
    }

    public function supports(SensorModel $sensor): bool
    {
        return strtolower((string) $sensor->sensorSource) === 'tasmota';
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

            $mode = (string) ($settings['tasmotaAccess'] ?? 'local');
            if ($mode === 'disabled') {
                $this->logger->debugMe('Tasmota-Zugriff ist deaktiviert.');

                return [];
            }
            if (!in_array($mode, ['local', 'raspberry'], true)) {
                throw new \RuntimeException("Unbekannte Tasmota-Zugriffsart '$mode'.");
            }

            $deviceUrl = $this->normalizeDeviceUrl((string) $sensors[0]->geraeteUrl);
            $data = $mode === 'raspberry'
                ? $this->requestViaRaspberry($settings, $deviceUrl)
                : $this->requestLocal($deviceUrl);
            $data = $this->addMissingDailyValues($data, $deviceUrl);

            $result = [];
            foreach ($sensors as $sensor) {
                $sensorId = (string) $sensor->sensorID;
                $localId = trim((string) $sensor->sensorLokalId);
                $access = $localId !== '' ? $localId : $sensorId;

                if ($this->isSnapshotSensor($sensorId) || $this->isSnapshotSensor($access)) {
                    $value = $data;
                    $unit = (string) $sensor->sensorEinheit;
                } else {
                    $found = false;
                    $value = $this->valueFromPayload($data, $access, $found);
                    if (!$found) {
                        $this->logger->Error("Tasmota-Wert '$access' für SensorID '$sensorId' fehlt.");
                        continue;
                    }
                    $unit = (string) $sensor->sensorEinheit;
                    $transform = trim((string) $sensor->transFormProcedur);
                    if ($transform !== '' && $transform !== '-') {
                        if (!method_exists($this, $transform)) {
                            $this->logger->Error("Tasmota-Transformation '$transform' für SensorID '$sensorId' existiert nicht.");
                        } else {
                            $transformed = $this->{$transform}($value);
                            $value = $transformed['wert'];
                            $unit = $transformed['einheit'];
                        }
                    }
                }

                $result[$sensorId] = [
                    'sensorID' => $sensorId,
                    'sensorValue' => $value,
                    'sensorEinheit' => $unit,
                    'sensorValueType' => (string) $sensor->sensorValueType,
                    'sensorSource' => (string) $sensor->sensorSource,
                ];
            }

            return $result;
        } catch (\Throwable $error) {
            $this->logger->Error('Tasmota: ' . $error->getMessage());

            return [];
        }
    }

    private function requestLocal(string $deviceUrl): array
    {
        $url = rtrim($deviceUrl, '/') . '/cm?cmnd=' . rawurlencode('Status 10');
        $response = $this->httpClient->request('GET', $url, ['timeout' => 15]);
        $data = $response->toArray(false);
        if ($response->getStatusCode() !== 200 || !is_array($data)) {
            throw new \RuntimeException("Lokaler Abruf lieferte HTTP {$response->getStatusCode()}.");
        }

        return $data;
    }

    private function addMissingDailyValues(array $data, string $deviceUrl): array
    {
        $variables = [
            'Verbrauch_heute' => 'bez_tag',
            'Einspeisung_heute' => 'einsp_tag',
        ];

        foreach ($variables as $jsonName => $scriptVariable) {
            if (array_key_exists($jsonName, $data['StatusSNS'] ?? [])) {
                continue;
            }

            try {
                $data['StatusSNS'][$jsonName] = $this->requestScriptValue($deviceUrl, $scriptVariable);
            } catch (\Throwable $error) {
                $this->logger->debugMe("Tasmota-Tageswert '$jsonName' konnte nicht direkt nachgeladen werden: {$error->getMessage()}");
            }
        }

        return $data;
    }

    private function requestScriptValue(string $deviceUrl, string $variable): mixed
    {
        $url = rtrim($deviceUrl, '/') . '/cm?cmnd=' . rawurlencode('script?' . $variable);
        $response = $this->httpClient->request('GET', $url, ['timeout' => 15]);
        $payload = $response->toArray(false);
        if ($response->getStatusCode() !== 200 || !array_key_exists($variable, $payload['script'] ?? [])) {
            throw new \RuntimeException("Tasmota-Scriptwert '$variable' fehlt.");
        }

        return $payload['script'][$variable];
    }

    private function requestViaRaspberry(array $settings, string $deviceUrl): array
    {
        $baseUrl = rtrim(trim((string) ($settings['tasmotaRaspberryBaseUrl'] ?? '')), '/');
        $path = '/' . ltrim(trim((string) ($settings['tasmotaRaspberryPath'] ?? '/api/coh/tasmota.php')), '/');
        if ($baseUrl === '') {
            throw new \RuntimeException('Raspberry-Basis-URL für Tasmota fehlt.');
        }
        $response = $this->httpClient->request('GET', $baseUrl . $path, [
            'headers' => [
                'Accept' => 'application/json',
                'X-COH-TOKEN' => (string) ($settings['tasmotaRaspberryToken'] ?? ''),
            ],
            'query' => ['deviceUrl' => $deviceUrl],
            'timeout' => max(1, (int) ($settings['tasmotaRequestTimeout'] ?? 15)),
        ]);
        $payload = $response->toArray(false);
        if ($response->getStatusCode() !== 200 || empty($payload['ok']) || !is_array($payload['data'] ?? null)) {
            throw new \RuntimeException('Ungültige Raspberry-Tasmota-Antwort: ' . json_encode($payload, JSON_UNESCAPED_UNICODE));
        }

        return $payload['data'];
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

        return rtrim($url, '/');
    }

    private function valueFromPayload(array $data, string $access, bool &$found): mixed
    {
        if (array_key_exists($access, $data['StatusSNS']['M60'] ?? [])) {
            $found = true;

            return $data['StatusSNS']['M60'][$access];
        }

        $path = preg_replace('~^tasmota\.~i', '', trim($access));
        $cursor = $data;
        foreach (explode('.', (string) $path) as $part) {
            if (!is_array($cursor) || !array_key_exists($part, $cursor)) {
                $found = false;

                return null;
            }
            $cursor = $cursor[$part];
        }
        $found = true;

        return $cursor;
    }

    private function isSnapshotSensor(string $id): bool
    {
        $id = strtolower(trim($id));

        return $id === 'tasmota.akt' || $id === 'tasmota.alltasmota.akt';
    }

    private function tskWh(mixed $value): array
    {
        return ['wert' => $value, 'einheit' => 'kWh'];
    }

    private function tsWatt(mixed $value): array
    {
        return ['wert' => $value, 'einheit' => 'W'];
    }
}
