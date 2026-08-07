<?php

declare(strict_types=1);

namespace PbdKn\ContaoContaohabBundle\Tests\Service\Sensors;

use Doctrine\DBAL\Connection;
use PbdKn\ContaoContaohabBundle\Model\SensorModel;
use PbdKn\ContaoContaohabBundle\Service\LoggerService;
use PbdKn\ContaoContaohabBundle\Service\Sensors\IQBoxSensorService;
use PHPUnit\Framework\TestCase;

final class IQBoxSensorServiceTest extends TestCase
{
    public function testOneSnapshotIsSharedByAllSensorsAndPathsAreMapped(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn(['storageProHost' => 'storage.local', 'storageProPort' => 502, 'storageProUnitId' => 1, 'storageProTimeout' => 3]);
        $reader = new class {
            public int $calls = 0;
            public function readSnapshot(): array
            {
                ++$this->calls;
                return ['data' => ['battery' => ['soc' => 70], 'pv' => ['power' => 2300]], 'aliases' => ['batterySoc' => 70, 'pvPower' => 2300]];
            }
        };
        $service = new IQBoxSensorService($this->createMock(LoggerService::class), $connection, static fn () => $reader);

        $battery = new SensorModel();
        $battery->setRow(['sensorID' => 'battery', 'sensorSource' => 'IQBox', 'sensorLokalId' => 'batterySoc']);
        $pv = new SensorModel();
        $pv->setRow(['sensorID' => 'pv', 'sensorSource' => 'IQBox', 'sensorLokalId' => 'pv.power']);
        $result = $service->fetchArr([$battery, $pv]);

        self::assertSame(1, $reader->calls);
        self::assertSame(70, $result['battery']['sensorValue']);
        self::assertSame(2300, $result['pv']['sensorValue']);
    }
}
