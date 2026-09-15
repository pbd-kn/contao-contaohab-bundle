<?php
declare(strict_types=1);

namespace PbdKn\ContaoContaohabBundle\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

final class RaspberrySensorApiClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly array $settings,
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

    public function fetchRange(array $sensorIds, int $from, int $to, int $maxPoints = 100): array
    {
        $wanted = array_fill_keys(array_map('strval', $sensorIds), true);
        if ($wanted === []) return [];
        if ($from < 0 || $to <= $from) {
            throw new \InvalidArgumentException('Ungueltiger Sensorwerte-Zeitraum.');
        }

        $maxPoints = max(10, min(500, $maxPoints));

        return $this->requestRows($wanted, [
            'from' => $from,
            'to' => $to,
            'maxPoints' => $maxPoints,
        ]);
    }

    private function requestRows(array $wanted, array $query): array
    {
        $settings = $this->settings;
        $base = rtrim(trim((string)($settings['raspberryApiWanBaseUrl'] ?? '')), '/');
        if ($base === '') $base = rtrim(trim((string)($settings['raspberryApiBaseUrl'] ?? '')), '/');
        if (!str_starts_with(strtolower($base), 'https://')) {
            throw new \RuntimeException('Die Raspberry-Adresse fehlt oder beginnt nicht mit HTTPS. In der .env.local muss COH_RASPBERRY_API_BASE_URL=https://… eingetragen sein.');
        }
        $token = trim((string)($settings['raspberryApiToken'] ?? ''));
        if ($token === '') {
            throw new \RuntimeException('Der Raspberry-API-Token fehlt. Bitte COH_RASPBERRY_API_TOKEN in der .env.local eintragen.');
        }

        try {
            $response = $this->httpClient->request('GET', $base . '/api/coh/sensorvalues.php', [
                'headers' => ['X-COH-TOKEN' => $token],
                'query' => $query + ['sensorIDs' => implode(',', array_keys($wanted))],
                'timeout' => max(1, (int)($settings['raspberryApiTimeout'] ?? 15)),
            ]);
            $status = $response->getStatusCode();
            $payload = $response->toArray(false);
        } catch (TransportExceptionInterface $exception) {
            throw new \RuntimeException('Der Raspberry ist über die eingetragene HTTPS-Adresse nicht erreichbar. Bitte MyFRITZ-/Portfreigabe, DNS und TLS-Zertifikat prüfen.', 0, $exception);
        }

        if ($status === 401 || $status === 403) {
            throw new \RuntimeException('Der Raspberry hat den Zugriff abgelehnt. COH_RASPBERRY_API_TOKEN beim Hoster und COH_API_TOKEN auf dem Raspberry müssen identisch sein.');
        }
        if ($status === 404) {
            throw new \RuntimeException('Die Sensorwerte-API wurde auf dem Raspberry nicht gefunden. Bitte prüfen, ob /api/coh/sensorvalues.php installiert und über die HTTPS-Adresse erreichbar ist.');
        }
        if ($status >= 500) {
            throw new \RuntimeException(sprintf('Die Sensorwerte-API auf dem Raspberry meldet einen Serverfehler (HTTP %d).', $status));
        }
        if ($status !== 200 || empty($payload['ok']) || !is_array($payload['rows'] ?? null)) {
            throw new \RuntimeException(sprintf('Der Raspberry lieferte keine gültigen Sensorwerte (HTTP %d).', $status));
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
