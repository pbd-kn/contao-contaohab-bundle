<?php

declare(strict_types=1);

namespace PbdKn\ContaoContaohabBundle\Service;

use Contao\System;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ModbusWriteDialog
{
    public function __construct(
        private readonly Connection $connection,
        private readonly HttpClientInterface $httpClient,
        private readonly array $settings,
    ) {}

    public function prepare(object $template, int $elementId, array $sensorIds, Request $request): ?Response
    {
        $template->modbusDevices = [];
        $template->modbusState = [];
        // Schreibzugriff setzt ein angemeldetes Frontend-Mitglied voraus.
        if (!System::getContainer()->get('contao.security.token_checker')->hasFrontendUser() || !$request->hasSession()) {
            return null;
        }
        $template->modbusRequestToken = System::getContainer()->get('contao.csrf.token_manager')->getDefaultTokenValue();
        $session = $request->getSession();
        $key = 'coh.modbus.dialog.' . $elementId;
        $state = $session->get($key, []);
        if (!is_array($state)) $state = [];
        $devices = [];
        try {
            foreach ($sensorIds as $sensorId) {
                $device = $this->connection->fetchAssociative(
                    'SELECT d.geraeteID, d.geraeteUrl FROM tl_coh_sensors s JOIN tl_coh_geraete d ON s.sensorSource = d.geraeteID WHERE s.sensorID = ? AND s.sensorActive = ?',
                    [(string) $sensorId, '1'],
                );
                if (!$device) continue;
                $source = strtolower(trim((string) $device['geraeteID']));
                $profile = ['iqbox' => 'ampere-storagepro', 'wechselrichtermodbus' => 'saj-h2'][$source] ?? null;
                if ($profile === null || isset($devices[$source])) continue;
                $host = trim((string) $device['geraeteUrl']);
                if (preg_match('~^https?://~i', $host)) $host = trim((string) parse_url($host, PHP_URL_HOST), '[]');
                $devices[$source] = ['label' => (string) $device['geraeteID'], 'host' => $host, 'profile' => $profile];
            }
            $catalogs = [];
            foreach ($devices as $id => $device) {
                $catalogs[$device['profile']] ??= $this->api(['action' => 'catalog', 'profile' => $device['profile']])['catalog'];
                $template->modbusDevices += [$id => ['label' => $device['label'], 'catalog' => $catalogs[$device['profile']]]];
            }
            if (!$request->isMethod('POST') || (string) $request->request->get('modbus_element') !== (string) $elementId) {
                $template->modbusState = $state;
                unset($state['open']);
                $session->set($key, $state);
                return null;
            }
            // Contao validiert auch die je Anfrage maskierten CSRF-Token korrekt.
            $csrf = System::getContainer()->get('contao.csrf.token_manager');
            $tokenName = (string) System::getContainer()->getParameter('contao.csrf_token_name');
            if (!$csrf->isTokenValid(new CsrfToken($tokenName, (string) $request->request->get('REQUEST_TOKEN')))) {
                throw new \RuntimeException('Ungültiger Request-Token. Seite neu laden.');
            }
            $action = (string) $request->request->get('modbus_action');
            if ($action === 'preview') {
                $state = [];
                $deviceId = (string) $request->request->get('modbus_device');
                $setting = (string) $request->request->get('modbus_setting');
                $input = (string) $request->request->get('modbus_value');
                $device = $devices[$deviceId] ?? null;
                $definition = $device ? ($catalogs[$device['profile']][$setting] ?? null) : null;
                if (($definition['writable'] ?? true) === false) {
                    throw new \InvalidArgumentException('Diese Einstellung ist als Information aufgeführt und noch nicht zum Schreiben freigegeben.');
                }
                if (!$definition || !preg_match('/^\d+$/D', $input) || !array_key_exists((int) $input, $definition['options'])) {
                    throw new \InvalidArgumentException('Gerät, Einstellung oder Auswahlwert ist nicht freigegeben.');
                }
                $value = (int) $input;
                $result = $this->api($device + ['action' => 'preview', 'setting' => $setting, 'value' => $value])['result'];
                $state['preview'] = [
                    'device' => $deviceId, 'host' => $device['host'], 'profile' => $device['profile'],
                    'setting' => $setting, 'value' => $value, 'expected' => $result['value'],
                    'label' => $definition['label'], 'deviceLabel' => $device['label'],
                    'valueLabel' => $definition['options'][$value],
                    'previousLabel' => $definition['options'][$result['value']] ?? ('Unbekannt (' . $result['value'] . ')'),
                    'expires' => time() + 120, 'nonce' => bin2hex(random_bytes(24)),
                ];
            } elseif ($action === 'execute') {
                $preview = $state['preview'] ?? [];
                unset($state['preview']);
                unset($state['error'], $state['message']);
                // Vor dem HTTP-Aufruf verbrauchen: auch bei Timeout keine Wiederholung durch Reload.
                $session->set($key, $state);
                if (!$preview || ($preview['expires'] ?? 0) < time()
                    || !hash_equals($preview['nonce'], (string) $request->request->get('modbus_nonce'))
                    || (string) $request->request->get('modbus_confirm') !== '1') {
                    throw new \RuntimeException('Bestätigung fehlt oder Vorschau ist abgelaufen. Bitte erneut prüfen.');
                }
                $device = $devices[$preview['device']] ?? null;
                if (!$device || $device['host'] !== $preview['host'] || $device['profile'] !== $preview['profile']) {
                    throw new \RuntimeException('Gerätekonfiguration hat sich geändert. Bitte erneut prüfen.');
                }
                $result = $this->api($device + [
                    'action' => 'execute', 'setting' => $preview['setting'], 'value' => $preview['value'],
                    'expected' => $preview['expected'], 'confirmed' => true,
                ]);
                if (($result['result']['verified'] ?? false) !== true) {
                    throw new \RuntimeException('Der Raspberry hat das Rücklesen nicht bestätigt. Aktuellen Zustand neu prüfen.');
                }
                $state['message'] = $preview['deviceLabel'] . ': ' . $preview['label'] . ' = ' . $preview['valueLabel'] . ' (' . $preview['value'] . ') geschrieben und durch Rücklesen bestätigt.';
            } else {
                throw new \InvalidArgumentException('Unbekannte Schreibaktion.');
            }
        } catch (\Throwable $error) {
            unset($state['preview']);
            $state['error'] = $error->getMessage();
        }
        $template->modbusState = $state;
        $session->set($key, $state);
        if ($request->isMethod('POST') && (string) $request->request->get('modbus_element') === (string) $elementId) {
            $state['open'] = true;
            $session->set($key, $state);
            return new RedirectResponse($request->getUri(), Response::HTTP_SEE_OTHER);
        }
        return null;
    }

    private function api(array $payload): array
    {
        $base = rtrim(trim((string) ($this->settings['raspberryApiWanBaseUrl'] ?? '')), '/');
        if ($base === '') $base = rtrim(trim((string) ($this->settings['raspberryApiBaseUrl'] ?? '')), '/');
        $token = trim((string) ($this->settings['raspberryApiToken'] ?? ''));
        if (!str_starts_with(strtolower($base), 'https://') || $token === '') {
            throw new \RuntimeException('HTTPS-Adresse oder Token der Raspberry-API fehlt.');
        }
        $response = $this->httpClient->request('POST', $base . '/api/coh/modbus-control.php', [
            'headers' => ['X-COH-TOKEN' => $token], 'json' => $payload,
            'timeout' => max(1, (int) ($this->settings['raspberryApiTimeout'] ?? 15)),
        ]);
        $result = $response->toArray(false);
        if ($response->getStatusCode() !== 200 || empty($result['ok'])) {
            throw new \RuntimeException((string) ($result['error'] ?? 'Raspberry-Schreibzugriff fehlgeschlagen. Zustand neu prüfen; Auftrag nicht automatisch wiederholen.'));
        }
        return $result;
    }
}
