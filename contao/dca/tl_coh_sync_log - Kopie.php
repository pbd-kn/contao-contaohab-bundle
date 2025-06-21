<?php

$GLOBALS['TL_DCA']['tl_coh_sync_log'] = [
    'config' => [
        'dataContainer' => 'Table',
        'closed' => true,
        'notDeletable' => true,
    ],

    'list' => [
        'sorting' => [
            'mode' => 1,
            'fields' => ['sync_type'],
            'flag' => 1,
        ],
        'label' => [
            'fields' => ['sync_type', 'last_sync'],
            'format' => '%s (%s)',
        ],
    ],

    'palettes' => [
        '__selector__' => [],
        'default' => 'sync_type,last_sync',
    ],

    'fields' => [
        'id' => [
            'label' => ['ID', 'Primärschlüssel'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['doNotCopy' => true],
            'sql' => "int(10) unsigned NOT NULL auto_increment",
        ],
        'sync_type' => [
            'label' => ['Sync-Typ', 'z. B. sensorvalue_pull'],
            'inputType' => 'text',
            'exclude' => true,
            'search' => true,
            'eval' => ['mandatory' => true, 'readonly' => true],
            'sql' => "varchar(32) NOT NULL default ''",
        ],
        'last_sync' => [
            'label' => ['Letzter Sync', 'Zeitpunkt der letzten Ausführung'],
            'inputType' => 'text',
            'exclude' => true,
            'eval' => ['readonly' => true],
            'sql' => "datetime NULL",
        ],
    ],

    'sql' => [
        'keys' => [
            'id' => 'primary',
        ],
    ],
];
