<?php

namespace PbdKn\ContaoContaohabBundle\Controller\ContentElement;


use Contao\ContentModel;
use Contao\FilesModel;
use Contao\StringUtil;
use Contao\System;
use Contao\BackendTemplate;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use PbdKn\ContaoContaohabBundle\Service\Sensors\SensorManager;


#[AsContentElement('canvas_ekd', category: 'COH')]
class CanvasEKDController extends AbstractContentElementController
{
    public function __construct(
        private readonly SensorManager $sensorManager
    ) {}
    
    protected function getResponse($template, ContentModel $model, Request $request): Response
    {
        $scope = System::getContainer()->get('request_stack')?->getCurrentRequest()?->attributes?->get('_scope');

        if ('backend' === $scope) {
            $wildcard = new BackendTemplate('be_wildcard_coh');
            $wildcard->title = StringUtil::deserialize($model->headline)['value'] ?? 'Canvas EKD';
            $wildcard->id = $model->id;
            $wildcard->link = 'Content-Element ID ' . $model->id;
            $wildcard->href = 'contao?do=themes&table=tl_content&id=' . $model->id;
            $data = StringUtil::deserialize($model->canvas_ekd_data, true);
            $wildcard->wildcard = 'Canvas EKD – Elemente: ' . count($data);
            return new Response($wildcard->parse());
        }

        // ✅ Hier Template aus dem Modell verwenden (wenn gesetzt)

        $template = $this->createTemplate($model, $model->canvas_ekd_template ?: 'ce_canvas_ekd_default');

        $selectedSensors = [
            'IQ_Live',                               // sensorLokalId "live" aus der Ampere.IQ-Cloud
            'IQ_Today',
            'ELaktTemp2'
        ];
        $rows = $this->sensorManager->fetchAll($selectedSensors);

        // Die ausgewählten Sensoren bleiben für alternative Canvas-Templates
        // in der bisherigen, vollständig befüllten Struktur verfügbar.
        $dataSensor = [];
        $timestamp = date('d.m.Y H:i');
        foreach ($selectedSensors as $sensorId) {
            $row = $rows[$sensorId] ?? [];
            $value = $row['sensorValue'] ?? 0;
            $normalizedValue = $value;
            if ( is_int($value) || is_float($value) || (is_string($value) && is_numeric($value)) ) {
                $normalizedValue = (float) $value;
            }
            $dataSensor[$sensorId] = [
                'time' => $timestamp,
                'label' => $sensorId,
                'sensorTitle' => $row['sensorTitle'] ?? $sensorId,
                'sensorId' => $sensorId,
                'sensorValue' => $normalizedValue,
                'sensorEinheit' => (string) ($row['sensorEinheit'] ?? ''),
                'sensorValueType' => (string) ($row['sensorValueType'] ?? ''),
                'sensorSource' => (string) ($row['sensorSource'] ?? ''),
            ];
        }
        $template->dataSensor = $dataSensor;

        $iqLive = $dataSensor['IQ_Live']['sensorValue'];
        if (!is_array($iqLive)) {
            $iqLive = [];
        }
        $heatingRodTemperature = $dataSensor['ELaktTemp2']['sensorValue'];
        $temperatureUnit = $dataSensor['ELaktTemp2']['sensorEinheit'];

        $iqToday = $dataSensor['IQ_Today']['sensorValue'];
        if (!is_array($iqToday)) {
            $iqToday = [];
        }
        $todayWork = isset($iqToday['work']) && is_array($iqToday['work'])
            ? $iqToday['work']
            : $iqToday;
        $todayWorkLabels = [
            'generation' => 'Solarerzeugung',
            'consumption' => 'Gesamtverbrauch',
            'batteryFeed' => 'Batterieladung',
            'batteryDraw' => 'Batterieentladung',
            'gridFeed' => 'Netzeinspeisung',
            'gridDraw' => 'Netzbezug',
        ];
        $todayWorkValues = [];
        foreach ($todayWorkLabels as $field => $title) {
            $valueWh = (float) ($todayWork[$field] ?? 0);
            $todayWorkValues[] = [
                'title' => $title,
                'value' => number_format($valueWh / 1000, 2, ',', '.') . ' kWh',
            ];
        }

        $selfSufficiency = $iqToday['selfSufficiency']['value'] ?? null;
        if (!is_numeric($selfSufficiency)) {
            $consumption = (float) ($todayWork['consumption'] ?? 0);
            $gridDraw = (float) ($todayWork['gridDraw'] ?? 0);
            $selfSufficiency = $consumption > 0
                ? (1 - $gridDraw / $consumption) * 100
                : 0;
        }
        $selfSufficiency = max(0, min(100, (float) $selfSufficiency));
        array_unshift($todayWorkValues, [
            'title' => 'Autarkiegrad',
            'value' => number_format($selfSufficiency, 1, ',', '.') . ' %',
            'featured' => true,
        ]);

        $template->todayWorkValues = $todayWorkValues;

        $pvPower = (float) ($iqLive['pvPower'] ?? 0);
        $housePower = (float) ($iqLive['housePower'] ?? 0);
        $gridPower = (float) ($iqLive['gridPower'] ?? 0);
        $batteryPower = (float) ($iqLive['batteryPower'] ?? 0);
        $heatingRodPower = (float) ($iqLive['heatingRodPower'] ?? 0);
        $batterySoc = (float) ($iqLive['batterySoc'] ?? 0);
        $pvPowerKw = round($pvPower / 1000, 2);
        $housePowerKw = round(abs($housePower) / 1000, 2);
        $gridPowerKw = round(abs($gridPower) / 1000, 2);
        $batteryPowerKw = round($batteryPower / 1000, 2);
        $heatingRodPowerKw = round(abs($heatingRodPower) / 1000, 2);
        $powerUnit = 'kW';
        $socUnit = '%';

        $data = StringUtil::deserialize($model->canvas_ekd_data, true);
        $elements = [];
        
        // daten für template canvas aufbauen  element aus dem canvas
        foreach ($data as $row) {
            $type = $row['type'] ?? 'image';
            $entry = [
                'type' => $type,
                'x' => (int)($row['x'] ?? 0),
                'y' => (int)($row['y'] ?? 0),
                'width' => (int)($row['width'] ?? 64),
                'height' => (int)($row['height'] ?? 64),
                'rotation' => (float)($row['rotation'] ?? 0),
                'opacity' => (float)($row['opacity'] ?? 1),
            ];
            if (strtolower($type ?? '') === 'bar') {
                $entry['value'] = (float)($row['value'] ?? 0);             // legt fest ob gescrollt wird
                $entry['direction'] = $row['direction'] ?? 'up';
                $entry['color'] = $row['color'] ?: '#f60';
                $entry['background'] = $row['background'] ?: '#ddd';
                $bartype = strtolower($row['label']);
                $entry['label'] = $bartype ;
                switch ($bartype) {
                    case 'barsolar':
                        $val ='';
                        $entry['label'] = $val ;
                        $entry['direction'] = 'down'; // oder jeder andere Wert aus deiner Select-Option 
                        if ($pvPower > 0) {
                          $entry['value'] = 100; // oder jeder andere Wert aus deiner Select-Option
                        } else {
                          $entry['value'] = 0; // oder jeder andere Wert aus deiner Select-Option
                        }
                        break;
                    case 'barakku':
                        $valueNum = $batteryPower;
                        //$val=$valueNum;
                        $val ='';
                        $entry['label'] = $val ;
                        if (abs($valueNum) > 0.1) {
                            // sicher kein "echtes" 0
                          $entry['value'] = 100; // oder jeder andere Wert aus deiner Select-Option
                          if ($valueNum > 0) {
                            $entry['direction'] = 'left'; // oder jeder andere Wert aus deiner Select-Option 
                          } else {
                            $entry['direction'] = 'right'; // oder jeder andere Wert aus deiner Select-Option 
                          }
                        } else {
                          $entry['value'] = 0; // oder jeder andere Wert aus deiner Select-Option
                        }
                        break;
                   case 'bareinspeisung':
                        $val ='';
                        $entry['label'] = $val ;
                        // Ampere.IQ: negativ = Einspeisung, positiv = Netzbezug.
                        if ($gridPower < 0) {
                           $entry['direction'] = 'right'; // oder jeder andere Wert aus deiner Select-Option 
                        } else {
                           $entry['direction'] = 'left'; // oder jeder andere Wert aus deiner Select-Option 
                        }
                        if (abs($gridPower) > 0.1) {
                          $entry['value'] = 100; // oder jeder andere Wert aus deiner Select-Option
                        } else {
                          $entry['value'] = 0; // oder jeder andere Wert aus deiner Select-Option
                        }
                        
                        break;
                   case 'barheizstab':
                        $val ='';
                        $entry['label'] = $val ;
                        $entry['direction'] = 'down'; // oder jeder andere Wert aus deiner Select-Option 
                        if (abs($heatingRodPower) > 0.1) {
                          $entry['value'] = 100; // oder jeder andere Wert aus deiner Select-Option
                        } else {
                          $entry['value'] = 0; // oder jeder andere Wert aus deiner Select-Option
                        }
                        break;
                    default:
                        $entry['label'] = $entry['label'] . " \n\n!! unbekannter bar";
                        break;
                }
                
            } else {
                if (!isset($row['image']) || !$row['image']) continue;
                $fileModel = FilesModel::findByUuid($row['image']);
                if ($fileModel === null) continue;
                $entry['src'] = $fileModel->path;
                $entry['label'] = trim((string)($row['label'] ?? ''));
                if (in_array(strtolower($type ?? ''), ['haus'], true)) {
                    $val = "Eigenverbrauch \n".$housePowerKw.' '.$powerUnit;
                    $entry['label'] = $entry['label'] . ' '.$val ;
                }
                if (in_array(strtolower($type ?? ''), ['solarzelle'], true)) {
                    $val = "Solarleistung \n".$pvPowerKw.' '.$powerUnit;
                    $entry['label'] = $val ;
                }
                if (in_array(strtolower($type ?? ''), ['heizstab'], true)) {
                    $val = "Heizstab \n".$heatingRodPowerKw.' '.$powerUnit;
                    $val .= "\n".$heatingRodTemperature.' '.$temperatureUnit;
                    $entry['label'] = $val ;
                }
                if (in_array(strtolower($type ?? ''), ['akku'], true)) {
                    $val = "Akku \n".$batterySoc.' '.$socUnit;
                    $val .= "\n".$batteryPowerKw.' '.$powerUnit;
                    $entry['label'] = $val ;
                }
                if (in_array(strtolower($type ?? ''), ['einspeisung'], true)) {
                    $gridLabel = $gridPower < 0 ? 'Einspeisung' : ($gridPower > 0 ? 'Netzbezug' : 'Netzleistung');
                    $val = $gridLabel."\n".$gridPowerKw.' '.$powerUnit;
                    $entry['label'] = $val ;
                }
            }

            $elements[] = $entry;
        }

        $maxX = $maxY = 0;
        foreach ($elements as $e) {
            $w = $e['width'] ?? 64;
            $h = $e['height'] ?? 64;
            $label = (string) ($e['label'] ?? '');
            $labelLines = $label !== '' ? (preg_split('/\R/u', $label) ?: []) : [];
            $longestLabelLength = 0;
            foreach ($labelLines as $labelLine) {
                $lineLength = function_exists('mb_strlen')
                    ? mb_strlen($labelLine)
                    : strlen($labelLine);
                $longestLabelLength = max($longestLabelLength, $lineLength);
            }

            // Das Template zeichnet Labels linksbündig in 12px sans-serif.
            // Etwa 7 Pixel pro Zeichen plus Reserve verhindern ein Abschneiden.
            $labelWidth = $longestLabelLength * 7 + 12;
            $labelHeight = $labelLines !== [] ? 4 + count($labelLines) * 14 : 0;

            $maxX = max($maxX, $e['x'] + $w, $e['x'] + $labelWidth);
            $maxY = max($maxY, $e['y'] + $h + $labelHeight);
        }





        $template->chartId = 'canvas_' . $model->id;
        $template->canvasWidth = $maxX + 40;
        $template->canvasHeight = $maxY + 40;
        $template->elementData = json_encode($elements);

        $headline = StringUtil::deserialize($model->headline, true);
        $headlineValue = $headline['value'] ?? '';
        $headlineUnit = $headline['unit'] ?? 'h2';
        $template->headlineText = $headlineValue;
        $template->headlineLevel = $headlineUnit;

        return $template->getResponse();
    }
}
