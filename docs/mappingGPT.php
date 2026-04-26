<?php
declare(strict_types=1);

/**
 * IQBox / SAJ / KiwiGrid Mapping
 * 1:1 für ContaoHab / eigene Scripts nutzbar
 *
 * Nutzung:
 * $map = require __DIR__ . '/iqbox_sensor_map.php';
 * $sensorId = $map['sensors']['IQpvPower'];
 *
 * Optional:
 * $parsed = $map['parseItem']($item);
 */

return [

    /*
     * ============================================================
     * 40 wichtige Kurz-IDs -> echte SensorIDs
     * ============================================================
     */
    'sensors' => [

        // --------------------------------------------------------
        // PV / Wechselrichter
        // --------------------------------------------------------
        'IQpvPower'                  => 'sajhybrid_inverter_94_HSR2103J2311E08738_inverter_pvPower',        // IQinverter_94_inverter_pvPower
        'IQpvEnergy'                 => 'sajhybrid_inverter_94_HSR2103J2311E08738_inverter_pvEnergy',       // IQinverter_94_inverter_pvEnergy
        'IQinverterPower'            => 'sajhybrid_inverter_94_HSR2103J2311E08738_inverter_activePower',    // IQinverter_94_inverter_activePower
        'IQinverterPowerRaw'         => 'sajhybrid_inverter_94_HSR2103J2311E08738_inverter_activePowerRaw', // IQinverter_94_inverter_activePowerRaw
        'IQinverterApparentPower'    => 'sajhybrid_inverter_94_HSR2103J2311E08738_inverter_apparentPower',  // IQinverter_94_inverter_apparentPower
        'IQselfConsumptionPower'     => 'sajhybrid_inverter_94_HSR2103J2311E08738_inverter_selfConsumptionPower',   // IQinverter_94_inverter_selfConsumptionPower
        'IQinverterEnergyIn'         => 'sajhybrid_inverter_94_HSR2103J2311E08738_inverter_energyIn',       // IQinverter_94_inverter_energyIn
        'IQinverterEnergyOut'        => 'sajhybrid_inverter_94_HSR2103J2311E08738_inverter_energyOut',      // IQinverter_94_inverter_energyOut
        'IQpvPowerMPPT'              => 'sajhybrid_inverter_94_HSR2103J2311E08738_inverter_pvPowerMPPT',    // IQinverter_94_inverter_pvPowerMPPT
        'IQpvPowerMeasured'          => 'sajhybrid_inverter_94_HSR2103J2311E08738_pv_power_production_measurable__inverter_pvPower', // IQ_pv_power_production_measurable_pvPowerProduction_P_pv

        // --------------------------------------------------------
        // Batterie
        // --------------------------------------------------------
        'IQbatterySoc'               => 'sajhybrid_battery_94_HSR2103J2311E08738_battery_stateOfCharge',    // IQbattery_94_battery_stateOfCharge
        'IQbatteryPower'             => 'sajhybrid_battery_94_HSR2103J2311E08738_battery_power',            // IQbattery_94_battery_power
        'IQbatteryPowerCalc'         => 'sajhybrid_battery_94_HSR2103J2311E08738_battery_power_calculated', // IQbattery_94_PowerCalc
        'IQbatteryChargePower'       => 'sajhybrid_battery_94_HSR2103J2311E08738_power_consumption_measurable_powerConsumption_P',  // IQbattery_94_power_consumption_measurable_powerConsumption_P
        'IQbatteryDischargePower'    => 'sajhybrid_battery_94_HSR2103J2311E08738_power_production_measurable_powerProduction_P',    // IQbattery_94_power_production_measurable_powerProduction_P
        'IQbatteryChargeTotal'       => 'sajhybrid_battery_94_HSR2103J2311E08738_battery_totalChargeEnergy',                        // IQbattery_94_battery_totalChargeEnergy
        'IQbatteryDischargeTotal'    => 'sajhybrid_battery_94_HSR2103J2311E08738_battery_totalDischargeEnergy',                     // IQbattery_94_battery_totalDischargeEnergy
        'IQbatteryTemp'              => 'sajhybrid_battery_94_HSR2103J2311E08738_battery_temperature',                              // IQbattery_94_battery_temperature
        'IQbatteryMode'              => 'sajhybrid_battery_94_HSR2103J2311E08738_battery_mode',                                     // IQbattery_94_battery_mode
        'IQbatteryModeTxt'           => 'sajhybrid_battery_94_HSR2103J2311E08738_battery_modeConverter',                            // IQbattery_94_battery_modeConverter
        'IQbatteryMaxCharge'         => 'sajhybrid_battery_94_HSR2103J2311E08738_battery_maxChargePower',                           // IQbattery_94_battery_maxChargePower
        'IQbatteryMaxDischarge'      => 'sajhybrid_battery_94_HSR2103J2311E08738_battery_maxDischargePower',                        // IQbattery_94_battery_maxDischargePower

        // --------------------------------------------------------
        // Powermeter / Netz
        // --------------------------------------------------------
        'IQgridPower'                => 'sajhybrid_powermeter_94_HSR2103J2311E08738_powermeter_realPower',                          // ZWZZaehlerPowermeterRealPower
        'IQgridImport'               => 'sajhybrid_powermeter_94_HSR2103J2311E08738_powermeter_totalImport',                        // ZWZZaehlerTotalPVEnergieImport
        'IQgridExport'               => 'sajhybrid_powermeter_94_HSR2103J2311E08738_powermeter_totalExport',                        // ZWZZaehlerTotalPVEnergieExport
        'IQphase1'                   => 'sajhybrid_powermeter_94_HSR2103J2311E08738_powermeter_real_power_1',                       // ZWZLeistung_Phase_L1_aktuell
        'IQphase2'                   => 'sajhybrid_powermeter_94_HSR2103J2311E08738_powermeter_real_power_2',                       // ZWZLeistung_Phase_L3_aktuell
        'IQphase3'                   => 'sajhybrid_powermeter_94_HSR2103J2311E08738_powermeter_real_power_3',                       // ZWZLeistung_Phase_L3_aktuell
        'IQgridPowerMeasuredProd'    => 'sajhybrid_powermeter_94_HSR2103J2311E08738_metering_getProduction_P_active',               // ZWZMeteringGetProductionPActive
        'IQgridPowerMeasuredCons'    => 'sajhybrid_powermeter_94_HSR2103J2311E08738_metering_getConsumption_P_active',              // ZWZMeteringGetConsumptionPActive

        // --------------------------------------------------------
        // KiwiGrid harmonized Power
        // --------------------------------------------------------
        'IQhousePower'               => 'kiwigrid_location_standard_a835aad0c619_harmonized_power_consumed',                        // IQKiwiHarmonizedPowerConsumed
        'IQpowerProduced'            => 'kiwigrid_location_standard_a835aad0c619_harmonized_power_produced',                        // IQKiwiHarmonizedPowerProduced
        'IQselfConsumed'             => 'kiwigrid_location_standard_a835aad0c619_harmonized_power_self_consumed',                   // IQKiwiHarmonizedPowerSelfConsumed
        'IQselfSupplied'             => 'kiwigrid_location_standard_a835aad0c619_harmonized_power_self_supplied',                   // IQKiwiHarmonizedPowerSelfSupplied
        'IQconsumedFromGrid'         => 'kiwigrid_location_standard_a835aad0c619_harmonized_power_consumed_from_grid',              // IQKiwiHarmonizedPowerConsumedFromGrid
        'IQconsumedFromStorage'      => 'kiwigrid_location_standard_a835aad0c619_harmonized_power_consumed_from_storage',           // IQKiwiHarmonizedPowerConsumedStorage
        'IQconsumedFromProducers'    => 'kiwigrid_location_standard_a835aad0c619_harmonized_power_consumed_from_producers',         // IQKiwiHarmonizedPowerConsumedProducers
        'IQpowerOut'                 => 'kiwigrid_location_standard_a835aad0c619_harmonized_power_out',                             // IQKiwiHarmonizedOut
        'IQpowerIn'                  => 'kiwigrid_location_standard_a835aad0c619_harmonized_power_in',                              // IQKiwiHarmonizedIn

        // --------------------------------------------------------
        // KiwiGrid harmonized Work / Energie
        // --------------------------------------------------------
        'IQhouseEnergyTotal'         => 'kiwigrid_location_standard_a835aad0c619_harmonized_work_consumed_total',                   // IQKiwiHarmonizedWorkConsumedTotal
        'IQpvEnergyTotal'            => 'kiwigrid_location_standard_a835aad0c619_harmonized_work_produced_total',                   // IQKiwiHarmonizedWorkProducedTotal
        'IQselfConsumedTotal'        => 'kiwigrid_location_standard_a835aad0c619_harmonized_work_self_consumed_total',              // IQKiwiHarmonizedWorkSelfConsumedTotal
        'IQselfSuppliedTotal'        => 'kiwigrid_location_standard_a835aad0c619_harmonized_work_self_supplied_total',              // IQKiwiHarmonizedWorkSelfSuppliedTotal
        'IQgridImportTotal'          => 'kiwigrid_location_standard_a835aad0c619_harmonized_work_consumed_from_grid_total',         // IQKiwiHarmonizedWorkConsumedGridTotal
        'IQbatteryUsedTotal'         => 'kiwigrid_location_standard_a835aad0c619_harmonized_work_consumed_from_storage_total',      // IQKiwiHarmonizedWorkConsumedStorageTotal
        'IQconsumedFromProducersTotal'=> 'kiwigrid_location_standard_a835aad0c619_harmonized_work_consumed_from_producers_total',   // IQKiwiHarmonizedWorkConsumedProducersTotal
        'IQworkOutTotal'             => 'kiwigrid_location_standard_a835aad0c619_harmonized_work_out_total',                        // IQKiwiHarmonizedWorkOutTotal
        'IQworkInTotal'              => 'kiwigrid_location_standard_a835aad0c619_harmonized_work_in_total',                         // IQKiwiHarmonizedWorkInTotal
    ],

    /*
     * ============================================================
     * Virtuelle Tageswerte
     * Diese kommen aus Differenz der Gesamtzähler seit 00:00
     * ============================================================
     */
    'virtual_today' => [
        'IQpvToday'                  => 'sajhybrid_inverter_94_HSR2103J2311E08738_inverter_pvPower',        // IQinverter_94_inverter_pvPower_day
        'IQbatteryChargeTotal'       => 'sajhybrid_battery_94_HSR2103J2311E08738_battery_totalChargeEnergy',                        // IQbattery_94_battery_totalChargeEnergy_day

        'IQbatteryDischargeToday'   => 'IQbatteryDischargeTotal',
        'IQgridImportToday'         => 'IQgridImport',
        'IQgridExportToday'         => 'IQgridExport',
        'IQhouseEnergyToday'        => 'IQhouseEnergyTotal',
        'IQselfConsumedToday'       => 'IQselfConsumedTotal',
        'IQselfSuppliedToday'       => 'IQselfSuppliedTotal',
    ],

    /*
     * ============================================================
     * Parser für rohe JSON-Items aus /rest/items
     * macht aus:
     *   "250 W"
     *   "16211.59 kWh"
     *   "1776533400000|31.73 Wh"
     * ein einheitliches Array
     * ============================================================
     */
    'parseItem' => static function (array $item): array {
        $sensorId = $item['name'] ?? '';
        $label    = $item['label'] ?? '';
        $raw      = trim((string)($item['state'] ?? ''));
        $type     = $item['type'] ?? '';
        $editable = (bool)($item['editable'] ?? false);

        $timestampMs = null;
        $valuePart   = $raw;

        if (str_contains($raw, '|')) {
            [$left, $right] = explode('|', $raw, 2);
            $left = trim($left);
            $right = trim($right);

            if (preg_match('/^\d{10,13}$/', $left)) {
                $timestampMs = (int)$left;
                $valuePart   = $right;
            }
        }

        $value = null;
        $unit  = '';

        if ($valuePart === '' || strtoupper($valuePart) === 'NULL' || strtoupper($valuePart) === 'UNDEF') {
            $value = null;
            $unit  = '';
        } elseif (preg_match('/^\s*([+-]?[0-9]+(?:[.,][0-9]+)?(?:E[+-]?[0-9]+)?)\s*(.*)\s*$/i', $valuePart, $m)) {
            $num = str_replace(',', '.', $m[1]);
            $value = is_numeric($num) ? (float)$num : null;
            $unit = trim($m[2] ?? '');
        } else {
            $value = $valuePart;
            $unit  = '';
        }

        return [
            'sensorID'      => $sensorId,
            'label'         => $label,
            'raw'           => $raw,
            'value_raw'     => $valuePart,
            'value'         => $value,
            'unit'          => $unit,
            'timestamp_ms'  => $timestampMs,
            'timestamp'     => $timestampMs !== null ? (int)floor($timestampMs / 1000) : null,
            'type'          => $type,
            'editable'      => $editable,
        ];
    },

    /*
     * ============================================================
     * Hilfsfunktion:
     * baut aus kompletter /rest/items Antwort ein Lookup
     * keyed by sensorID
     * ============================================================
     */
    'buildLookup' => static function (array $items): array {
        $lookup = [];

        foreach ($items as $item) {
            if (!is_array($item) || empty($item['name'])) {
                continue;
            }

            $sensorId = (string)$item['name'];
            $raw      = trim((string)($item['state'] ?? ''));
            $timestampMs = null;
            $valuePart   = $raw;

            if (str_contains($raw, '|')) {
                [$left, $right] = explode('|', $raw, 2);
                $left = trim($left);
                $right = trim($right);

                if (preg_match('/^\d{10,13}$/', $left)) {
                    $timestampMs = (int)$left;
                    $valuePart   = $right;
                }
            }

            $value = null;
            $unit  = '';

            if ($valuePart === '' || strtoupper($valuePart) === 'NULL' || strtoupper($valuePart) === 'UNDEF') {
                $value = null;
                $unit = '';
            } elseif (preg_match('/^\s*([+-]?[0-9]+(?:[.,][0-9]+)?(?:E[+-]?[0-9]+)?)\s*(.*)\s*$/i', $valuePart, $m)) {
                $num = str_replace(',', '.', $m[1]);
                $value = is_numeric($num) ? (float)$num : null;
                $unit = trim($m[2] ?? '');
            } else {
                $value = $valuePart;
            }

            $lookup[$sensorId] = [
                'sensorID'      => $sensorId,
                'label'         => $item['label'] ?? '',
                'raw'           => $raw,
                'value_raw'     => $valuePart,
                'value'         => $value,
                'unit'          => $unit,
                'timestamp_ms'  => $timestampMs,
                'timestamp'     => $timestampMs !== null ? (int)floor($timestampMs / 1000) : null,
                'type'          => $item['type'] ?? '',
                'editable'      => (bool)($item['editable'] ?? false),
            ];
        }

        return $lookup;
    },

];
<?php
/**
 * Bedeutung der Mapping-Keys
 * Kurzname => Erklärung / Einsatz
 */

$mappingInfo = [

/* =====================================================
   PV / WECHSELRICHTER
   ===================================================== */

'IQpvPower' =>
'Aktuelle PV-Leistung live in Watt. Hauptwert für Solarproduktion jetzt. Standardwert fürs Dashboard.',

'IQpvEnergy' =>
'Gesamter bisher erzeugter PV-Ertrag in kWh seit Installation. Basis für PV-Tageswert durch Differenz 00:00.',

'IQinverterPower' =>
'Aktuelle reale AC-Ausgangsleistung des Wechselrichters Richtung Haus/Netz.',

'IQinverterPowerRaw' =>
'Direkter Rohwert aus dem Wechselrichterregister. Gut für Diagnose und Vergleich mit berechnetem Wert.',

'IQinverterApparentPower' =>
'Scheinleistung (VA). Enthält Blindleistung. Technikwert für Netzqualität.',

'IQselfConsumptionPower' =>
'Aktuelle Leistung, die direkt selbst verbraucht wird (PV sofort im Haus genutzt).',

'IQinverterEnergyIn' =>
'Gesamte Energie in den Wechselrichter hinein. Technik-/Bilanzwert.',

'IQinverterEnergyOut' =>
'Gesamte Energie aus dem Wechselrichter heraus. Technik-/Bilanzwert.',

'IQpvPowerMPPT' =>
'PV-Leistung aus den MPPT-Trackern berechnet. Sehr guter Solarwert, oft präziser als Rohwert.',

'IQpvPowerMeasured' =>
'Gemessene PV-Leistung aus separatem Produktions-Messmodell (measurable production). Zweitquelle / Kontrollwert zu IQpvPower.',


/* =====================================================
   BATTERIE
   ===================================================== */

'IQbatterySoc' =>
'State of Charge in Prozent. Wichtigster Akkuwert.',

'IQbatteryPower' =>
'Aktuelle Batterieleistung. Positiv/negativ je nach Herstellerlogik Laden oder Entladen.',

'IQbatteryPowerCalc' =>
'Berechnete Batterieleistung aus Spannung × Strom. Kontrollwert.',

'IQbatteryChargePower' =>
'Aktuelle Ladeleistung des Akkus in Watt. Separat nur fürs Laden.',

'IQbatteryDischargePower' =>
'Aktuelle Entladeleistung des Akkus in Watt. Separat nur fürs Entladen.',

'IQbatteryChargeTotal' =>
'Gesamte in den Akku geladene Energie in kWh seit Installation. Basis für Tagesladung.',

'IQbatteryDischargeTotal' =>
'Gesamte aus Akku entladene Energie in kWh seit Installation. Basis für Tagesentladung.',

'IQbatteryTemp' =>
'Akkutemperatur in °C. Wichtig für Lebensdauer und Warnungen.',

'IQbatteryMode' =>
'Numerischer Betriebsmodus des Akkus. Meist -1 entladen / 0 idle / 1 laden.',

'IQbatteryModeTxt' =>
'Textdarstellung des Betriebsmodus, z.B. OFF / IDLE / CHARGE.',

'IQbatteryMaxCharge' =>
'Momentan maximal mögliche Ladeleistung. Vom System begrenzt nach SoC/Temperatur.',

'IQbatteryMaxDischarge' =>
'Momentan maximal mögliche Entladeleistung. Vom System begrenzt nach SoC/Temperatur.',


/* =====================================================
   NETZ / POWERMETER
   ===================================================== */

'IQgridPower' =>
'Aktueller Netzfluss live. Bezug oder Einspeisung je nach Vorzeichen.',

'IQgridImport' =>
'Gesamter Strombezug aus dem Netz seit Installation. Basis für Netzbezug heute.',

'IQgridExport' =>
'Gesamte Einspeisung ins Netz seit Installation. Basis für Einspeisung heute.',

'IQphase1' =>
'Leistung Phase L1 aktuell.',

'IQphase2' =>
'Leistung Phase L2 aktuell.',

'IQphase3' =>
'Leistung Phase L3 aktuell.',

'IQgridPowerMeasuredProd' =>
'Gemessener Produktionsfluss über Powermeter. Kontroll-/Sonderwert.',

'IQgridPowerMeasuredCons' =>
'Gemessener Verbrauchsfluss über Powermeter. Kontroll-/Sonderwert.',


/* =====================================================
   KIWIGRID HARMONIZED POWER
   ===================================================== */

'IQhousePower' =>
'Berechneter aktueller Hausverbrauch. Einer der besten Live-Verbrauchswerte.',

'IQpowerProduced' =>
'Systemweit harmonisierte aktuelle Produktion.',

'IQselfConsumed' =>
'Aktuelle Leistung, die direkt selbst verbraucht wird.',

'IQselfSupplied' =>
'Aktuelle Leistung, mit der sich das Haus selbst versorgt (PV + Akku).',

'IQconsumedFromGrid' =>
'Aktueller Verbrauch, der gerade aus dem Netz kommt.',

'IQconsumedFromStorage' =>
'Aktueller Verbrauch, der gerade aus dem Akku kommt.',

'IQconsumedFromProducers' =>
'Aktueller Verbrauch direkt aus PV-Erzeugern.',

'IQpowerOut' =>
'Aktueller Gesamtfluss nach außen.',

'IQpowerIn' =>
'Aktueller Gesamtfluss nach innen.',


/* =====================================================
   KIWIGRID HARMONIZED ENERGY TOTAL
   ===================================================== */

'IQhouseEnergyTotal' =>
'Gesamter Hausverbrauch seit Start/Installation.',

'IQpvEnergyTotal' =>
'Gesamte erzeugte Energie laut harmonisiertem Systemwert.',

'IQselfConsumedTotal' =>
'Gesamter direkt selbst verbrauchter Strom.',

'IQselfSuppliedTotal' =>
'Gesamte autarke Versorgung (PV + Akku).',

'IQgridImportTotal' =>
'Gesamter aus Netz bezogener Strom laut KiwiGrid.',

'IQbatteryUsedTotal' =>
'Gesamte aus Akku im Haus verbrauchte Energie.',

'IQconsumedFromProducersTotal' =>
'Gesamtverbrauch direkt aus Erzeugern.',

'IQworkOutTotal' =>
'Gesamte Energie aus dem System hinaus.',

'IQworkInTotal' =>
'Gesamte Energie ins System hinein.',
];