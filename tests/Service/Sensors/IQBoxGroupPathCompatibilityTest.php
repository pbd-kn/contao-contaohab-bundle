<?php

declare(strict_types=1);

namespace PbdKn\ContaoContaohabBundle\Tests\Service\Sensors;

use Doctrine\DBAL\Connection;
use PbdKn\ContaoContaohabBundle\Model\SensorModel;
use PbdKn\ContaoContaohabBundle\Service\LoggerService;
use PbdKn\ContaoContaohabBundle\Service\Sensors\IQBoxSensorService;
use PHPUnit\Framework\TestCase;

final class IQBoxGroupPathCompatibilityTest extends TestCase
{
    public function testTodayWorkAndNestedModbusGroupsAreReturned(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn(['storageProHost' => 'storage.local']);
        $reader = new class {
            public function readSnapshot(): array
            {
                return ['data' => [
                    'grid' => ['power' => -500, 'l1' => ['voltage' => 230.1]],
                    'energy' => [
                        'pv' => ['today' => 4.0],
                        'house' => ['calculatedToday' => 3.5],
                        'battery' => ['chargeToday' => 0.2, 'dischargeToday' => 0.3],
                        'grid' => ['sellToday' => 1.0, 'feedInToday' => 0.4],
                    ],
                ], 'aliases' => []];
            }
        };
        $service = new IQBoxSensorService($this->createMock(LoggerService::class), $connection, static fn () => $reader);

        $today = new SensorModel();
        $today->setRow(['sensorID' => 'IQ_Today_work', 'sensorSource' => 'IQBox', 'sensorLokalId' => 'today.work']);
        $grid = new SensorModel();
        $grid->setRow(['sensorID' => 'IQ_Modbus_grid', 'sensorSource' => 'IQBox', 'sensorLokalId' => 'modbus.grid']);

        $values = $service->fetchArr([$today, $grid]);

        self::assertSame(4000.0, $values['IQ_Today_work']['sensorValue']['generation']);
        self::assertSame(3500.0, $values['IQ_Today_work']['sensorValue']['consumption']);
        self::assertSame('Wh', $values['IQ_Today_work']['sensorValue']['unit']);
        self::assertSame(230.1, $values['IQ_Modbus_grid']['sensorValue']['l1']['voltage']);
    }
}
