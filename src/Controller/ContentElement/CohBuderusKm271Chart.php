<?php

declare(strict_types=1);

namespace PbdKn\ContaoContaohabBundle\Controller\ContentElement;

use Contao\BackendTemplate;
use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\StringUtil;
use Contao\System;
use Doctrine\DBAL\Connection;
use PbdKn\ContaoContaohabBundle\Service\Km271WriteApiClient;
use PbdKn\ContaoContaohabBundle\Service\LoggerService;
use PbdKn\ContaoContaohabBundle\Service\RaspberrySensorApiClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

#[AsContentElement(self::TYPE, category: 'COH', template: 'ce_coh_buderus_km271_chart_display')]
final class CohBuderusKm271Chart extends AbstractContentElementController
{
    use RaspberryApiErrorResponseTrait;

    public const TYPE = 'ce_coh_buderus_km271_chart';

    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerService $logger,
        private readonly RaspberrySensorApiClient $sensorApi,
        private readonly Km271WriteApiClient $km271WriteApi,
    ) {
    }

    protected function getResponse($template, ContentModel $model, Request $request): Response
    {
        $scope = System::getContainer()->get('request_stack')?->getCurrentRequest()?->attributes?->get('_scope');
        if ($scope === 'backend') {
            $wildcard = new BackendTemplate('be_wildcard_coh');
            $wildcard->title = StringUtil::deserialize($model->headline)['value'] ?? 'Buderus KM271';
            $wildcard->id = $model->id;
            $wildcard->href = 'contao?do=themes&table=tl_content&id=' . $model->id;
            $wildcard->wildcard = '<div class="text-truncate">### BUDERUS KM271 ###</div>';
            return new Response($wildcard->parse());
        }

        $this->addCssOnce('bundles/pbdkncontaocontaohab/css/coh_aktuell_panel.css');
        $template = $this->createTemplate($model, 'ce_coh_buderus_km271_chart_display');
        $headline = StringUtil::deserialize($model->headline, true);
        $template->headline = (string) ($headline['value'] ?? '');
        $template->hl = (string) ($headline['unit'] ?? 'h2');
        $selectedSensors = StringUtil::deserialize($model->selectedSensors, true);
        $values = [];
        $sensorError = null;
        if ($selectedSensors !== []) {
            try {
                $values = $this->findKm271Values($this->sensorApi->fetchLatest($selectedSensors));
            } catch (\Throwable $exception) {
                $sensorError = $exception->getMessage();
            }
        }

        [$hk1, $general, $faults] = $this->groupValues($values);
        $template->hk1Values = $hk1;
        $template->generalValues = $general;
        $template->faultValues = $faults;
        $template->valueDescriptions = $this->valueDescriptions();
        $template->hasActiveFault = $this->hasActiveFault($faults);
        $template->sensorError = $sensorError;
        $template->pollTimeMinutes = $this->pollTimeMinutes();
        $template->requestToken = System::getContainer()->get('contao.csrf.token_manager')->getDefaultTokenValue();
        $template->writeEnabled = (bool) $model->coh_km271_write_preview;
        $template->writeCatalog = [];
        $template->executableIds = [];
        $template->writeSelected = '';
        $template->writeInput = '';
        $template->writePreview = null;
        $template->writeResult = null;
        $template->writeError = null;

        if ($template->writeEnabled) {
            $writeResponse = $this->prepareWriteDialog($template, $model, $request, $values);
            if ($writeResponse instanceof Response) {
                return $writeResponse;
            }
        }

        return $template->getResponse();
    }

    private function prepareWriteDialog(object $template, ContentModel $model, Request $request, array $values): ?Response
    {
        try {
            $configuration = $this->km271WriteApi->configuration();
            $template->writeCatalog = $configuration['catalog'];
            $template->executableIds = $configuration['writeEnabled'] ? $configuration['executable'] : [];

            if (!$request->isMethod('POST')) {
                $this->restoreWriteDialogState($template, $model, $request);
                return null;
            }

            $selected = trim((string) $request->request->get('km271_lokale_id', ''));
            if ($selected !== '' && !isset($configuration['catalog'][$selected])) {
                throw new \InvalidArgumentException('Diese KM271-Einstellung ist nicht freigegeben.');
            }
            $template->writeSelected = $selected;

            if (!$request->isMethod('POST') || (string) $request->request->get('km271_element_id') !== (string) $model->id) {
                return null;
            }

            $action = (string) $request->request->get('km271_action');
            $value = $request->request->all()['km271_wert'] ?? null;
            if (!in_array($action, ['preview', 'execute'], true)) {
                return null;
            }
            if ($selected === '' || $value === null || (is_string($value) && trim($value) === '')) {
                throw new \InvalidArgumentException('Bitte eine Einstellung und einen neuen Wert angeben.');
            }
            $template->writeInput = is_scalar($value) ? (string) $value : '';
            $template->writePreview = $this->km271WriteApi->preview($selected, $value);

            if ($action !== 'execute') {
                return $this->redirectAfterWritePost($template, $model, $request);
            }
            if (!in_array($selected, $template->executableIds, true)) {
                throw new \InvalidArgumentException('Diese Einstellung ist auf dem Raspberry nicht zum Schreiben freigegeben.');
            }
            if ((string) $request->request->get('km271_confirm') !== '1') {
                throw new \InvalidArgumentException('Der Schreibauftrag wurde nicht ausdrücklich bestätigt.');
            }
            $template->writeResult = $this->km271WriteApi->execute($selected, $value, $request->getClientIp());
            $this->logger->Info(sprintf(
                'KM271-Schreibauftrag ausgeführt: %s = %s (Client %s)',
                $selected,
                is_scalar($value) ? (string) $value : '?',
                $request->getClientIp() ?? 'unbekannt',
            ));
            return $this->redirectAfterWritePost($template, $model, $request);
        } catch (\Throwable $exception) {
            $template->writeError = $exception->getMessage();
            $this->logger->Error('KM271-Schreibdialog: ' . $exception->getMessage());
            if ($request->isMethod('POST') && (string) $request->request->get('km271_element_id') === (string) $model->id) {
                return $this->redirectAfterWritePost($template, $model, $request);
            }
        }

        return null;
    }

    private function redirectAfterWritePost(object $template, ContentModel $model, Request $request): Response
    {
        if ($request->hasSession()) {
            $request->getSession()->set($this->writeDialogSessionKey($model), [
                'selected' => (string) ($template->writeSelected ?? ''),
                'input' => (string) ($template->writeInput ?? ''),
                'preview' => is_array($template->writePreview ?? null) ? $template->writePreview : null,
                'result' => is_array($template->writeResult ?? null) ? $template->writeResult : null,
                'error' => is_string($template->writeError ?? null) ? $template->writeError : null,
            ]);
        }

        return new RedirectResponse($request->getUri(), Response::HTTP_SEE_OTHER);
    }

    private function restoreWriteDialogState(object $template, ContentModel $model, Request $request): void
    {
        if (!$request->hasSession()) {
            return;
        }
        $state = $request->getSession()->remove($this->writeDialogSessionKey($model));
        if (!is_array($state)) {
            return;
        }
        $template->writeSelected = (string) ($state['selected'] ?? '');
        $template->writeInput = (string) ($state['input'] ?? '');
        $template->writePreview = is_array($state['preview'] ?? null) ? $state['preview'] : null;
        $template->writeResult = is_array($state['result'] ?? null) ? $state['result'] : null;
        $template->writeError = is_string($state['error'] ?? null) ? $state['error'] : null;
    }

    private function writeDialogSessionKey(ContentModel $model): string
    {
        return 'coh.km271.write_dialog.' . $model->id;
    }

    private function findKm271Values(array $rows): array
    {
        foreach ($rows as $row) {
            $value = $row['sensorValue'] ?? null;
            if (is_array($value) && is_array($value['KM271'] ?? null)) {
                return $value['KM271'];
            }
        }
        return [];
    }

    /** @return array{array<string, array<string, mixed>>, array<string, array<string, mixed>>, array<string, array<string, mixed>>} */
    private function groupValues(array $values): array
    {
        $hk1 = [];
        $general = [];
        $faults = [];
        foreach ($values as $key => $value) {
            if (!is_array($value) || str_starts_with((string) $key, 'HK2_')) {
                continue;
            }
            if ($key === 'Brenner_Einschalttemperatur') {
                $value['Name'] = 'Aktuelle Brenner-Einschaltschwelle';
            } elseif ($key === 'Brenner_Ausschalttemperatur') {
                $value['Name'] = 'Aktuelle Brenner-Ausschaltschwelle';
            }
            if ($this->isFaultKey((string) $key)) {
                $faults[(string) $key] = $value;
            } elseif (str_starts_with((string) $key, 'HK1_') || $key === 'Frostschutz_ab') {
                $hk1[(string) $key] = $value;
            } else {
                $general[(string) $key] = $value;
            }
        }
        $hk1 = $this->sortValues($hk1, [
            'HK1_Sommergrenze', 'HK1_Betriebsart', 'HK1_Tagtemperatur', 'HK1_Nachttemperatur',
            'HK1_Vorlauf_Isttemperatur', 'HK1_Vorlauf_Solltemperatur', 'HK1_Raum_Isttemperatur',
            'HK1_Raum_Solltemperatur', 'Frostschutz_ab',
        ]);
        $general = $this->sortValues($general, [
            'Außentemperatur', 'Außentemperatur_gedämpft', 'Kessel_Isttemperatur',
            'Kessel_Solltemperatur', 'Warmwasser_Isttemperatur', 'Warmwasser_Solltemperatur',
            'Brenner_Stufe_1', 'Brenner_Stufe_2', 'Brennerlaufzeit_Stunden',
        ]);
        $sort = static fn (array $a, array $b): int => strnatcasecmp((string) ($a['Name'] ?? ''), (string) ($b['Name'] ?? ''));
        uasort($faults, $sort);
        return [$hk1, $general, $faults];
    }

    private function sortValues(array $values, array $priorityKeys): array
    {
        $priority = array_flip($priorityKeys);
        uksort($values, static function (string $a, string $b) use ($priority, $values): int {
            $rankA = $priority[$a] ?? PHP_INT_MAX;
            $rankB = $priority[$b] ?? PHP_INT_MAX;
            if ($rankA !== $rankB) {
                return $rankA <=> $rankB;
            }
            return strnatcasecmp(
                (string) ($values[$a]['Name'] ?? $a),
                (string) ($values[$b]['Name'] ?? $b),
            );
        });
        return $values;
    }

    private function isFaultKey(string $key): bool
    {
        return str_starts_with($key, 'Störung_')
            || str_starts_with($key, 'Stoerung_')
            || $key === 'Externe_Störung'
            || $key === 'Externe_Stoerung';
    }

    private function hasActiveFault(array $faults): bool
    {
        foreach ($faults as $fault) {
            $value = $fault['Wert'] ?? null;
            if (is_numeric($value) && (float) $value !== 0.0) {
                return true;
            }
            if (is_string($value) && !in_array(mb_strtolower(trim($value)), ['', '0', 'aus', 'nein', 'false', 'keine'], true)) {
                return true;
            }
        }
        return false;
    }

    private function pollTimeMinutes(): int
    {
        $value = $this->connection->fetchOne(
            "SELECT cfgValue FROM tl_coh_cfgcollect WHERE cfgType='pollTime' ORDER BY id DESC LIMIT 1"
        );
        return max(1, (int) ($value ?: 15));
    }

    /** @return array<string, string> */
    private function valueDescriptions(): array
    {
        return [
            'HK1_Sommergrenze' => 'Grenzwert der gedämpften Außentemperatur, ab dem Heizkreis 1 in den Sommerbetrieb wechselt. Die Raumheizung wird dann abgeschaltet; die Warmwasserbereitung bleibt davon unberührt.',
            'HK1_Betriebsart' => 'Aktuelle Betriebsart von Heizkreis 1: Nacht, Tag oder Automatik nach dem eingestellten Zeitprogramm.',
            'HK1_Tagtemperatur' => 'Gewünschte Raumtemperatur für die Tag- beziehungsweise Komfortphasen des Heizprogramms.',
            'HK1_Nachttemperatur' => 'Gewünschte reduzierte Raumtemperatur für die Nacht- beziehungsweise Absenkphasen.',
            'HK1_Urlaubstemperatur' => 'Gewünschte reduzierte Raumtemperatur während eines eingestellten Urlaubszeitraums.',
            'HK1_Vorlauf_Isttemperatur' => 'Vom Fühler gemessene aktuelle Vorlauftemperatur von Heizkreis 1.',
            'HK1_Vorlauf_Solltemperatur' => 'Von der Regelung aktuell berechnete Zieltemperatur für den Vorlauf von Heizkreis 1.',
            'HK1_Raum_Isttemperatur' => 'Gemessene Raumtemperatur. Ohne angeschlossene oder aktive Raumfernbedienung kann dieser Wert 0 sein.',
            'HK1_Raum_Solltemperatur' => 'Von der Regelung aktuell verwendeter Raumtemperatur-Sollwert. Ohne Raumaufschaltung kann der Statuswert 0 sein.',
            'HK1_Frostschutz_ab' => 'Außentemperatur, unterhalb der die Regelung den Frostschutz für Heizkreis 1 aktiviert.',
            'Frostschutz_ab' => 'Außentemperatur, unterhalb der die Regelung den Frostschutz für Heizkreis 1 aktiviert.',
            'HK1_Auslegungstemperatur' => 'Vorlauftemperatur, für welche die Heizkurve bei der Auslegungs-Außentemperatur ausgelegt ist.',
            'HK1_Aufschalttemperatur' => 'Temperaturgrenze für die Umschaltung auf raumtemperaturabhängige Aufschaltung, sofern diese Betriebsweise unterstützt und aktiviert ist.',
            'HK1_Aussenhalt_ab' => 'Außentemperaturgrenze für die Absenkungsart Außenhalt.',
            'HK1_Absenkungsart' => 'Legt fest, wie sich Heizkreis 1 während der Absenkphase verhält.',
            'HK1_Heizprogramm' => 'Ausgewähltes Zeitprogramm für die Umschaltung zwischen Tag- und Nachtbetrieb.',
            'HK1_Ferientage' => 'Anzahl der noch eingestellten Ferientage.',
            'HK1_Maximaltemperatur' => 'Obere Begrenzung der Vorlauftemperatur von Heizkreis 1.',
            'HK1_Temperatur_Offset' => 'Parallelverschiebung beziehungsweise Korrektur der Heizkennlinie von Heizkreis 1.',
            'HK1_Fernbedienung' => 'Zeigt, ob für Heizkreis 1 eine Raumfernbedienung erkannt beziehungsweise aktiviert ist.',
            'HK1_Pumpenleistung' => 'Aktuelle Ansteuerung der Heizkreispumpe in Prozent.',
            'HK1_Mischerstellung' => 'Aktuelle Stellung beziehungsweise Ansteuerung des Mischers von Heizkreis 1.',
            'HK1_Einschaltoptimierung' => 'Von der Regelung berechnete Vorlaufzeit, um die gewünschte Raumtemperatur rechtzeitig zu erreichen.',
            'HK1_Ausschaltoptimierung' => 'Von der Regelung berechnete Zeit für ein vorzeitiges Abschalten vor dem Ende der Heizphase.',
            'Außentemperatur' => 'Aktuell am Außenfühler gemessene Temperatur.',
            'Außentemperatur_gedämpft' => 'Zeitlich geglättete Außentemperatur. Sie reagiert langsamer auf kurze Schwankungen und wird für Heizgrenzen und Heizkurve verwendet.',
            'Kessel_Isttemperatur' => 'Aktuell gemessene Temperatur des Kesselwassers.',
            'Kessel_Solltemperatur' => 'Von der Regelung aktuell angeforderte Zieltemperatur des Kessels.',
            'Brenner_Einschalttemperatur' => 'Aktuell von der Regelung berechnete Kesseltemperatur, bei deren Unterschreitung der Brenner eingeschaltet werden kann. Sie wird aus der momentanen Kessel-Solltemperatur und der Schalthysterese gebildet und ist keine feste Benutzereinstellung.',
            'Brenner_Ausschalttemperatur' => 'Aktuell von der Regelung berechnete Kesseltemperatur, bei deren Erreichen der Brenner wieder ausgeschaltet wird. Sie liegt um die Schalthysterese über der momentanen Kessel-Solltemperatur und ist keine feste Benutzereinstellung.',
            'Brenner_Ansteuerung' => 'Aktueller Ansteuerungs- beziehungsweise Leistungswert, den die Regelung an den Brenner ausgibt.',
            'Brennerlaufzeit_Minuten' => 'Gesamte erfasste Brennerlaufzeit in Minuten.',
            'Brennerlaufzeit_Stunden' => 'Gesamte erfasste Brennerlaufzeit in Stunden.',
            'Warmwasser_Isttemperatur' => 'Aktuell am Warmwasserspeicher gemessene Temperatur.',
            'Warmwasser_Solltemperatur' => 'Aktuell von der Regelung verwendete Zieltemperatur des Warmwassers.',
            'Warmwasser_eingestellte_Temperatur' => 'Vom Benutzer eingestellte Zieltemperatur für die Warmwasserbereitung.',
            'Warmwasser_Betriebsart' => 'Betriebsart der Warmwasserbereitung: Nacht, Tag oder Automatik.',
            'Warmwasserbereitung' => 'Zeigt, ob die Warmwasserbereitung in der Regelung grundsätzlich eingeschaltet oder ausgeschaltet ist.',
            'Warmwasser_Zirkulation_Einstellung' => 'Legt fest, wie oft die Zirkulationspumpe je Stunde innerhalb der freigegebenen Zeiten läuft.',
            'Warmwasser_Einschaltoptimierung' => 'Von der Regelung berechnete Vorlaufzeit, damit das Warmwasser zum programmierten Zeitpunkt die gewünschte Temperatur erreicht.',
            'Warmwasser_Ladepumpe' => 'Aktueller Schaltzustand der Speicherladepumpe. 1 bedeutet eingeschaltet, 0 ausgeschaltet.',
            'Warmwasser_Zirkulationspumpe' => 'Aktueller Schaltzustand der Warmwasser-Zirkulationspumpe. 1 bedeutet eingeschaltet, 0 ausgeschaltet.',
            'Warmwasser_Solarpumpe' => 'Aktueller Schaltzustand der vom Regler erfassten Solarpumpe. 1 bedeutet eingeschaltet, 0 ausgeschaltet.',
            'Brennerart' => 'Konfigurierte Brennerausführung, beispielsweise einstufig, zweistufig oder modulierend.',
            'Maximale_Kesseltemperatur' => 'In der Regelung konfigurierte obere Begrenzung der Kesseltemperatur.',
            'Pumpenlogik_Temperatur' => 'Kesseltemperaturgrenze, ab der die Regelung die Heizkreispumpe entsprechend ihrer Pumpenlogik freigibt.',
            'Abgastemperatur' => 'Vom Abgasfühler gemessene Temperatur. Der Wert wird nur angezeigt, wenn der KM271 einen gültigen Messwert liefert.',
            'Abgastest' => 'Status des Abgas- beziehungsweise Schornsteinfegertests. 1 bedeutet aktiv, 0 nicht aktiv.',
            'Brenner_Stufe_1' => 'Aktueller Zustand der ersten Brennerstufe. 1 bedeutet eingeschaltet, 0 ausgeschaltet.',
            'Brenner_Stufe_2' => 'Aktueller Zustand der zweiten Brennerstufe. 1 bedeutet eingeschaltet, 0 ausgeschaltet.',
            'Kesselschutz' => 'Zeigt an, ob die Regelung gerade eine Kesselschutzfunktion ausführt.',
            'Kessel_aktiv' => 'Zeigt an, ob der Kessel von der Regelung aktuell als aktiv geführt wird.',
            'Brennerfreigabe' => 'Zeigt, ob die Regelung den Brennerbetrieb aktuell freigibt.',
            'Brennerfreigabe_hohe_Leistung' => 'Zeigt, ob die Regelung eine hohe Brennerleistung beziehungsweise die nächste Brennerstufe freigibt.',
            'Störung_Brenner' => 'Störungsstatus des Brenners. 0 bedeutet keine Störung, 1 bedeutet Störung erkannt.',
            'Störung_Kesselfühler' => 'Störungsstatus des Kesseltemperaturfühlers. 0 bedeutet keine Störung, 1 bedeutet Störung erkannt.',
            'Störung_Zusatzfühler' => 'Störungsstatus des Zusatzfühlers. 0 bedeutet keine Störung, 1 bedeutet Störung erkannt.',
            'Störung_Kessel_bleibt_kalt' => 'Die Regelung meldet, dass der Kessel trotz Wärmeanforderung nicht ausreichend warm wird. 0 bedeutet keine Störung.',
            'Störung_Abgasfühler' => 'Störungsstatus des Abgastemperaturfühlers. 0 bedeutet keine Störung, 1 bedeutet Störung erkannt.',
            'Störung_Abgasgrenze' => 'Meldung zur Überschreitung der zulässigen Abgasgrenze. 0 bedeutet keine Störung.',
            'Störung_Sicherheitskette' => 'Störungsstatus der Sicherheitskette des Kessels. 0 bedeutet keine Störung.',
            'Externe_Störung' => 'Von einem extern angeschlossenen Kontakt gemeldete Störung. 0 bedeutet keine Störung.',
        ];
    }

    private function addCssOnce(string $file): void
    {
        $file .= '|static';
        if (!in_array($file, $GLOBALS['TL_CSS'] ?? [], true)) {
            $GLOBALS['TL_CSS'][] = $file;
        }
    }
}
