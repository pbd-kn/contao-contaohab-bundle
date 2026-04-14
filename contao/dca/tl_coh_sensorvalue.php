<?php

declare(strict_types=1);

use Contao\DataContainer;
use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_coh_sensorvalue'] = [

    // ---------------------------------------------------
    // CONFIG
    // ---------------------------------------------------
    'config' => [
        'dataContainer'    => DC_Table::class,
        'enableVersioning' => false,

        'sql' => [
            'keys' => [
                'id'              => 'primary',

                // ?? WICHTIG: Composite UNIQUE (für Sync!)
                'sensorID,tstamp' => 'unique',

                // Performance-Indizes
                'sensorID'        => 'index',
                'tstamp'          => 'index',
            ],
        ],
    ],

    // ---------------------------------------------------
    // LIST
    // ---------------------------------------------------
    'list' => [
        'sorting' => [
            'mode'        => 2,
            'fields'      => ['sensorID'],
            'flag'        => DataContainer::SORT_ASC,
            'panelLayout' => 'filter;sort,search,limit',
        ],

        'label' => [
            'fields' => ['sensorID', 'tstamp'],
            'format' => '%s [%s]',
        ],

        'operations' => [
            'edit' => [
                'href' => 'act=edit',
                'icon' => 'edit.svg',
            ],
            'delete' => [
                'href'       => 'act=delete',
                'icon'       => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'' . ($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? '') . '\'))return false;"',
            ],
            'show' => [
                'href' => 'act=show',
                'icon' => 'show.svg',
            ],
        ],
    ],

    // ---------------------------------------------------
    // PALETTES
    // ---------------------------------------------------
    'palettes' => [
        'default' => 'sensorID,tstamp,sensorEinheit,sensorValueType,sensorSource,sensorValue',
    ],

    // ---------------------------------------------------
    // FIELDS
    // ---------------------------------------------------
    'fields' => [

        'id' => [
            'sql' => "int(10) unsigned NOT NULL auto_increment",
        ],

        'tstamp' => [
            'sorting' => true,
            'flag'    => DataContainer::SORT_DESC,
            'sql'     => "int(10) unsigned NOT NULL default 0",
        ],

        'sensorID' => [
            'inputType' => 'text',
            'search'    => true,
            'filter'    => true,
            'sorting'   => true,
            'eval'      => [
                'mandatory' => true,
                'maxlength' => 255,
                'tl_class'  => 'w50',
            ],
            'sql' => "varchar(255) NOT NULL default ''",
        ],

        // ?? WICHTIG: TEXT statt SELECT ? sonst gehen Einheiten verloren
        'sensorEinheit' => [
            'inputType' => 'text',
            'eval'      => [
                'maxlength' => 50,
                'tl_class'  => 'w50',
            ],
            'sql' => "varchar(50) NOT NULL default ''",
        ],

        'sensorValueType' => [
            'inputType' => 'text',
            'eval'      => [
                'maxlength' => 50,
                'tl_class'  => 'w50',
            ],
            'sql' => "varchar(50) NOT NULL default ''",
        ],

        'sensorSource' => [
            'inputType' => 'text',
            'eval'      => [
                'maxlength' => 50,
                'tl_class'  => 'w50',
            ],
            'sql' => "varchar(50) NOT NULL default ''",
        ],

        'sensorValue' => [
            'inputType' => 'textarea',
            'eval'      => ['tl_class' => 'clr'],
            'sql'       => "longtext NULL",
        ],
    ],
];