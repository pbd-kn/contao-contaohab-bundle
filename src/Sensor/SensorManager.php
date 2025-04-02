<?php

// src/PbdKn/ContaoContaohabBundle/Sensor/SensorManager.php

namespace PbdKn\ContaoContaohabBundle\Sensor;

use PbdKn\ContaoContaohabBundle\Model\SensorModel;

class SensorManager
{
    private iterable $fetchers;

    public function __construct(iterable $fetchers)
    {
        $this->fetchers = $fetchers;
    }

    public function fetchAll(): array
    {
        $allData = [];

        $sensors = SensorModel::findAll();

        if ($sensors === null) {
            return $allData;
        }
        $timestampNow = time();

        foreach ($sensors as $sensor) {
            $lastTime = (int) $sensor->lastUpdated; // Neues Feld nötig, siehe unten
            $interval = (int) $sensor->pollInterval;

            if (isset($interval)&&$interval > 0 && ($timestampNow - $lastTime < $interval)) {
              continue; // noch nicht dran
            }
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
