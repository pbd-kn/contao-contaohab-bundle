<?php
namespace PbdKn\ContaoContaohabBundle\Module;

use Contao\Database;
use Contao\StringUtil; // Richtiges Contao-Utility importieren
use Contao\Input;


/*
 * enthält alle callback funktionen die in tl_module verwendet werden.
 * in tl_module steht das dca für das be_um die Auswahl im Modul anzuzeigen
 */

class ModuleCohCallback 
{
  public static function pbdgetThingsOptions()
  {
    $database = Database::getInstance();
    $result = $database->execute("SELECT thingID, thingTitle FROM tl_coh_things");

    $options = [];
    while ($result->next()) {
        $options[$result->thingID] = $result->thingTitle; // thingTitle als Wert, thingsTitle als sichtbarer Text
    }

    return $options;
  }

  public static function pbdgetSensorvariables($dc)
  {
    $database = Database::getInstance();
    // Prüfen, ob ein bestehendes Thing bearbeitet wird
    if ($dc->activeRecord && $dc->activeRecord->id) {

      // Die aktuell gewählte thingId abrufen
      $selectedThingId = \Contao\Input::post('coh_selectedThing') ?? $dc->activeRecord->coh_selectedThing;

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
    public function pbdserializeSensorSelection($varValue,  $dc)
    {
        // Debugging: Prüfen, ob die Funktion aufgerufen wird
        var_dump($varValue);
//        die('serializeSensorSelection wurde aufgerufen!');

        // Falls nichts ausgewählt wurde, NULL speichern
        if (empty($varValue)) {
            return null;
        }

        // Sicherstellen, dass es sich um ein Array handelt
        $sensorIds = StringUtil::deserialize($varValue, true);
        if (!is_array($sensorIds)) {
            return null;
        }

        // Werte serialisieren und zurückgeben
//die('serializeSensorSelection wurde aufgerufen!');
        return serialize($sensorIds);
    }


}
