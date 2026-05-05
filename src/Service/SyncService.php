<?php

namespace PbdKn\ContaoContaohabBundle\Service;

use mysqli;
use Symfony\Component\Console\Output\OutputInterface;
use PbdKn\ContaoContaohabBundle\Service\LoggerService;

class SyncService
{
    private array $local = [
        'host' => '127.0.0.1',
        'port' => 3306,
        'user' => 'peter',
        'pass' => 'sql666sql',
        'db'   => 'co5_solar',
    ];

    private array $lima = [
        'host' => 'localhost',
        'port' => 3306,
        'user' => 'USER261774',
        'pass' => 'sql666sql',
        'db'   => 'db_261774_20',
    ];

    private array $raspiApi = [
        'lanBase'  => 'http://192.168.178.49',
        'wanBase'  => 'http://p1pu92iv4i9yh2m2.myfritz.net',
        'token'    => 'COH_CODE',
        'pullPath' => '/api/coh/sensorvalues.php',
        'pushPath' => '/api/coh/config_push.php',
    ];

    private ?LoggerService $logger = null;

    public function __construct(LoggerService $logger)
    {
        $this->logger = $logger;
    }

    public function sync(?OutputInterface $output = null): ?array
    {
        $resarray = [];
        $output?->writeln("<info>Starte Synchronisation</info>");
        $this->logger->debugMe("Start Synchronisation");
        mysqli_report(MYSQLI_REPORT_OFF);
        $result = $this->connectFirstAvailable([
            ['name' => 'LIMA',  'cfg' => $this->lima],
            ['name' => 'LOCAL', 'cfg' => $this->local],
        ], $output);
        $masterDb = $result[0];
        $env      = $result[1];
        $cfg      = $result[2];
        if (!$masterDb) {
            $msg = 'Keine Master-DB Verbindung möglich.';
            $this->logger->Error($msg);
            $output?->writeln("<error>$msg</error>");
            $resarray['status']="NOK";
            $resarray['msg']="error $msg";
            return $resarray;
        }
        $this->logger->debugMe("MasterDB ok, Env=$env");
        $raspiBase = $this->getRaspiBaseUrl((string)$env);
        $this->logger->debugMe("RaspiBase=$raspiBase");
        // ==================================================
        // 1. Sync-Log sicherstellen
        // ==================================================
        $this->ensureSyncLogRows($masterDb);
        // ==================================================
        // 2. Pull Sensorwerte
        // ==================================================
        $retpull=$this->runPull($masterDb, $raspiBase);
        // ==================================================
        // 3. Push nur LOCAL
        // ==================================================
        if ($env === 'LOCAL') {
            $retpush=$this->runConfigPush($masterDb, $raspiBase);
        } else {
            $this->logger->debugMe("Config Push übersprungen (Env=$env)");
            $retpush['status'] = 'OK';
            $retpush['msg'] = 'Von hier kein Push';
        }
        // ==================================================
        // 4. Cleanup
        // ==================================================
        $this->runCleanupIfDue($masterDb);
        $resarray['pull']=$retpull;
        $resarray['push']=$retpush;
        $resarray['status']="OK";
        return $resarray;
    }

    // ======================================================
    // Sync-Log Initialisierung
    // ======================================================
    private function ensureSyncLogRows(mysqli $db): void
    {
        foreach (['sensorvalue_pull', 'config_push'] as $type) {
            $typeEsc = $db->real_escape_string($type);

            $res = $db->query("
                SELECT COUNT(*) AS cnt
                FROM tl_coh_sync_log
                WHERE sync_type='$typeEsc'
            ");

            $row = $res?->fetch_assoc();
            $cnt = (int)($row['cnt'] ?? 0);

            if ($cnt === 0) {
                $db->query("
                    INSERT INTO tl_coh_sync_log
                    (sync_type, last_sync, tstamp)
                    VALUES
                    ('$typeEsc', '1970-01-01 00:00:00', UNIX_TIMESTAMP())
                ");
            }
        }

        $this->logger->debugMe("Sync-Log geprüft");
    }

    // ======================================================
    // PULL
    // ======================================================
    private function runPull(mysqli $db, string $raspiBase): ?array
    {
        $resarr = [];
        $res = $db->query("
            SELECT last_sync
            FROM tl_coh_sync_log
            WHERE sync_type='sensorvalue_pull'
            LIMIT 1
        ");
        $row = $res?->fetch_assoc();
        $lastSync = trim((string)($row['last_sync'] ?? ''));
        $ts = strtotime($lastSync);
        if ($ts === false) {
            $this->logger->debugMe("WARNUNG last_sync ungültig [$lastSync]");
            $ts = 0;
        }
        if ($ts >= time() - 300) {
            $this->logger->debugMe("Pull nicht fällig");
            $resarr['status'] = 'OK';
            $resarr['msg'] = "Pull nicht fällig";
            return $resarr;
        }
        $pullUrl = $raspiBase . $this->raspiApi['pullPath'] . '?since=' . $ts;
        $this->logger->debugMe("Pull startet: $pullUrl");
        try {
            $api = $this->apiGetJson($pullUrl);
        } catch (\Throwable $e) {
            $this->logger->Error("Pull Fehler: " . $e->getMessage());
            $resarr['status'] = 'NOK';
            $resarr['msg'] = "Pull Fehler: " . $e->getMessage();
            return $resarr;
        }
        $rows = $api['rows'] ?? [];
        $count = 0;
        $db->begin_transaction();
        try {
            $batch = [];
            $batchSize = 1000;
            foreach ($rows as $r) {
                $tstamp          = (int)($r['tstamp'] ?? 0);
                $sensorID        = $db->real_escape_string((string)($r['sensorID'] ?? ''));
                $sensorValue     = $db->real_escape_string((string)($r['sensorValue'] ?? ''));
                $sensorEinheit   = $db->real_escape_string((string)($r['sensorEinheit'] ?? ''));
                $sensorValueType = $db->real_escape_string((string)($r['sensorValueType'] ?? ''));
                $sensorSource    = $db->real_escape_string((string)($r['sensorSource'] ?? ''));
                if ($tstamp <= 0 || $sensorID === '') {
                    continue;
                }
                $batch[] = "(
                    $tstamp,
                    '$sensorID',
                    '$sensorValue',
                    '$sensorEinheit',
                    '$sensorValueType',
                    '$sensorSource'
                )";
                if (count($batch) >= $batchSize) {
                    $count += $this->insertBatch($db, $batch);
                    $batch = [];
                }
            }
            if (!empty($batch)) {
                $count += $this->insertBatch($db, $batch);
            }
            $db->query("
                UPDATE tl_coh_sync_log
                SET last_sync = NOW(),
                    tstamp = UNIX_TIMESTAMP()
                WHERE sync_type='sensorvalue_pull'
            ");
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            $this->logger->Error("Pull Rollback: " . $e->getMessage());
            $resarr['status'] = 'NOK';
            $resarr['msg'] = "Pull Rollback: " . $e->getMessage();
            return $resarr;
        }
        $this->logger->debugMe("Pull fertig: $count Datensätze");
        if ($this->logger->isDebug()) {
            $this->debugStats($db);
        }
        $resarr['status'] = 'OK';
        $resarr['msg'] = "Pull $count Datensätze";
        return $resarr;
        
    }
    private function insertBatch(mysqli $db, array $batch): int
    {
        $sql = "
            INSERT INTO tl_coh_sensorvalue
            (
                tstamp,
                sensorID,
                sensorValue,
                sensorEinheit,
                sensorValueType,
                sensorSource
            )
            VALUES " . implode(',', $batch) . "
            ON DUPLICATE KEY UPDATE
                sensorValue = VALUES(sensorValue),
                sensorEinheit = VALUES(sensorEinheit),
                sensorValueType = VALUES(sensorValueType),
                sensorSource = VALUES(sensorSource)
        ";
        if (!$db->query($sql)) {
            throw new \RuntimeException($db->error);
        }
        return count($batch);
    }
    // ======================================================
    // CONFIG PUSH (nur LOCAL aufgerufen)
    // ======================================================
    private function runConfigPush(mysqli $db, string $raspiBase): ?array
    {
        $resarr = [];
        $res = $db->query("
            SELECT last_sync
            FROM tl_coh_sync_log
            WHERE sync_type='config_push'
            LIMIT 1
        ");
        $row = $res?->fetch_assoc();
        $lastSync = $row['last_sync'] ?? '1970-01-01 00:00:00';
        $ts = strtotime((string)$lastSync);
        if ($ts === false) { $ts = 0; }
        // nur alle 10 Minuten pushen
        if ($ts >= time() - 600) {
            $this->logger->debugMe("Push wegen Zeit nicht nötig. Last Sync $lastSync");
            $resarr['status'] = 'OK';
            $resarr['msg'] = "Push nicht fällig";
            return $resarr;
        }
        $tables = [
            'tl_coh_sensors',
            'tl_coh_cfgcollect',
            'tl_coh_geraete',
        ];
        foreach ($tables as $table) {
            $this->logger->debugMe("Push Tabelle: $table");
            $rs = $db->query("SELECT * FROM $table");
            if (!$rs) {
                $this->logger->Error("Push SELECT Fehler $table: " . $db->error);
                $resarr['status'] = 'NOK';
                $resarr['msg'] = "Push SELECT Fehler $table: " . $db->error;
                return $resarr;
            }
            $rows = [];
            while ($r = $rs->fetch_assoc()) {
                $rows[] = $r;
            }
            $pushUrl = $raspiBase . $this->raspiApi['pushPath'];
            $this->logger->debugMe( "API PUSH URL: $pushUrl (table=$table rows=" . count($rows) . ")" );
            try {
                $resp = $this->apiPostJson($pushUrl, [
                    'table' => $table,
                    'rows'  => $rows,
                ]);
                $this->logger->debugMe( "Push OK $table: " . json_encode($resp, JSON_UNESCAPED_UNICODE) );
            } catch (\Throwable $e) {
                $this->logger->Error( "API Push fehlgeschlagen ($table): " . $e->getMessage() );
                $resarr['status'] = 'NOK';
                $resarr['msg'] = "API Push fehlgeschlagen ($table): " . $e->getMessage();
                return $resarr;
            }
        }
        // nur wenn alle Tabellen erfolgreich übertragen wurden
        $db->query("
            UPDATE tl_coh_sync_log
            SET last_sync = NOW(),
                tstamp = UNIX_TIMESTAMP()
                WHERE sync_type='config_push'
            ");
        $this->logger->debugMe("Push fertig");
        $resarr['status'] = 'OK';
        $resarr['msg'] = 'Push fertig';
        return $resarr;
    }
    // ======================================================
    // CLEANUP
    // ======================================================
    private function runCleanupIfDue(mysqli $db): void
    {
        $res = $db->query("
            SELECT last_sync
            FROM tl_coh_sync_log
            WHERE sync_type='cleanup'
            LIMIT 1
        ");
        $row = $res?->fetch_assoc();
        $lastSync = $row['last_sync'] ?? '1970-01-01 00:00:00';
        $ts = strtotime((string)$lastSync);
        if ($ts === false) { $ts = 0; }
        if ($ts >= time() - 600) { return; }
        $sql = "
            DELETE FROM tl_coh_sensorvalue
            WHERE tstamp < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 1 YEAR))
            ORDER BY tstamp ASC
            LIMIT 1000
        ";
        if (!$db->query($sql)) {
            $this->logger->Error("Cleanup Fehler: " . $db->error);
            return;
        }

        $deleted = $db->affected_rows;
        $db->query("
            UPDATE tl_coh_sync_log
            SET last_sync = NOW(),
                tstamp = UNIX_TIMESTAMP()
            WHERE sync_type='cleanup'
        ");
        $this->logger->debugMe("Cleanup gelöscht: $deleted");
    }

    // ======================================================
    // Statistik
    // ======================================================
    private function debugStats(mysqli $db): void
    {
        $res = $db->query("SELECT COUNT(*) AS cnt FROM tl_coh_sensorvalue");
        $row = $res?->fetch_assoc();
        $this->logger->debugMe("Anzahl Sätze: " . ($row['cnt'] ?? 0));
        $res = $db->query("
            SELECT sensorID, DATE(FROM_UNIXTIME(tstamp)) AS day, COUNT(*) AS cnt
            FROM tl_coh_sensorvalue
            GROUP BY sensorID, day
            ORDER BY day DESC, cnt DESC
            LIMIT 50
        ");
        while ($r = $res?->fetch_assoc()) {
            $this->logger->debugMe(
                "{$r['day']} {$r['sensorID']} = {$r['cnt']}"
            );
        }
    }

    // ======================================================
    // Helper
    // ======================================================
    private function getRaspiBaseUrl(string $env): string
    {
        return ($env === 'LIMA')
            ? $this->raspiApi['wanBase']
            : $this->raspiApi['lanBase'];
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
        ]);

        $body = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);

        curl_close($ch);

        if ($body === false || $http !== 200) {
            throw new \RuntimeException("HTTP=$http ERR=$err");
        }

        $json = json_decode((string)$body, true);

        if (!is_array($json) || empty($json['ok'])) {
            throw new \RuntimeException("Ungültige JSON Antwort");
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

            if (@$db->real_connect(
                $cfg['host'],
                $cfg['user'],
                $cfg['pass'],
                $cfg['db'],
                $cfg['port']
            )) {
                return [$db, $c['name'], $cfg];
            }
        }

        return [null, null, null];
    }
}