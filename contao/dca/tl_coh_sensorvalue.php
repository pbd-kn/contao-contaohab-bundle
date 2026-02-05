<?php

declare(strict_types=1);

/*
 * This file is part of ContaoHab.
 *
 * (c) Peter Broghammer 2025 <pb-contao@gmx.de>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/pbd-kn/contao-contaohab-bundle
 */

use Contao\Backend;
use Contao\DataContainer;
use Contao\DC_Table;
use Contao\Input;

$strDca='tl_coh_sensorvalue';


$GLOBALS['TL_DCA'][$strDca] = array(
    'config' => array(
        'dataContainer'    => DC_Table::class,
        'enableVersioning' => true,
        'sql'              => array(
            'keys' => array(
                'id' => 'primary',
                'sensorID,tstamp' => 'unique'
            )
        ),
        'onsubmit_callback' => array ($strDca, 'onSubmitRecord'),
    ),
    'list'        => array(
        'sorting'           => array(
            'mode'        => 2, 
            'fields'      => array('sensorID'),
            'flag'        => DataContainer::SORT_ASC,
            'panelLayout' => 'filter;sort,search,limit'
        ),
        'label'             => array(
            'fields' => array('sensorID'),
            'format' => '%s',
        ),
        'global_operations' => array(
            'all' => array(
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"'
            )
        ),
        'operations'        => array(
            'edit'   => array(
                'href'  => 'act=edit',
                'icon'  => 'edit.svg'
            ),
            'copy'   => array(
                'href'  => 'act=copy',
                'icon'  => 'copy.svg'
            ),
            'delete' => array(
                'href'       => 'act=delete',
                'icon'       => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'' . ($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? null) . '\'))return false;Backend.getScrollOffset()"'
            ),
            'show'   => array(
                'href'       => 'act=show',
                'icon'       => 'show.svg',
                'attributes' => 'style="margin-right:3px"'
            ),
        )
    ),
    'palettes'    => array(
        'default'      => 'sensorID,sensorEinheit,sensorValueType,sensorSource,sensorValue'
    ),
    'fields'      => array(
        'id'             => array(
            'sql' => "int(10) unsigned NOT NULL auto_increment"
        ),
        'tstamp'         => array(
            'sql' => "int(10) unsigned NOT NULL default '0'"
        ),
        'sensorID'          => array(
            'inputType' => 'text',
            'exclude'   => true,
            'search'    => true,
            'filter'    => true,
            'sorting'   => true,
            'eval'      => array('mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'),
            'sql'       => "varchar(255) NOT NULL default ''"
        ),
        'sensorValue'          => array(
            'inputType' => 'text',
            'exclude'   => true,
            'search'    => true,
            'filter'    => true,
            'sorting'   => true,
            'eval'      => array( 'maxlength' => 255, 'tl_class' => 'w50'),
            'sql'       => "varchar(8192) NOT NULL default ''"
        ),
        'sensorEinheit'  => array(
            'inputType' => 'select',
            'exclude'   => true,
            'search'    => true,
            'filter'    => true,
            'sorting'   => true,
            'reference' => &$GLOBALS['TL_LANG']['tl_coh_sensorvalue'], // Sprachreferenz für die Labels
            'options'   => [
                              'kwh' => 'kwh',
                              'W' => 'W',
                              'GradC' => 'GradC',
                              'Datum' => 'Datum',
                              'Zeit' => 'Zeit',
                              'DatumZeit' => 'DatumZeit',
                              'Text' => 'Text'
                           ],
            'eval'      => array('includeBlankOption' => false, 'chosen' => true, 'tl_class' => 'w50'),
            'sql'       => "varchar(255) NOT NULL default ''",
            'default'   => 'Text',
        ),
        'sensorValueType'          => array(
            'inputType' => 'select',
            'exclude'   => true,
            'search'    => true,
            'filter'    => true,
            'sorting'   => true,
            'options'   => [
                              'int' => 'int',
                              'float' => 'float',
                              'GradC' => 'GradC',
                              'Datum' => 'Datum',
                              'Zeit' => 'Zeit',
                              'DatumZeit' => 'DatumZeit',
                              'Text' => 'Text'
                           ],
            'eval'      => array( 'maxlength' => 255, 'tl_class' => 'w50'),
            'sql'       => "varchar(255) NOT NULL default ''",
            'default'   => 'Text',
        ),
        'sensorSource'  => array(
            'inputType' => 'select',
            'exclude'   => true,
            'search'    => true,
            'filter'    => true,
            'sorting'   => true,
            'reference' => &$GLOBALS['TL_LANG']['tl_coh_sensorvalue'], // Sprachreferenz für die Labels
            'options'   => [
                              0 => 'Heizstab',
                              1 => 'IQbox',
                              2 => 'Tasmota',
                              3 => 'PHP-Script',
                              4 => 'sonst'
                           ],
            'eval'      => array('includeBlankOption' => false, 'chosen' => true, 'tl_class' => 'w50'),
            'sql'       => "varchar(255) NOT NULL default ''",
            'default'   => 0,
        ),
    )
);



