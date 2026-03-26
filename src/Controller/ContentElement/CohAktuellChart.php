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

    private string $baseGet = "http://192.168.178.65:5333/trio/get/";
    private string $baseSet = "http://192.168.178.65:5333/trio/set/";

    public function __construct(
        private readonly Connection $connection,
        private readonly SyncService $syncService,
        private readonly LoggerService $logger
    ) {}

    protected function getResponse($template, ContentModel $model, Request $request): Response
    {
        $scope = System::getContainer()->get('request_stack')?->getCurrentRequest()?->attributes?->get('_scope');

        // ---------------------------------------------------
        // BACKEND VIEW
        // ---------------------------------------------------
        if ('backend' === $scope) {
            $templateName = $model->coh_aktuell_template ?: 'coh_aktuell_template';
            $wildcard = new BackendTemplate('be_wildcard_coh');
            $wildcard->title = StringUtil::deserialize($model->headline)['value'] ?? 'Kein Titel';
            $wildcard->id = $model->id;
            $wildcard->href = 'contao?do=themes&table=tl_content&id=' . $model->id;

            $wildcardtxt = "### COH Aktuell ###<br>Template: $templateName<br>";
            $selectedSensors = StringUtil::deserialize($model->selectedSensors, true);
            foreach ($selectedSensors as $s) {
                $wildcardtxt .= "$s ";
            }
            $wildcard->wildcard = '<div class="text-truncate" title="'.$wildcardtxt.'">'.$wildcardtxt.'</div>';
            return new Response($wildcard->parse());
        }
        // ---------------------------------------------------
        // TEMPLATE erzeugen (WICHTIG: danach erst setzen!)
        // ---------------------------------------------------
        $this->addCssOnce('bundles/pbdkncontaocontaohab/css/coh_aktuell_panel.css');
        $templateName = $model->coh_aktuell_template ?: 'ce_coh_aktuell_chart';
        $template = $this->createTemplate($model, $templateName);
        $error = $this->syncService->sync();
        if ($error !== null) { $template->syncError = "<br>Syncronisation mit rasperry fehlgeschlagen.<br>$error"; }
        $selectedSensors = StringUtil::deserialize($model->selectedSensors, true);
        $data = [];
        if (!empty($selectedSensors)) {
            // s1 sensorValue s3 sensor
            $rows = $this->connection->fetchAllAssociative(
                'SELECT 
                    id, tstamp,sensorID,sensorValue,sensorEinheit,sensorValueType,sensorSource,
                    s3_id,sensor_tstamp,sensorTitle,config_sensorEinheit,outputMode,sensorlokalid
                    FROM (
                        SELECT
                        s1.id,s1.tstamp,s1.sensorID,s1.sensorValue,s1.sensorEinheit,s1.sensorValueType,s1.sensorSource,
                        s3.id AS s3_id,s3.tstamp AS sensor_tstamp,s3.sensorTitle,s3.sensorEinheit AS config_sensorEinheit,s3.outputMode,s3.sensorlokalid,
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
                $sensorID = $row['sensorID'];   // ✅ DAS ist dein Key
                $val = is_numeric($row['sensorValue']) ? round((float)$row['sensorValue'], 2) : $row['sensorValue'];
                $data[$sensorID]['sensorValue']   = $val;
                $data[$sensorID]['sensorID']      = $row['sensorID'];
                $data[$sensorID]['sensorTitle']   = $row['sensorTitle'];
                $data[$sensorID]['sensorEinheit'] = $row['sensorEinheit'];
                $this->logger->debugMe("Aktuell {$sensorID} = {$val}");
            }        
        }

        $template->chartId = 'chart_' . $model->id;
        $template->data = $data;
        $template->ajaxToken = 'COH_CODE';

        // --- Sync Info ---
        $result = $this->connection->executeQuery("SELECT last_sync FROM tl_coh_sync_log WHERE sync_type = 'sensorvalue_pull'")->fetchOne();

        $template->lastPullSync = $result ? date('d.m.Y H:i', strtotime($result)) : 'Keine Sync-Info vorhanden';

        // --- Letzte Änderung Sensorwerte ---
        $lastChange = $this->connection->executeQuery("SELECT MAX(tstamp) FROM tl_coh_sensorvalue")->fetchOne();
        if ($lastChange) {
            $template->lastSensorChange = date('d.m.Y H:i', (int)$lastChange);
            $diff = time() - (int)$lastChange;
            $template->lastSensorChangeStatus = ($diff > 900) ? 'Fehler: Letzter Eintrag älter als 15 Min' : 'OK';
        } else {
            $template->lastSensorChange = 'Keine Daten';
            $template->lastSensorChangeStatus = 'Fehler';
        }

        // --- Push Sync ---
        $result = $this->connection->executeQuery("SELECT last_sync FROM tl_coh_sync_log WHERE sync_type = 'config_push'")->fetchOne();
        $template->lastPushSync = $result ? date('d.m.Y H:i', strtotime($result)) : 'Keine Sync-Info vorhanden';

        return $template->getResponse();
    }


    private function addCssOnce(string $file): void
    {
        $file .= '|static';
        if (!in_array($file, $GLOBALS['TL_CSS'] ?? [], true)) {
            $GLOBALS['TL_CSS'][] = $file;
        }
    }

    private function getSensorColor(int|string $id): string
    {
        $colors = ['#60A5FA', '#F87171', '#34D399', '#FBBF24', '#A78BFA', '#F472B6'];
        $idNumeric = is_numeric($id) ? (int) $id : crc32($id);
        return $colors[$idNumeric % count($colors)];
    }
}