<?php

declare(strict_types=1);

/*
 * This file is part of ContaoHab.
 *
 * (c) Peter Broghammer 2025 <pb-contao@gmx.de>
 * @license GPL-3.0-or-later
 * @link https://github.com/pbd-kn/contao-contaohab-bundle
 */

use Contao\DC_Table;
use Contao\DataContainer;
use Contao\System;

$strTable = 'tl_coh_sync_log';

$GLOBALS['TL_DCA'][$strTable] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'enableVersioning' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary'
            ]
        ],
    ],

    'list' => [
        'sorting' => [
            'mode' => 2,
            'fields' => ['sync_type'],
            'flag' => DataContainer::SORT_ASC,
            'panelLayout' => 'filter;sort,search,limit'
        ],
        'label' => [
            'fields' => ['sync_type', 'last_sync'],
            'format' => '%s (%s)',
        ],
        'global_operations' => [
            'all' => [
                'href' => 'act=select',
                'class' => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"'
            ]
        ],
        'operations' => [
            'edit' => [
                'label' => &$GLOBALS['TL_LANG'][$strTable]['edit'],
                'href' => 'act=edit',
                'icon' => 'edit.svg'
            ],
            'copy' => [
                'label' => &$GLOBALS['TL_LANG'][$strTable]['copy'],
                'href' => 'act=copy',
                'icon' => 'copy.svg'
            ],
            'delete' => [
                'label' => &$GLOBALS['TL_LANG'][$strTable]['delete'],
                'href' => 'act=delete',
                'icon' => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'' . ($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? '') . '\'))return false;Backend.getScrollOffset()"'
            ],
            'show' => [
                'label' => &$GLOBALS['TL_LANG'][$strTable]['show'],
                'href' => 'act=show',
                'icon' => 'show.svg',
                'attributes' => 'style="margin-right:3px"'
            ],
        ]
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
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default 0"
        ],
    ]
];
