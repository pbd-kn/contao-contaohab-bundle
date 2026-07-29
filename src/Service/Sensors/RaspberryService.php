<?php

declare(strict_types=1);

namespace PbdKn\ContaoContaohabBundle\Service\Sensors;

use Doctrine\DBAL\Connection;
use PbdKn\ContaoContaohabBundle\Model\SensorModel;
use PbdKn\ContaoContaohabBundle\Service\LoggerService;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class RaspberryService implements SensorFetcherInterface
{
    private ?array $snapshot = null;
    private int $snapshotAt = 0;

    public function __construct(
        private readonly LoggerService $logger,
        private readonly Connection $connection,
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function supports(SensorModel $sensor): bool
    {
        return strtolower((string) $sensor->sensorSource) === 'raspberry';
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
            $values = $this->snapshot()['values'] ?? [];
        } catch (\Throwable $error) {
            $this->logger->Error('Raspberry HTTP: ' . $error->getMessage());
            return [];
        }

        $result = [];
        foreach ($sensors as $sensor) {
            $path = trim((string) $sensor->sensorLokalId);
            if ($path === '') {
                $this->logger->Error('Raspberry: sensorLokalId fehlt für ' . $sensor->sensorID);
                continue;
            }
            $found = false;
            $value = $this->pathValue($values, $path, $found);
            if (!$found) {
                $this->logger->Error("Raspberry: API-Wert '$path' fehlt für {$sensor->sensorID}");
                continue;
            }
            $result[(string) $sensor->sensorID] = [
                'sensorID' => (string) $sensor->sensorID,
                'sensorValue' => $value,
                'sensorEinheit' => (string) $sensor->sensorEinheit,
                'sensorValueType' => (string) $sensor->sensorValueType,
                'sensorSource' => (string) $sensor->sensorSource,
            ];
        }

        return $result;
    }

    private function snapshot(): array
    {
        $settings = $this->connection->fetchAssociative(
            'SELECT * FROM tl_coh_sensorcollector_settings ORDER BY id ASC LIMIT 1'
        );
        if (!$settings) {
            throw new \RuntimeException('Keine Sensorcollector-Einstellungen vorhanden.');
        }
        $mode = (string) ($settings['raspberryAccess'] ?? '');
        if (!in_array($mode, ['disabled', 'local', 'http'], true)) {
            $mode = !empty($settings['raspberryApiEnabled']) ? 'local' : 'disabled';
        }
        if ($mode === 'disabled') {
            throw new \RuntimeException('Raspberry-Zugriff ist deaktiviert.');
        }
        $cacheSeconds = max(0, (int) ($settings['raspberryApiCacheSeconds'] ?? 15));
        if ($this->snapshot !== null && time() - $this->snapshotAt < $cacheSeconds) {
            return $this->snapshot;
        }
        $baseUrlField = $mode === 'http' ? 'raspberryApiWanBaseUrl' : 'raspberryApiBaseUrl';
        $baseUrl = rtrim(trim((string) ($settings[$baseUrlField] ?? '')), '/');
        if ($baseUrl === '') {
            throw new \RuntimeException('Raspberry-API-Basis-URL fehlt.');
        }
        $path = '/' . ltrim(trim((string) ($settings['raspberryApiPath'] ?? '/api/coh/raspberry-status.php')), '/');
        $response = $this->httpClient->request('GET', $baseUrl . $path, [
            'headers' => [
                'Accept' => 'application/json',
                'X-COH-TOKEN' => (string) ($settings['raspberryApiToken'] ?? ''),
            ],
            'timeout' => max(1, (int) ($settings['raspberryApiTimeout'] ?? 10)),
        ]);
        $payload = $response->toArray(false);
        if ($response->getStatusCode() !== 200 || empty($payload['ok']) || !is_array($payload['values'] ?? null)) {
            throw new \RuntimeException('Ungültige Raspberry-API-Antwort: ' . json_encode($payload, JSON_UNESCAPED_UNICODE));
        }
        $this->snapshot = $payload;
        $this->snapshotAt = time();
        return $payload;
    }

    private function pathValue(array $values, string $path, bool &$found): mixed
    {
        $path = trim($path);
        if (strcasecmp($path, 'raspberry.all') === 0) {
            $found = true;
            return $values;
        }

        if (str_starts_with(strtolower($path), 'raspberry.')) {
            $path = substr($path, strlen('raspberry.'));
        }

        $cursor = $values;
        foreach (explode('.', $path) as $part) {
            if (!is_array($cursor) || !array_key_exists($part, $cursor)) {
                $found = false;
                return null;
            }
            $cursor = $cursor[$part];
        }
        $found = true;
        return $cursor;
    }
}
