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
            'fields'      => ['sensorTitle','sensorID','sensorSource'],
            'flag'        => DataContainer::SORT_ASC,
            'panelLayout' => 'filter;sort,search,limit',
        ],
        'label' => [
            'fields' => ['sensorID','sensorTitle','sensorEinheit','sensorActive'],
            'label_callback' => ['tl_coh_sensors', 'formatSensorLabel'],
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
            sensorActive,sensorEinheit,sensorValueType,
            sensorSource,sensorLokalId,sensorComment,
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
            'label'     => ['Sensor-ID', 'Eindeutige technische ID. Wenn sensorLokalId nicht gesetzt ist, verwendet der Controller auf dem Raspberry diese ID zum Zugriff auf den Sensorwert.'],
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

        'sensorActive' => [
            'label'     => ['Aktiv', 'Deaktivierte Sensoren bleiben erhalten, werden aber nicht angezeigt und nicht zum Raspberry Ã¼bertragen.'],
            'inputType' => 'checkbox',
            'filter'    => true,
            'eval'      => ['tl_class'=>'w50'],
            'sql'       => "char(1) NOT NULL default '1'",
            'default'   => '1',
        ],
        'sensorEinheit' => [
            'label' => ['Einheit'],
            'inputType' => 'select',
            'options'   => ['-','kWh','W','kW','°C','Datum','Zeit','DatumZeit','Text','OK','%'],
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
            'label' => ['Quelle', 'Auch Verweis auf Service beim Raspberry'],
            'inputType' => 'select',
            'search'    => true,
            'filter'    => true,
            'sorting'   => true,
            'options_callback' => ['tl_coh_sensors','getGeraeteIDs'],
            'eval' => ['includeBlankOption'=>true,'chosen'=>true,'tl_class'=>'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],

        'sensorLokalId' => [
            'label' => ['Lokale ID', 'Diese Referenz steht dem Service auf dem Raspberry zur VerfÃ¼gung.'],
            'inputType' => 'text',
            'search'    => true,
            'eval'      => ['maxlength'=>255,'tl_class'=>'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],

        'sensorComment' => [
            'label'     => ['Kommentar', 'Interne Notiz dazu, was der Sensor misst oder wofÃ¼r er verwendet wird.'],
            'inputType' => 'textarea',
            'eval'      => ['tl_class'=>'clr', 'rows'=>4],
            'sql'       => "text NULL",
        ],
        'transFormProcedur' => [
            'label' => ['Transform'],
            'inputType' => 'select',
            'options' => [
                '-_,'durch_10','durch_100','durch_1000','elwaPwrkWh','elwaPwr','elwaTemp',
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
            'reference' => ['Nein','Polltime','StÃ¼ndlich','TÃ¤glich','WÃ¶chentlich','Monatlich'],
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
    public function formatSensorLabel(array $row, string $label, DataContainer $dc, array $args): string
    {
        $sensorId = (string)($row['sensorID'] ?? $args[0] ?? '');
        $title = (string)($row['sensorTitle'] ?? $args[1] ?? '');
        $unit = (string)($row['sensorEinheit'] ?? $args[2] ?? '');
        $active = (string)($row['sensorActive'] ?? $args[3] ?? '');

        $label = sprintf('%s | %s (%s)', $sensorId, $title, $unit);

        if ($active === '1') {
            return $label;
        }

        return sprintf(
            '<span style="color:#777;text-decoration:line-through;">[INAKTIV] %s</span>',
            htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        );
    }
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

        $res = $db->prepare("SELECT sensorID FROM tl_coh_sensors WHERE sensorActive='1' ORDER BY sensorID")->execute();

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
