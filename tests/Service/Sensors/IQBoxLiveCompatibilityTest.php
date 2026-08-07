<?php

declare(strict_types=1);

namespace PbdKn\ContaoContaohabBundle\Tests\Service\Sensors;

use Doctrine\DBAL\Connection;
use PbdKn\ContaoContaohabBundle\Model\SensorModel;
use PbdKn\ContaoContaohabBundle\Service\LoggerService;
use PbdKn\ContaoContaohabBundle\Service\Sensors\IQBoxSensorService;
use PHPUnit\Framework\TestCase;

final class IQBoxLiveCompatibilityTest extends TestCase
{
    public function testLiveContainsLoopCompatibleValuesAndNestedModbusData(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn(['storageProHost' => 'storage.local']);
        $reader = new class {
            public function readSnapshot(): array
            {
                return ['data' => [
                    'inverter' => ['temperature' => 54.4, 'power' => 1671],
                    'grid' => ['power' => -1179, 'l1' => ['voltage' => 237.3]],
                    'battery' => ['power' => 1005, 'soc' => 99.6, 'soh' => 97.5],
                    'pv' => ['power' => 1497, 'string1' => ['power' => 750]],
                    'house' => ['power' => 318],
                ], 'aliases' => []];
            }
        };
        $service = new IQBoxSensorService($this->createMock(LoggerService::class), $connection, static fn () => $reader);
        $sensor = new SensorModel();
        $sensor->setRow(['sensorID' => 'IQ_Live', 'sensorSource' => 'IQBox', 'sensorLokalId' => 'live']);

        $live = $service->fetchArr([$sensor])['IQ_Live']['sensorValue'];

        self::assertSame(1497, $live['pvPower']);
        self::assertSame(-318.0, $live['housePower']);
        self::assertSame(-1005.0, $live['batteryPower']);
        self::assertSame(54.4, $live['inverter']['temperature']);
        self::assertSame(237.3, $live['grid']['l1']['voltage']);
        self::assertSame(97.5, $live['battery']['soh']);
        self::assertSame(750, $live['pv']['string1']['power']);
        self::assertSame(318, $live['house']['power']);
    }
}
