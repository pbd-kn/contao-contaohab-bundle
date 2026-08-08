<?php

declare(strict_types=1);

namespace PbdKn\ContaoContaohabBundle\Service\Sensors;

use Doctrine\DBAL\Connection;
use PbdKn\ContaoContaohabBundle\Model\SensorModel;
use PbdKn\ContaoContaohabBundle\Service\LoggerService;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class IQBoxSensorService implements SensorFetcherInterface
{
    private const DEFAULT_HOST = 'ASP-HSR2103J2311E08738.local';

    /** @var array<string,string> */
    private const LEGACY_SUFFIXES = [
        '_batteries_0_state_of_charge' => 'battery.soc',
        '_batteries_0_power' => 'battery.power',
        '_photovoltaics_0_power' => 'pv.power',
        '_consumption_power' => 'house.power',
        '_powermeter_power' => 'grid.power',
    ];

    /** @var null|callable(string,int,int,float):object */
    private $modbusFactory;

    public function __construct(
        private readonly LoggerService $logger,
        private readonly Connection $connection,
        ?callable $modbusFactory = null,
        private readonly ?HttpClientInterface $httpClient = null,
    ) {
        $this->modbusFactory = $modbusFactory;
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
        $snapshot = null;
        $settings = $this->settings();
        $access = strtolower(trim((string) ($settings['storageProAccess'] ?? 'local')));
        if ($access === 'disabled') {
            $this->logger->debugMe('IQBox-StoragePro-Zugriff ist deaktiviert.');
            return [];
        }
        if (!in_array($access, ['local', 'raspberry'], true)) {
            $this->logger->Error("IQBox StoragePro: unbekannte Zugriffsart '$access'.");
            return [];
        }

        foreach ($sensors as $sensor) {
            $sensorId = (string) $sensor->sensorID;
            $selection = trim((string) $sensor->sensorLokalId);
            if ($selection === '') {
                $this->logger->Error("IQBox StoragePro: sensorLokalId fehlt für Sensor $sensorId");
                continue;
            }
            try {
                $snapshot ??= $access === 'raspberry' ? $this->requestViaRaspberry($settings) : $this->modbus($settings)->readSnapshot();
                $result[$sensorId] = [
                    'sensorID' => $sensorId,
                    'sensorEinheit' => (string) $sensor->sensorEinheit,
                    'sensorValueType' => (string) $sensor->sensorValueType,
                    'sensorSource' => (string) $sensor->sensorSource,
                    'sensorValue' => $this->snapshotValue($snapshot, $selection),
                ];
            } catch (\Throwable $error) {
                $this->logger->Error($this->qualifiedErrorMessage($selection, $error));
            }
        }

        return $result;
    }

    private function qualifiedErrorMessage(string $selection, \Throwable $error): string
    {
        $message = $error->getMessage();
        if (str_contains($message, 'Modbus-Antwort unvollstÃ¤ndig: Verbindung beendet')) {
            return sprintf(
                "IQBox StoragePro: lokalId %s konnte nicht gelesen werden. Der StoragePro hat die Modbus-TCP-Verbindung beendet. Wahrscheinlich ist bereits ein anderer Modbus-Client verbunden (z. B. json-solar-modbus-loop.php). Bitte den anderen Client die Verbindung nach jedem Zugriff freigeben lassen. Technischer Fehler: %s",
                $selection,
                $message,
            );
        }
        if (str_contains($message, 'Timeout')) {
            return sprintf(
                'IQBox StoragePro: Timeout beim Lesen von lokalId %s. Host, Port, Erreichbarkeit und eingestellten Timeout prÃ¼fen. Technischer Fehler: %s',
                $selection,
                $message,
            );
        }
        if (str_contains($message, 'Modbus-Verbindung zu')) {
            return sprintf(
                'IQBox StoragePro: Verbindung fÃ¼r lokalId %s konnte nicht aufgebaut werden. Host, Port und Netzwerk prÃ¼fen. Technischer Fehler: %s',
                $selection,
                $message,
            );
        }

        return "IQBox StoragePro: Fehler bei lokalId $selection: $message";
    }

    private function settings(): array
    {
        $settings = $this->connection->fetchAssociative('SELECT * FROM tl_coh_sensorcollector_settings ORDER BY id ASC LIMIT 1');
        if (!$settings) {
            throw new \RuntimeException('StoragePro-Modbus-Einstellungen fehlen im Contao-Backend.');
        }
        return $settings;
    }

    private function requestViaRaspberry(array $settings): array
    {
        if ($this->httpClient === null) {
            throw new \RuntimeException('HTTP-Client fÃ¼r den Raspberry-IQBox-Zugriff fehlt.');
        }
        $baseUrl = rtrim(trim((string) ($settings['storageProRaspberryBaseUrl'] ?? '')), '/');
        $path = '/' . ltrim(trim((string) ($settings['storageProRaspberryPath'] ?? '/api/coh/iqbox-modbus.php')), '/');
        if ($baseUrl === '') {
            throw new \RuntimeException('Raspberry-Basis-URL fÃ¼r IQBox/StoragePro fehlt.');
        }
        $response = $this->httpClient->request('GET', $baseUrl . $path, [
            'headers' => [
                'Accept' => 'application/json',
                'X-COH-TOKEN' => trim((string) ($settings['storageProRaspberryToken'] ?? ''))
                    ?: (string) ($settings['tasmotaRaspberryToken'] ?? ''),
            ],
            'query' => [
                'host' => trim((string) ($settings['storageProHost'] ?? '')) ?: self::DEFAULT_HOST,
                'port' => max(1, (int) ($settings['storageProPort'] ?? 502)),
                'unitId' => isset($settings['storageProUnitId']) ? (int) $settings['storageProUnitId'] : 1,
                'timeout' => max(1, (int) ($settings['storageProTimeout'] ?? 3)),
            ],
            'timeout' => max(1, (int) ($settings['storageProRaspberryTimeout'] ?? 15)),
        ]);
        $status = $response->getStatusCode();
        $body = $response->getContent(false);
        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            throw new \RuntimeException(sprintf(
                'Raspberry-IQBox-Endpunkt lieferte HTTP %d und kein gueltiges JSON: %s',
                $status,
                mb_substr(trim(strip_tags($body)), 0, 500),
            ));
        }
        if ($status !== 200 || empty($payload['ok']) || !is_array($payload['snapshot'] ?? null)) {
            throw new \RuntimeException(sprintf(
                'Raspberry-IQBox-Endpunkt lieferte HTTP %d: %s',
                $status,
                (string) ($payload['error'] ?? json_encode($payload, JSON_UNESCAPED_UNICODE)),
            ));
        }
        return $payload['snapshot'];
    }

    private function modbus(array $settings): object
    {
        $host = trim((string) ($settings['storageProHost'] ?? '')) ?: self::DEFAULT_HOST;
        $port = max(1, (int) ($settings['storageProPort'] ?? 502));
        $unitId = isset($settings['storageProUnitId']) ? (int) $settings['storageProUnitId'] : 1;
        $timeout = max(0.1, (float) ($settings['storageProTimeout'] ?? 3));

        return $this->modbusFactory !== null
            ? ($this->modbusFactory)($host, $port, $unitId, $timeout)
            : new AmpereStorageProModbus($host, $port, $unitId, $timeout);
    }

    private function snapshotValue(array $snapshot, string $selection): mixed
    {
        $data = $snapshot['data'] ?? [];
        $live = $this->compatibleLiveData($data);
        $today = $this->compatibleTodayData($data);
        $lifetime = $this->compatibleLifetimeData($data);
        $normalized = strtolower(trim($selection));

        if ($normalized === 'modbus') return $data;
        if (str_starts_with($normalized, 'modbus.')) return $this->pathNode($data, substr(trim($selection), 7));

        $lifetimeAliases = ['pv-total' => 'lifetime.pvProduction', 'pv-gesamt' => 'lifetime.pvProduction', 'total-pv' => 'lifetime.pvProduction', 'lifetime-pv' => 'lifetime.pvProduction'];
        $lifetimeSelection = $lifetimeAliases[$normalized] ?? trim($selection);
        if (strcasecmp($lifetimeSelection, 'lifetime') === 0 || strcasecmp($lifetimeSelection, 'lifetime.work') === 0) return $lifetime['work'];
        if (str_starts_with(strtolower($lifetimeSelection), 'lifetime.')) return $this->pathNode($lifetime, substr($lifetimeSelection, 9));

        $todayAliases = [
            'heute' => 'today', 'work' => 'today.work', 'arbeit' => 'today.work', 'today-work' => 'today.work',
            'today-self-sufficiency' => 'today.selfSufficiency', 'today-autarkie' => 'today.selfSufficiency',
            'today-self-consumption' => 'today.selfConsumption', 'today-eigenverbrauch' => 'today.selfConsumption',
            'today-saving' => 'today.saving', 'today-ersparnis' => 'today.saving', 'today-saving-energy' => 'today.saving.energy',
            'today-saving-pv-production' => 'today.saving.energy.pvProduction', 'today-saving-grid-feed' => 'today.saving.energy.gridFeed',
            'today-saving-own-consumption' => 'today.saving.energy.ownConsumption',
        ];
        $todaySelection = $todayAliases[$normalized] ?? trim($selection);
        if (strcasecmp($todaySelection, 'today') === 0) return $today;
        if (str_starts_with(strtolower($todaySelection), 'today.')) {
            $path = substr($todaySelection, 6);
            if ($path === 'work.consumation') $path = 'work.consumption';
            return $this->pathNode($today, $path);
        }

        if ($normalized === 'live' || $normalized === 'live.power') return $live;
        if (str_starts_with($normalized, 'live.power.')) return $this->pathNode($live, substr($selection, 11));
        if (str_starts_with($normalized, 'live.')) return $this->pathNode($live, substr($selection, 5));
        if (array_key_exists($selection, $live) && !is_array($live[$selection])) return $live[$selection];
        if (array_key_exists($selection, $snapshot['aliases'] ?? [])) return $snapshot['aliases'][$selection];

        $path = $selection;
        foreach (self::LEGACY_SUFFIXES as $suffix => $modbusPath) {
            if (str_ends_with($selection, $suffix)) { $path = $modbusPath; break; }
        }
        if (str_contains($selection, '_powermeter_') && str_ends_with($selection, 'harmonized_power_out')) return max(0.0, -(float) $this->pathValue($data, 'grid.power'));
        if (str_contains($selection, '_powermeter_') && str_ends_with($selection, 'harmonized_power_in')) return max(0.0, (float) $this->pathValue($data, 'grid.power'));
        return $this->pathValue($data, $path);
    }

    private function compatibleLifetimeData(array $data): array
    {
        $e = $data['energy'] ?? [];
        $generation = (float) ($e['pv']['total'] ?? 0);
        $charge = (float) ($e['battery']['chargeTotal'] ?? 0);
        $discharge = (float) ($e['battery']['dischargeTotal'] ?? 0);
        $sell = (float) ($e['grid']['sellTotal'] ?? 0);
        $import = (float) ($e['grid']['feedInTotal'] ?? 0);
        $consumption = round($generation + $import + $discharge - $sell - $charge, 2);
        return ['pvProduction' => round($generation * 1000, 2), 'work' => [
            'generation' => round($generation * 1000, 2), 'consumption' => round($consumption * 1000, 2),
            'batteryFeed' => round($charge * 1000, 2), 'batteryDraw' => round($discharge * 1000, 2),
            'gridFeed' => round($sell * 1000, 2), 'gridDraw' => round($import * 1000, 2),
            'unit' => 'Wh', 'throughDate' => date('Y-m-d'), 'source' => 'StoragePro-Modbus-Gesamtzaehler',
        ]];
    }

    private function compatibleTodayData(array $data): array
    {
        $e = $data['energy'] ?? [];
        $generation = round((float) ($e['pv']['today'] ?? 0) * 1000, 2);
        $consumption = round((float) ($e['house']['calculatedToday'] ?? 0) * 1000, 2);
        $charge = round((float) ($e['battery']['chargeToday'] ?? 0) * 1000, 2);
        $discharge = round((float) ($e['battery']['dischargeToday'] ?? 0) * 1000, 2);
        $sell = round((float) ($e['grid']['sellToday'] ?? 0) * 1000, 2);
        $import = round((float) ($e['grid']['feedInToday'] ?? 0) * 1000, 2);
        $autarky = $consumption <= 0 ? null : round(max(0.0, min(100.0, ($consumption - $import) / $consumption * 100)), 2);
        $own = $generation <= 0 ? null : round(max(0.0, min(100.0, ($generation - $sell) / $generation * 100)), 2);
        return [
            'work' => ['generation' => $generation, 'consumption' => $consumption, 'batteryFeed' => $charge, 'batteryDraw' => $discharge, 'gridFeed' => $sell, 'gridDraw' => $import, 'unit' => 'Wh'],
            'selfSufficiency' => ['value' => $autarky], 'selfConsumption' => ['value' => $own],
            'saving' => ['energy' => ['pvProduction' => $generation, 'gridFeed' => $sell, 'ownConsumption' => max(0.0, round($generation - $sell, 2))], '_notice' => 'Kosten, Preise, Fahrzeuge und Emissionen sind nicht per Modbus verfügbar.'],
        ];
    }

    private function compatibleLiveData(array $data): array
    {
        $batteryPower = (float) ($data['battery']['power'] ?? 0);
        return [
            'pvPower' => $data['pv']['power'] ?? null, 'housePower' => -abs((float) ($data['house']['power'] ?? 0)),
            'gridPower' => $data['grid']['power'] ?? null, 'batteryPower' => $batteryPower == 0.0 ? 0 : $batteryPower,
            'heatingRodPower' => null, 'batterySoc' => $data['battery']['soc'] ?? null,
            'inverter' => $data['inverter'] ?? [], 'grid' => $data['grid'] ?? [], 'battery' => $data['battery'] ?? [],
            'pv' => $data['pv'] ?? [], 'house' => $data['house'] ?? [],
        ];
    }

    private function pathValue(array $data, string $path): mixed
    {
        $value = $this->pathNode($data, $path);
        if (is_array($value)) throw new \InvalidArgumentException("Sensorpfad '$path' bezeichnet keinen einzelnen Messwert.");
        return $value;
    }

    private function pathNode(array $data, string $path): mixed
    {
        $value = $data;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) throw new \InvalidArgumentException("Sensorpfad '$path' ist lokal nicht verfügbar.");
            $value = $value[$segment];
        }
        return $value;
    }
}
