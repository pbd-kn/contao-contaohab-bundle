<?php

declare(strict_types=1);

namespace PbdKn\ContaoContaohabBundle\Service\Sensors;

use Doctrine\DBAL\Connection;
use PbdKn\ContaoContaohabBundle\Model\SensorModel;
use PbdKn\ContaoContaohabBundle\Service\LoggerService;

final class IQBoxSensorService implements SensorFetcherInterface
{
    private ?AmpereIqHttpAccess $cloud = null;

    public function __construct(
        private readonly LoggerService $logger,
        private readonly Connection $connection,
    ) {
    }

    public function supports(SensorModel $sensor): bool
    {
        return strtolower((string) $sensor->sensorSource) === 'iqbox';
    }

    public function fetch(SensorModel $sensor): ?array
    {
        return $this->fetchArr([$sensor])[(string) $sensor->sensorID] ?? null;
    }

    public function fetchArr(array $sensors, ?string $date = null, array $fetchedValues = []): ?array
    {
        $result = [];

        foreach ($sensors as $sensor) {
            $sensorId = (string) $sensor->sensorID;
            $selection = trim((string) $sensor->sensorLokalId);
            if ($selection === '') {
                $this->logger->Error("IQBox Cloud: sensorLokalId fehlt für Sensor $sensorId");
                continue;
            }
            try {
                $this->logger->debugMe("sensorId $sensorId lokalid $selection");
                $rawValue = $this->cloud()->getValue($selection, $date);
                $debugValue = is_array($rawValue) || is_object($rawValue)
                    ? json_encode($rawValue, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
                    : (string) $rawValue;
                $this->logger->debugMe("sensorId $sensorId lokalid $selection rawValue $debugValue");
                $row = [
                    'sensorID' => $sensorId,
                    'sensorEinheit' => (string) $sensor->sensorEinheit,
                    'sensorValueType' => (string) $sensor->sensorValueType,
                    'sensorSource' => (string) $sensor->sensorSource,
                ];

                if (str_starts_with(strtolower($selection), 'history.')) {
                    $row['historyPoints'] = $this->normalizeHistoryPoints($rawValue);
                    $row['sensorValue'] = $row['historyPoints'] !== []
                        ? $row['historyPoints'][array_key_last($row['historyPoints'])]['y']
                        : null;
                } else {
                    $row['sensorValue'] = $this->normalizeLiveValue($rawValue);
                }

                $result[$sensorId] = $row;
            } catch (\Throwable $error) {
                $this->logger->Error("IQBox Cloud: Fehler bei lokalId $selection: {$error->getMessage()}");
            }
        }

        return $result;
    }

    private function cloud(): AmpereIqHttpAccess
    {
        $settings = $this->connection->fetchAssociative(
            'SELECT * FROM tl_coh_sensorcollector_settings ORDER BY id ASC LIMIT 1'
        );
        if (!$settings) {
            throw new \RuntimeException('Ampere.IQ-Einstellungen fehlen im Contao-Backend.');
        }
        $tokens = json_decode((string) ($settings['ampereTokens'] ?? ''), true);
        $parameters = ['ampereIq' => [
            'username' => (string) ($settings['ampereUsername'] ?? ''),
            'password' => (string) ($settings['amperePassword'] ?? ''),
            'tokens' => is_array($tokens) ? $tokens : [],
            'retries' => max(1, (int) ($settings['ampereRetries'] ?? 3)),
            'retryDelay' => max(0, (int) ($settings['ampereRetryDelay'] ?? 10)),
            'lifetimeCacheSeconds' => max(0, (int) ($settings['lifetimeCacheSeconds'] ?? 60)),
        ]];
        // TaskAccess.php enthält auch den AmpereIqHttpAccess. Durch diesen
        // Aufruf ist die Datei geladen, bevor die Access-Klasse erzeugt wird.
        $logger = TaskAccess::loggerAdapter($this->logger);
        return $this->cloud ??= new AmpereIqHttpAccess(
            '',
            $parameters['ampereIq']['retries'],
            $parameters['ampereIq']['retryDelay'],
            $logger,
            $parameters['ampereIq']['lifetimeCacheSeconds'],
            $parameters,
            function (array $newTokens) use ($settings): void {
                $this->connection->update('tl_coh_sensorcollector_settings', [
                    'ampereTokens' => json_encode($newTokens, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'tstamp' => time(),
                ], ['id' => (int) $settings['id']]);
            },
        );
    }

    private function normalizeLiveValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            $this->logger->debugMe("normal  numeric ".$this->numericValue($value));
            return $this->numericValue($value);
        }

        foreach (['value', 'power', 'work', 'state', 'amount'] as $key) {
            if (array_key_exists($key, $value) && !is_array($value[$key])) {
                $this->logger->debugMe("value[$key] " .$value[$key]. " numeric ".$this->numericValue($value[$key]));

                return $this->numericValue($value[$key]);
            }
        }

        // Bereichsauswahlen wie "live" liefern die vollstaendige strukturierte
        // API-Antwort. Diese bleibt als Array erhalten; nur konkrete Feldpfade
        // werden oben zu einem einzelnen Sensorwert normalisiert.
        return $value;
    }

    /** @return list<array{x: string, y: int|float}> */
    private function normalizeHistoryPoints(mixed $payload): array
    {
        $points = [];
        $this->collectHistoryPoints($payload, $points);
        usort($points, static fn (array $a, array $b): int => strcmp($a['x'], $b['x']));

        $unique = [];
        foreach ($points as $point) {
            $unique[$point['x']] = $point;
        }

        return array_values($unique);
    }

    private function collectHistoryPoints(mixed $node, array &$points): void
    {
        if (!is_array($node)) {
            return;
        }

        $timestamp = $this->firstScalar($node, ['timestamp', 'time', 'date', 'datetime', 'x', 'from']);
        $value = $this->firstScalar($node, ['value', 'power', 'work', 'price', 'amount', 'y']);
        if ($timestamp !== null && $value !== null && is_numeric($value)) {
            $normalizedTime = $this->normalizeTimestamp($timestamp);
            if ($normalizedTime !== null) {
                $points[] = ['x' => $normalizedTime, 'y' => (float) $value];
            }
        }

        foreach ($node as $key => $child) {
            if (is_numeric($child) && is_string($key)) {
                $normalizedTime = $this->normalizeTimestamp($key);
                if ($normalizedTime !== null) {
                    $points[] = ['x' => $normalizedTime, 'y' => (float) $child];
                    continue;
                }
            }
            if (is_array($child)) {
                $this->collectHistoryPoints($child, $points);
            }
        }
    }

    private function firstScalar(array $values, array $keys): mixed
    {
        foreach ($keys as $wanted) {
            foreach ($values as $key => $value) {
                if (strtolower((string) $key) === strtolower($wanted) && !is_array($value)) {
                    return $value;
                }
            }
        }
        return null;
    }

    private function normalizeTimestamp(mixed $value): ?string
    {
        if (is_numeric($value)) {
            $timestamp = (int) $value;
            if ($timestamp > 20_000_000_000) {
                $timestamp = intdiv($timestamp, 1000);
            }
            return $timestamp > 0 ? date(DATE_ATOM, $timestamp) : null;
        }

        try {
            return (new \DateTimeImmutable((string) $value))->format(DATE_ATOM);
        } catch (\Throwable) {
            return null;
        }
    }

    private function numericValue(mixed $value): mixed
    {
        if (is_string($value) && preg_match('/^[^|]*\|\s*([+-]?[0-9.,]+)/', $value, $matches)) {
            $value = $matches[1];
        }
        if (is_string($value) && preg_match('/^\s*([+-]?[0-9.,]+)/', $value, $matches)) {
            $value = $matches[1];
        }
        return is_numeric(str_replace(',', '.', (string) $value))
            ? (float) str_replace(',', '.', (string) $value)
            : $value;
    }
}
