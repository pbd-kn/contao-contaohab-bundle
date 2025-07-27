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


#[AsContentElement(CohAktuellChart::TYPE, category: 'COH')]
class CohAktuellChart extends AbstractContentElementController
{
    public const TYPE = 'ce_coh_aktuell_chart';

    public function __construct(
        private readonly Connection $connection,
        private readonly SyncService $syncService
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
    $templateName = $model->coh_aktuell_template ?: 'ce_coh_aktuell_chart';
    $template = $this->createTemplate($model, $templateName);

    $error = $this->syncService->sync();              // Daten synchronisieren
    if ($error !== null) {
        $template->syncError = $error;
    }
        $selectedSensors = StringUtil::deserialize($model->selectedSensors, true);
        $data = [];

        if (!empty($selectedSensors)) {
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

            foreach ($rows as $row) {
//                $ts = date('c', $row['tstamp']);
                $ts = date('d.m.Y H:i', $row['tstamp']);

                $id = $row['sensorID'];
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
