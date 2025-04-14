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

/* enthaelt die beschrteibung und daten zu den eine Sensoren
 */
use Contao\DC_Table;
use Contao\DataContainer;
use PbdKn\ContaoBesslichschmuck\Resources\contao\dataContainer\tableList;
use Contao\Backend;
use Contao\System;
use Contao\Image;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Contao\CoreBundle\Exception\InvalidFieldValueException;



$strTable='tl_coh_geraete';
/**
 * Table tl_coh_sensors
 */
$GLOBALS['TL_DCA'][$strTable] = array(
    'config'      => array(
        'dataContainer'    => DC_Table::class,
        'enableVersioning' => true,
        'sql'              => array(
            'keys' => array(
                'id' => 'primary'
            )
        ),
    ),
    'list'        => array(
        'sorting'           => array(
            'mode'        => 2, 
            'fields'      => array('geraeteTitle'),
            'flag'        => DataContainer::SORT_ASC,
            'panelLayout' => 'filter;sort,search,limit'
        ),
        'label'             => array(
            'fields' => array('geraeteTitle','geraeteID','geraeteUrl'),
            'format' => '%s (GeräteId: %s URL: %s)',
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
                'label' => &$GLOBALS['TL_LANG'][$strTable]['edit'],
                'href'  => 'act=edit',
                'icon'  => 'edit.svg'
            ),
            'copy'   => array(
                'label' => &$GLOBALS['TL_LANG'][$strTable]['copy'],
                'href'  => 'act=copy',
                'icon'  => 'copy.svg'
            ),
            'delete' => array(
                'label' => &$GLOBALS['TL_LANG'][$strTable]['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'' . ($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? null) . '\'))return false;Backend.getScrollOffset()"'
            ),
            'show'   => array(
                'label' => &$GLOBALS['TL_LANG'][$strTable]['show'],
                'href'       => 'act=show',
                'icon'       => 'show.svg',
                'attributes' => 'style="margin-right:3px"'
            ),
        )
    ),
    'palettes'    => array(
        'default'      => '{first_legend},geraeteID,geraeteTitle,geraeteUrl,geraeteDescription'
    ),
    'fields'      => array(
        'id'             => array(
            'sql' => "int(10) unsigned NOT NULL auto_increment"
        ),
        'tstamp'         => array(
            'sql' => "int(10) unsigned NOT NULL default '0'"
        ),
        'geraeteID'          => array(
            'label'     => &$GLOBALS['TL_LANG'][$strTable]['geraeteID'], 
            'inputType' => 'text',
            'exclude'   => true,
            'search'    => true,
            'filter'    => true,
            'sorting'   => true,
            'flag'      => DataContainer::SORT_INITIAL_LETTER_ASC,
            'eval'      => array('mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'),
            'sql'       => "varchar(255) NOT NULL default ''"
        ),
        'geraeteTitle'          => array(
            'label'     => &$GLOBALS['TL_LANG'][$strTable]['geraeteTitle'], 
            'inputType' => 'text',
            'exclude'   => true,
            'search'    => true,
            'filter'    => true,
            'sorting'   => true,
            'flag'      => DataContainer::SORT_INITIAL_LETTER_ASC,
            'eval'      => array('mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'),
            'sql'       => "varchar(255) NOT NULL default ''"
        ),
        'geraeteUrl'          => array(
            'label'     => &$GLOBALS['TL_LANG'][$strTable]['geraeteUrl'], 
            'inputType' => 'text',
            'exclude'   => true,
            'search'    => true,
            'filter'    => true,
            'sorting'   => true,
            'flag'      => DataContainer::SORT_INITIAL_LETTER_ASC,
            'eval'      => array('mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50','rgxp'      => 'custom',),
            'save_callback' => [
                [tl_coh_geraete::class, 'validateHostOrIp']
             ],
            'sql'       => "varchar(255) NOT NULL default ''"
        ),
        'geraeteDescription'          => array(
            'label'     => &$GLOBALS['TL_LANG'][$strTable]['geraeteDescription'],
            'inputType' => 'textarea',
            'exclude'   => true,
            'search'    => true,
            'filter'    => true,
            'sorting'   => true,
            'flag'      => DataContainer::SORT_INITIAL_LETTER_ASC,
            'eval'      => [
                             'mandatory' => false,
                              'rows'      => 4,
                              'cols'      => 40,
                              'tl_class'  => 'w50',
                               
                           ],
            'sql'       => "varchar(255) NOT NULL default ''"
        ),
    )
);



/* klasse für alle callback funktionen zu things */
class tl_coh_geraete
{
    public function validateHostOrIp(string $value): string
    {
        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return $value;
        }

        // einfache Hostname-Prüfung (z. B. "my-host.local" oder "example.com")
        if (preg_match('/^([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}$/', $value)) {
            return $value;
        }

        throw new \InvalidArgumentException('Bitte eine gültige IP-Adresse oder einen Hostnamen eingeben.');
    }

}
