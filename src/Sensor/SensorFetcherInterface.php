<?php

namespace PbdKn\ContaoContaohabBundle\Sensor;

use PbdKn\ContaoContaohabBundle\Model\SensorModel;

interface SensorFetcherInterface
{
    public function supports(SensorModel $sensor): bool;

    public function fetch(SensorModel $sensor): ?array; // <- HIER!
}
