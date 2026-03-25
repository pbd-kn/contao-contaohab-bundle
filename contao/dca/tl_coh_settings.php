<?php

$GLOBALS['TL_DCA']['tl_coh_settings'] = [

    // ---------------------------------------------------
    // CONFIG
    // ---------------------------------------------------
    'config' => [
        'dataContainer' => 'Table',
        'enableVersioning' => false,
        'closed' => true,   // nur ein Datensatz!
        'sql' => [
            'keys' => [
                'id' => 'primary'
            ]
        ]
    ],

    // ---------------------------------------------------
    // LIST
    // ---------------------------------------------------
    'list' => [
        'sorting' => [
            'mode' => 1,
            'flag' => 1
        ],
        'label' => [
            'fields' => ['coh_syr_base_url'],
            'format' => 'SYR: %s'
        ],
        'global_operations' => [
            'all' => [
                'label' => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href'  => 'act=select',
                'class' => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"'
            ]
        ],
        'operations' => [
            'edit' => [
                'label' => &$GLOBALS['TL_LANG']['tl_coh_settings']['edit'],
                'href'  => 'act=edit',
                'icon'  => 'edit.svg'
            ]
        ]
    ],

    // ---------------------------------------------------
    // PALETTES
    // ---------------------------------------------------
    'palettes' => [
        'default' => '{coh_legend},coh_syr_base_url,coh_api_token,coh_timeout'
    ],

    // ---------------------------------------------------
    // FIELDS
    // ---------------------------------------------------
    'fields' => [

        'id' => [
            'sql' => "int(10) unsigned NOT NULL auto_increment"
        ],

        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default 0"
        ],

        // -----------------------------
        // SYR URL
        // -----------------------------
        'coh_syr_base_url' => [
            'label'     => ['SYR Basis URL', 'z.B. http://192.168.178.65:5333'],
            'inputType' => 'text',
            'eval'      => ['maxlength'=>255, 'tl_class'=>'w50'],
            'sql'       => "varchar(255) NOT NULL default ''"
        ],

        // -----------------------------
        // TOKEN
        // -----------------------------
        'coh_api_token' => [
            'label'     => ['API Token', 'Token für AJAX Zugriff'],
            'inputType' => 'text',
            'eval'      => ['maxlength'=>255, 'tl_class'=>'w50'],
            'sql'       => "varchar(255) NOT NULL default ''"
        ],

        // -----------------------------
        // TIMEOUT
        // -----------------------------
        'coh_timeout' => [
            'label'     => ['Timeout (Sekunden)', 'Wartezeit Ventilsteuerung'],
            'inputType' => 'text',
            'default'   => '50',
            'eval'      => ['rgxp'=>'digit','maxlength'=>3,'tl_class'=>'w50'],
            'sql'       => "int(3) NOT NULL default 50"
        ],
    ]
];