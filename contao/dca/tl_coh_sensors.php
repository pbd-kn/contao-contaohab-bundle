<?php

declare(strict_types=1);

use Contao\DC_Table;
use Contao\DataContainer;
use Contao\Database;

$GLOBALS['TL_DCA']['tl_coh_sensors'] = [

    'config' => [
        'dataContainer'    => DC_Table::class,
        'enableVersioning' => false,
        'oncopy_callback'  => [
            ['tl_coh_sensors', 'setUniqueSensorIDOnCopy'],
        ],
        'sql' => [
            'keys' => [
                'id'       => 'primary',
                'sensorID' => 'unique',
            ],
        ],
    ],

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
            'edit'   => ['href'=>'act=edit','icon'=>'edit.svg'],
            'copy'   => ['href'=>'act=copy','icon'=>'copy.svg'],
            'delete' => ['href'=>'act=delete','icon'=>'delete.svg'],
            'show'   => ['href'=>'act=show','icon'=>'show.svg'],
        ],
    ],

    'palettes' => [
        '__selector__' => ['isComponent','isHistory'],

        'default' => '
            {base_legend},sensorID,sensorTitle,
            sensorEinheit,sensorValueType,
            sensorSource,sensorLokalId,
            transFormProcedur,outputMode;

            {calc_legend},isComponent;

            {history_legend},isHistory
        ',
    ],

    'subpalettes' => [
        'isComponent' => 'componentSensors,componentFormula',
        'isHistory'   => 'history',
    ],

    'fields' => [

        'id' => [
            'sql' => "int(10) unsigned NOT NULL auto_increment",
        ],

        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],

        // ---------------- BASIS ----------------

        'sensorID' => [
            'label'     => ['Sensor-ID', 'Eindeutige technische ID'],
            'inputType' => 'text',
            'search'    => true,
            'sorting'   => true,
            'eval'      => ['maxlength'=>255,'tl_class'=>'w50','unique'=>true],
            'save_callback' => [
                ['tl_coh_sensors','generateSensorID'],
            ],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],

        'sensorTitle' => [
            'label'     => ['Bezeichnung'],
            'inputType' => 'text',
            'eval'      => ['mandatory'=>true,'maxlength'=>255,'tl_class'=>'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],

        'sensorEinheit' => [
            'label' => ['Einheit'],
            'inputType' => 'select',
            'options'   => ['kWh','W','kW','°C','Datum','Zeit','DatumZeit','Text','OK'],
            'eval'      => ['includeBlankOption'=>true,'chosen'=>true,'tl_class'=>'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],

        'sensorValueType' => [
            'label' => ['Wertetyp'],
            'inputType' => 'select',
            'options'   => ['int','float','text'],
            'eval'      => ['tl_class'=>'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],

        'sensorSource' => [
            'label' => ['Quelle'],
            'inputType' => 'select',
            'options_callback' => ['tl_coh_sensors','getGeraeteIDs'],
            'eval' => ['includeBlankOption'=>true,'chosen'=>true,'tl_class'=>'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],

        'sensorLokalId' => [
            'label' => ['Lokale ID'],
            'inputType' => 'text',
            'eval'      => ['maxlength'=>255,'tl_class'=>'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],

        'transFormProcedur' => [
            'label' => ['Transform'],
            'inputType' => 'select',
            'options' => [
                'elwaPwrkWh','elwaPwr','elwaTemp',
                'tskWh','tsWatt'
            ],
            'eval' => ['includeBlankOption'=>true,'chosen'=>true,'tl_class'=>'w50'],
            'sql'  => "varchar(255) NOT NULL default ''",
        ],

        'outputMode' => [
            'label' => ['Ausgabe'],
            'inputType' => 'select',
            'options' => [
                'absolute'=>'Absolut',
                'daily'=>'Heute',
                'woche'=>'7 Tage',
                'monat'=>'30 Tage',
                'jahr'=>'365 Tage',
            ],
            'eval' => ['mandatory'=>true,'chosen'=>true,'tl_class'=>'w50'],
            'sql' => "varchar(20) NOT NULL default 'absolute'",
        ],

        // ---------------- COMPONENT ----------------

        'isComponent' => [
            'label'     => ['Komponente'],
            'inputType' => 'checkbox',
            'eval'      => ['submitOnChange'=>true,'tl_class'=>'w50 clr'],
            'sql'       => "char(1) NOT NULL default ''",
        ],

        'componentSensors' => [
            'label' => ['Sensoren'],
            'inputType' => 'multiColumnWizard',
            'eval' => [
                'columnFields' => [
                    'alias' => [
                        'label'=>['Alias'],
                        'inputType'=>'text',
                        'eval'=>['tl_class'=>'w50']
                    ],
                    'sensor' => [
                        'label'=>['Sensor'],
                        'inputType'=>'select',
                        'options_callback'=>['tl_coh_sensors','getSensorIDs'],
                        'eval'=>['tl_class'=>'w50']
                    ],
                    'factor' => [
                        'label'=>['Faktor'],
                        'inputType'=>'text',
                        'eval'=>['tl_class'=>'w50']
                    ],
                ],
                'tl_class'=>'clr',
            ],
            'sql' => "blob NULL",
        ],

        'componentFormula' => [
            'label' => ['Formel'],
            'inputType' => 'text',
            'eval' => ['tl_class'=>'clr'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],

        // ---------------- HISTORY ----------------

        'isHistory' => [
            'label'     => ['History aktiv'],
            'inputType' => 'checkbox',
            'eval'      => ['submitOnChange'=>true,'tl_class'=>'w50 clr'],
            'sql'       => "char(1) NOT NULL default '0'",
        ],

        'history' => [
            'label' => ['Speichern'],
            'inputType' => 'select',
            'options'   => [0,1,2,3,4,5],
            'reference' => ['Nein','Polltime','Stündlich','Täglich','Wöchentlich','Monatlich'],
            'eval'      => ['tl_class'=>'w50'],
            'sql'       => "tinyint(1) NOT NULL default '0'",
        ],

        'historycount' => [
            'sql' => "int(10) unsigned NOT NULL default 0",
        ],
    ],
];


class tl_coh_sensors
{
    public function getGeraeteIDs(): array
    {
        $db = Database::getInstance();
        $options = [];

        if (!$db->tableExists('tl_coh_geraete')) {
            return $options;
        }

        $res = $db->prepare("SELECT geraeteID FROM tl_coh_geraete")->execute();

        while ($res->next()) {
            $options[$res->geraeteID] = $res->geraeteID;
        }

        return $options;
    }

    public function getSensorIDs(): array
    {
        $db = Database::getInstance();
        $options = [];

        $res = $db->prepare("SELECT sensorID FROM tl_coh_sensors")->execute();

        while ($res->next()) {
            $options[$res->sensorID] = $res->sensorID;
        }

        return $options;
    }

    public function generateSensorID($value, DataContainer $dc): string
    {
        if (!empty($value)) {
            return $this->unique($value, (int)($dc->id ?? 0));
        }

        $title = $dc->activeRecord->sensorTitle ?? '';

        $base = $title
            ? str_replace(' ', '_', trim($title))
            : 'sensor_' . date('Ymd_His');

        return $this->unique($base, (int)($dc->id ?? 0));
    }

    public function setUniqueSensorIDOnCopy(int $insertId): void
    {
        $db = Database::getInstance();

        $row = $db->prepare("SELECT sensorTitle FROM tl_coh_sensors WHERE id=?")
                  ->execute($insertId);

        $base = $row->sensorTitle
            ? str_replace(' ', '_', trim($row->sensorTitle))
            : 'sensor_' . date('Ymd_His');

        $new = $this->unique($base, $insertId);

        $db->prepare("UPDATE tl_coh_sensors SET sensorID=? WHERE id=?")
           ->execute($new, $insertId);
    }

    private function unique(string $base, int $id): string
    {
        $db = Database::getInstance();

        $candidate = $base;
        $i = 1;

        while (
            $db->prepare("SELECT id FROM tl_coh_sensors WHERE sensorID=? AND id!=?")
               ->execute($candidate, $id)
               ->numRows > 0
        ) {
            $candidate = $base . '_' . $i;
            $i++;
        }

        return $candidate;
    }
}