<?php

declare(strict_types=1);

namespace PbdKn\ContaoContaohabBundle\Tests\Service\Sensors;

use Doctrine\DBAL\Connection;
use PbdKn\ContaoContaohabBundle\Model\SensorModel;
use PbdKn\ContaoContaohabBundle\Service\LoggerService;
use PbdKn\ContaoContaohabBundle\Service\Sensors\IQBoxSensorService;
use PHPUnit\Framework\TestCase;

final class IQBoxTodayCompatibilityTest extends TestCase
{
    public function testTodayMatchesLoopCompatibleStructure(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn(['storageProHost' => 'storage.local']);
        $reader = new class {
            public function readSnapshot(): array
            {
                return ['data' => ['energy' => [
                    'pv' => ['today' => 10.5],
                    'inverter' => ['today' => 10.0],
                    'house' => ['today' => 9.5, 'calculatedToday' => 9.5],
                    'battery' => ['chargeToday' => 0.5, 'dischargeToday' => 1.0],
                    'grid' => ['sellToday' => 3.0, 'feedInToday' => 2.0],
                ]], 'aliases' => []];
            }
        };
        $service = new IQBoxSensorService($this->createMock(LoggerService::class), $connection, static fn () => $reader);
        $sensor = new SensorModel();
        $sensor->setRow(['sensorID' => 'IQ_Today', 'sensorSource' => 'IQBox', 'sensorLokalId' => 'today']);

        $today = $service->fetchArr([$sensor])['IQ_Today']['sensorValue'];

        self::assertSame(10000.0, $today['work']['generation']);
        self::assertSame(9500.0, $today['work']['consumption']);
        self::assertSame(3000.0, $today['work']['gridFeed']);
        self::assertSame(2000.0, $today['work']['gridDraw']);
        self::assertSame('Wh', $today['work']['unit']);
        self::assertSame(7000.0, $today['saving']['energy']['ownConsumption']);
        self::assertEqualsWithDelta(78.95, $today['selfSufficiency']['value'], 0.01);
        self::assertSame(70.0, $today['selfConsumption']['value']);
    }
}
