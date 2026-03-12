<?php

namespace PbdKn\ContaoContaohabBundle\Service;

use mysqli;
use Symfony\Component\Console\Output\OutputInterface;
use PbdKn\ContaoContaohabBundle\Service\LoggerService;

class SyncService
{
    /*
        MASTER: Lima oder Local (connectFirstAvailable)
        SLAVE (Raspberry): NICHT mehr via MySQL, sondern via HTTPS JSON API

        Pull:
          Hoster/Lokal -> HTTPS GET -> Raspi sensorvalues.php -> JSON -> in MASTER-DB schreiben

        Push (auskommentiert):
          MASTER-DB lesen -> HTTPS POST -> Raspi config_push.php -> Raspi schreibt lokal

        Hinweis:
          - lanBase = Zugriff im LAN (z.B. https://192.168.178.49)
          - wanBase = Zugriff von außen (MyFritz/DynDNS), Fritzbox Portfreigabe TCP 443 -> 192.168.178.49:443
          - Token MUSS auf Raspi und hier identisch sein
    */
    //  die jewewiligen db -zugriffe
    private array $local = [
        'host' => '127.0.0.1',
        'port' => 3306,
        'user' => 'peter',
        'pass' => 'sql666sql',
        'db'   => 'co5_solar',
    ];

    private array $lima = [
        'host' => 'localhost',            // bei Lima nicht db.lima-city.de!
        'port' => 3306,
        'user' => 'USER261774',
        'pass' => 'sql666sql',
        'db'   => 'db_261774_20',
    ];

    // ===== Raspberry API Konfiguration =====
    private array $raspiApi = [
        // LAN Zugriff (wenn Sync lokal läuft, env=LOCAL)
        'lanBase' => 'http://192.168.178.49',
        // WAN Zugriff (wenn Sync am Hoster läuft, env=LIMA) -> MyFritz/DynDNS:
        //'wanBase' => 'https://DEINNAME.myfritz.net',
        //'wanBase' => 'http://31.47.83.250',                  // angebblich kann ich auf meinem hoster eine subdomain einrrichten und dann per
                                                            // A-Record raspi 31.47.83.250 einrichten dann kann mann immer die subdomaoin raspi.pb-broghammer.de verwenden
        'wanBase' => 'http://p1pu92iv4i9yh2m2.myfritz.net',                                                            
        // Token wie in sensorvalues.php / config_push.php:
        'token'   => 'COH_CODE',
        // API Pfade:
        'pullPath' => '/api/coh/sensorvalues.php',
        'pushPath' => '/api/coh/config_push.php',
    ];

    private ?LoggerService $logger = null;

    public function __construct(LoggerService $logger)
    {
        $this->logger = $logger;
    }

    public function sync(?OutputInterface $output = null): ?string
    {
        $output?->writeln("<info>Starte Synchronisation</info>");
        $this->logger->debugMe("Start Synchronisation");

        mysqli_report(MYSQLI_REPORT_OFF);

        // --- MASTER: Lima oder Local ---
/*
        [$masterDb, $env, $masterCfg] = $this->connectFirstAvailable([
            ['name' => 'LIMA',  'cfg' => $this->lima],
            ['name' => 'LOCAL', 'cfg' => $this->local],
        ], $output);
*/
        $result = $this->connectFirstAvailable([
            ['name' => 'LIMA',  'cfg' => $this->lima],
            ['name' => 'LOCAL', 'cfg' => $this->local],
            ], $output);

        $masterDb  = $result[0];    // connect zu localen db
        $env       = $result[1];    // name
        $masterCfg = $result[2];    // this-> lima opder this->local
        
        if (!$masterDb) {
            $msg = 'Keine Master-DB Verbindung möglich (Lima und Local fehlgeschlagen).';
            $output?->writeln("<error>$msg</error>");
            $this->logger->Error($msg);
            return $msg;
        }

        $raspiBase = $this->getRaspiBaseUrl($env);   // basisadresse zum requesrt auf den Raspbi
        $this->logger->debugMe("RASPI API Base ($env): " . $raspiBase);

        $output?->writeln("<info>Starte Synchronisation (MASTER={$env}, RASPI_API={$raspiBase})...</info>");
        $this->logger->debugMe("Starte Synchronisation (MASTER={$env}, RASPI_API={$raspiBase})");

        // --- Sicherstellen: tl_coh_sync_log auf MASTER ---
        foreach (['sensorvalue_pull', 'config_push'] as $type) {
            $res = $masterDb->query("SELECT COUNT(*) FROM tl_coh_sync_log WHERE sync_type='$type'");
            if ($res && ($res->fetch_row()[0] == 0)) {
                $masterDb->query("
                    INSERT INTO tl_coh_sync_log (sync_type, last_sync, tstamp)
                    VALUES ('$type', '1970-01-01 00:00:00', UNIX_TIMESTAMP())
                ");
                $output?->writeln("<comment>Sync-Eintrag für '$type' automatisch angelegt.</comment>");
                $this->logger->debugMe("Sync-Eintrag für '$type' automatisch angelegt.");
            }
        }

        // =========================================================
        // ===================== PULL (AKTIV) ======================
        // =========================================================
        $res = $masterDb->query("SELECT last_sync FROM tl_coh_sync_log WHERE sync_type='sensorvalue_pull'");
        $row = $res?->fetch_assoc();
        $lastSync = $row['last_sync'] ?? '1970-01-01 00:00:00';
        $this->logger->debugMe("Pull lastSync $lastSync ".strtotime($lastSync) . " time " . time() - 5 * 60);
//$lastSync='1970-01-01 00:00:00';   immer pull
        if (strtotime($lastSync) < time() - 5 * 60) {

            $sinceTs = strtotime($lastSync);

            $pullUrl = $raspiBase . $this->raspiApi['pullPath'] . '?since=' . $sinceTs;
            $this->logger->debugMe("API PULL URL: " . $pullUrl . '&' . $this->raspiApi['token']);

            try {
                $api = $this->apiGetJson($pullUrl);
            } catch (\Throwable $e) {
                $msg = "API Pull fehlgeschlagen: " . $e->getMessage();
                $output?->writeln("<error>$msg</error>");
                $this->logger->Error($msg);
                return $msg;
            }

            $rows = $api['rows'] ?? [];
            $i = 0;

            $sql = "
                INSERT INTO tl_coh_sensorvalue (tstamp, sensorID, sensorValue, sensorEinheit, sensorValueType, sensorSource) VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    sensorValue      = VALUES(sensorValue),
                    sensorEinheit    = VALUES(sensorEinheit),
                    sensorValueType  = VALUES(sensorValueType),
                    sensorSource     = VALUES(sensorSource)
            ";

            $insert = $masterDb->prepare($sql);
            if (!$insert) {
                $msg = "Prepare fehlgeschlagen (MASTER insert): " . $masterDb->error;
                $output?->writeln("<error>$msg</error>");
                $this->logger->Error($msg);
                return $msg;
            }

            $insert->bind_param('isssss', $tstamp, $sensorID, $sensorValue, $sensorEinheit, $sensorValueType, $sensorSource);

            $masterDb->begin_transaction();

            foreach ($rows as $r) {
                $tstamp          = (int)($r['tstamp'] ?? 0);
                $sensorID        = (string)($r['sensorID'] ?? '');
                $sensorValue     = (string)($r['sensorValue'] ?? '');
                $sensorEinheit   = (string)($r['sensorEinheit'] ?? '');
                $sensorValueType = (string)($r['sensorValueType'] ?? '');
                $sensorSource    = (string)($r['sensorSource'] ?? '');

                if ($tstamp <= 0 || $sensorID === '') {
                    continue;
                }

                if (!$insert->execute()) {
                    $this->logger->Error('Sensor-API-Sync Fehler (sensorID=' . $sensorID . ', tstamp=' . $tstamp . '): ' . $insert->error);
                } else {
                    $i++;
                }
            }

            $masterDb->commit();

            $masterDb->query("
                UPDATE tl_coh_sync_log
                SET last_sync = NOW(), tstamp = UNIX_TIMESTAMP()
                WHERE sync_type = 'sensorvalue_pull'
            ");

            $output?->writeln("<comment>Pull fertig: $i Sensorwerte übernommen.</comment>");
            $this->logger->debugMe("Pull fertig: $i Sensorwerte übernommen.");

        } else {
            $this->logger->debugMe("Pull wegen time nicht noetig. Last Sync $lastSync");
        }

        // =========================================================
        // ===================== PUSH nur von local ==============
        // =========================================================
        if ($env == 'LOCAL') {
            $res = $masterDb->query("SELECT last_sync FROM tl_coh_sync_log WHERE sync_type='config_push'");
            $row = $res?->fetch_assoc();
            $lastSync = $row['last_sync'] ?? '1970-01-01 00:00:00';
//$lastSync='1970-01-01 00:00:00';   immer push
            if (strtotime($lastSync) < time() - 10 * 60) {

                foreach (['tl_coh_sensors', 'tl_coh_cfgcollect', 'tl_coh_geraete'] as $table) {
                    $output?->writeln("<info>Push: $table</info>");
                    $this->logger->debugMe("Push: Tabelle $table");

                    $rs = $masterDb->query("SELECT * FROM $table");
                    if (!$rs) {
                        $this->logger->Error("Push: SELECT Fehler $table: " . $masterDb->error);
                        continue;
                    }

                    $rows = [];
                    while ($r = $rs->fetch_assoc()) {
                        $rows[] = $r;
                    }

                    $pushUrl = $raspiBase . $this->raspiApi['pushPath'];
                    $this->logger->debugMe("API PUSH URL: " . $pushUrl . " (table=$table, rows=" . count($rows) . ")");

                    try {
                        $resp = $this->apiPostJson($pushUrl, [
                            'table' => $table,
                            'rows'  => $rows,
                        ]);
                        $this->logger->debugMe("Push OK: " . json_encode($resp));
                    } catch (\Throwable $e) {
                        $msg = "API Push fehlgeschlagen ($table): " . $e->getMessage();
                        $output?->writeln("<error>$msg</error>");
                        $this->logger->Error($msg);
                        return $msg;
                    }
                }

                $masterDb->query("UPDATE tl_coh_sync_log SET last_sync=NOW(), tstamp=UNIX_TIMESTAMP() WHERE sync_type='config_push'");
                $this->logger->debugMe("Push: fertig");
                $output?->writeln("<info>Push fertig.</info>");
            } else {
                $this->logger->debugMe("push wegen time nicht noetig. Last Sync $lastSync");
            }
        }
        

        $this->logger->debugMe("Synchronisation erfolgreich abgeschlossen.");
        $output?->writeln('<info>Synchronisation erfolgreich abgeschlossen.</info>');

        return null;
    }

    // ========================= Helpers =========================

    private function getRaspiBaseUrl(string $env): string
    {
        // Wenn Master=LIMA (Hoster), braucht es WAN URL (MyFritz/DynDNS)
        // Wenn Master=LOCAL (dein PC im LAN), kann LAN URL genutzt werden
        return ($env === 'LIMA') ? $this->raspiApi['wanBase'] : $this->raspiApi['lanBase'];
    }

    private function apiGetJson(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_HTTPHEADER     => [
                'X-COH-TOKEN: ' . $this->raspiApi['token'],
                'Accept: application/json',
            ],
            // Wenn du (noch) ein selbstsigniertes Zertifikat hast, ggf. TEMPORÄR aktivieren:
            // CURLOPT_SSL_VERIFYPEER => false,
            // CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $body = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false || $http !== 200) {
            throw new \RuntimeException("API GET failed HTTP=$http err=$err url=$url body=" . substr((string)$body, 0, 200));
        }

        $json = json_decode((string)$body, true);
        if (!is_array($json) || empty($json['ok'])) {
            throw new \RuntimeException("API GET invalid JSON url=$url body=" . substr((string)$body, 0, 200));
        }

        return $json;
    }

private function apiPostJson(string $url, array $payload): array
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => [
            'X-COH-TOKEN: ' . $this->raspiApi['token'],
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);

    $body = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);

    curl_close($ch);

    if ($body === false) {
        throw new \RuntimeException("API CURL ERROR: $err url=$url");
    }

    if ($http !== 200) {
        throw new \RuntimeException("API HTTP ERROR: HTTP=$http body=$body");
    }

    $json = json_decode($body, true);

    if (!is_array($json)) {
        throw new \RuntimeException("API INVALID JSON: $body");
    }

    if (empty($json['ok'])) {
        throw new \RuntimeException("API ERROR: " . json_encode($json));
    }

    return $json;
}

    private function connectFirstAvailable(array $candidates, ?OutputInterface $output): array
    {
        foreach ($candidates as $c) {
            $cfg = $c['cfg'];
            $db = mysqli_init();
            $db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);

            if (@$db->real_connect($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['db'], $cfg['port'])) {
                $output?->writeln("<info>Verbunden: {$c['name']} ({$cfg['host']}:{$cfg['port']})</info>");
                $this->logger->debugMe('Verbunden: '.$c['name'].' host ('.$cfg['host'].':'.$cfg['port'].')');
                return [$db, $c['name'], $cfg];
            } else {
                $output?->writeln("<comment>Kein Connect: {$c['name']} ({$cfg['host']}:{$cfg['port']}) ? {$db->connect_error}</comment>");
                $this->logger->debugMe('Kein Connect zu: '.$c['name'].' host ('.$cfg['host'].':'.$cfg['port'].') error '.$db->connect_error);
            }
        }
        return [null, null, null];
    }
}