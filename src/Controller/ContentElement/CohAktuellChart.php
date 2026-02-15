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
        foreach ($selectedSensors as $s) {
            $wildcardtxt .= "$s ";
        }

        $wildcard->wildcard = '<div class="text-truncate" title="'.$wildcardtxt.'">'.$wildcardtxt.'</div>';
        return new Response($wildcard->parse());
    }

    $this->logger->debugMe("getResponse: sensorwerte liefern");

    $templateName = $model->coh_aktuell_template ?: 'ce_coh_aktuell_chart';
    $template = $this->createTemplate($model, $templateName);

    $error = $this->syncService->sync();
    if ($error !== null) {
        $template->syncError = "<br>Syncronisation mit rasperrry fehlgeschlagen.<br>$error<br>Die angezeigten Daten beziehen sich auf einen alten Stand";
    }

    $selectedSensors = StringUtil::deserialize($model->selectedSensors, true);
    $data = [];

    if (!empty($selectedSensors)) {

        // Letzter Messwert pro Sensor + outputMode laden
        $rows = $this->connection->fetchAllAssociative(
            'SELECT *
             FROM (
                 SELECT s1.*, s3.sensorTitle, s3.outputMode,
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

        $dayStart = (new \DateTime('today'))->getTimestamp();

        foreach ($rows as $row) {

            $id = $row['sensorID'];
            $mode = $row['outputMode'] ?? 'absolute';
            $sensorTitle = $row['sensorTitle'] ?: 'Kein Titel';
            $unitLabel = $row['sensorEinheit'] ?: '';
            $ts = date('d.m.Y H:i', $row['tstamp']);

            if ($mode === 'daily') {

                // Startwert seit 00:00 holen
                $startValue = $this->connection->fetchOne(
                    'SELECT sensorValue
                         FROM tl_coh_sensorvalue
                         WHERE sensorID = ?
                         AND tstamp >= ?
                         ORDER BY tstamp ASC
                         LIMIT 1',
                        [$id, $dayStart]
                );

                if ($startValue === false) {
                    // fallback: letzten Wert vor Tagesbeginn nehmen
                    $startValue = $this->connection->fetchOne(
                        'SELECT sensorValue
                             FROM tl_coh_sensorvalue
                             WHERE sensorID = ?
                             AND tstamp < ?
                             ORDER BY tstamp DESC
                             LIMIT 1',
                            [$id, $dayStart]
                    );
                }

                $currentValue = is_numeric($row['sensorValue'])
                    ? (float)$row['sensorValue']
                    : 0;
                if ($startValue !== false && is_numeric($startValue)) {
                    $startValue = (float)$startValue;

                    // Reset-Schutz
                    $val = $currentValue >= $startValue
                        ? $currentValue - $startValue
                        : $currentValue;
                    $val = round($val, 2);
                } else {
                    $val = 0;
                }
            } else {
                // absolute Wert wie bisher
                $val = is_numeric($row['sensorValue']) ? round((float)$row['sensorValue'], 2) : $row['sensorValue'];
            }

            $data[$id]['time'] = $ts;
            $data[$id]['label'] = $id;
            $data[$id]['sensorTitle'] = $sensorTitle;
            $data[$id]['sensorId'] = $id;
            $data[$id]['sensorValue'] = $val;
            $data[$id]['sensorEinheit'] = $unitLabel;
            $data[$id]['sensorValueType'] = $row['sensorValueType'] ?? '';
            $data[$id]['sensorSource'] = $row['sensorSource'] ?? '';

            $this->logger->debugMe("Aktuell {$id} = {$val} ({$mode})");
        }
    }

    $template->chartId = 'chart_' . $model->id;
    $template->data = $data;

    // --- Sync Info ---
    $result = $this->connection
        ->executeQuery("SELECT last_sync FROM tl_coh_sync_log WHERE sync_type = 'sensorvalue_pull'")
        ->fetchOne();

    $template->lastPullSync = $result
        ? date('d.m.Y H:i', strtotime($result))
        : 'Keine Sync-Info vorhanden';

    // --- Letzte Änderung Sensorwerte ---
    $lastChange = $this->connection
        ->executeQuery("SELECT MAX(tstamp) FROM tl_coh_sensorvalue")
        ->fetchOne();

    if ($lastChange) {
        $template->lastSensorChange = date('d.m.Y H:i', (int)$lastChange);
        $diff = time() - (int)$lastChange;
        $template->lastSensorChangeStatus = ($diff > 900)
            ? 'Fehler: Letzter Eintrag älter als 15 Min'
            : 'OK';
    } else {
        $template->lastSensorChange = 'Keine Daten in tl_coh_sensorvalue';
        $template->lastSensorChangeStatus = 'Fehler';
    }

    // --- Push Sync ---
    $result = $this->connection
        ->executeQuery("SELECT last_sync FROM tl_coh_sync_log WHERE sync_type = 'config_push'")
        ->fetchOne();

    $template->lastPushSync = $result
        ? date('d.m.Y H:i', strtotime($result))
        : 'Keine Sync-Info vorhanden';

    return $template->getResponse();
}

    private function getSensorColor(int|string $id): string
    {
        $colors = ['#60A5FA', '#F87171', '#34D399', '#FBBF24', '#A78BFA', '#F472B6'];
        $idNumeric = is_numeric($id) ? (int) $id : crc32($id);
        return $colors[$idNumeric % count($colors)];
    }
}
