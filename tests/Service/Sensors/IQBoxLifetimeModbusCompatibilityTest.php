<?php

declare(strict_types=1);

namespace PbdKn\ContaoContaohabBundle\Tests\Service\Sensors;

use Doctrine\DBAL\Connection;
use PbdKn\ContaoContaohabBundle\Model\SensorModel;
use PbdKn\ContaoContaohabBundle\Service\LoggerService;
use PbdKn\ContaoContaohabBundle\Service\Sensors\IQBoxSensorService;
use PHPUnit\Framework\TestCase;

final class IQBoxLifetimeModbusCompatibilityTest extends TestCase
{
    public function testLifetimeAndCompleteModbusTree(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn(['storageProHost' => 'storage.local']);
        $reader = new class {
            public function readSnapshot(): array
            {
                return ['data' => [
                    'inverter' => ['temperature' => 54.4],
                    'battery' => ['soc' => 99.6],
                    'energy' => [
                        'pv' => ['total' => 100.0],
                        'battery' => ['chargeTotal' => 20.0, 'dischargeTotal' => 15.0],
                        'grid' => ['sellTotal' => 30.0, 'feedInTotal' => 10.0],
                    ],
                ], 'aliases' => []];
            }
        };
        $service = new IQBoxSensorService($this->createMock(LoggerService::class), $connection, static fn () => $reader);
        $lifetimeSensor = new SensorModel();
        $lifetimeSensor->setRow(['sensorID' => 'IQ_Lifetime', 'sensorSource' => 'IQBox', 'sensorLokalId' => 'lifetime']);
        $modbusSensor = new SensorModel();
        $modbusSensor->setRow(['sensorID' => 'IQ_Modbus', 'sensorSource' => 'IQBox', 'sensorLokalId' => 'modbus']);

        $values = $service->fetchArr([$lifetimeSensor, $modbusSensor]);

        self::assertSame(100000.0, $values['IQ_Lifetime']['sensorValue']['generation']);
        self::assertSame(75000.0, $values['IQ_Lifetime']['sensorValue']['consumption']);
        self::assertSame('Wh', $values['IQ_Lifetime']['sensorValue']['unit']);
        self::assertSame(54.4, $values['IQ_Modbus']['sensorValue']['inverter']['temperature']);
        self::assertSame(99.6, $values['IQ_Modbus']['sensorValue']['battery']['soc']);
    }
}
