<?php

namespace PbdKn\ContaoContaohabBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Contao\BackendTemplate;
use Contao\StringUtil;
use Contao\System;
use PbdKn\ContaoContaohabBundle\Service\LoggerService;
use PbdKn\ContaoContaohabBundle\Service\Sensors\SensorManager;

#[AsContentElement(CohAktuellChart::TYPE, category: 'COH')]
class CohAktuellChart extends AbstractContentElementController
{
    public const TYPE = 'ce_coh_aktuell_chart';

    private string $baseGet = "http://192.168.178.65:5333/trio/get/";
    private string $baseSet = "http://192.168.178.65:5333/trio/set/";

    public function __construct(
        private readonly LoggerService $logger,
        private readonly SensorManager $sensorManager
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
        $selectedSensors = StringUtil::deserialize($model->selectedSensors, true);
        $data = [];
        if (!empty($selectedSensors)) {
            $rows = $this->sensorManager->fetchAll($selectedSensors);
            foreach ($rows as $row) {
                $sensorID = $row['sensorID'];   // ✅ DAS ist dein Key
                //$ts = date('d.m.Y H:i', (int) $row['sensorvalue_tstamp']);
                $val = is_numeric($row['sensorValue']) ? round((float)$row['sensorValue'], 2) : $row['sensorValue'];
                $data[$sensorID]['sensorValue']   = $val;
                $data[$sensorID]['sensorID']      = $row['sensorID'];
                $data[$sensorID]['sensorTitle']   = $row['sensorTitle'] ?? $sensorID;
                $data[$sensorID]['sensorEinheit'] = $row['sensorEinheit'];
                $data[$sensorID]['sensorDatum'] = date('d.m.Y H:i');
                $data[$sensorID]['sensorConfigDatum'] = '';
                $logValue = is_array($val)
                    ? json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : (string) $val;
                $this->logger->debugMe("Aktuell {$sensorID} = {$logValue}");
            }        
        }

        $template->chartId = 'chart_' . $model->id;
        $template->data = $data;
        $template->ajaxToken = 'COH_CODE';

        // --- Sync Info ---
        $result = false;

        $template->lastPullSync = $result ? date('d.m.Y H:i', strtotime($result)) : 'Keine Sync-Info vorhanden';

        // --- Letzte Änderung Sensorwerte ---
        $lastChange = time();
        if ($lastChange) {
            $template->lastSensorChange = date('d.m.Y H:i', (int)$lastChange);
            $diff = time() - (int)$lastChange;
            $template->lastSensorChangeStatus = ($diff > 1800) ? 'Fehler: Letzter Wert aus dB älter als 30 Min' : 'OK';
        } else {
            $template->lastSensorChange = 'Keine Daten';
            $template->lastSensorChangeStatus = 'Fehler';
        }

        // --- Push Sync ---
        $result = false;
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
