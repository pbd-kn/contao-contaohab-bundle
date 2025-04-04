<?php

namespace PbdKn\ContaoContaohabBundle\Module;

use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
use Contao\Module;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;

use PbdKn\ContaoContaohabBundle\Sensor\SensorManager;
use PbdKn\ContaoContaohabBundle\Service\LoggerService;
use Contao\System;

/**
 * @FrontendModule("coh_things", category="coh", template="mod_coh_things_default")
 */
#[FrontendModule(name: "coh_things", category: "coh", template: "mod_coh_things_default")]
class ModuleCohThings extends Module
{
    protected $strTemplate = 'mod_coh_things_default';
    private Connection $database;
    private LoggerService $logger;

    public function __construct($moduleModel, $column = 'main')
    {
      parent::__construct($moduleModel, $column);
      $this->database = \Contao\System::getContainer()->get('doctrine.dbal.default_connection');
      // 🔥 Logger holen
      $this->logger = System::getContainer()->get(LoggerService::class);
    }
    public function generate()
    {
      // Falls im Backend kein spezifisches Template gewählt wurde, Standard setzen
      // muss hier geschen. im Compile ist es zu spät warum ??
        if (!empty($this->coh_template)) {
            $this->strTemplate = $this->coh_template;
        }

        return parent::generate();
    }
   
    protected function compile(): void
    {
 $this->logger->debugMe('Frontend-Modul compile ModuleCohThings wurde aufgerufen');   
      // Gespeicherte Things und sensorIds abrufen (im Modul konfiguriert)
      $selectedThingIds = StringUtil::deserialize($this->coh_selectedThing ?? '', true);
      $selectedSensorIds = StringUtil::deserialize($this->coh_selectedSensor ?? '', true);
      $allthings = [];   // feld wird an template übergeben sollte vielleicht einzeln sls Things und sensoren übergben werden. Template wird dann einfacher
      $things = [];
      foreach ($selectedThingIds as $k=>$v) {
        $allthings['Thing'][$k] = $v;
        $things[$k] = $v;
      } 
      $sensors=[];
      foreach ($selectedSensorIds as $k=>$v) {
        $allthings['Sensor'][$k]['sensorID'] = $v;
        $sensors[$k]['ID']=$v;
        $sqlDescriptionSensorsQuery = "SELECT * FROM tl_coh_sensors WHERE sensorID = ? " ;
        $stmt = $this->database->executeQuery($sqlDescriptionSensorsQuery, [$v]);
        $row = $stmt->fetchAssociative();

        if ($row !== false && is_array($row)) {
            $sensordesr = [];
            foreach ($row as $field => $value) {
                $sensordesr[$field] = $value;
            }
            $allthings['Sensor'][$k]['description'] = $sensordesr;
            $sensors[$k]['description'] = $sensordesr;
        } else {
    // Optional: leeres Array zuweisen oder Hinweis
            $allthings['Sensor'][$k]['description'] = [];
            $sensors[$k]['description'] = [];
        }
      } 
      $this->Template->things = $things;
      $this->Template->sensors = $sensors;
      $this->Template->allthings = $allthings;
      /* nun die werte lesen */

      
      $sensorManager = System::getContainer()->get(SensorManager::class);

      $data = $sensorManager->fetchAll();

        // Jetzt kannst du die $data im Template verwenden:
      $this->Template->sensorData = $data;
      
      return;
    }
}

