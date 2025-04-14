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
use Contao\Database;

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
            'fields' => array('sensorTitle','sensorLokalId'),
            'format' => '%s (ID: %s)',
        ),
        'global_operations' => array(
            'csvimport' => [
            //'label' => &$GLOBALS['TL_LANG']['tl_coh_sensors']['importCSV'][0], 
                'label' => 'Import Csv Sensors', 
    			'href'                => '',
                'class' => 'header_csv_import', 
                'attributes' => 'onclick="Backend.getScrollOffset();"',
                'button_callback' => ['tl_coh_sensors', 'importCsvButton'],
            ],            
            'export_csv' => [
                'label' => 'Export Csv Sensors',
                'href'  => 'key=exportCsv',
                'class' => 'header_export_csv',
                'button_callback' => ['tl_coh_sensors', 'generateExportButton'],
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
        'default'      => '{first_legend},sensorID,sensorTitle,sensorEinheit,sensorSource,sensorLokalId,transFormProcedur'
    ),
    'fields'      => array(
        'id'             => array(
            'sql' => "int(10) unsigned NOT NULL auto_increment"
        ),
        'tstamp'         => array(
            'sql' => "int(10) unsigned NOT NULL default '0'"
        ),
        'sensorID'          => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_coh_sensors']['sensorID'],
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
            'label'     => &$GLOBALS['TL_LANG']['tl_coh_sensors']['sensorTitle'],
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
            'label'     => &$GLOBALS['TL_LANG']['tl_coh_sensors']['sensorEinheit'],
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
                              'kW' => 'kW',
                              'GradC' => 'GradC',
                              'Datum' => 'Datum',
                              'Zeit' => 'Zeit',
                              'DatumZeit' => 'DatumZeit',
                              'Text' => 'Text',
                              'OK' => 'OK'
                           ],
            'eval'      => array('includeBlankOption' => true, 'chosen' => true, 'tl_class' => 'w50'),
            'sql'       => "varchar(255) NOT NULL default ''",
            'default'   => 'Text',
        ),
        'sensorSource'  => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_coh_sensors']['sensorSource'],
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
            'eval'      => array('mandatory' => true,'includeBlankOption' => false, 'chosen' => true, 'tl_class' => 'w50'),
            'sql'       => "varchar(255) NOT NULL default ''",
            'default'   => 0,
        ),
        'transFormProcedur'  => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_coh_sensors']['transFormProcedur'],
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
            'eval'      => array('mandatory' => false,'includeBlankOption' => false, 'chosen' => true, 'tl_class' => 'w50'),
            'sql'       => "varchar(255) NOT NULL default ''",
            'default'   => 0,
        ),
        'sensorLokalId'  => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_coh_sensors']['sensorLokalId'],
            'inputType' => 'text',
            'exclude'   => true,
            'search'    => true,
            'filter'    => true,
            'sorting'   => true,
            'eval'      => array('maxlength' => 255, 'tl_class' => 'w50'),
            'sql'       => "varchar(255) NOT NULL default ''",
        ),
        'persistent' => [                     // noch nicht verwendet
            'label'     => &$GLOBALS['TL_LANG']['tl_coh_sensors']['persistent'],
            'inputType' => 'select',
            'exclude'   => true,
            'filter'    => true,
            'sorting'   => true,
            'options'   => [0, 1], // ? nur die Schlüssel
            'reference' => &$GLOBALS['TL_LANG']['tl_coh_sensors']['persistent_options'], // ? Anzeigenamen
            'eval' => [
                'includeBlankOption' => false,
                'tl_class' => 'w50',
                'isBoolean' => true // ? erzwingt boolsche Behandlung (0/1)
            ],
            'sql' => "TINYINT(1) NOT NULL DEFAULT '0'",
            'default' => 0
        ],
        'lastUpdated' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_coh_sensors']['lastUpdated'],
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],

        'pollInterval' => [              // noch nicht verwendet
            'label'     => &$GLOBALS['TL_LANG']['tl_coh_sensors']['pollInterval'],
            'inputType' => 'text',
            'exclude'   => true,
            'eval'      => ['rgxp' => 'digit', 'tl_class' => 'w50'],
            'sql'       => "int(10) unsigned NOT NULL default '60'",
            'default'   => 60,
        ],
        'lastValue' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_coh_sensors']['lastValue'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'lastError' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_coh_sensors']['lastError'],
            'inputType' => 'textarea',
            'exclude'   => true,
            'eval'      => ['rte' => 'none', 'tl_class' => 'clr', 'readonly' => true],
            'sql'       => "text NULL",
        ],    
    )
);



/* klasse für alle callback funktionen zu things */
class tl_coh_sensors
{

    public function getsensorReferenz(): array
    {
        $options = [];
        $result = Database::getInstance()
            ->prepare("SELECT geraeteID FROM tl_coh_geraete ORDER BY geraeteID")
            ->execute();

        while ($result->next()) {
            $options[$result->geraeteID] = $result->geraeteID;
        }

        return $options;
    }
    /**
     * Erzeugt den CSV-Import-Button mit einer Route.
     */
    //public function generateExportButton(array $row, ?string $href, string $label, string $title, string $icon, string $attributes): string
    public function generateExportButton(string $table, ?string $href, string $label, string $title, string $icon, string $attributes): string
    {
        $icon = 'bundles/pbdkncontaocontaohab/icons/exportCSV.gif';
        $router = \Contao\System::getContainer()->get('router');

        $url = $router->generate('export_coh_sensor_action', [], UrlGeneratorInterface::ABSOLUTE_URL);  // erzeugt wohl die url fuer den Namen
        $class = 'header_csv_export';
        $strRet='<a href="'.$url.'?table=tl_coh_sensors"  class="' . $class .'" title="' . $title . '"' . $attributes . '>' ."<img src='$icon' alt=''>". $label . '</a> ';
        return $strRet;
    
    }
    public function exportCsv(): StreamedResponse
    {
        $filename = 'sensors_export_' . date('Y-m-d_H-i-s') . '.csv';

        $response = new StreamedResponse(function () {
            $handle = fopen('php://output', 'w');

            // Daten aus deiner Tabelle holen
            $rows = \Database::getInstance()
                ->prepare("SELECT * FROM tl_coh_sensors")
                ->execute()
                ->fetchAllAssoc();

            if (!empty($rows)) {
                // Spaltenüberschriften
                fputcsv($handle, array_keys($rows[0]));

                foreach ($rows as $row) {
                    fputcsv($handle, $row);
                }
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }    

    /**
     * Erzeugt den CSV-Import-Button mit einer Route.
     */
    public function importCsvButton($row, $href, $label, $title, $icon, $attributes)
    {
        // Den Symfony-Router verwenden, um die URL zu generieren 
        $icon = 'bundles/pbdkncontaocontaohab/icons/importCSV.gif';
        $router = \Contao\System::getContainer()->get('router');

        $url = $router->generate('import_coh_sensor', [], UrlGeneratorInterface::ABSOLUTE_URL);  // erzeugt wohl die url fuer den Namen
        $class = 'header_csv_import';
        $strRet='<a href="'.$url.'?table=tl_coh_sensors"  class="' . $class .'" title="' . $title . '"' . $attributes . '>' ."<img src='$icon' alt=''>". $label . '</a> ';
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
