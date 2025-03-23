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




/**
 * Table tl_coh_sensors
 */
$GLOBALS['TL_DCA']['tl_coh_sensors'] = array(
    'config'      => array(
        'dataContainer'    => DC_Table::class,
        'enableVersioning' => true,
        'sql'              => array(
            'keys' => array(
                'id' => 'primary'
            )
        ),
        'onsubmit_callback' => array ('tl_coh_sensors', 'onSubmitRecord'),   //wird bei speichern des satzes aufgerufen

    ),
    'list'        => array(
        'sorting'           => array(
            'mode'        => 2, 
            'fields'      => array('sensorTitle'),
            'flag'        => DataContainer::SORT_ASC,
            'panelLayout' => 'filter;sort,search,limit'
        ),
        'label'             => array(
            'fields' => array('sensorTitle'),
            'format' => '%s',
        ),
        'global_operations' => array(
            'csvimport' => [
            //'label' => &$GLOBALS['TL_LANG']['tl_coh_sensors']['importCSV'][0], 
            'label' => 'Import Csv Sensors', 
            //'href' => 'key=importCSV', 
			'href'                => '',
            'class' => 'header_csv_import', 
            'attributes' => 'onclick="Backend.getScrollOffset();"',
                'button_callback' => ['tl_coh_sensors', 'importCsvButton'],

            ],            
        
            'all' => array(
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"'
            )
        ),
        'operations'        => array(
            'edit'   => array(
                'label' => &$GLOBALS['TL_LANG']['tl_coh_sensors']['edit'],
                'href'  => 'act=edit',
                'icon'  => 'edit.svg'
            ),
            'copy'   => array(
                'label' => &$GLOBALS['TL_LANG']['tl_coh_sensors']['copy'],
                'href'  => 'act=copy',
                'icon'  => 'copy.svg'
            ),
            'delete' => array(
                'label' => &$GLOBALS['TL_LANG']['tl_coh_sensors']['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'' . ($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? null) . '\'))return false;Backend.getScrollOffset()"'
            ),
            'show'   => array(
                'label' => &$GLOBALS['TL_LANG']['tl_coh_sensors']['show'],
                'href'       => 'act=show',
                'icon'       => 'show.svg',
                'attributes' => 'style="margin-right:3px"'
            ),
        )
    ),
    'palettes'    => array(
        '__selector__' => array('addSubpalette'),
        'default'      => '{first_legend},sensorID,sensorTitle,sensorEinheit,sensorSource,transFormProcedur,persistent}'
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
            'flag'      => DataContainer::SORT_INITIAL_LETTER_ASC,
            'eval'      => array('mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'),
            'sql'       => "varchar(255) NOT NULL default ''"
        ),
        'sensorTitle'          => array(
            'inputType' => 'text',
            'exclude'   => true,
            'search'    => true,
            'filter'    => true,
            'sorting'   => true,
            'flag'      => DataContainer::SORT_INITIAL_LETTER_ASC,
            'eval'      => array('mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'),
            'sql'       => "varchar(255) NOT NULL default ''"
        ),
        'sensorEinheit'  => array(
            'inputType' => 'select',
            'exclude'   => true,
            'search'    => true,
            'filter'    => true,
            'sorting'   => true,
            'reference' => &$GLOBALS['TL_LANG']['tl_coh_sensors'],
            'reference' => &$GLOBALS['TL_LANG']['tl_coh_sensors'], // Sprachreferenz für die Labels
            'options'   => [
                              'kwh' => 'kwh',
                              'W' => 'W',
                              'GradC' => 'GradC',
                              'Datum' => 'Datum',
                              'Zeit' => 'Zeit',
                              'DatumZeit' => 'DatumZeit',
                              'Text' => 'Text'
                           ],
            //'foreignKey'            => 'tl_user.name',
            //'options_callback'      => array('CLASS', 'METHOD'),
            'eval'      => array('mandatory' => true,'includeBlankOption' => false, 'chosen' => true, 'tl_class' => 'w50'),
            'sql'       => "varchar(255) NOT NULL default ''",
            //'relation'  => array('type' => 'hasOne', 'load' => 'lazy')
            'default'   => 'Text',
        ),
        'sensorSource'  => array(
            'inputType' => 'select',
            'exclude'   => true,
            'search'    => true,
            'filter'    => true,
            'sorting'   => true,
            'reference' => &$GLOBALS['TL_LANG']['tl_coh_sensors'],
            'reference' => &$GLOBALS['TL_LANG']['tl_coh_sensors'], // Sprachreferenz für die Labels
            'options'   => [
                              0 => 'Heizstab',
                              1 => 'IQbox',
                              2 => 'Tasmota',
                              3 => 'PHP-Script',
                              4 => 'sonst'
                           ],
            //'foreignKey'            => 'tl_user.name',
            //'options_callback'      => array('CLASS', 'METHOD'),
            'eval'      => array('mandatory' => true,'includeBlankOption' => false, 'chosen' => true, 'tl_class' => 'w50'),
            'sql'       => "varchar(255) NOT NULL default ''",
            //'relation'  => array('type' => 'hasOne', 'load' => 'lazy')
            'default'   => 0,
        ),
        'transFormProcedur'  => array(
            'inputType' => 'select',
            'exclude'   => true,
            'search'    => true,
            'filter'    => true,
            'sorting'   => true,
            'reference' => &$GLOBALS['TL_LANG']['tl_coh_sensors'],
            'reference' => &$GLOBALS['TL_LANG']['tl_coh_sensors'], // Sprachreferenz für die Labels
            'options'   => [
                              'elwaPwrkWh','elwaPwr','elwaPwr','elwaTemp',
                              'IQkW','IQkWh','IQSOC','IQTemp',
                              'tskWh','tsWatt'
                           ],
            //'foreignKey'            => 'tl_user.name',
            //'options_callback'      => array('CLASS', 'METHOD'),
            'eval'      => array('mandatory' => false,'includeBlankOption' => false, 'chosen' => true, 'tl_class' => 'w50'),
            'sql'       => "varchar(255) NOT NULL default ''",
            //'relation'  => array('type' => 'hasOne', 'load' => 'lazy')
            'default'   => 0,
        ),
        'sensorReferenz'  => array(
            'inputType' => 'select',
            'exclude'   => true,
            'search'    => true,
            'filter'    => true,
            'sorting'   => true,
            'reference' => &$GLOBALS['TL_LANG']['tl_coh_sensors'],
            'reference' => &$GLOBALS['TL_LANG']['tl_coh_sensors'], // Sprachreferenz für die Labels
            'options'   => [
                           ],
            'options_callback'      => array('tl_coh_sensors', 'getsensorReferenz'),
            //'foreignKey'            => 'tl_user.name',
            //'options_callback'      => array('CLASS', 'METHOD'),
            'eval'      => array('mandatory' => true,'includeBlankOption' => false, 'chosen' => true, 'tl_class' => 'w50'),
            'sql'       => "varchar(255) NOT NULL default ''",
            //'relation'  => array('type' => 'hasOne', 'load' => 'lazy')
            'default'   => 0,
        ),
        'persistent'  => array(
            'inputType' => 'select',
            'exclude'   => true,
            'search'    => true,
            'filter'    => true,
            'sorting'   => true,
            'reference' => &$GLOBALS['TL_LANG']['tl_coh_sensors'], // Sprachreferenz für die Labels
            'options'   => [
                              0 => 'Nein',
                              1 => 'Ja'
                           ],
            //'foreignKey'            => 'tl_user.name',
            //'options_callback'      => array('CLASS', 'METHOD'),
            'eval'      => array('mandatory' => true,'includeBlankOption' => false, 'chosen' => true, 'tl_class' => 'w50'),
            'sql'       => "varchar(255) NOT NULL default ''",
            //'relation'  => array('type' => 'hasOne', 'load' => 'lazy')
            'default'   => 'Text',
        ),
    )
);



/* klasse für alle callback funktionen zu things */
class tl_coh_sensors
{

    public function getsensorReferenz(): array
    {
        $options = [];
        $objResult = \Database::getInstance()->execute("SELECT id, title FROM tl_other_table ORDER BY title");

        while ($objResult->next()) {
            $options[$objResult->id] = $objResult->title;
        }

        return $options;
    }

    /**
     * Erzeugt den CSV-Import-Button mit einer Route.
     */
    public function importCsvButton($row, $href, $label, $title, $icon, $attributes)
    {
        // Den Symfony-Router verwenden, um die URL zu generieren 
//        $router = System::getContainer()->get('router');
        $router = \Contao\System::getContainer()->get('router');

        //$url = $router->generate('ImportHeikePreislisteController::importAction', [], UrlGeneratorInterface::ABSOLUTE_URL);  // für filetree geht aber nicht
        //$url = $router->generate('import_coh_sensor',..   // erzeugt wohl die url fuer den Namen aus der routes.yaml
        $url = $router->generate('import_coh_sensor', [], UrlGeneratorInterface::ABSOLUTE_URL);  // erzeugt wohl die url fuer den Namen
        $class = 'header_csv_import';
        $strRet='<a href="'.$url.'?table=tl_coh_sensors"  class="' . $class .'" title="' . $title . '"' . $attributes . '>' . $label . '</a> ';
        return $strRet;
    }
    /*
     * return ein Array das zur Selection dews Items das mit diesem thing verknüüft ist
     * bei der selection
     */
     
    public function getItemstoRegister(): array
    {
        $options = [];
/*
        $objResult = \Database::getInstance()->execute("SELECT id, title FROM tl_other_table ORDER BY title");

        while ($objResult->next()) {
            $options[$objResult->id] = $objResult->title;
        }

*/
        $options['1']='eintrag 1';
        $options['2']='eintrag 2';
        $options['3']='eintrag 3';

        return $options;
    }
    
    /* wird bei speichern des datensatzes gerufen */
    public function onSubmitRecord(DataContainer $dc)
    {
        if (!$dc->id) {
            return;
        }

        // Hole den aktuellen Datensatz aus der Datenbank
        $objRecord = \Database::getInstance()
            ->prepare("SELECT sensorTitle FROM tl_coh_sensors WHERE id=?")
            ->execute($dc->id);

        if ($objRecord->numRows) {
            \System::log("Sensor Datensatz gespeichert: " . $objRecord->sensorTitle, __METHOD__, TL_GENERAL);
        }
        // Achtung es kann auch über active record auf den aktuellen Record zugegriffen werden
        // Direkt auf die gespeicherten Werte zugreifen
        $sensorTitle = $dc->activeRecord->sensorTitle;        
    }
    
}
