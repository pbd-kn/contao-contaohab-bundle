<?php

declare(strict_types=1);

use Contao\DC_Table;
use Contao\DataContainer;
use Contao\Database;

$GLOBALS['TL_DCA']['tl_coh_sensors'] = [

    // ---------------------------------------------------
    // CONFIG
    // ---------------------------------------------------
    'config' => [
        'dataContainer'    => DC_Table::class,
        'enableVersioning' => false,
        'sql' => [
            'keys' => [
                'id' => 'primary',
            ],
        ],
    ],

    // ---------------------------------------------------
    // LIST
    // ---------------------------------------------------
    'list' => [
        'sorting' => [
            'mode'        => 2,
            'fields'      => ['sensorTitle'],
            'flag'        => DataContainer::SORT_ASC,
            'panelLayout' => 'filter;sort,search,limit',
        ],

        'label' => [
            'fields' => ['sensorID','sensorTitle','sensorEinheit'],
            'format' => '%s | %s (%s)',
        ],

        'operations' => [
            'edit'   => ['href' => 'act=edit',   'icon' => 'edit.svg'],
            'copy'   => ['href' => 'act=copy',   'icon' => 'copy.svg'],
            'delete' => ['href' => 'act=delete', 'icon' => 'delete.svg'],
            'show'   => ['href' => 'act=show',   'icon' => 'show.svg'],
        ],
    ],

    // ---------------------------------------------------
    // PALETTES
    // ---------------------------------------------------
    'palettes' => [
        '__selector__' => ['isComponent','isHistory'],

        'default' => '
            {base_legend},sensorID,sensorTitle,
            sensorEinheit,sensorValueType,
            sensorSource,sensorLokalId,
            transFormProcedur,
            outputMode,
            {calc_legend},isComponent,
            {history_legend},isHistory
        ',
    ],

    // ---------------------------------------------------
    // SUBPALETTES (TOGGLES)
    // ---------------------------------------------------
    'subpalettes' => [
        'isComponent' => 'componentSensors,componentFormula',
        'isHistory'   => 'history',
    ],

    // ---------------------------------------------------
    // FIELDS
    // ---------------------------------------------------
    'fields' => [

        'id' => [
            'sql' => "int(10) unsigned NOT NULL auto_increment",
        ],

        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],

        // ---------------- BASE ----------------

        'sensorID' => [
            'label'     => ['Sensor-ID', 'Eindeutige technische ID'],
            'inputType' => 'text',
            'search'    => true,
            'sorting'   => true,
            'eval'      => ['mandatory'=>true, 'maxlength'=>255, 'tl_class'=>'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],

        'sensorTitle' => [
            'label'     => ['Bezeichnung', 'Anzeige im Frontend'],
            'inputType' => 'text',
            'search'    => true,
            'sorting'   => true,
            'eval'      => ['mandatory'=>true, 'maxlength'=>255, 'tl_class'=>'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],

        'sensorEinheit' => [
            'label'     => ['Einheit', 'Anzeigeeinheit'],
            'inputType' => 'select',
            'options'   => ['kWh','W','kW','°C','Datum','Zeit','DatumZeit','Text','OK'],
            'eval'      => ['includeBlankOption'=>true, 'chosen'=>true, 'tl_class'=>'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],

        'sensorValueType' => [
            'label'     => ['Wertetyp', 'Datentyp'],
            'inputType' => 'select',
            'options'   => ['int','float','text'],
            'eval'      => ['tl_class'=>'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],

        'sensorSource' => [
            'label' => ['Quelle', 'Gerät'],
            'inputType' => 'select',
            'options_callback' => ['tl_coh_sensors', 'getGeraeteIDs'],
            'eval' => ['includeBlankOption'=>true, 'chosen'=>true, 'tl_class'=>'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],

        'sensorLokalId' => [
            'label'     => ['Lokale ID', 'z.B. API-Key'],
            'inputType' => 'text',
            'eval'      => ['maxlength'=>255, 'tl_class'=>'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],

        'transFormProcedur' => [
            'label' => ['Transform', 'Optionaler Umrechnungsprozess'],
            'inputType' => 'select',
            'options' => [
                'elwaPwrkWh','elwaPwr','elwaTemp',
                'IQkW','IQkWh','IQSOC','IQTemp',
                'tskWh','tsWatt'
            ],
            'eval' => ['includeBlankOption'=>true, 'chosen'=>true, 'tl_class'=>'w50'],
            'sql'  => "varchar(255) NOT NULL default ''",
        ],

        'outputMode' => [
            'label' => ['Ausgabe', 'Wie soll der Wert berechnet werden?'],
            'inputType' => 'select',
            'options' => [
                'absolute' => 'Absolut',
                'daily'    => 'Heute',
                'woche'    => '7 Tage',
                'monat'    => '30 Tage',
                'jahr'     => '365 Tage',
            ],
            'eval' => ['mandatory'=>true, 'chosen'=>true, 'tl_class'=>'w50'],
            'sql' => "varchar(20) NOT NULL default 'absolute'",
        ],

        // ---------------- COMPONENT ----------------

        'isComponent' => [
            'label'     => ['Komponente', 'Besteht aus mehreren Sensoren'],
            'inputType' => 'checkbox',
            'eval'      => ['submitOnChange'=>true, 'tl_class'=>'w50 clr'],
            'sql'       => "char(1) NOT NULL default ''",
        ],

        'componentSensors' => [
            'label' => ['Sensoren', 'Alias + Sensor wählen'],
            'inputType' => 'multiColumnWizard',
            'eval' => [
                'columnFields' => [
                    'alias' => [
                        'label' => ['Alias'],
                        'inputType' => 'text',
                        'eval' => ['maxlength'=>32],
                    ],
                    'sensor' => [
                        'label' => ['Sensor'],
                        'inputType' => 'select',
                        'options_callback' => ['tl_coh_sensors', 'getSensorIDs'],
                    ],
                    'factor' => [
                        'label' => ['Faktor'],
                        'inputType' => 'text',
                    ],
                ],
                'tl_class' => 'clr',
            ],
            'sql' => "blob NULL",
        ],

        'componentFormula' => [
            'label' => ['Formel', 'z.B. a + b'],
            'inputType' => 'text',
            'eval' => ['maxlength'=>255, 'tl_class'=>'clr'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],

        // ---------------- HISTORY ----------------

        'isHistory' => [
            'label'     => ['History aktiv', 'Zeitreihe speichern abhängig von collectsensort bei daten sammeln'],
            'inputType' => 'checkbox',
            'eval'      => ['submitOnChange'=>true, 'tl_class'=>'w50 clr'],
            'sql'       => "char(1) NOT NULL default ''",
        ],

        'history' => [
            'label' => ['Speichern', '0 = nein, 1 = ja'],
            'inputType' => 'select',
            'options'   => [0,1],
            'reference' => ['Nein','Ja'],
            'eval'      => ['tl_class'=>'w50'],
            'sql'       => "tinyint(1) NOT NULL default '0'",
        ],
    ],
];


// ---------------------------------------------------
// HELPER
// ---------------------------------------------------
class tl_coh_sensors
{
    public function getGeraeteIDs(): array
    {
        $options = [];
        $db = Database::getInstance();

        if (!$db->tableExists('tl_coh_geraete')) {
            return $options;
        }

        $result = $db
            ->prepare("SELECT geraeteID FROM tl_coh_geraete ORDER BY geraeteID")
            ->execute();

        while ($result->next()) {
            $options[$result->geraeteID] = $result->geraeteID;
        }

        return $options;
    }

    public function getSensorIDs(): array
    {
        $options = [];
        $db = Database::getInstance();

        $result = $db
            ->prepare("SELECT sensorID FROM tl_coh_sensors ORDER BY sensorID")
            ->execute();

        while ($result->next()) {
            $options[$result->sensorID] = $result->sensorID;
        }

        return $options;
    }
}