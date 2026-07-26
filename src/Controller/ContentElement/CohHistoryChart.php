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
use PbdKn\ContaoContaohabBundle\Service\Sensors\SensorManager;

#[AsContentElement(CohHistoryChart::TYPE, category: 'COH')]
class CohHistoryChart extends AbstractContentElementController
{
    public const TYPE = 'coh_history_chart';

    public function __construct(
        private readonly SensorManager $sensorManager
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

        $templateName = $model->coh_history_template ?: 'coh_history_template';
        $template = $this->createTemplate($model, $templateName);

        $unitField  = 'unit_chart_' . $model->id;
        $valueField = 'value_chart_' . $model->id;

        // Die Ampere.IQ-History-Endpunkte werden derzeit tageweise abgefragt.
        $allowedUnits = ['day'];

        $unit = (string) $request->query->get($unitField, 'day');
        if (!in_array($unit, $allowedUnits, true)) {
            $unit = 'day';
        }

        $currentValue = (string) $request->query->get($valueField, '');
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $currentValue);

        if (!$dt || $dt->format('Y-m-d') !== $currentValue) {
            $dt = new \DateTimeImmutable('today');
            $currentValue = $dt->format('Y-m-d');
        }

        $date = $dt;

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

            $rows = [];
            foreach ($this->sensorManager->fetchAll($selectedSensors, $currentValue) as $row) {
                $row['sensorTitle'] ??= $row['sensorID'] ?? '';
                $row['outputMode'] ??= 'absolute';
                if (!empty($row['historyPoints'])) {
                    foreach ($row['historyPoints'] as $point) {
                        $historyRow = $row;
                        $historyRow['tstamp'] = (new \DateTimeImmutable($point['x']))->getTimestamp();
                        $historyRow['sensorValue'] = $point['y'];
                        unset($historyRow['historyPoints']);
                        $rows[] = $historyRow;
                    }
                    continue;
                }
                $row['tstamp'] = (new \DateTimeImmutable($currentValue))->getTimestamp();
                $rows[] = $row;
            }

            // gruppieren
            $grouped = [];
            foreach ($rows as $row) {
                $grouped[$row['sensorID']][] = $row;
            }

            foreach ($grouped as $sensorID => $sensorRows) {
                $firstRow = reset($sensorRows);
                $mode = $firstRow['outputMode'] ?? 'absolute';
                $sensorTitle = !empty($firstRow['sensorTitle']) ? $firstRow['sensorTitle'] : $sensorID;
                // ? EINHEIT AUS ZEITRAUM (von hinten suchen)
                // Einheit suchen (von hinten)
                $unitLabel = '';
                for ($i = count($sensorRows) - 1; $i >= 0; $i--) {
                    $u = trim((string)($sensorRows[$i]['sensorEinheit'] ?? ''));
                    if ($u !== '') {
                        $unitLabel = $u;
                        break;
                    }
                }
                // ?? DEFAULT wenn nichts gefunden wurde
                //if ($unitLabel === '') { $unitLabel = 'raw'; }
                // ?? IMMER eindeutige Achse pro Sensor
                $axisId = 'y_' . preg_replace('/[^a-z0-9]/i', '_', strtolower($sensorID));
                $color = $this->getSensorColor($sensorTitle);
                if ($mode === 'daily') {
                    $rowsArray = array_values($sensorRows);
                    if (empty($rowsArray) || !is_numeric($rowsArray[0]['sensorValue'])) continue;
                    $firstValue = (float) $rowsArray[0]['sensorValue'];
                    foreach ($rowsArray as $row) {
                        if (!is_numeric($row['sensorValue'])) continue;
                        $ts = date('c', (int) $row['tstamp']);
                        $current = (float) $row['sensorValue'];
                        $val = $current >= $firstValue ? $current - $firstValue : $current;
                        $val = round($val, 2);
                        $timestamps[] = $ts;
                        $datasets[$sensorTitle]['label'] ??= $sensorTitle;
                        $datasets[$sensorTitle]['data'][] = ['x' => $ts, 'y' => $val];
                        $datasets[$sensorTitle]['borderColor'] ??= $color;
                        $datasets[$sensorTitle]['fill'] = false;
                        $datasets[$sensorTitle]['tension'] = 0.1;
                        $datasets[$sensorTitle]['yAxisID'] = $axisId;
                    }
                } else {
                    foreach ($sensorRows as $row) {

                        $ts = date('c', (int) $row['tstamp']);

                        $val = is_numeric($row['sensorValue'])
                            ? round((float)$row['sensorValue'], 2)
                            : $row['sensorValue'];

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
