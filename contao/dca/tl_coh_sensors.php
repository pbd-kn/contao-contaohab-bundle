<?php

declare(strict_types=1);

use Contao\DC_Table;
use Contao\DataContainer;
use Contao\Database;
use Contao\CoreBundle\DataContainer\PaletteManipulator;

$GLOBALS['TL_DCA']['tl_coh_sensors'] = [

    'config' => [
        'dataContainer'    => DC_Table::class,
        'enableVersioning' => false,

        // Damit die Palette je nach Checkbox dynamisch erweitert werden kann
        'onload_callback'  => [
            ['tl_coh_sensors', 'toggleComponentFields'],
        ],

        'sql' => [
            'keys' => [
                'id' => 'primary',
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
            'fields' => ['sensorID','sensorTitle','sensorLokalId','sensorEinheit','transFormProcedur'],
            'format' => 'sensorID: %s | sensorTitle: %s | sensorLokalId: %s | sensorEinheit: %s | transFormProcedur: %s',
        ],
        'operations' => [
            'edit' => [
                'href' => 'act=edit',
                'icon' => 'edit.svg',
            ],
            'copy' => [
                'href' => 'act=copy',
                'icon' => 'copy.svg',
            ],
            'delete' => [
                'href' => 'act=delete',
                'icon' => 'delete.svg',
            ],
            'show' => [
                'href' => 'act=show',
                'icon' => 'show.svg',
            ],
        ],
    ],

    // Wichtig: Hier stehen NUR die Felder, die IMMER sichtbar sind.
    // componentSensors + componentFormula werden per PaletteManipulator dynamisch ergänzt.
    'palettes' => [
        'default' => '{first_legend},sensorID,sensorTitle,sensorEinheit,sensorValueType,sensorSource,sensorLokalId,transFormProcedur,history,outputMode,isComponent',
    ],

    'fields' => [

        'id' => [
            'sql' => "int(10) unsigned NOT NULL auto_increment",
        ],

        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],

        'sensorID' => [
            'inputType' => 'text',
            'search'    => true,
            'filter'    => true,
            'sorting'   => true,
            'eval'      => ['mandatory'=>true, 'maxlength'=>255, 'tl_class'=>'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],

        'sensorTitle' => [
            'inputType' => 'text',
            'search'    => true,
            'filter'    => true,
            'sorting'   => true,
            'eval'      => ['mandatory'=>true, 'maxlength'=>255, 'tl_class'=>'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],

        'sensorEinheit' => [
            'inputType' => 'select',
            'options'   => ['kWh','W','kW','GradC','Datum','Zeit','DatumZeit','Text','OK'],
            'eval'      => ['includeBlankOption'=>true, 'chosen'=>true, 'tl_class'=>'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],

        'sensorValueType' => [
            'inputType' => 'select',
            'options'   => ['int','float','GradC','Datum','Zeit','DatumZeit','Text'],
            'eval'      => ['tl_class'=>'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],

        'sensorSource' => [
            'inputType' => 'select',
            'search'    => true,
            'filter'    => true,
            'sorting'   => true,
            'options_callback' => ['tl_coh_sensors', 'getGeraeteIDs'],
            'eval' => [
                'mandatory'           => false,
                'includeBlankOption'  => true,
                'chosen'              => true,
                'tl_class'            => 'w50',
            ],
            'sql' => "varchar(255) NOT NULL default ''",
        ],

        'sensorLokalId' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_coh_sensors']['sensorLokalId'],
            'inputType' => 'text',
            'search'    => true,
            'filter'    => true,
            'sorting'   => true,
            'eval'      => [
                'maxlength' => 255,
                'tl_class'  => 'w50',
            ],
            'sql' => "varchar(255) NOT NULL DEFAULT ''",
        ],

        'transFormProcedur' => [
            'inputType' => 'select',
            'options' => [
                'elwaPwrkWh','elwaPwr','elwaTemp',
                'IQkW','IQkWh','IQSOC','IQTemp',
                'tskWh','tsWatt'
            ],
            'eval' => ['includeBlankOption'=>true, 'chosen'=>true, 'tl_class'=>'w50'],
            'sql'  => "varchar(255) NOT NULL default ''",
        ],

        'history' => [
            'inputType' => 'select',
            'options'   => [0,1],
            'eval'      => ['isBoolean'=>true, 'tl_class'=>'w50'],
            'sql'       => "tinyint(1) NOT NULL default '0'",
        ],

        'outputMode' => [
            'label' => ['Ausgabemodus', 'Wie soll der Wert berechnet werden?'],
            'inputType' => 'select',
            'options' => [
                'absolute' => 'Absolut (Rohwert)',
                'daily'    => 'Tageswert (00:00 ? jetzt)'
            ],
            'eval' => [
                'mandatory' => true,
                'chosen'    => true,
                'tl_class'  => 'w50',
            ],
            'sql' => "varchar(20) NOT NULL default 'absolute'",
        ],

        // === Schalter: Komponente ja/nein ===
        'isComponent' => [
            'label'     => ['Komponente', 'Dieser Sensor besteht aus mehreren anderen Sensoren'],
            'inputType' => 'checkbox',
            'eval'      => [
                'submitOnChange' => true,   // damit Reload passiert und PaletteManipulator greifen kann
                'tl_class'       => 'w50',
            ],
            'sql' => "char(1) NOT NULL default ''",
        ],

        // === Nur sichtbar, wenn isComponent aktiv (wird per PaletteManipulator eingefügt) ===
        'componentSensors' => [
            'label' => ['Komponenten-Sensoren', 'Reihenfolge per Drag & Drop festlegen (Aliase in der Formel verwenden)'],
            'inputType' => 'multiColumnWizard',
            'eval' => [
                'columnFields' => [
                    'alias' => [
                        'label' => ['Alias', 'in Formel verwenden z.B. a, pv1, haus_verbrauch (nur a-z0-9_- )'],
                        'inputType' => 'text',
                        'eval' => [
                            'rgxp'      => 'alias',
                            'maxlength' => 32,
                            'style'     => 'width:160px',
                        ],
                    ],
                    'sensor' => [
                        'label' => ['Sensor'],
                        'inputType' => 'select',
                        'options_callback' => ['tl_coh_sensors', 'getSensorIDs'],
                        'eval' => [
                            'chosen' => true,
                            'includeBlankOption' => true,
                            'style' => 'width:320px',
                        ],
                    ],
                    'factor' => [
                        'label' => ['Faktor', 'optional (z.B. -1, 0.5, 2)'],
                        'inputType' => 'text',
                        'eval' => [
                            'rgxp'  => 'numeric',
                            'style' => 'width:120px',
                        ],
                    ],
                ],
                'dragAndDrop' => true,
                'tl_class'    => 'clr',
            ],
            'sql' => "blob NULL",
        ],

        // === Nur sichtbar, wenn isComponent aktiv (wird per PaletteManipulator eingefügt) ===
        'componentFormula' => [
            'label' => ['Formel', 'Beispiel: a + b*0.95 - c (Aliase aus der Liste verwenden)'],
            'inputType' => 'text',
            'eval' => [
                'mandatory' => true,
                'maxlength' => 255,
                'tl_class'  => 'clr',
            ],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
    ],
];


class tl_coh_sensors
{
    /**
     * Blendet componentSensors + componentFormula nur ein, wenn isComponent aktiv ist.
     * Reihenfolge: isComponent -> componentSensors -> componentFormula
     */
    public function toggleComponentFields(DataContainer $dc = null): void
    {
        if (!$dc || !$dc->id) {
            return;
        }

        $obj = Database::getInstance()
            ->prepare("SELECT isComponent FROM tl_coh_sensors WHERE id=?")
            ->execute($dc->id);

        if ($obj->isComponent) {
            PaletteManipulator::create()
                ->addField('componentSensors', 'isComponent', PaletteManipulator::POSITION_AFTER)
                ->addField('componentFormula', 'componentSensors', PaletteManipulator::POSITION_AFTER)
                ->applyToPalette('default', 'tl_coh_sensors');
        }
    }

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