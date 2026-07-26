<?php

declare(strict_types=1);

namespace PbdKn\ContaoContaohabBundle\Service\Sensors;

use Contao\StringUtil;
use PbdKn\ContaoContaohabBundle\Model\SensorModel;
use PbdKn\ContaoContaohabBundle\Service\LoggerService;

final class ComponentService implements SensorFetcherInterface
{
    public function __construct(private readonly LoggerService $logger)
    {
    }

    public function supports(SensorModel $sensor): bool
    {
        return strtolower((string) $sensor->sensorSource) === 'zcomponent';
    }

    public function fetch(SensorModel $sensor): ?array
    {
        return null;
    }

    public function fetchArr(array $sensors, ?string $date = null, array $fetchedValues = []): ?array
    {
        $result = [];
        foreach ($sensors as $sensor) {
            if ((string) $sensor->isComponent !== '1') {
                continue;
            }

            $variables = [];
            foreach (StringUtil::deserialize($sensor->componentSensors, true) as $mapping) {
                $alias = trim((string) ($mapping['alias'] ?? ''));
                $sensorId = trim((string) ($mapping['sensor'] ?? ''));
                if ($alias === '' || $sensorId === '' || !isset($fetchedValues[$sensorId])) {
                    continue;
                }
                $value = $fetchedValues[$sensorId]['sensorValue'] ?? null;
                if (!is_numeric($value)) {
                    continue;
                }
                $variables[$alias] = (float) $value * (float) ($mapping['factor'] ?: 1);
            }

            $value = $this->calculate((string) $sensor->componentFormula, $variables);
            if ($value === null) {
                $this->logger->Error("ComponentService: Formel für {$sensor->sensorID} konnte nicht berechnet werden.");
                continue;
            }
            $result[(string) $sensor->sensorID] = [
                'sensorID' => (string) $sensor->sensorID,
                'sensorValue' => $value,
                'sensorEinheit' => (string) $sensor->sensorEinheit,
                'sensorValueType' => (string) $sensor->sensorValueType,
                'sensorSource' => (string) $sensor->sensorSource,
            ];
        }
        return $result;
    }

    private function calculate(string $formula, array $variables): ?float
    {
        $formula = trim(html_entity_decode($formula, ENT_QUOTES | ENT_HTML5));
        if ($formula === '') {
            return null;
        }
        foreach (['sum' => 'array_sum', 'min' => 'min', 'max' => 'max'] as $name => $function) {
            $formula = preg_replace_callback('/' . $name . '\(([^()]*)\)/i', static function (array $match) use ($variables, $function): string {
                $values = array_map(static fn (string $item): float => (float) ($variables[trim($item)] ?? trim($item)), explode(',', $match[1]));
                return (string) $function($values);
            }, $formula);
        }
        $formula = preg_replace_callback('/abs\(([^()]*)\)/i', static fn (array $match): string => (string) abs((float) ($variables[trim($match[1])] ?? trim($match[1]))), $formula);
        $formula = preg_replace_callback('/round\(([^,()]+),\s*(\d+)\)/i', static fn (array $match): string => (string) round((float) ($variables[trim($match[1])] ?? trim($match[1])), (int) $match[2]), $formula);
        foreach ($variables as $name => $value) {
            $formula = preg_replace('/\b' . preg_quote((string) $name, '/') . '\b/', (string) $value, $formula);
        }
        if (!preg_match('/^[0-9eE+\-*\/()., ]+$/', $formula)) {
            return null;
        }
        try {
            $value = eval('return ' . $formula . ';');
            return is_numeric($value) ? (float) $value : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
