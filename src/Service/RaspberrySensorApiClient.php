<?php
declare(strict_types=1);

namespace PbdKn\ContaoContaohabBundle\Service;

use Doctrine\DBAL\Connection;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class RaspberrySensorApiClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly Connection $connection,
    ) {}

    public function fetchLatest(array $sensorIds): array
    {
        $wanted = array_fill_keys(array_map('strval', $sensorIds), true);
        if ($wanted === []) return [];
        $rows = $this->requestRows($wanted, ['latest' => 1]);
        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row['sensorID']] = $row;
        }
        return $result;
    }

    public function fetchRange(array $sensorIds, int $from, int $to): array
    {
        $wanted = array_fill_keys(array_map('strval', $sensorIds), true);
        if ($wanted === []) return [];
        if ($from < 0 || $to <= $from) {
            throw new \InvalidArgumentException('Ungueltiger Sensorwerte-Zeitraum.');
        }

        return $this->requestRows($wanted, ['from' => $from, 'to' => $to, 'bulk' => 1]);
    }

    private function requestRows(array $wanted, array $query): array
    {
        $settings = $this->connection->fetchAssociative(
            'SELECT * FROM tl_coh_sensorcollector_settings ORDER BY id ASC LIMIT 1'
        );
        if (!$settings) throw new \RuntimeException('Raspberry-API-Einstellungen fehlen.');
        $base = rtrim(trim((string)($settings['raspberryApiWanBaseUrl'] ?? '')), '/');
        if ($base === '') $base = rtrim(trim((string)($settings['raspberryApiBaseUrl'] ?? '')), '/');
        if (!str_starts_with(strtolower($base), 'https://')) {
            throw new \RuntimeException('Die Sensorwerte-API muss ueber HTTPS aufgerufen werden.');
        }
        $response = $this->httpClient->request('GET', $base . '/api/coh/sensorvalues.php', [
            'headers' => ['X-COH-TOKEN' => (string)($settings['raspberryApiToken'] ?? '')],
            'query' => $query + ['sensorIDs' => implode(',', array_keys($wanted))],
            'timeout' => max(1, (int)($settings['raspberryApiTimeout'] ?? 15)),
        ]);
        $payload = $response->toArray(false);
        if ($response->getStatusCode() !== 200 || empty($payload['ok']) || !is_array($payload['rows'] ?? null)) {
            throw new \RuntimeException('Ungueltige Antwort der Raspberry-Sensorwerte-API.');
        }
        $result = [];
        foreach ($payload['rows'] as $row) {
            $id = (string)($row['sensorID'] ?? '');
            if (!isset($wanted[$id])) continue;
            $value = $row['sensorValue'] ?? null;
            if (($row['sensorEinheit'] ?? '') === 'json' || ($row['sensorValueType'] ?? '') === 'json') {
                $decoded = is_string($value) ? json_decode($value, true) : null;
                if (json_last_error() === JSON_ERROR_NONE) $value = $decoded;
            }
            $row['sensorValue'] = $value;
            $result[] = $row;
        }
        return $result;
    }
}
