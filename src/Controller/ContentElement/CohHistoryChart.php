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
use PbdKn\ContaoContaohabBundle\Service\RaspberrySensorApiClient;

#[AsContentElement(CohHistoryChart::TYPE, category: 'COH')]
class CohHistoryChart extends AbstractContentElementController
{
    use RaspberryApiErrorResponseTrait;

    public const TYPE = 'coh_history_chart';
    private const MAX_POINTS_PER_SENSOR = 100;

    public function __construct(
        private readonly RaspberrySensorApiClient $sensorApi
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
        $periodsField = 'periods_chart_' . $model->id;

        $allowedUnits = ['day', 'week', 'month', 'year'];

        $defaultUnit = (string) $model->coh_history_default_unit;
        if (!in_array($defaultUnit, $allowedUnits, true)) {
            $defaultUnit = 'day';
        }

        $unit = (string) $request->query->get($unitField, $defaultUnit);
        if (!in_array($unit, $allowedUnits, true)) {
            $unit = $defaultUnit;
        }

        $currentValue = (string) $request->query->get($valueField, '');
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $currentValue);

        if (!$dt || $dt->format('Y-m-d') !== $currentValue) {
            $dt = new \DateTimeImmutable('today');
            $currentValue = $dt->format('Y-m-d');
        }

        $date = $dt;
        $periodOptions = $this->createPeriodOptions($unit, $date);
        $allowedPeriods = array_column($periodOptions, null, 'value');
        $requestedPeriods = $request->query->all($periodsField);
        $requestedPeriods = is_array($requestedPeriods) ? array_map('strval', $requestedPeriods) : [];
        $selectedPeriodIds = [];
        foreach ($requestedPeriods as $requestedPeriod) {
            if (isset($allowedPeriods[$requestedPeriod])) {
                $selectedPeriodIds = [$requestedPeriod];
                break;
            }
        }
        if ([] === $selectedPeriodIds) {
            $selectedPeriodIds = [$this->defaultPeriod($unit, $date)];
        }

        $rangeStart = null;
        $rangeEnd = null;
        foreach ($selectedPeriodIds as $periodId) {
            $period = $allowedPeriods[$periodId];
            if (null === $rangeStart || $period['start'] < $rangeStart) {
                $rangeStart = $period['start'];
            }
            if (null === $rangeEnd || $period['end'] > $rangeEnd) {
                $rangeEnd = $period['end'];
            }
        }

        foreach ($periodOptions as &$option) {
            $option['checked'] = in_array($option['value'], $selectedPeriodIds, true);
        }
        unset($option);

        $selectedSensors = StringUtil::deserialize($model->selectedSensors, true);

        $datasets = [];
        $axes = [];
        $timestamps = [];

        if (!empty($selectedSensors)) {

            try {
                $rows = [];
                $pointsPerRange = max(10, (int) ceil(self::MAX_POINTS_PER_SENSOR / count($selectedPeriodIds)));
                foreach ($selectedPeriodIds as $periodId) {
                    $period = $allowedPeriods[$periodId];
                    array_push($rows, ...$this->sensorApi->fetchRange(
                        $selectedSensors,
                        $period['start']->getTimestamp(),
                        $period['end']->getTimestamp(),
                        $pointsPerRange,
                        $unit
                    ));
                }
            } catch (\Throwable $exception) {
                return $this->raspberryApiErrorResponse($exception);
            }

            // gruppieren
            $grouped = [];
            foreach ($rows as $row) {
                if (is_numeric($row['sensorValue'] ?? null) || (($row['outputMode'] ?? '') === 'counter' && array_key_exists('sensorValue', $row) && $row['sensorValue'] === null)) {
                    $grouped[$row['sensorID']][] = $row;
                }
            }

            foreach ($grouped as $sensorID => $sensorRows) {
                usort($sensorRows, static fn (array $a, array $b): int => (int) $a['tstamp'] <=> (int) $b['tstamp']);
                $mode = $sensorRows[0]['outputMode'] ?? 'absolute';
                if ('day' !== $unit && 'counter' !== $mode) {
                    $sensorRows = $this->aggregateRowsForBars($sensorRows, $unit);
                }
                $firstRow = reset($sensorRows);
                $mode = $firstRow['outputMode'] ?? 'absolute';
                $isBinary = 'counter' !== $mode && (
                    strcasecmp((string) ($firstRow['sensorLokalId'] ?? ''), 'Brennerfreigabe') === 0
                    || in_array(strtolower((string) ($firstRow['sensorValueType'] ?? '')), ['bool', 'boolean'], true)
                );
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
                foreach ($sensorRows as $row) {

                        $ts = date('c', (int) $row['tstamp']);

                        $val = is_numeric($row['sensorValue'])
                            ? round((float)$row['sensorValue'], 2)
                            : $row['sensorValue'];

                        $timestamps[] = $ts;

                        $datasets[$sensorTitle]['label'] ??= $sensorTitle;
                        $datasets[$sensorTitle]['data'][] = ['x' => $ts, 'y' => $val];
                        $datasets[$sensorTitle]['borderColor'] ??= $color;
                        $datasets[$sensorTitle]['backgroundColor'] ??= $color;
                        $datasets[$sensorTitle]['fill'] = false;
                        $datasets[$sensorTitle]['tension'] = 'counter' === $mode ? 0 : 0.1;
                        $datasets[$sensorTitle]['stepped'] = $isBinary && 'day' === $unit;
                        $datasets[$sensorTitle]['pointRadius'] = 0.75;
                        $datasets[$sensorTitle]['pointBorderWidth'] = 1;
                        $datasets[$sensorTitle]['pointHoverRadius'] = 4;
                        $datasets[$sensorTitle]['maxBarThickness'] = 42;
                        $datasets[$sensorTitle]['yAxisID'] = $axisId;
                }

                $axes[$axisId] ??= [
                    'unit' => $unitLabel,
                    'color' => $color,
                    'beginAtZero' => 'counter' === $mode,
                    'binary' => $isBinary,
                    'title' => $isBinary ? $sensorTitle : $unitLabel,
                ];
            }
        }

        $template->chartdata = (!empty($datasets) && !empty($timestamps))
            ? json_encode([
                'labels' => array_values(array_unique($timestamps)),
                'datasets' => array_values($datasets),
                'axes' => $axes,
                'xUnit' => $unit,
                'xDisplayUnit' => 'day' === $unit && 1 === count($selectedPeriodIds) ? 'hour' : ('year' === $unit ? 'month' : 'day'),
                'rangeMin' => $rangeStart?->format(DATE_ATOM),
                'rangeMax' => $rangeEnd?->format(DATE_ATOM),
            ], JSON_THROW_ON_ERROR)
            : null;

        $template->chartId = 'chart_' . $model->id;
        $template->unitField = $unitField;
        $template->valueField = $valueField;
        $template->periodsField = $periodsField;
        $template->currentUnit = $unit;
        $template->currentValue = $currentValue;
        $template->periodOptions = $periodOptions;
        $template->previousValue = $this->moveAnchor($unit, $date, -1)->format('Y-m-d');
        $template->nextValue = $this->moveAnchor($unit, $date, 1)->format('Y-m-d');

        $template->rangeLabel = match ($unit) {
            'day' => 'Woche ' . $date->format('W / Y'),
            'week' => '4 Wochen bis KW ' . $date->format('W / Y'),
            'month' => 'Monate ' . $date->format('Y'),
            'year' => 'Jahre bis ' . $date->format('Y'),
        };

        return $template->getResponse();
    }

    /**
     * Bildet fuer Balkendiagramme einen Mittelwert pro sichtbarer X-Achsen-Einheit:
     * Woche/Monat je Kalendertag, Jahr je Kalendermonat.
     */
    private function aggregateRowsForBars(array $rows, string $unit): array
    {
        $buckets = [];

        foreach ($rows as $row) {
            $timestamp = (int) ($row['tstamp'] ?? 0);
            $value = $row['sensorValue'] ?? null;
            if ($timestamp <= 0 || !is_numeric($value)) {
                continue;
            }

            $date = (new \DateTimeImmutable('@'.$timestamp))->setTimezone(new \DateTimeZone(date_default_timezone_get()));
            $key = 'year' === $unit ? $date->format('Y-m') : $date->format('Y-m-d');
            $bucketTimestamp = 'year' === $unit
                ? $date->modify('first day of this month')->setTime(12, 0)->getTimestamp()
                : $date->setTime(12, 0)->getTimestamp();

            $buckets[$key] ??= [
                'row' => $row,
                'sum' => 0.0,
                'count' => 0,
                'tstamp' => $bucketTimestamp,
            ];
            $buckets[$key]['sum'] += (float) $value;
            ++$buckets[$key]['count'];
        }

        ksort($buckets, SORT_STRING);

        return array_map(static function (array $bucket): array {
            $row = $bucket['row'];
            $row['tstamp'] = $bucket['tstamp'];
            $row['sensorValue'] = round($bucket['sum'] / $bucket['count'], 2);

            return $row;
        }, array_values($buckets));
    }

    private function createPeriodOptions(string $unit, \DateTimeImmutable $anchor): array
    {
        if ('day' === $unit) {
            $monday = $anchor->modify('monday this week')->setTime(0, 0);
            $weekdays = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
            $options = [];
            for ($offset = 0; $offset < 7; ++$offset) {
                $start = $monday->modify('+' . $offset . ' days');
                $options[] = [
                    'value' => $start->format('Y-m-d'),
                    'label' => $weekdays[$offset] . ', ' . $start->format('d.m.Y'),
                    'start' => $start,
                    'end' => $start->modify('+1 day'),
                ];
            }

            return $options;
        }

        if ('week' === $unit) {
            $lastWeek = $anchor->modify('monday this week')->setTime(0, 0);
            $options = [];
            for ($offset = 3; $offset >= 0; --$offset) {
                $start = $lastWeek->modify('-' . $offset . ' weeks');
                $options[] = [
                    'value' => $start->format('o-\\WW'),
                    'label' => 'KW ' . $start->format('W/Y') . ': ' . $start->format('d.m.Y') . ' – ' . $start->modify('+6 days')->format('d.m.Y'),
                    'start' => $start,
                    'end' => $start->modify('+1 week'),
                ];
            }

            return $options;
        }

        if ('month' === $unit) {
            $monthNames = [
                1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
                'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember',
            ];
            $options = [];
            $year = (int) $anchor->format('Y');
            for ($month = 1; $month <= 12; ++$month) {
                $start = $anchor->setDate($year, $month, 1)->setTime(0, 0);
                $options[] = [
                    'value' => $start->format('Y-m'),
                    'label' => $monthNames[$month] . ' ' . $year . ': ' . $start->format('d.m.Y') . ' – ' . $start->modify('last day of this month')->format('d.m.Y'),
                    'start' => $start,
                    'end' => $start->modify('+1 month'),
                ];
            }

            return $options;
        }

        $options = [];
        $anchorYear = (int) $anchor->format('Y');
        for ($year = $anchorYear - 5; $year <= $anchorYear; ++$year) {
            $start = $anchor->setDate($year, 1, 1)->setTime(0, 0);
            $options[] = [
                'value' => (string) $year,
                'label' => $year . ': 01.01.' . $year . ' – 31.12.' . $year,
                'start' => $start,
                'end' => $start->modify('+1 year'),
            ];
        }

        return $options;
    }

    private function defaultPeriod(string $unit, \DateTimeImmutable $anchor): string
    {
        return match ($unit) {
            'day' => $anchor->format('Y-m-d'),
            'week' => $anchor->modify('monday this week')->format('o-\\WW'),
            'month' => $anchor->format('Y-m'),
            'year' => $anchor->format('Y'),
        };
    }

    private function moveAnchor(string $unit, \DateTimeImmutable $anchor, int $direction): \DateTimeImmutable
    {
        $amount = match ($unit) {
            'day' => 1,
            'week' => 4,
            'month' => 1,
            'year' => 6,
        };

        $interval = in_array($unit, ['month', 'year'], true) ? 'years' : 'weeks';

        return $anchor->modify(sprintf('%+d %s', $direction * $amount, $interval));
    }

    private function getSensorColor(int|string $id): string
    {
        $colors = ['#000000','#0033A0', '#E69F00', '#00723F', '#B00020', '#6A1B9A'];
        $idNumeric = is_numeric($id) ? (int) $id : crc32($id);
        return $colors[$idNumeric % count($colors)];
    }
}
