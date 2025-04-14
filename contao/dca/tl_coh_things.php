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
 * Table tl_coh_things
 */
$GLOBALS['TL_DCA']['tl_coh_things'] = array(
    'config'      => array(
        'dataContainer'    => DC_Table::class,
        'enableVersioning' => true,
        'sql'              => array(
            'keys' => array(
                'id' => 'primary'
            )
        ),
        'onsubmit_callback' => array ('tl_coh_things', 'onSubmitRecord'),   //wird bei speichern des satzes aufgerufen

    ),
    'list'        => array(
        'sorting'           => array(
            'mode'        => 2, 
            'fields'      => array('thingTitle','thingID'),
            'flag'        => DataContainer::SORT_ASC,
            'panelLayout' => 'filter;sort,search,limit'
        ),
        'label'             => array(
            'fields' => array('thingTitle','thingID'),
            'format' => '%s (ID: %s)',
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
                'button_callback' => ['tl_coh_things', 'generateCustomButton'],
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
        'default' => '{title_legend},thingID,thingTitle,Sensorvariable,coh_selectedSensor;'
    ],    
    'fields'      => array(
        'id'             => array(
            'sql' => "int(10) unsigned NOT NULL auto_increment"
        ),
        'tstamp'         => array(
            'sql' => "int(10) unsigned NOT NULL default '0'"
        ),
        'thingID'          => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_coh_sensors']['lastValue'],
            'inputType' => 'text',
            'exclude'   => true,
            'search'    => true,
            'filter'    => true,
            'sorting'   => true,
            'flag'      => DataContainer::SORT_INITIAL_LETTER_ASC,
            'eval'      => array('mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50','unique' => true,),
            'sql'       => "varchar(255) NOT NULL default ''",
        ),
        'thingTitle'          => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_coh_sensors']['lastValue'],
            'inputType' => 'text',
            'exclude'   => true,
            'search'    => true,
            'filter'    => true,
            'sorting'   => true,
            'flag'      => DataContainer::SORT_INITIAL_LETTER_ASC,
            'eval'      => array('mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'),
            'sql'       => "varchar(255) NOT NULL default ''",
        ),
        'thingType'  => array(                // noch nicht ausgewertet
            'label'     => &$GLOBALS['TL_LANG']['tl_coh_sensors']['lastValue'],
            'inputType' => 'select',
            'exclude'   => true,
            'search'    => true,
            'filter'    => true,
            'sorting'   => true,
            'options'   => [
                              'php' => 'php',
                              'costum' => 'costum',
                           ],
            'eval'      => array('mandatory' => true,'includeBlankOption' => false, 'chosen' => true, 'tl_class' => 'w50'),
            'sql'       => "varchar(255) NOT NULL default ''",
            'default'   => 'php',
            'sql'       => "varchar(255) NOT NULL default ''",
        ),
        'Sensorvariable' => array (                    // enthält alle möglichen sensoren, die in dieswem Thing möglich sind
            'label'     => &$GLOBALS['TL_LANG']['tl_coh_sensors']['lastValue'],
            'inputType'        => 'select',
            'options_callback' => ['tl_coh_things', 'getAllSensors'], // Selectbox mit Werten aus `tl_coh_sensors` füllen
            'eval'             => ['mandatory' => true, 'multiple' => true,'chosen' => true, 'tl_class' => 'w50'],
            'sql'              => "blob NULL",
        ),




        'coh_customParameter' => array (
            'label'     => &$GLOBALS['TL_LANG']['tl_coh_sensors']['lastValue'],
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
         ),
        
      )
    );



/* klasse für alle callback funktionen zu things */
class tl_coh_things
{
    /*
     * return ein Array das zur Selection dews Items das mit diesem thing verknüüft ist
     * bei der selection
     */

/*
  public static function getSensorvariables($dc)
  {
        return self::getAllSensors();
  }
*/
/**
 * Lädt alle verfügbaren Sensoren aus `tl_coh_sensors`
 */
public function generateCustomButton(array $row, string $href, string $label, string $title, string $icon, string $attributes): string
{
    $href = \Contao\System::getContainer()->get('router')->generate('contao_backend') . '?' . sprintf($href, $row['id']);
    // link auf die funktion
    return sprintf(
        '<a href="%s" title="%s"%s><img src="%s" alt=""> %s</a>',
        $href,
        $title,
        $attributes,
        $icon,
        $label
    );
}

    public static function getAllSensors()
    {
        /** @var LoggerService $logger */
      $logger = System::getContainer()->get(LoggerService::class);
      $database = Database::getInstance();

      $query = "SELECT sensorId, sensorTitle FROM tl_coh_sensors ORDER BY sensorTitle";
      $result = $database->execute($query);

      $options = [];
      while ($result->next()) {
        $options[$result->sensorId] = sprintf('%s (%s)', $result->sensorTitle, $result->sensorId);
      }
      $logger->debugMe('getAllSensors Anzahl Sensoren: '.count($options));    

      return $options;
    }  


    public function onSubmitRecord(DataContainer $dc)
    {
        /** @var LoggerService $logger */
        $logger = System::getContainer()->get(LoggerService::class);
        if (!$dc->id) {
            $logger->debugMe('onSubmitRecord keine dc->id');    
            return;
        }

        // Hole den aktuellen Datensatz aus der Datenbank
        $objRecord = \Database::getInstance()
            ->prepare("SELECT thingTitle FROM tl_coh_things WHERE id=?")
            ->execute($dc->id);

        if ($objRecord->numRows) {
            \System::log("Thing Datensatz gespeichert: " . $objRecord->thingTitle, __METHOD__, TL_GENERAL);
            $logger->debugMe("Thing Datensatz gespeichert Titel: " . $objRecord->thingTitle);    
        }
        // Achtung es kann auch über active record auf den aktuellen Record zugegriffen werden
        // Direkt auf die gespeicherten Werte zugreifen
        $thingTitle = $dc->activeRecord->thingTitle;        
    }
    /* wird wohl nicht mehr gebraucht */
    public function serializeSensorSelection($varValue, DataContainer $dc)
    {
        var_dump($varvalue);
        //die(serializeSensorSelection);
        if (empty($varValue)) {
            return null; // Falls nichts ausgewählt ist, speichere NULL
        }

        $sensorIds = StringUtil::deserialize($varValue, true);
        if (!is_array($sensorIds)) {
            return null; // Falls ungültige Daten vorliegen
        }

        return serialize($sensorIds); // Speichert die Auswahl serialisiert
    }
}
