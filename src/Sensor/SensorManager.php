<?php

// src/PbdKn/ContaoContaohabBundle/Sensor/SensorManager.php

namespace PbdKn\ContaoContaohabBundle\Sensor;

use PbdKn\ContaoContaohabBundle\Model\SensorModel;
use PbdKn\ContaoContaohabBundle\Service\LoggerService;

class SensorManager
{
    private iterable $fetchers;
    private ?LoggerService $logger = null;


    public function __construct(iterable $fetchers, LoggerService $logger)
    {
        $this->fetchers = $fetchers;
        $this->logger = $logger;

    }

    public function fetchAll(): array
    {
        $allData = [];
$this->logger->debugMe("fetchAll gerufen");
        $sensors = SensorModel::findAll();

        if ($sensors === null) {
            return $allData;
        }
        $timestampNow = time();

        foreach ($sensors as $sensor) {
//            $lastTime = (int) $sensor->lastUpdated; // Neues Feld nötig, siehe unten
//            $interval = (int) $sensor->pollInterval;
$this->logger->debugMe("verarbeite ID: ".$sensor->sensorID. " sensorTitle: ".$sensor->sensorTitle." sensorSource: ".$sensor->sensorSource);

//            if (isset($interval)&&$interval > 0 && ($timestampNow - $lastTime < $interval)) {
//$this->logger->debugMe("noch nicht dran");
//              continue; // noch nicht dran
//            }
            foreach ($this->fetchers as $fetcher) {
                if ($fetcher->supports($sensor)) {
                    $data = $fetcher->fetch($sensor);
                    if ($data !== null) {
                        $allData[] = $data;
                    }
                }
            }
        }

        return $allData;   // werden im contaohabDaemon in die tl_sensorvalue geschrieben
    }
}
