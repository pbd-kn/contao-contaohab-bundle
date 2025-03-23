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
use Contao\Controller;
use Contao\StringUtil;
use Contao\ModuleModel;
use Contao\Input;
use PbdKn\ContaoContaohabBundle\Controller\FrontendModule\DisplayThingsController;
use PbdKn\ContaoContaohabBundle\Module\ModuleCohCallback; // Die Klasse importieren!



$GLOBALS['TL_DCA']['tl_module']['palettes'][DisplayThingsController::TYPE] = 
'{title_legend},name,headline,type;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID';



$GLOBALS['TL_DCA']['tl_module']['palettes']['coh_things'] = '
  {title_legend},name,headline,type;
  {config_legend},coh_selectedThing,coh_selectedSensor,coh_sensorThingMap,coh_customParameter;
  {template_legend},coh_template;
  {expert_legend:hide},cssID
';
$GLOBALS['TL_DCA']['tl_module']['fields']['coh_selectedThing'] = [
    'label'            => &$GLOBALS['TL_LANG']['tl_module']['coh_selectedThing'],
    'exclude'          => true,
    'inputType'        => 'select',
    'options_callback' => ['tl_module_coh', 'getThingsOptions'], // Holt die Liste der Things
    'eval'             => ['mandatory' => false, 'multiple' => true, 'chosen' => true, 'tl_class' => 'w50', 'submitOnChange' => true],
    'save_callback'    => [['tl_module_coh', 'serializeSelectedThing']],
    'sql'              => "blob NULL"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['coh_customParameter'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_module']['coh_customParameter'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['tl_class' => 'w50'],
    'sql'       => "varchar(255) NOT NULL default ''"
];
$GLOBALS['TL_DCA']['tl_module']['fields']['coh_selectedSensor'] = [
    'label'            => &$GLOBALS['TL_LANG']['tl_module']['coh_selectedSensor'],
    'exclude'          => true,
    'inputType'        => 'select',
    'options_callback' => ['tl_module_coh', 'getSensorOptions'], // Holt nur die Sensoren aus dem gewählten Thing
    'eval'             => ['mandatory' => false, 'multiple' => true, 'chosen' => true, 'tl_class' => 'w50'],  // ✅ `multiple` aktivieren
    'sql'              => "blob NULL",
    'save_callback'    => [['tl_module_coh', 'serializeSelectedSensors']],
];

$GLOBALS['TL_DCA']['tl_module']['fields']['coh_template'] = [
    'label'            => &$GLOBALS['TL_LANG']['tl_module']['coh_template'],
    'exclude'          => true,
    'inputType'        => 'select',
    'options_callback' => static function () {
        $templates = \Contao\Controller::getTemplateGroup('mod_coh_things_');
        //var_dump($templates); // Debugging: Prüft, ob Templates gefunden werden
        return $templates;
    },
    'eval'             => ['tl_class' => 'w50', 'includeBlankOption' => true, 'helpwizard' => true],
    'sql'              => "varchar(64) NOT NULL default ''",
    'explanation'      => 'coh_template_help', // Schlüssel für die Sprachdatei
];
// In tl_module.php   nimmt die kombination thing selectet sensors in einer json schreibweise auf igentlich brauche ich dann das save_callback bei coh_selectedSensor und 
// coh_selectedThing nochjt mehr


/*
$GLOBALS['TL_DCA']['tl_module']['fields']['coh_sensorThingMap'] = [
    'label' => ['Sensor-Zuordnung', 'Sensoren den ausgewählten Things zuordnen'],
    'inputType' => 'multiColumnWizard',
    'eval' => [
        'columnFields' => [
            'thing' => [
                'label'     => ['Thing'],
                'inputType' => 'select',
                'options_callback' => ['tl_module_coh', 'getThingOptions'],
                'eval'      => ['style' => 'width:200px', 'chosen' => true],
            ],
            'sensors' => [
                'label'     => ['Sensoren'],
                'inputType' => 'select',
                'options_callback' => ['tl_module_coh', 'getSensorsForSelectedThings'],
                'eval'      => ['multiple' => true, 'style' => 'width:300px', 'chosen' => true],
            ],
        ]
    ],
    'sql' => 'blob NULL'
];

$GLOBALS['TL_DCA']['tl_module']['config']['onsubmit_callback'][] = ['tl_module_coh', 'generateSensorThingMap'];


$GLOBALS['TL_DCA']['tl_module']['fields']['coh_sensorThingMap'] = [
    'label' => ['Sensor-Zuordnung', 'Sensoren den ausgewählten Things zuordnen'],
    'exclude'   => true,
    'inputType'=> 'textarea',
    'eval'      => [
        'mandatory' => false,
        'rows'      => 4,
        'cols'      => 40,
        'tl_class'  => 'clr w50',
    ],
    'sql'       => "text NULL",
];
*/


class tl_module_coh
{
    public function generateSensorThingMap( $dc): void
    {
        // Schutz: Nur wenn ID vorhanden
        if (!$dc->id) {
            return;
        }

        // Modul-Datensatz laden
        $module = ModuleModel::findByPk($dc->id);
        if (!$module) {
            return;
        }

        // Felder des Moduls auslesen
        $things = StringUtil::deserialize($module->coh_selectedThing, true);
        $sensors = StringUtil::deserialize($module->coh_selectedSensor, true);

        if (empty($things) || empty($sensors)) {
            $module->coh_sensorThingMap = ''; // Leeren, falls keine Daten
            $module->save();
            return;
        }

        // Zuordnung erstellen: Jeder Thing bekommt alle Sensoren
        $map = [];
        var_dump($things);
        foreach ($things as $thing) {
            $map[$thing] = $sensors;
        }

        // JSON speichern (formatiert)
        $module->coh_sensorThingMap = json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $module->save();
    }

    public function getThingOptions(): array
    {
        $result = [];
        $stmt = \Contao\System::getContainer()->get('database_connection')
            ->executeQuery('SELECT thingTitle FROM tl_coh_things ORDER BY thingTitle');

        while ($row = $stmt->fetchAssociative()) {
            $result[] = $row['thingTitle'];
        }

        return $result;
    }

    public function getSensorsForSelectedThings( $dc): array
    {
        $module = \Contao\ModuleModel::findByPk($dc->id ?? 0);
        if (!$module) {
            return [];
        }

        // Ausgewählte Things lesen
        $selectedThings = \Contao\StringUtil::deserialize($module->coh_selectedThing, true);

        if (empty($selectedThings)) {
            return [];
        }

        // Sensoren filtern, die zu den ausgewählten Things gehören
        $placeholders = implode(',', array_fill(0, count($selectedThings), '?'));
        $sql = "SELECT sensorTitle FROM tl_coh_sensors WHERE thingId IN ($placeholders)";
        $stmt = \Contao\System::getContainer()->get('database_connection')->executeQuery($sql, $selectedThings);

        $result = [];
        while ($row = $stmt->fetchAssociative()) {
            $result[] = $row['sensorTitle'];
        }

        return $result;
    }

    public function saveSelectedThing($varValue,  $dc)
    {
        if (empty($varValue)) {
            return null;
        }

        // Sicherstellen, dass die Auswahl als Array gespeichert wird
        $thingIds = StringUtil::deserialize($varValue, true);
        if (!is_array($thingIds)) {
            return null;
        }

        return serialize($thingIds); // Speichert die Auswahl serialisiert in `tl_module`
    }
    public static function getThingsOptions($dc)
    {
        $database = System::getContainer()->get('doctrine.dbal.default_connection');

        // Hole alle Things aus `tl_coh_things`
        $sql = "SELECT thingId, thingTitle FROM tl_coh_things ORDER BY thingTitle";
        $stmt = $database->executeQuery($sql);
        $things = $stmt->fetchAllAssociative();

        if (empty($things)) {
            return ["Kein Thing verfügbar"];
        }

        $options = [];
        foreach ($things as $thing) {
            $options[$thing['thingId']] = $thing['thingTitle'];
        }
        return $options;
    }
    public static function getThings()
    {
        $database = Database::getInstance();
        $result = $database->execute("SELECT thingID, thingTitle FROM tl_coh_things");

        $options = [];
        while ($result->next()) {
            $options[$result->thingID] = $result->thingTitle; // thingTitle als Wert, thingsTitle als sichtbarer Text
        }

        return $options;
      }         
    public static function getSensorOptions($dc)
    {
    
        $database = System::getContainer()->get('doctrine.dbal.default_connection');

        // 🔹 DEBUG: Prüfen, ob ein Thing ausgewählt wurde
        if (!$dc->activeRecord || empty($dc->activeRecord->coh_selectedThing)) {
//            var_dump("Kein Thing ausgewählt.");
//            exit;
            return [];
        }

        // 🔹 DEBUG: Thing-ID abrufen
        $selectedThingIds = StringUtil::deserialize($dc->activeRecord->coh_selectedThing, true);
//        exit;
        var_dump("Ausgewählte Thing-IDs:", $selectedThingIds);


        if (empty($selectedThingIds)) {
            return [];
        }

        // Sensorvariable (BLOB) für die gewählten Things abrufen
        $placeholders = implode(',', array_fill(0, count($selectedThingIds), '?'));
        $sql = "SELECT Sensorvariable FROM tl_coh_things WHERE thingId IN ($placeholders)";
        $stmt = $database->executeQuery($sql, $selectedThingIds);

        $sensorIds = [];
        while ($row = $stmt->fetchAssociative()) {
            $thingSensors = StringUtil::deserialize($row['Sensorvariable'], true);
            if (is_array($thingSensors)) {
                $sensorIds = array_merge($sensorIds, $thingSensors);
            }
        }

        // 🔹 DEBUG: Sensor-IDs anzeigen
//        var_dump("Sensor-IDs aus tl_coh_things:", $sensorIds);
//        exit;

        if (empty($sensorIds)) {
            return [];
        }

        // Sensor-Titel für die Sensor-IDs abrufen
        $placeholders = implode(',', array_fill(0, count($sensorIds), '?'));
        $sql = "SELECT sensorId, sensorTitle FROM tl_coh_sensors WHERE sensorId IN ($placeholders)";
        $stmt = $database->executeQuery($sql, $sensorIds);

        $options = [];
        while ($row = $stmt->fetchAssociative()) {
            $options[$row['sensorId']] = sprintf('%s (%s)', $row['sensorTitle'], $row['sensorId']);
        }

        // 🔹 DEBUG: Optionen anzeigen
//        var_dump("Optionen für coh_selectedSensor:", $options);
//        exit;

        return $options;
    }    
    /**
     * Speichert die ausgewählten Things serialisiert in `tl_module`
     */
    public function serializeSelectedThing($varValue,  $dc)
    {
        var_dump('serializeSelectedThing',$varValue);
//    die ('serializeSelectedThing');
        if (empty($varValue)) {
            return null;
        }

        $sensorIds = StringUtil::deserialize($varValue, true);
        if (!is_array($sensorIds)) {
            return null;
        }

        return serialize($sensorIds);
    }
    /**
     * Speichert die ausgewählten Things serialisiert in `tl_module`
     */
    public function serializeSelectedSensors($varValue,  $dc)
    {
    var_dump($varValue);
//    die ('serializeSelectedSensors');
        if (empty($varValue)) {
            return null;
        }

        $sensorIds = StringUtil::deserialize($varValue, true);
        if (!is_array($sensorIds)) {
            return null;
        }

        return serialize($sensorIds);
    }
}
