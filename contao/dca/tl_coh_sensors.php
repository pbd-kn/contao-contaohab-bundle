<?php

declare(strict_types=1);


use Contao\DC_Table;
use Contao\DataContainer;
use Contao\Database;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

$GLOBALS['TL_DCA']['tl_coh_sensors'] = [

    'config' => [
        'dataContainer'    => DC_Table::class,
        'enableVersioning' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary',
            ],
        ],
        'onsubmit_callback' => [
            ['tl_coh_sensors', 'onSubmitRecord'],
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

    'palettes' => [
        'default' =>
            '{first_legend},
            sensorID,
            sensorTitle,
            sensorEinheit,
            sensorValueType,
            sensorSource,
            sensorLokalId,
            transFormProcedur,
            history',
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
            'eval' => ['mandatory'=>true, 'maxlength'=>255, 'tl_class'=>'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],

        'sensorTitle' => [
            'inputType' => 'text',
            'eval' => ['mandatory'=>true, 'maxlength'=>255, 'tl_class'=>'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],

        'sensorEinheit' => [
            'inputType' => 'select',
            'options' => ['kWh','W','kW','GradC','Datum','Zeit','DatumZeit','Text','OK'],
            'eval' => ['includeBlankOption'=>true, 'chosen'=>true, 'tl_class'=>'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],

        'sensorValueType' => [
            'inputType' => 'select',
            'options' => ['int','float','GradC','Datum','Zeit','DatumZeit','Text'],
            'eval' => ['tl_class'=>'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],

        'sensorSource' => [
            'inputType' => 'select',
            'options_callback' => ['tl_coh_sensors', 'getGeraeteIDs'],
            'eval' => [
                'mandatory' => false,
                'includeBlankOption' => true,
                'chosen' => true,
                'tl_class' => 'w50',
            ],
            'sql' => "varchar(255) NOT NULL default ''",
        ],

'sensorLokalId' => [
    'label'     => &$GLOBALS['TL_LANG']['tl_coh_sensors']['sensorLokalId'],
    'inputType' => 'text',
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
            'sql' => "varchar(255) NOT NULL default ''",
        ],

        'history' => [
            'inputType' => 'select',
            'options' => [0,1],
            'eval' => ['isBoolean'=>true, 'tl_class'=>'w50'],
            'sql' => "tinyint(1) NOT NULL default '0'",
        ],
    ],
];

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

    public function onSubmitRecord(DataContainer $dc): void
    {
    /* funktioniert bei contao 5 nicht mehr
        \System::log(
            'Gespeichert: sensorLokalId=' . ($dc->activeRecord->sensorLokalId ?? 'NULL'),
            __METHOD__,
            TL_GENERAL
        );
    */
        if (!$dc->id) {
            return;
        }

        // Wert sensorLokalId direkt aus POST holen
// Wert direkt aus dem ActiveRecord (bereits normalisiert durch Contao)
$data = $dc->getCurrentRecord();
    $value = (string) ($data['sensorLokalId'] ?? '');
        \Contao\Database::getInstance()
            ->prepare("UPDATE tl_coh_sensors SET sensorLokalId=? WHERE id=?")
            ->execute($value, $dc->id);
    
    }
}
