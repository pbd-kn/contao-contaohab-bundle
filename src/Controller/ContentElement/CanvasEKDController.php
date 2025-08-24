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
use Doctrine\DBAL\Connection;
use PbdKn\ContaoContaohabBundle\Service\SyncService;


#[AsContentElement('canvas_ekd', category: 'COH')]
class CanvasEKDController extends AbstractContentElementController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly SyncService $syncService
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
        $error = $this->syncService->sync();              // Daten synchronisieren
        if ($error !== null) {
            $template->syncError = $error;
        }
        // notwendige sensoren lesen
        $selectedSensors = [
            'IQbattery_94_battery_stateOfCharge',   // füllstand Akku
            'IQinverter_94_inverter_pvPower',       // Leistung Solar
            'IQinverter_94_inverter_selfConsumptionPower', // Eigenverbrauch
            'IQbattery_94_battery_power',            // Akku laden/entladen
            'IQbattery_94_harmonized_power_in',
            'IQbattery_94_harmonized_power_out',
            'ZWZZaehlerPowerIn',                    // Power von SWR
            'ZWZZaehlerPowerOut',                   // Power zu SWR
            'ELaktPwr',                              // Leisung Heizstab
            'ELaktTemp2'
        ];
        $placeholders = implode(',', array_fill(0, count($selectedSensors), '?'));

        $rows = $this->connection->fetchAllAssociative(
            'SELECT s1.*, s3.sensorTitle
                 FROM tl_coh_sensorvalue s1
                     INNER JOIN (
                         SELECT sensorID, MAX(tstamp) AS max_tstamp
                             FROM tl_coh_sensorvalue
                             WHERE sensorID IN (?)
                             GROUP BY sensorID
                     ) s2 ON s1.sensorID = s2.sensorID AND s1.tstamp = s2.max_tstamp
                     LEFT JOIN tl_coh_sensors s3 ON s1.sensorID = s3.sensorID
                     ORDER BY s1.sensorID ASC',
                [$selectedSensors],
                [\Doctrine\DBAL\Connection::PARAM_STR_ARRAY]
        );
        // daten der Sensoren speichern
        $dataSensor = [];
        foreach ($rows as $row) {
            $ts = date('d.m.Y H:i', $row['tstamp']);

            $id = $row['sensorID'];
            // Prüfen, ob numerisch (z. B. "12.3", "42", aber auch "3e5")
            if (is_numeric($row['sensorValue'])) {
                $val = (float) $row['sensorValue'];
            } else {
                $val = $row['sensorValue']; // als Text übernehmen
            }
            $unitLabel = $row['sensorEinheit'] ?: '';
            $sensorTitle = $row['sensorTitle'] ?: 'Kein Titel';
            $dataSensor[$id]['time'] = $ts;
            $dataSensor[$id]['label'] = $id;
            $dataSensor[$id]['sensorTitle'] = $sensorTitle;
            $dataSensor[$id]['sensorId'] = $id;
            $dataSensor[$id]['sensorValue'] = $val;
            $dataSensor[$id]['sensorEinheit'] = $unitLabel;
            $dataSensor[$id]['sensorValueType'] = !empty($row['sensorValueType']) ? $row['sensorValueType'] : '';
            $dataSensor[$id]['sensorSource'] = !empty($row['sensorSource']) ? $row['sensorSource'] : '';
        }
        
        $template->dataSensor = $dataSensor;

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
            if (in_array(strtolower($type ?? ''), ['bar'], true)) {
                $entry['value'] = (float)($row['value'] ?? 0);             // legt fest ob gescrollt wird
                $entry['direction'] = $row['direction'] ?? 'up';
                $entry['color'] = $row['color'] ?: '#f60';
                $entry['background'] = $row['background'] ?: '#ddd';
                $bartype = strtolower($row['label']);
                $entry['label'] = $bartype ;
                switch ($bartype) {
                    case 'barsolar':
                        $val = $entry['label'] .' '.$dataSensor['IQinverter_94_inverter_pvPower']['sensorValue'].' '.$dataSensor['IQinverter_94_inverter_pvPower']['sensorEinheit'];
                        $val ='';
                        $entry['label'] = $val ;
                        $entry['direction'] = 'down'; // oder jeder andere Wert aus deiner Select-Option 
                        if ($dataSensor['IQinverter_94_inverter_pvPower']['sensorValue'] > 0) {
                          $entry['value'] = 100; // oder jeder andere Wert aus deiner Select-Option
                        } else {
                          $entry['value'] = 0; // oder jeder andere Wert aus deiner Select-Option
                        }
                        break;
                    case 'barakku':
                        $val = $entry['label'].' '.$dataSensor['IQbattery_94_battery_stateOfCharge']['sensorValue'].' '.$dataSensor['IQbattery_94_battery_stateOfCharge']['sensorEinheit'];
                        $val = $entry['label'].' '.$dataSensor['IQbattery_94_battery_power']['sensorValue'].' '.$dataSensor['IQbattery_94_battery_power']['sensorEinheit'];
                        $valueNum = (float) $dataSensor['IQbattery_94_battery_power']['sensorValue'];
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
                        $val = $entry['label'] . ' '.$dataSensor['ZWZZaehlerPowerIn']['sensorValue'].' '.$dataSensor['ZWZZaehlerPowerIn']['sensorEinheit'];
                        $val .= ' '.$dataSensor['ZWZZaehlerPowerOut']['sensorValue'].' '.$dataSensor['ZWZZaehlerPowerOut']['sensorEinheit'];
                        $val ='';
                        $entry['label'] = $val ;
                        if ($dataSensor['ZWZZaehlerPowerIn']['sensorValue'] >= 0 ) {
                           $entry['direction'] = 'right'; // oder jeder andere Wert aus deiner Select-Option 
                        } else {
                           $entry['direction'] = 'left'; // oder jeder andere Wert aus deiner Select-Option 
                        }
                        if ($dataSensor['ZWZZaehlerPowerIn']['sensorValue'] >= 0) {
                          $entry['value'] = 100; // oder jeder andere Wert aus deiner Select-Option
                        } else {
                          $entry['value'] = 0; // oder jeder andere Wert aus deiner Select-Option
                        }
                        
                        break;
                   case 'barheizstab':
                        $val = $entry['label'].' '.$dataSensor['ELaktPwr']['sensorValue'].' '.$dataSensor['ELaktPwr']['sensorEinheit'];
                        $val ='';
                        $entry['label'] = $val ;
                        $entry['direction'] = 'down'; // oder jeder andere Wert aus deiner Select-Option 
                        if ($dataSensor['ELaktPwr']['sensorValue'] > 0) {
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
                    $valSolar=$dataSensor['IQinverter_94_inverter_pvPower']['sensorValue'];
                    $valHeizstab=$dataSensor['ELaktPwr']['sensorValue'];
                    $valAkku=$dataSensor['IQbattery_94_battery_power']['sensorValue'];
                    $valSWR=$dataSensor['ZWZZaehlerPowerOut']['sensorValue']-$dataSensor['ZWZZaehlerPowerIn']['sensorValue'];
                    $valBerechnet = $valSolar-$valHeizstab-$valAkku-$valSWR;
                    $val = "Eigenverbrauch \n".$dataSensor['IQinverter_94_inverter_selfConsumptionPower']['sensorValue'].' '.$dataSensor['IQinverter_94_inverter_selfConsumptionPower']['sensorEinheit'];
                    $val .= "\nberechnet ".$valBerechnet.' '.$dataSensor['IQinverter_94_inverter_selfConsumptionPower']['sensorEinheit'];
                    $entry['label'] = $entry['label'] . ' '.$val ;
                }
                if (in_array(strtolower($type ?? ''), ['solarzelle'], true)) {
                    $val = "Solarleistung \n".$dataSensor['IQinverter_94_inverter_pvPower']['sensorValue'].' '.$dataSensor['IQinverter_94_inverter_pvPower']['sensorEinheit'];
                    $entry['label'] = $val ;
                }
                if (in_array(strtolower($type ?? ''), ['heizstab'], true)) {
                    $val = "Heizstab \n".$dataSensor['ELaktPwr']['sensorValue'].' '.$dataSensor['ELaktPwr']['sensorEinheit'];
                    $val .= "\n".$dataSensor['ELaktTemp2']['sensorValue'].' '.$dataSensor['ELaktTemp2']['sensorEinheit'];
                    $entry['label'] = $val ;
                }
                if (in_array(strtolower($type ?? ''), ['akku'], true)) {
                    $val = "Akku \n".$dataSensor['IQbattery_94_battery_stateOfCharge']['sensorValue'].' '.$dataSensor['IQbattery_94_battery_stateOfCharge']['sensorEinheit'];
                    $val .= "\n".$dataSensor['IQbattery_94_battery_power']['sensorValue'].' '.$dataSensor['IQbattery_94_battery_power']['sensorEinheit'];
                    $entry['label'] = $val ;
                }
                if (in_array(strtolower($type ?? ''), ['einspeisung'], true)) {
                    $val = "Einspeisung";
                    $val .= "\nvon SWR\t".$dataSensor['ZWZZaehlerPowerIn']['sensorValue'].' '.$dataSensor['ZWZZaehlerPowerIn']['sensorEinheit'];
                    $val .= "\nnach SWR\t".$dataSensor['ZWZZaehlerPowerOut']['sensorValue'].' '.$dataSensor['ZWZZaehlerPowerOut']['sensorEinheit'];
                    $entry['label'] = $val ;
                }
            }

            $elements[] = $entry;
        }

        $maxX = $maxY = 0;
        foreach ($elements as $e) {
            $w = $e['width'] ?? 64;
            $h = $e['height'] ?? 64;
            $maxX = max($maxX, $e['x'] + $w);
            $maxY = max($maxY, $e['y'] + $h + ($e['label'] ? 20 : 0));
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
