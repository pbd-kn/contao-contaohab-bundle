<?php

// src/PbdKn/ContaoContaohabBundle/Sensor/SensorManager.php

namespace PbdKn\ContaoContaohabBundle\Sensor;

use PbdKn\ContaoContaohabBundle\Model\SensorModel;
use PbdKn\ContaoContaohabBundle\Service\LoggerService;
use Doctrine\DBAL\Connection;

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

public function fetchAll(?array $sensorIds = null): array
{
    $allData = [];

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

    // Sensoren pro Fetcher gruppieren
    foreach ($this->fetchers as $fetcher) {
        $supported = [];

        foreach ($sensors as $sensor) {
            if ($fetcher->supports($sensor)) {
                $supported[] = $sensor;
            }
        }

        if (!empty($supported)) {
            $this->logger->debugMe("Fetcher " . get_class($fetcher) . " verarbeitet " . count($supported) . " Sensoren");
            try {
              $data = $fetcher->fetchArr($supported); // <- Jetzt wird ein Array übergeben
              if (is_array($data)) {
                $allData = array_merge($allData, $data);
              }
            } catch (\Throwable $e) {
              $this->logger->Error("Heizstab: Fehler bei fetchdata " . $e->getMessage());
            }
        }
    }

    return $allData;
}

}
