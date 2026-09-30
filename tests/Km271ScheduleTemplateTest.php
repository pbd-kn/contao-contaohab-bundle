<?php
declare(strict_types=1);

/** Standalone template smoke test. All readings are fixtures; no hardware/API access. */
#[AllowDynamicProperties]
final class Km271TemplateFixture
{
    public function __construct(array $data) { foreach ($data as $key => $value) $this->$key = $value; }
    public function getData(): array { return get_object_vars($this); }
    public function insert(string $name, array $data): void { echo (new self($data))->render($name); }
    public function render(string $name): string
    {
        ob_start();
        include dirname(__DIR__) . '/contao/templates/frontend/' . $name . '.html5';
        return ob_get_clean();
    }
}

set_error_handler(static function (int $severity, string $message, string $file, int $line): never { throw new ErrorException($message, 0, $severity, $file, $line); });
$programs = ['Eigen', 'Familie', 'Früh', 'Spät', 'Vormittag', 'Nachmittag', 'Mittag', 'Single', 'Senior'];
$intervals = array_fill(0, 21, null);
for ($i = 0; $i < 7; ++$i) $intervals[$i] = ['onDay' => $i, 'onTime' => '06:30', 'offDay' => $i, 'offTime' => '22:00'];
$data = [
    'id' => 42, 'headline' => 'Buderus Heizung', 'hl' => 'h2', 'sensorError' => null,
    'hasActiveFault' => false, 'faultValues' => [], 'valueDescriptions' => [],
    'allSensors' => ['HK1_Heizprogramm' => ['Wert' => 'Familie', 'Datum' => '2026-09-29T18:00:00+02:00']],
    'writeEnabled' => true, 'writeCatalog' => [], 'executableIds' => ['HK1_Heizprogramm', 'HK2_Heizprogramm'],
    'writeSelected' => '', 'writeInput' => '', 'writePreview' => null, 'writeResult' => null, 'writeError' => null,
    'requestToken' => 'fixture-only', 'pollTimeMinutes' => 15, 'scheduleSupported' => true,
    'scheduleState' => ['circuit' => 1, 'base' => ['program' => 'Familie', 'readAt' => '2026-09-29T18:00:00+02:00', 'intervals' => $intervals], 'intervals' => $intervals],
];
foreach ([1, 2] as $hc) $data['writeCatalog']['HK' . $hc . '_Heizprogramm'] = ['Bezeichnung' => 'HK' . $hc . ' Heizprogramm', 'Auswahl' => $programs];
foreach (['ce_coh_buderus_km271_chart_display', 'ce_coh_buderus_km271_chart_display_all'] as $template) {
    $html = (new Km271TemplateFixture($data))->render($template);
    if (substr_count($html, 'data-interval-enabled') !== 23) throw new RuntimeException('Intervallfelder fehlen.');
    if (!str_contains($html, 'HK2: Programm auswählen')) throw new RuntimeException('HK2 fehlt.');
}
$data['scheduleState']['error'] = '<script>alert(1)</script>';
$escaped = (new Km271TemplateFixture($data))->render('coh_km271_schedule');
if (str_contains($escaped, '<script>alert(1)</script>')) throw new RuntimeException('Unescaped error.');
unset($data['scheduleState']['error']);
$data['scheduleState']['plan'] = ['intervals' => $intervals, 'changedBlocks' => 5, 'activate' => true, 'base' => ['program' => 'Familie']];
$data['scheduleState']['nonce'] = 'fixture-preview';
$html = (new Km271TemplateFixture($data))->render('ce_coh_buderus_km271_chart_display');
if (!str_contains($html, 'fixture-preview')) throw new RuntimeException('Bestätigung fehlt.');
if (isset($argv[1])) {
    $css = file_get_contents(dirname(__DIR__) . '/public/css/coh_aktuell_panel.css');
    file_put_contents($argv[1], '<!doctype html><html lang="de"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>KM271 Editor – Testdaten</title><style>body{font-family:Arial,sans-serif;margin:24px auto;max-width:1100px;background:#edf6ff;padding:16px} ' . $css . '</style>' . $html . '</html>');
}
echo "OK: Beide KM271-Templates, 21 Intervalle, HK2, Vorschau und HTML-Escaping.\n";
