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
            'fields' => array('thingTitle'),
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
'palettes' => [
        'default' => '{title_legend},thingID,thingTitle,thingType,Sensorvariable,coh_selectedSensor;'
    ],    
    'fields'      => array(
        'id'             => array(
            'sql' => "int(10) unsigned NOT NULL auto_increment"
        ),
        'tstamp'         => array(
            'sql' => "int(10) unsigned NOT NULL default '0'"
        ),
        'thingID'          => array(
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
            'inputType' => 'text',
            'exclude'   => true,
            'search'    => true,
            'filter'    => true,
            'sorting'   => true,
            'flag'      => DataContainer::SORT_INITIAL_LETTER_ASC,
            'eval'      => array('mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'),
            'sql'       => "varchar(255) NOT NULL default ''",
        ),
        'thingType'  => array(
            'inputType' => 'select',
            'exclude'   => true,
            'search'    => true,
            'filter'    => true,
            'sorting'   => true,
            'reference' => &$GLOBALS['TL_LANG']['tl_coh_things'],
            'reference' => &$GLOBALS['TL_LANG']['tl_coh_things'], // Sprachreferenz für die Labels
            'options'   => [
                              'php' => 'php',
                              'costum' => 'costum',
                           ],
            //'foreignKey'            => 'tl_user.name',
            //'options_callback'      => array('CLASS', 'METHOD'),
            'eval'      => array('mandatory' => true,'includeBlankOption' => false, 'chosen' => true, 'tl_class' => 'w50'),
            'sql'       => "varchar(255) NOT NULL default ''",
            //'relation'  => array('type' => 'hasOne', 'load' => 'lazy')
            'default'   => 'php',
            'sql'       => "varchar(255) NOT NULL default ''",
        ),
        'Sensorvariable' => array (                    // enthält alle möglichen sensoren, die in dieswem Thing möglich sind
            'label'            => &$GLOBALS['TL_LANG']['tl_coh_things']['Sensorvariable'],
            'inputType'        => 'select',
            'options_callback' => ['tl_coh_things', 'getSensorvariables'], // Selectbox mit Werten aus `tl_coh_sensors` füllen
            'eval'             => ['mandatory' => true, 'multiple' => true,'chosen' => true, 'tl_class' => 'w50'],
            'sql'              => "blob NULL",
        ),




        'coh_customParameter' => array (
            'label'     => &$GLOBALS['TL_LANG']['tl_coh_things']['coh_customParameter'],
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

  public static function getSensorvariables($dc)
  {
        return self::getAllSensors();
    $database = Database::getInstance();
    // Prüfen, ob ein bestehendes Thing bearbeitet wird
    if ($dc->activeRecord && $dc->activeRecord->id) {

      // Die aktuell gewählte thingId abrufen

      $selectedThingId = \Contao\Input::post('coh_selectedThing') ?? $dc->activeRecord->coh_selectedThing;
die ("getsensorvariables recorde da ".$dc->activeRecord->coh_selectedThing);

      // Falls nichts ausgewählt ist, leere Liste zurückgeben
      if (!$selectedThingId) {
          return [];
      }
 
      // Deserialisieren (falls multiple => true)
      $selectedThingIds = \Contao\StringUtil::deserialize($selectedThingId, true);

      // Falls das Array leer ist, nichts tun
      if (empty($selectedThingIds)) {
          return [];
      }

      // 1?? Erste SQL-Abfrage: Sensorvariable (BLOB) für die `thingId` auslesen
      $placeholders = implode(',', array_fill(0, count($selectedThingIds), '?'));
      $query = "SELECT Sensorvariable FROM tl_coh_things WHERE thingId IN ($placeholders)";
      $result = $database->prepare($query)->execute(...$selectedThingIds);

      $sensorIds = [];
      while ($result->next()) {
        $sensorVars = \Contao\StringUtil::deserialize($result->Sensorvariable, true);
        if (is_array($sensorVars)) {
            $sensorIds = array_merge($sensorIds, $sensorVars);
        }
      }

      // Falls keine Sensoren gefunden wurden, leere Liste zurückgeben
      if (empty($sensorIds)) {
die ("keine sensoren");
        return [];
      }
      // 2?? Zweite SQL-Abfrage: Sensor-Titel für die Sensor-IDs abrufen
      $placeholders = implode(',', array_fill(0, count($sensorIds), '?'));
      $query = "SELECT sensorId, sensorTitle FROM tl_coh_sensors WHERE sensorId IN ($placeholders)";
      $result = $database->prepare($query)->execute(...$sensorIds);

      $options = [];
      while ($result->next()) {
        $options[$result->sensorId] = sprintf('%s (%s)', $result->sensorTitle, $result->sensorId);
      }

      return $options;
    } else {
        // Wenn ein neues Thing erstellt wird, alle Sensoren anzeigen
        return self::getAllSensors();
    }
  }
/**
 * Lädt alle verfügbaren Sensoren aus `tl_coh_sensors`
 */
    private static function getAllSensors()
    {
      $database = Database::getInstance();

      $query = "SELECT sensorId, sensorTitle FROM tl_coh_sensors ORDER BY sensorTitle";
      $result = $database->execute($query);

      $options = [];
      while ($result->next()) {
        $options[$result->sensorId] = sprintf('%s (%s)', $result->sensorTitle, $result->sensorId);
      }

      return $options;
    }  


    public function onSubmitRecord(DataContainer $dc)
    {
        if (!$dc->id) {
            return;
        }

        // Hole den aktuellen Datensatz aus der Datenbank
        $objRecord = \Database::getInstance()
            ->prepare("SELECT thingTitle FROM tl_coh_things WHERE id=?")
            ->execute($dc->id);

        if ($objRecord->numRows) {
            \System::log("Thing Datensatz gespeichert: " . $objRecord->thingTitle, __METHOD__, TL_GENERAL);
        }
        // Achtung es kann auch über active record auf den aktuellen Record zugegriffen werden
        // Direkt auf die gespeicherten Werte zugreifen
        $thingTitle = $dc->activeRecord->thingTitle;        
    }
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
