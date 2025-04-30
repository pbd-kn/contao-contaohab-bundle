<?php

namespace PbdKn\ContaoContaohabBundle\Module;

use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
use Contao\Module;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;

use PbdKn\ContaoContaohabBundle\Sensor\SensorManager;
use PbdKn\ContaoContaohabBundle\Service\LoggerService;
use Contao\BackendTemplate;
use Contao\System;


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

        $scope = System::getContainer()
        ->get('request_stack')
        ?->getCurrentRequest()
        ?->attributes
        ?->get('_scope');

    if ('backend' === $scope) {
        // Wir sind im Backend!
            $template = new BackendTemplate('be_wildcard');
            $template->wildcard = '### ModuleCohThings ###';
            $template->title = $this->headline;
            $template->id = $this->id;
            $template->link = $this->name;
            $template->href = 'contao?do=themes&table=tl_module&id=' . $this->id;

            return $template->parse();
        }      
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
 $this->logger->debugMe("coh_selectedSensor: ".$this->coh_selectedSensor);   
      $allthings = [];   // feld wird an template übergeben sollte vielleicht einzeln sls Things und sensoren übergben werden. Template wird dann einfacher
      $things = [];
      foreach ($selectedThingIds as $k=>$v) {
        $allthings['Thing'][$k] = $v;
        $things[$k] = $v;
      } 
      $sensors=[];
      $sensorNamen=[];
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
            $sensorNamen[]=$sensordesr['sensorID'];

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

      $data = $sensorManager->fetchAll($sensorNamen);
        // Jetzt kannst du die $data im Template verwenden:
      $this->Template->sensorData = $data;
      
      return;
    }
}

