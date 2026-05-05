<?php

declare(strict_types=1);

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

#[AsContentElement(SensorElement::TYPE, category: 'COH', template: 'ce_coh_sensorelement')]
class SensorElement extends AbstractContentElementController
{
    public const TYPE = 'coh_sensorelement';

    public function __construct(
        private readonly Connection $connection,
        private readonly SyncService $syncService,
        private readonly LoggerService $logger
    ) {}

    protected function getResponse($template, ContentModel $model, Request $request): Response
    {
        $scope = System::getContainer()
            ->get('request_stack')
            ?->getCurrentRequest()
            ?->attributes
            ?->get('_scope');
        /*-----------------------------------------
         * Backend Wildcard
         *-----------------------------------------
        */
        if ('backend' === $scope) {
            $wildcard = new BackendTemplate('be_wildcard');
            $headline = StringUtil::deserialize($model->headline);
            $wildcard->wildcard = '### COH SENSOR ELEMENT ###';
            $wildcard->title = $headline['value'] ?? 'COH Sensor Element';
            $wildcard->id = $model->id;
            $wildcard->link = $wildcard->title;
            $wildcard->href = 'contao?do=themes&table=tl_content&id='.$model->id;
            return new Response($wildcard->parse());
        }
        /*-----------------------------------------
         * Template wählen
         * -----------------------------------------
        */
        $templateName = $model->coh_template ?: 'ce_coh_sensorelement';
        $template = $this->createTemplate($model, $templateName);
        $resSync = $this->syncService->sync();
        if ($resSync['status'] !== 'OK') { $template->syncError = "<br>Syncronisation mit rasperry fehlgeschlagen.<br>".$resSync['status']; }
        $template->syncResult = $resSync;
        /*
         * -----------------------------------------
         * eindeutiger Formularparameter
         * -----------------------------------------
        */
        $field = 'sensor_'.$model->id;
        /*
         * -----------------------------------------
         * Frontend Auswahl
         * -----------------------------------------
        */
        $selectedSensors = $request->query->all($field);
        /*
         * -----------------------------------------
         * Fallback auf DCA
         * -----------------------------------------
        */
        if (empty($selectedSensors)) {
            $selectedSensors = StringUtil::deserialize($model->coh_selectedSensor, true);
        }
        /*
         * -----------------------------------------
         * Alle Sensoren laden (für Checkboxliste)
         * -----------------------------------------
        */
        $allSensors = $this->connection->fetchAllAssociative(
            "SELECT sensorID, sensorTitle, sensorEinheit, sensorLokalId, outputMode
             FROM tl_coh_sensors
             ORDER BY sensorTitle"
        );
        /*
         * -----------------------------------------
         * Gewählte Sensoren laden + letzter Wert
         * -----------------------------------------
        */
        $sensors = [];


if (!empty($selectedSensors)) {

    $rows = $this->connection->fetchAllAssociative(
        "SELECT 
            s.*,
            sv.sensorValue,
            sv.tstamp,
            sv.sensorEinheit AS svsensorEinheit,
            sv.sensorValueType AS svsensorValueType
        FROM tl_coh_sensors s
        LEFT JOIN (
            SELECT v1.*
            FROM tl_coh_sensorvalue v1
            INNER JOIN (
                SELECT sensorID, MAX(tstamp) AS max_tstamp
                FROM tl_coh_sensorvalue
                GROUP BY sensorID
            ) v2 
            ON v1.sensorID = v2.sensorID 
            AND v1.tstamp = v2.max_tstamp
        ) sv ON sv.sensorID = s.sensorID
        WHERE s.sensorID IN (?)
        ORDER BY s.sensorTitle",
        [$selectedSensors],
        [Connection::PARAM_STR_ARRAY]
    );

    foreach ($rows as $row) {

        $row['date'] = !empty($row['tstamp'])
            ? date('d.m.Y H:i:s', (int)$row['tstamp'])
            : '';

        if (!empty($row['svsensorEinheit'])) {
            $row['sensorEinheit'] = $row['svsensorEinheit'];
        }

        if (!empty($row['svsensorValueType'])) {
            $row['sensorValueType'] = $row['svsensorValueType'];
        }

        $sensors[] = $row;
    }
}        /*
         * -----------------------------------------
         * Template Variablen
         * -----------------------------------------
        */
        $template->allSensors = $allSensors;
        $template->sensors = $sensors;
        $template->selectedSensors = $selectedSensors;
        $template->fieldName = $field;
        
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
}