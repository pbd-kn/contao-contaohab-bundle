<?php

declare(strict_types=1);

namespace PbdKn\ContaoContaohabBundle\Service;

use Contao\Database;
use Contao\StringUtil;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class CohFormularService
{
    private ExpressionLanguage $el;

    /**
     * Mapping alias => sensorID
     */
    private array $aliasToSensorId = [];

    public function __construct(
        private readonly LoggerService $loggerService
    ) {
        $this->el = new ExpressionLanguage();

        // --------------------------
        // Standard Mathe-Funktionen
        // --------------------------
        $this->el->register('max', fn() => '', fn($values, $a, $b) => max((float)$a, (float)$b));
        $this->el->register('min', fn() => '', fn($values, $a, $b) => min((float)$a, (float)$b));
        $this->el->register('abs', fn() => '', fn($values, $x) => abs((float)$x));
        $this->el->register('round', fn() => '', fn($values, $x, $p = 0) => round((float)$x, (int)$p));

        // --------------------------
        // DAILY(alias)
        // --------------------------
        $this->el->register(
            'daily',
            fn() => '',
            function ($values, $x) {

                // Alias ermitteln
                $alias = null;
                foreach ($values as $k => $v) {
                    if ($v === $x) {
                        $alias = $k;
                        break;
                    }
                }

                if ($alias === null || !isset($this->aliasToSensorId[$alias])) {
                    return 0.0;
                }

                $sensorId = $this->aliasToSensorId[$alias];

                return $this->getDailyValue($sensorId);
            }
        );
    }

    /**
     * Hauptfunktion: berechnet Formel
     */
    public function calculate(
        ?string $serializedComponents,
        string $formula,
        bool $failHard = false
    ): float {
        try {

            $components = StringUtil::deserialize($serializedComponents, true);

            $vars = $this->buildVariables($components);

            $this->assertFormulaAllowed($formula);

            $result = $this->el->evaluate($formula, $vars);

            if (!is_numeric($result)) {
                throw new \RuntimeException('Formel-Ergebnis nicht numerisch.');
            }

            $value = (float) $result;

            if (is_infinite($value) || is_nan($value)) {
                throw new \RuntimeException('Ungültiges Ergebnis (INF/NAN).');
            }

            return $value;

        } catch (\Throwable $e) {

            $this->loggerService->debugMe(
                'CohFormularService calculate(): '
                . $e->getMessage()
                . ' | Formel=' . $formula
            );

            if ($failHard) {
                throw $e;
            }

            return 0.0;
        }
    }

    /**
     * Baut Alias => Wert Mapping
     */
    private function buildVariables(array $components): array
    {
        $vars = [];
        $this->aliasToSensorId = [];

        foreach ($components as $row) {

            $alias    = (string)($row['alias'] ?? '');
            $sensorId = (string)($row['sensor'] ?? '');

            if ($alias === '' || $sensorId === '') {
                continue;
            }

            if (isset($vars[$alias])) {
                throw new \RuntimeException('Alias doppelt vergeben: ' . $alias);
            }

            $factor = 1.0;
            if (isset($row['factor']) && $row['factor'] !== '') {
                $factor = (float)$row['factor'];
            }

            $value = $this->getLatestSensorValue($sensorId);

            $vars[$alias] = $value * $factor;

            $this->aliasToSensorId[$alias] = $sensorId;
        }

        return $vars;
    }

    /**
     * Letzten Messwert holen
     */
    public function getLatestSensorValue(string $sensorId): float
    {
        $db = Database::getInstance();

        $obj = $db->prepare("
                SELECT sensorValue
                FROM tl_coh_sensorvalue
                WHERE sensorID=?
                ORDER BY tstamp DESC
                LIMIT 1
            ")
            ->execute($sensorId);

        if (!$obj->numRows) {
            return 0.0;
        }

        return $this->parseNumeric($obj->sensorValue);
    }

    /**
     * Tageswert berechnen (Reset-Schutz wie in deinem Controller)
     */
    private function getDailyValue(string $sensorId): float
    {
        $db = Database::getInstance();

        $startOfDay = strtotime('today midnight');

        // erster Wert des Tages
        $first = $db->prepare("
                SELECT sensorValue
                FROM tl_coh_sensorvalue
                WHERE sensorID=? AND tstamp>=?
                ORDER BY tstamp ASC
                LIMIT 1
            ")
            ->execute($sensorId, $startOfDay);

        if (!$first->numRows) {
            return 0.0;
        }

        $firstValue = $this->parseNumeric($first->sensorValue);

        // letzter Wert
        $last = $db->prepare("
                SELECT sensorValue
                FROM tl_coh_sensorvalue
                WHERE sensorID=?
                ORDER BY tstamp DESC
                LIMIT 1
            ")
            ->execute($sensorId);

        if (!$last->numRows) {
            return 0.0;
        }

        $lastValue = $this->parseNumeric($last->sensorValue);

        // Reset-Schutz
        return $lastValue >= $firstValue
            ? round($lastValue - $firstValue, 2)
            : round($lastValue, 2);
    }

    /**
     * Numeric Parser (Komma ? Punkt)
     */
    private function parseNumeric(string $raw): float
    {
        $raw = trim($raw);
        $raw = str_replace([' ', "\t", "\n", "\r"], '', $raw);
        $raw = str_replace(',', '.', $raw);

        if (!is_numeric($raw)) {
            throw new \RuntimeException('Nicht numerischer Sensorwert: ' . $raw);
        }

        return (float)$raw;
    }

    /**
     * Sicherheitsprüfung der Formel
     */
    private function assertFormulaAllowed(string $formula): void
    {
        $formula = trim($formula);

        if ($formula === '') {
            throw new \RuntimeException('Formel ist leer.');
        }

        if (!preg_match('/^[0-9a-zA-Z_\s\.\+\-\*\/\(\),]+$/', $formula)) {
            throw new \RuntimeException('Formel enthält ungültige Zeichen.');
        }
    }
}