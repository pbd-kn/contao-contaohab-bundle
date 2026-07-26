<?php

// src/PbdKn/ContaoContaohabBundle/Sensor/SensorManager.php

namespace PbdKn\ContaoContaohabBundle\Service\Sensors;

use PbdKn\ContaoContaohabBundle\Model\SensorModel;
use PbdKn\ContaoContaohabBundle\Service\LoggerService;
use Doctrine\DBAL\Connection;
use Contao\StringUtil;

class SensorManager
{
    private iterable $fetchers;
    private ?LoggerService $logger = null;
    private Connection $connection;


   public function __construct(iterable $fetchers, LoggerService $logger, Connection $connection)
   {
        $this->fetchers = $fetchers;
        $this->logger = $logger;
        $this->connection = $connection;

    }

public function fetchAll(?array $sensorIds = null, ?string $date = null): array
{
    $allData = [];
    $requestedIds = array_values(array_filter(array_map('strval', $sensorIds ?? [])));

    $qb = $this->connection->createQueryBuilder();
    $qb->select('s.*', 'g.geraeteID', 'g.geraeteTitle', 'g.geraeteUrl')
        ->from('tl_coh_sensors', 's')
        ->leftJoin('s', 'tl_coh_geraete', 'g', 's.sensorSource = g.geraeteID');

    if (!empty($sensorIds)) {
        $this->logger->debugMe("SensorIDs übergeben: " . implode(', ', $sensorIds));
        $qb->where($qb->expr()->in('s.sensorID', ':ids'))
           ->setParameter('ids', $sensorIds, Connection::PARAM_STR_ARRAY);
    }

    $rows = $qb->executeQuery()->fetchAllAssociative();

    // Ein ausgewählter Komponenten-Sensor benötigt seine Quellsensoren. Diese
    // werden ebenfalls direkt gelesen, später aber nicht zusätzlich ausgegeben.
    $dependencyIds = [];
    foreach ($rows as $row) {
        if (strtolower((string) ($row['sensorSource'] ?? '')) !== 'zcomponent') {
            continue;
        }
        foreach (StringUtil::deserialize($row['componentSensors'] ?? null, true) as $mapping) {
            $dependencyId = trim((string) ($mapping['sensor'] ?? ''));
            if ($dependencyId !== '') {
                $dependencyIds[] = $dependencyId;
            }
        }
    }
    $dependencyIds = array_values(array_diff(array_unique($dependencyIds), array_column($rows, 'sensorID')));
    if ($dependencyIds !== []) {
        $dependencyRows = $this->connection->createQueryBuilder()
            ->select('s.*', 'g.geraeteID', 'g.geraeteTitle', 'g.geraeteUrl')
            ->from('tl_coh_sensors', 's')
            ->leftJoin('s', 'tl_coh_geraete', 'g', 's.sensorSource = g.geraeteID')
            ->where('s.sensorID IN (:ids)')
            ->setParameter('ids', $dependencyIds, Connection::PARAM_STR_ARRAY)
            ->executeQuery()
            ->fetchAllAssociative();
        $rows = array_merge($rows, $dependencyRows);
    }

    if (empty($rows)) {
        $this->logger->debugMe("Keine Sensoren gefunden.");
        return $allData;
    }

    // Sensor-Modelle erzeugen
    $sensors = [];
    foreach ($rows as $row) {
        $model = new SensorModel();
        $model->setRow($row);
        $sensors[] = $model;
    }

    // Komponenten werden zuletzt ausgewertet, weil sie Ergebnisse anderer
    // Fetcher als Eingangsvariablen verwenden.
    $fetchers = is_array($this->fetchers) ? $this->fetchers : iterator_to_array($this->fetchers, false);
    usort($fetchers, static fn (SensorFetcherInterface $a, SensorFetcherInterface $b): int =>
        ($a instanceof ComponentService ? 1 : 0) <=> ($b instanceof ComponentService ? 1 : 0)
    );

    // Sensoren pro Fetcher gruppieren
    foreach ($fetchers as $fetcher) {
        $supported = [];

        foreach ($sensors as $sensor) {
            if ($fetcher->supports($sensor)) {
                $supported[] = $sensor;
            }
        }

        if (!empty($supported)) {
            $this->logger->debugMe("Fetcher " . get_class($fetcher) . " verarbeitet " . count($supported) . " Sensoren");
            try {
              $data = $fetcher->fetchArr($supported, $date, $allData);
              if (is_array($data)) {
                foreach ($data as $sensorId => &$value) {
                    if (!is_array($value)) {
                        continue;
                    }
                    foreach ($supported as $sensor) {
                        if ((string) $sensor->sensorID !== (string) $sensorId) {
                            continue;
                        }
                        $value['sensorTitle'] ??= $sensor->sensorTitle ?: $sensor->sensorID;
                        $value['outputMode'] ??= $sensor->outputMode ?: 'absolute';
                        $value['sensorLokalId'] ??= (string) $sensor->sensorLokalId;
                        $value['sensorEinheit'] ??= (string) $sensor->sensorEinheit;
                        $value['sensorValueType'] ??= (string) $sensor->sensorValueType;
                        break;
                    }
                }
                unset($value);
                $allData = array_merge($allData, $data);
              }
            } catch (\Throwable $e) {
              $this->logger->Error('Sensor-Fetcher ' . get_class($fetcher) . ': Fehler bei fetchArr: ' . $e->getMessage());
            }
        }
    }

    return $requestedIds === []
        ? $allData
        : array_intersect_key($allData, array_flip($requestedIds));
}

}

