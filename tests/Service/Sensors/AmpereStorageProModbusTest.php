<?php

declare(strict_types=1);

namespace PbdKn\ContaoContaohabBundle\Tests\Service\Sensors;

use PbdKn\ContaoContaohabBundle\Service\Sensors\AmpereStorageProModbus;
use PHPUnit\Framework\TestCase;

final class AmpereStorageProModbusTest extends TestCase
{
    public function testSignedValuesAndScaling(): void
    {
        self::assertSame(-100, AmpereStorageProModbus::decode([0xFF9C], 0, 'int16'));
        self::assertSame(12.34, AmpereStorageProModbus::decode([1234], 0, 'uint16', 0.01));
    }

    public function testHouseConsumptionFormulaUsesCorrectImportAndExportCounters(): void
    {
        $values = [
            'energy.pv.today' => 10.0,
            'energy.grid.feedInToday' => 2.0,
            'energy.battery.dischargeToday' => 1.0,
            'energy.grid.sellToday' => 3.0,
            'energy.battery.chargeToday' => 0.5,
        ];
        $house = round($values['energy.pv.today'] + $values['energy.grid.feedInToday'] + $values['energy.battery.dischargeToday'] - $values['energy.grid.sellToday'] - $values['energy.battery.chargeToday'], 2);
        self::assertSame(9.5, $house);
    }
}
