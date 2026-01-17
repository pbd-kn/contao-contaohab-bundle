<?php

namespace PbdKn\ContaoContaohabBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Connection;
use Contao\BackendTemplate;
use Contao\StringUtil;
use Contao\System;
use PbdKn\ContaoContaohabBundle\Service\SyncService;
use PbdKn\ContaoContaohabBundle\Service\LoggerService;


#[AsContentElement(CohAktuellChart::TYPE, category: 'COH')]
class CohAktuellChart extends AbstractContentElementController
{
    public const TYPE = 'ce_coh_aktuell_chart';

    public function __construct(
        private readonly Connection $connection,
        private readonly SyncService $syncService,
        private readonly LoggerService $logger
    ) {}

    protected function getResponse($template, ContentModel $model, Request $request): Response
    {
        $scope = System::getContainer()->get('request_stack')?->getCurrentRequest()?->attributes?->get('_scope');
        if ('backend' === $scope) {
            $templateName = $model->coh_aktuell_template ?: 'coh_aktuell_template';
            $wildcard = new BackendTemplate('be_wildcard_coh');
            $wildcard->title = StringUtil::deserialize($model->headline)['value'] ?? 'Kein Titel';
            $wildcard->id = $model->id;
            $wildcard->href = 'contao?do=themes&table=tl_content&id=' . $model->id;
            $wildcardtxt = "### COH Aktuell ###<br>Template: $templateName<br>";
            $selectedSensors = StringUtil::deserialize($model->selectedSensors, true);
            //$wildcardtxt .= "anz. Selectoren ".count($selectedSensors)."<br>";
            foreach ($selectedSensors as $s) $wildcardtxt .= "$s ";
            $wildcard->wildcard = '<div class="text-truncate" title="'.$wildcardtxt.'">'.$wildcardtxt.'</div>';
            return new Response($wildcard->parse());
        }

 // ?? Template dynamisch wählen ??
    $this->logger->debugMe("getResponse: sensorwerte liefern");

    $templateName = $model->coh_aktuell_template ?: 'ce_coh_aktuell_chart';
    $template = $this->createTemplate($model, $templateName);

    $error = $this->syncService->sync();              // Daten synchronisieren
    $this->logger->debugMe("getResponse: sync ok");
    if ($error !== null) {
        $template->syncError = "<br>Syncronisation mit rasperrry fehlgeschlagen.<br>$error<br>Die angezeigten Daten beziehen sich auf einen alten Stand";
    }
        $selectedSensors = StringUtil::deserialize($model->selectedSensors, true);
        $data = [];

        if (!empty($selectedSensors)) {
            $placeholders = implode(',', array_fill(0, count($selectedSensors), '?'));
            //„letzter nicht-leerer Messwert pro Sensor“
            $rows = $this->connection->fetchAllAssociative(
                'SELECT *
                    FROM (
                        SELECT s1.*, s3.sensorTitle,
                            ROW_NUMBER() OVER (
                                PARTITION BY s1.sensorID
                                ORDER BY s1.tstamp DESC, s1.id DESC
                            ) rn
                    FROM tl_coh_sensorvalue s1
                        LEFT JOIN tl_coh_sensors s3 ON s1.sensorID = s3.sensorID
                            WHERE s1.sensorID IN (?)
                                AND s1.sensorValue IS NOT NULL
                                AND s1.sensorValue <> \'\'
                    ) x
                WHERE rn = 1
                ORDER BY sensorID',
                [$selectedSensors],
                [\Doctrine\DBAL\Connection::PARAM_STR_ARRAY]
            );

            foreach ($rows as $row) {
//                $ts = date('c', $row['tstamp']);
                $ts = date('d.m.Y H:i', $row['tstamp']);

                $id = $row['sensorID'];
    //$this->logger->debugMe("getResponse: sensorID $id");
    $this->logger->debugMe("ROW: id={$row['id']} sensorID={$row['sensorID']} tstamp={$row['tstamp']} raw=" . var_export($row['sensorValue'], true));

                // Prüfen, ob numerisch (z. B. "12.3", "42", aber auch "3e5")
                if (is_numeric($row['sensorValue'])) {
                    $val = (float) $row['sensorValue'];
                } else {
                    $val = $row['sensorValue']; // als Text übernehmen
                }
//                $val = (float) $row['sensorValue'];
                $unitLabel = $row['sensorEinheit'] ?: '';
                $sensorTitle = $row['sensorTitle'] ?: 'Kein Titel';


//                $color = $this->getSensorColor($id);
                $data[$id]['time'] = $ts;
                $data[$id]['label'] = $id;
                $data[$id]['sensorTitle'] = $sensorTitle;
//                $data[$id]['borderColor'] = $color;
                $data[$id]['sensorId'] = $id;
//                isset($color) && $data[$id]['borderColor'] ??= $color;
                $data[$id]['sensorValue'] = $val;
                $data[$id]['sensorEinheit'] = $unitLabel;
                $data[$id]['sensorValueType'] = !empty($row['sensorValueType']) ? $row['sensorValueType'] : '';
                $data[$id]['sensorSource'] = !empty($row['sensorSource']) ? $row['sensorSource'] : '';
                
                
                $str = print_r($data[$id], true);
                $this->logger->debugMe("getResponse: data $str");

            }
        }

        $template->chartId = 'chart_' . $model->id;
        $template->data = $data;
        $result = $this->connection
          ->executeQuery("SELECT last_sync FROM tl_coh_sync_log WHERE sync_type = 'sensorvalue_pull'")
          ->fetchOne();
        if ($result) {
            // Unix-Timestamp aus datetime erzeugen
            $timestamp = strtotime($result);
            $template->lastPullSync = date('d.m.Y H:i', $timestamp);
        } else {
            $template->lastPullSync = 'Keine Sync-Info vorhanden';
        }   
        // Letzte Änderung aus tl_coh_sensorvalue (Unix → DateTime)
        $lastChange = $this->connection
            ->executeQuery("SELECT MAX(tstamp) FROM tl_coh_sensorvalue")
            ->fetchOne();

        if ($lastChange) {
            $template->lastSensorChange = date('d.m.Y H:i', (int)$lastChange);

            // Prüfen ob älter als 15 Minuten
            $diff = time() - (int)$lastChange;
            if ($diff > 900) {
                $template->lastSensorChangeStatus = 'Fehler: Letzter Eintrag älter als 15 Min';
            } else {
                $template->lastSensorChangeStatus = 'OK';
            }
        } else {
            $template->lastSensorChange = 'Keine Daten in tl_coh_sensorvalue';
            $template->lastSensorChangeStatus = 'Fehler';
        }              
        $result = $this->connection
          ->executeQuery("SELECT last_sync FROM tl_coh_sync_log WHERE sync_type = 'config_push'")
          ->fetchOne();
        if ($result) {
            // Unix-Timestamp aus datetime erzeugen
            $timestamp = strtotime($result);
            $template->lastPushSync = date('d.m.Y H:i', $timestamp);
        } else {
            $template->lastPushSync = 'Keine Sync-Info vorhanden';
        }          
        

        return $template->getResponse();
    }

    private function getSensorColor(int|string $id): string
    {
        $colors = ['#60A5FA', '#F87171', '#34D399', '#FBBF24', '#A78BFA', '#F472B6'];
        $idNumeric = is_numeric($id) ? (int) $id : crc32($id);
        return $colors[$idNumeric % count($colors)];
    }
}
