<?php

declare(strict_types=1);

namespace PbdKn\ContaoContaohabBundle\Tests\Service\Sensors;

use Doctrine\DBAL\Connection;
use PbdKn\ContaoContaohabBundle\Model\SensorModel;
use PbdKn\ContaoContaohabBundle\Service\LoggerService;
use PbdKn\ContaoContaohabBundle\Service\Sensors\IQBoxSensorServiceCloud;
use PHPUnit\Framework\TestCase;

final class IQBoxSensorServiceCloudTest extends TestCase
{
    public function testOnlyIqBoxClUsesCloudFallback(): void
    {
        $service = new IQBoxSensorServiceCloud(
            $this->createMock(LoggerService::class),
            $this->createMock(Connection::class),
        );
        $cloud = new SensorModel();
        $cloud->setRow(['sensorSource' => 'IQBoxCL']);
        $modbus = new SensorModel();
        $modbus->setRow(['sensorSource' => 'IQBox']);

        self::assertTrue($service->supports($cloud));
        self::assertFalse($service->supports($modbus));
    }
}
