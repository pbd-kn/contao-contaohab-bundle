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

#[AsContentElement(CohHistoryChart::TYPE, category: 'COH')]
class CohHistoryChart extends AbstractContentElementController
{
    public const TYPE = 'coh_history_chart';

    public function __construct(
        private readonly Connection $connection,
        private readonly SyncService $syncService
    ) {}
    protected function getResponse($template, ContentModel $model, Request $request): Response
    {
        $scope = System::getContainer()->get('request_stack')?->getCurrentRequest()?->attributes?->get('_scope');
        if ('backend' === $scope) {
            $templateName = $model->coh_history_template ?: 'coh_history_template';

            $wildcard = new BackendTemplate('be_wildcard_coh');
            $wildcard->title = StringUtil::deserialize($model->headline)['value'] ?? 'Kein Titel';
            $wildcard->id = $model->id;
            $wildcard->href = 'contao?do=themes&table=tl_content&id=' . $model->id;
            $wildcardtxt = "### COH HISTORY ###<br>Template: $templateName<br>";
            $selectedSensors = StringUtil::deserialize($model->selectedSensors, true);
            foreach ($selectedSensors as $s) $wildcardtxt .= "$s ";
            $wildcard->wildcard = '<div class="text-truncate" title="'.$wildcardtxt.'">'.$wildcardtxt.'</div>';
            return new Response($wildcard->parse());
        }
 // ?? Template dynamisch wählen ??
        $templateName = $model->coh_history_template ?: 'coh_history_template';
        $template = $this->createTemplate($model, $templateName);
        $this->syncService->sync();
        // Range-Parameter pro CE
        $unitField = 'unit_chart_' . $model->id;
        $valueField = 'value_chart_' . $model->id;

        $unit = $request->query->get($unitField, 'day');
        $currentValue = $request->query->get($valueField, (new \DateTimeImmutable())->format('Y-m-d'));
        $date = new \DateTimeImmutable($currentValue);

        $start = match ($unit) {
            'day' => $date->setTime(0, 0),
            'week' => $date->modify('monday this week')->setTime(0, 0),
            'month' => $date->modify('first day of this month')->setTime(0, 0),
            'year' => $date->setDate((int) $date->format('Y'), 1, 1)->setTime(0, 0),
            default => $date->setTime(0, 0),
        };
        $end = match ($unit) {
            'day' => $start->modify('+1 day'),
            'week' => $start->modify('+1 week'),
            'month' => $start->modify('+1 month'),
            'year' => $start->modify('+1 year'),
            default => $start->modify('+1 day'),
        };

        $selectedSensors = StringUtil::deserialize($model->selectedSensors, true);
        $datasets = [];
        $axes = [];
        $timestamps = [];

        if (!empty($selectedSensors)) {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT sv.tstamp, sv.sensorID, sv.sensorValue, sv.sensorEinheit, s.sensorTitle
                     FROM tl_coh_sensorvalue sv
                     LEFT JOIN tl_coh_sensors s ON sv.sensorID = s.sensorID
                         WHERE sv.tstamp >= ? AND sv.tstamp < ? AND sv.sensorID IN (?)
                         ORDER BY sv.tstamp ASC',
                    [$start->getTimestamp(), $end->getTimestamp(), $selectedSensors],
                    [\PDO::PARAM_INT, \PDO::PARAM_INT, Connection::PARAM_STR_ARRAY]
            );


            foreach ($rows as $row) {
                $ts = date('c', $row['tstamp']);
//                $id = $row['sensorID'];
                $id = $row['sensorTitle'];
                $val = (float) $row['sensorValue'];
                $unitLabel = $row['sensorEinheit'] ?: '';
                $sensorTitle = $row['sensorTitle'];

                $axisId = 'y_' . preg_replace('/[^a-z0-9]/i', '_', $unitLabel);
                $color = $this->getSensorColor($id);
                $timestamps[] = $ts;

                $datasets[$id]['label'] ??= $id;
                $datasets[$id]['data'][] = ['x' => $ts, 'y' => $val];
                $datasets[$id]['borderColor'] ??= $color;
                $datasets[$id]['fill'] = false;
                $datasets[$id]['tension'] = 0.1;
                $datasets[$id]['yAxisID'] = $axisId;

                $axes[$axisId] ??= ['unit' => $unitLabel, 'color' => $color];
            }
        }

        $template->chartdata = (!empty($datasets) && !empty($timestamps)) ? json_encode([
            'labels' => array_values(array_unique($timestamps)),
            'datasets' => array_values($datasets),
            'axes' => $axes,
            'xUnit' => $unit,
        ], JSON_THROW_ON_ERROR) : null;

        $template->chartId = 'chart_' . $model->id;
        $template->unitField = $unitField;
        $template->valueField = $valueField;
        $template->currentUnit = $unit;
        $template->currentValue = $currentValue;
        $template->rangeLabel = match ($unit) {
            'day' => $date->format('d.m.Y'),
            'week' => 'KW ' . $date->format('W') . ' ' . $date->format('Y'),
            'month' => $date->format('F Y'),
            'year' => $date->format('Y'),
            default => $date->format('d.m.Y'),
        };

        return $template->getResponse();
    }

    private function getSensorColor(int|string $id): string
    {
        $colors = ['purple','yellow', 'green', 'blue', 'gray', 'red', '#F472B6'];
        $idNumeric = is_numeric($id) ? (int) $id : crc32($id);
        return $colors[$idNumeric % count($colors)];
    }
}
