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

use Contao\System;

use Contao\Database;
use Contao\Backend;
use Contao\DataContainer;
use Contao\DC_Table;
use Contao\Input;
use Contao\StringUtil;


use Contao\Controller;
//use PbdKn\ContaoContaohabBundle\Service\LoggerService;
//use PbdKn\ContaoContaohabBundle\Module\ModuleCohCallback; // Die Klasse importieren!



/**
 * Table tl_coh_cfgcollect
 */
$GLOBALS['TL_DCA']['tl_coh_cfgcollect'] = array(
    'config'      => array(
        'dataContainer'    => DC_Table::class,
        'enableVersioning' => true,
        'onload_callback'  => array(
            array(PbdKn\ContaoContaohabBundle\EventListener\RaspberryConfigPushCallback::class, '__invoke'),
        ),
        'sql'              => array(
            'keys' => array(
                'id' => 'primary'
            )
        ),
        'onsubmit_callback' => array ('tl_coh_cfgcollect', 'onSubmitRecord'),   //wird bei speichern des satzes aufgerufen

    ),
    'list'        => array(
        'sorting'           => array(
            'mode'        => 2, 
            'fields'      => array('cfgID','beschreibung'),
            'flag'        => DataContainer::SORT_ASC,
            'panelLayout' => 'filter;sort,search,limit'
        ),
        'label'             => array(
            'fields' => array('cfgID','beschreibung','cfgValue'),
            'format' => '%s (Beschreibung: %s; Wert: %s)',
        ),
        'global_operations' => array(
            'pullRaspberryConfig' => array(
                'label'      => &$GLOBALS['TL_LANG']['tl_coh_cfgcollect']['pullRaspberryConfig'],
                'href'       => 'key=pullRaspberryConfig',
                'class'      => 'header_sync',
                'attributes' => 'onclick="return confirm(\'' . ($GLOBALS['TL_LANG']['tl_coh_cfgcollect']['pullRaspberryConfigConfirm'] ?? '') . '\')"',
            ),
            'pushRaspberryConfig' => array(
                'label'      => &$GLOBALS['TL_LANG']['tl_coh_cfgcollect']['pushRaspberryConfig'],
                'href'       => 'key=pushRaspberryConfig',
                'class'      => 'header_sync',
                'attributes' => 'onclick="return confirm(\'' . ($GLOBALS['TL_LANG']['tl_coh_cfgcollect']['pushRaspberryConfigConfirm'] ?? '') . '\')"',
            ),
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
'palettes' => [
        'default' => '{title_legend},cfgID,beschreibung,cfgValue;'
    ],    
    'fields'      => array(
        'id'             => array(
            'sql' => "int(10) unsigned NOT NULL auto_increment"
        ),
        'tstamp'         => array(
            'sql' => "int(10) unsigned NOT NULL default '0'"
        ),
        'cfgID'          => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_coh_cfgcollect']['sensorID'],
            'inputType' => 'text',
            'exclude'   => true,
            'search'    => true,
            'filter'    => true,
            'sorting'   => true,
            'flag'      => DataContainer::SORT_INITIAL_LETTER_ASC,
            'eval'      => array('mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'),
            'sql'       => "varchar(255) NOT NULL default ''"
        ),

        'beschreibung'  => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_coh_cfgcollect']['beschreibung'],
            'inputType' => 'text',
            'exclude'   => true,
            'search'    => true,
            'filter'    => true,
            'sorting'   => true,
            'eval'      => array('maxlength' => 255, 'tl_class' => 'w50'),
            'sql'       => "varchar(255) NOT NULL default ''",
        ),
        'cfgValue' => array (
            'label'     => &$GLOBALS['TL_LANG']['tl_coh_cfgcollect']['cfgValue'],
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
         ),
        
      )
    );



class tl_coh_cfgcollect
{
}
