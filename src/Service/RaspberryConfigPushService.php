<?php

declare(strict_types=1);

namespace PbdKn\ContaoContaohabBundle\Service;

use Doctrine\DBAL\Connection;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class RaspberryConfigPushService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly Connection $connection,
    ) {
    }

    /** @return array{devices: int, sensors: int} */
    public function push(): array
    {
        [$settings, $baseUrl, $token] = $this->getApiSettings();

        $devices = $this->connection->fetchAllAssociative('SELECT * FROM tl_coh_geraete ORDER BY id');
        $sensors = $this->connection->fetchAllAssociative(
            "SELECT * FROM tl_coh_sensors WHERE sensorActive = '1' ORDER BY id"
        );

        $response = $this->httpClient->request('POST', $baseUrl.'/api/coh/config_push.php', [
            'headers' => [
                'X-COH-TOKEN' => $token,
                'Accept' => 'application/json',
            ],
            'json' => [
                'devices' => $devices,
                'sensors' => $sensors,
            ],
            'timeout' => max(1, min(15, (int) ($settings['raspberryApiTimeout'] ?? 10))),
        ]);

        $payload = $response->toArray(false);

        if (200 !== $response->getStatusCode() || empty($payload['ok'])) {
            $error = is_string($payload['error'] ?? null) ? $payload['error'] : 'ungueltige API-Antwort';
            throw new \RuntimeException(sprintf('Raspberry meldet: %s', $error));
        }

        return ['devices' => count($devices), 'sensors' => count($sensors)];
    }

    /** @return array{devices: int, sensors: int} */
    public function pull(): array
    {
        [$settings, $baseUrl, $token] = $this->getApiSettings();

        $response = $this->httpClient->request('GET', $baseUrl.'/api/coh/config_push.php', [
            'headers' => [
                'X-COH-TOKEN' => $token,
                'Accept' => 'application/json',
            ],
            'timeout' => max(1, min(15, (int) ($settings['raspberryApiTimeout'] ?? 10))),
        ]);
        $payload = $response->toArray(false);

        if (
            200 !== $response->getStatusCode()
            || empty($payload['ok'])
            || !is_array($payload['devices'] ?? null)
            || !is_array($payload['sensors'] ?? null)
        ) {
            $error = is_string($payload['error'] ?? null) ? $payload['error'] : 'ungueltige API-Antwort';
            throw new \RuntimeException(sprintf('Raspberry meldet: %s', $error));
        }

        $this->connection->beginTransaction();

        try {
            $deviceCount = $this->mergeRows('tl_coh_geraete', 'geraeteID', $payload['devices']);
            $sensorCount = $this->mergeRows(
                'tl_coh_sensors',
                'sensorID',
                $payload['sensors'],
                ['historycount', 'lastUpdated', 'pollInterval', 'lastValue', 'lastError']
            );
            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }

        return ['devices' => $deviceCount, 'sensors' => $sensorCount];
    }

    /** @return array{0: array<string, mixed>, 1: string, 2: string} */
    private function getApiSettings(): array
    {
        $settings = $this->connection->fetchAssociative(
            'SELECT * FROM tl_coh_sensorcollector_settings ORDER BY id ASC LIMIT 1'
        );

        if (!$settings) {
            throw new \RuntimeException('Raspberry-API-Einstellungen fehlen.');
        }

        $baseUrl = rtrim(trim((string) ($settings['raspberryApiWanBaseUrl'] ?? '')), '/');
        $token = trim((string) ($settings['raspberryApiToken'] ?? ''));

        if (!str_starts_with(strtolower($baseUrl), 'https://')) {
            throw new \RuntimeException('Fuer die Raspberry-Konfigurationsuebertragung ist eine HTTPS-URL erforderlich.');
        }

        if ($token === '') {
            throw new \RuntimeException('Der Raspberry-API-Token fehlt.');
        }

        return [$settings, $baseUrl, $token];
    }

    private function mergeRows(
        string $table,
        string $identityField,
        array $rows,
        array $protectedFields = []
    ): int {
        $columns = array_fill_keys(
            $this->connection->fetchFirstColumn(sprintf('SHOW COLUMNS FROM `%s`', $table)),
            true
        );
        $ignoredFields = array_fill_keys(array_merge(['id', 'tstamp'], $protectedFields), true);
        $count = 0;

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $identity = trim((string) ($row[$identityField] ?? ''));
            if ($identity === '') {
                throw new \RuntimeException(sprintf('%s fehlt in der Raspberry-Antwort.', $identityField));
            }

            $data = array_diff_key(array_intersect_key($row, $columns), $ignoredFields);
            $existingId = $this->connection->fetchOne(
                sprintf('SELECT id FROM `%s` WHERE `%s` = ? ORDER BY id LIMIT 1', $table, $identityField),
                [$identity]
            );

            if (false === $existingId) {
                $data['tstamp'] = time();
                $this->connection->insert($table, $data);
            } else {
                $this->connection->update($table, $data, ['id' => (int) $existingId]);
            }

            ++$count;
        }

        return $count;
    }
}
