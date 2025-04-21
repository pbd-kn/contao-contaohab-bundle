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
use PbdKn\ContaoContaohabBundle\Controller\FrontendModule\DisplayThingsController;
use PbdKn\ContaoContaohabBundle\Service\LoggerService;
//use PbdKn\ContaoContaohabBundle\Module\ModuleCohCallback; // Die Klasse importieren!



/**
 * Table tl_coh_cfgcollect
 */
$GLOBALS['TL_DCA']['tl_coh_cfgcollect'] = array(
    'config'      => array(
        'dataContainer'    => DC_Table::class,
        'enableVersioning' => true,
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
            'fields'      => array('cfgID','cfgType'),
            'flag'        => DataContainer::SORT_ASC,
            'panelLayout' => 'filter;sort,search,limit'
        ),
        'label'             => array(
            'fields' => array('cfgID','cfgType','cfgValue'),
            'format' => '%s (Type: %s Value: %s)',
        ),
        'global_operations' => array(
            'all' => array(
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"'
            )
        ),
        'operations'        => array(
/*
            'meinKnopf' => array(
                'label' => ['Mein Button', 'Das ist mein Spezialknopf'],
                'href'  => '/contao?do=coh_things&key=custom&id=%s',
                'icon'  => 'bundles/pbdkncontaocontaohab/icons/mail.gif',
                'button_callback' => ['tl_coh_cfgcollect', 'generateCustomButton'],
            ),
*/
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
        'default' => '{title_legend},cfgID,cfgType,cfgValue;'
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

        'cfgType'  => array( 
            'label'     => &$GLOBALS['TL_LANG']['tl_coh_cfgcollect']['cfgType'],
            'inputType' => 'select',
            'exclude'   => true,
            'search'    => true,
            'filter'    => true,
            'sorting'   => true,
            'options'   => [
                              'pollTime' => 'pollTime',
                              'urlremoteDB' => 'urlremoteDB',
                              'nameremoteDB' => 'urlremoteDB',
                              'debug' => 'debug',
                              'text' => 'text',
                           ],
            'eval'      => array('mandatory' => true,'includeBlankOption' => false, 'chosen' => true, 'tl_class' => 'w50'),
            'sql'       => "varchar(255) NOT NULL default ''",
            'default'   => 'pollTime',
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



/* klasse für alle callback funktionen zu things */
class tl_coh_cfgcollect
{
}
