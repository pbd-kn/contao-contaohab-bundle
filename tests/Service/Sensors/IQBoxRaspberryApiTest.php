<?php

declare(strict_types=1);

namespace PbdKn\ContaoContaohabBundle\Tests\Service\Sensors;

use Doctrine\DBAL\Connection;
use PbdKn\ContaoContaohabBundle\Model\SensorModel;
use PbdKn\ContaoContaohabBundle\Service\LoggerService;
use PbdKn\ContaoContaohabBundle\Service\Sensors\IQBoxSensorService;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class IQBoxRaspberryApiTest extends TestCase
{
    public function testRaspberryPerformsOneSnapshotRequestForAllSelections(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn([
            'storageProAccess' => 'raspberry',
            'storageProRaspberryBaseUrl' => 'https://raspberry.example',
            'storageProRaspberryPath' => '/api/coh/iqbox-modbus.php',
            'storageProRaspberryToken' => '',
            'tasmotaRaspberryToken' => 'test-token',
            'storageProRaspberryTimeout' => 15,
            'storageProHost' => 'storage.local',
            'storageProPort' => 502,
            'storageProUnitId' => 1,
            'storageProTimeout' => 3,
        ]);
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getContent')->with(false)->willReturn(json_encode(['ok' => true, 'snapshot' => [
            'data' => ['battery' => ['soc' => 88.5], 'pv' => ['power' => 1234]],
            'aliases' => ['batterySoc' => 88.5, 'pvPower' => 1234],
        ]], JSON_THROW_ON_ERROR));
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects(self::once())->method('request')->with(
            'GET',
            'https://raspberry.example/api/coh/iqbox-modbus.php',
            self::callback(static fn (array $options): bool =>
                $options['headers']['X-COH-TOKEN'] === 'test-token'
                && $options['query']['host'] === 'storage.local'
                && $options['query']['unitId'] === 1
            )
        )->willReturn($response);
        $service = new IQBoxSensorService($this->createMock(LoggerService::class), $connection, null, $http);
        $soc = new SensorModel();
        $soc->setRow(['sensorID' => 'soc', 'sensorSource' => 'IQBox', 'sensorLokalId' => 'batterySoc']);
        $pv = new SensorModel();
        $pv->setRow(['sensorID' => 'pv', 'sensorSource' => 'IQBox', 'sensorLokalId' => 'pv.power']);

        $values = $service->fetchArr([$soc, $pv]);

        self::assertSame(88.5, $values['soc']['sensorValue']);
        self::assertSame(1234, $values['pv']['sensorValue']);
    }
}
