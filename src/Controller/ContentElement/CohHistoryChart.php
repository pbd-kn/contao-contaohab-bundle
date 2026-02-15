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
        foreach ($selectedSensors as $s) {
            $wildcardtxt .= "$s ";
        }

        $wildcard->wildcard = '<div class="text-truncate" title="'.$wildcardtxt.'">'.$wildcardtxt.'</div>';
        return new Response($wildcard->parse());
    }

    // Template wählen
    $templateName = $model->coh_history_template ?: 'coh_history_template';
    $template = $this->createTemplate($model, $templateName);

    $this->syncService->sync();

    // Range-Parameter
    $unitField = 'unit_chart_' . $model->id;
    $valueField = 'value_chart_' . $model->id;

    $unit = $request->query->get($unitField, 'day');
    $currentValue = $request->query->get($valueField, (new \DateTimeImmutable())->format('Y-m-d'));
    $date = new \DateTimeImmutable($currentValue);

    $start = match ($unit) {
        'day'   => $date->setTime(0, 0),
        'week'  => $date->modify('monday this week')->setTime(0, 0),
        'month' => $date->modify('first day of this month')->setTime(0, 0),
        'year'  => $date->setDate((int)$date->format('Y'), 1, 1)->setTime(0, 0),
        default => $date->setTime(0, 0),
    };

    $end = match ($unit) {
        'day'   => $start->modify('+1 day'),
        'week'  => $start->modify('+1 week'),
        'month' => $start->modify('+1 month'),
        'year'  => $start->modify('+1 year'),
        default => $start->modify('+1 day'),
    };

    $selectedSensors = StringUtil::deserialize($model->selectedSensors, true);

    $datasets = [];
    $axes = [];
    $timestamps = [];

    if (!empty($selectedSensors)) {

        $rows = $this->connection->fetchAllAssociative(
            'SELECT sv.tstamp, sv.sensorID, sv.sensorValue, sv.sensorEinheit,
                    s.sensorTitle, s.outputMode
             FROM tl_coh_sensorvalue sv
             LEFT JOIN tl_coh_sensors s ON sv.sensorID = s.sensorID
             WHERE sv.tstamp >= ? AND sv.tstamp < ?
             AND sv.sensorID IN (?)
             ORDER BY sv.sensorID ASC, sv.tstamp ASC',
            [$start->getTimestamp(), $end->getTimestamp(), $selectedSensors],
            [\PDO::PARAM_INT, \PDO::PARAM_INT, \Doctrine\DBAL\Connection::PARAM_STR_ARRAY]
        );

        // Nach Sensor gruppieren
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['sensorID']][] = $row;
        }
        foreach ($grouped as $sensorID => $sensorRows) {
            $firstRow = reset($sensorRows);
            $mode = $firstRow['outputMode'] ?? 'absolute';
            $sensorTitle = $firstRow['sensorTitle'] ?: $sensorID;
            $unitLabel = $firstRow['sensorEinheit'] ?: '';
            $axisId = 'y_' . preg_replace('/[^a-z0-9]/i', '_', $unitLabel);
            $color = $this->getSensorColor($sensorTitle);
            if ($mode === 'daily') {
                $rowsArray = array_values($sensorRows);
                // kein Wert oder erster Wert nicht numerisch ? abbrechen
                if (empty($rowsArray) || !is_numeric($rowsArray[0]['sensorValue'])) { continue; }
                // erster Zählerstand des Zeitraums
                $firstValue = (float) $rowsArray[0]['sensorValue'];
                foreach ($rowsArray as $row) {
                    $ts = date('c', (int) $row['tstamp']);
                    if (!is_numeric($row['sensorValue'])) { continue;  } // daily nur für numerische Sensoren 
                    $currentSensorValue = (float) $row['sensorValue'];
                    // Reset-Schutz
                    $val = $currentSensorValue >= $firstValue ? $currentSensorValue - $firstValue : $currentSensorValue;
                    $val = round($val, 2);
                    $timestamps[] = $ts;
                    // exakt gleiche Struktur wie im ALL-Block
                    $datasets[$sensorTitle]['label'] ??= $sensorTitle;
                    $datasets[$sensorTitle]['data'][] = [
                        'x' => $ts,
                        'y' => $val
                    ];
                    $datasets[$sensorTitle]['borderColor'] ??= $color;
                    $datasets[$sensorTitle]['fill'] = false;
                    $datasets[$sensorTitle]['tension'] = 0.1;
                    $datasets[$sensorTitle]['yAxisID'] = $axisId;
                }
            } else {
                foreach ($sensorRows as $row) {
                    $ts = date('c', $row['tstamp']);
                    if (is_numeric($row['sensorValue'])) { $val = round((float)$row['sensorValue'], 2);
                    } else { $val = $row['sensorValue']; }
                    $timestamps[] = $ts;
                    $datasets[$sensorTitle]['label'] ??= $sensorTitle;
                    $datasets[$sensorTitle]['data'][] = ['x' => $ts, 'y' => $val];
                    $datasets[$sensorTitle]['borderColor'] ??= $color;
                    $datasets[$sensorTitle]['fill'] = false;
                    $datasets[$sensorTitle]['tension'] = 0.1;
                    $datasets[$sensorTitle]['yAxisID'] = $axisId;
                }
            }
            $axes[$axisId] ??= [
                'unit' => $unitLabel,
                'color' => $color
            ];
        }
    }
    $template->chartdata = (!empty($datasets) && !empty($timestamps))
        ? json_encode([
            'labels' => array_values(array_unique($timestamps)),
            'datasets' => array_values($datasets),
            'axes' => $axes,
            'xUnit' => $unit,
        ], JSON_THROW_ON_ERROR)
        : null;

    $template->chartId = 'chart_' . $model->id;
    $template->unitField = $unitField;
    $template->valueField = $valueField;
    $template->currentUnit = $unit;
    $template->currentValue = $currentValue;

    $template->rangeLabel = match ($unit) {
        'day'   => $date->format('d.m.Y'),
        'week'  => 'KW ' . $date->format('W') . ' ' . $date->format('Y'),
        'month' => $date->format('F Y'),
        'year'  => $date->format('Y'),
        default => $date->format('d.m.Y'),
    };

    return $template->getResponse();
}

    private function getSensorColor(int|string $id): string
    {
        $colors = ['#000000','#0033A0', '#E69F00', '#00723F', '#B00020', '#6A1B9A'];
        $idNumeric = is_numeric($id) ? (int) $id : crc32($id);
        return $colors[$idNumeric % count($colors)];
    }
}
