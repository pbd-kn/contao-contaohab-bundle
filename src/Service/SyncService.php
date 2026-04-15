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
        'lanBase' => 'http://192.168.178.49',
        'wanBase' => 'http://p1pu92iv4i9yh2m2.myfritz.net',
        'token'   => 'COH_CODE',
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

        $result = $this->connectFirstAvailable([
            ['name' => 'LIMA',  'cfg' => $this->lima],
            ['name' => 'LOCAL', 'cfg' => $this->local],
        ], $output);
        $this->logger->debugMe("connectFirstAvailable fertig");

        $masterDb  = $result[0];
        $env       = $result[1];
        $masterCfg = $result[2];
        
        if (!$masterDb) {
            $msg = 'Keine Master-DB Verbindung möglich.';
            $output?->writeln("<error>$msg</error>");
            $this->logger->Error($msg);
            return $msg;
        }
        $this->logger->debugMe("MasterDB ok, Env: " . (string)$env);

        $raspiBase = $this->getRaspiBaseUrl($env);
        $this->logger->debugMe("RaspiBase: " . $raspiBase);

        foreach (['sensorvalue_pull', 'config_push'] as $type) {
            $res = $masterDb->query("SELECT COUNT(*) FROM tl_coh_sync_log WHERE sync_type='$type'");
            if ($res && ($res->fetch_row()[0] == 0)) {
                $masterDb->query("
                    INSERT INTO tl_coh_sync_log (sync_type, last_sync, tstamp)
                    VALUES ('$type', '1970-01-01 00:00:00', UNIX_TIMESTAMP())
                ");
            }
        }
        $this->logger->debugMe("Sync-Log geprüft");

        // ===================== PULL ======================
        $res = $masterDb->query("SELECT last_sync FROM tl_coh_sync_log WHERE sync_type='sensorvalue_pull'");
        $row = $res?->fetch_assoc();

    // 🔥 WICHTIG: alten Wert merken (für Cleanup!)
        $lastSyncBeforeUpdate = $row['last_sync'] ?? '1970-01-01 00:00:00';
        $lastSync = $lastSyncBeforeUpdate;
        
        //$lastSync = $row['last_sync'] ?? '1970-01-01 00:00:00';
        $this->logger->debugMe("lastSync sensorvalue_pull = " . $lastSync);

        if (strtotime($lastSync) < time() - 5 * 60) {

            $sinceTs = strtotime($lastSync);

            $pullUrl = $raspiBase . $this->raspiApi['pullPath'] . '?since=' . $sinceTs;
            $this->logger->debugMe("Pull startet: " . $pullUrl);

            try {
                $api = $this->apiGetJson($pullUrl);
            } catch (\Throwable $e) {
                $msg = "API Pull fehlgeschlagen: " . $e->getMessage();
                $this->logger->Error($msg);
                return $msg;
            }

            $rows = $api['rows'] ?? [];
            $this->logger->debugMe("Pull startet: " . $pullUrl);
            $i = 0;

            // ===================== BULK INSERT ======================
            $masterDb->begin_transaction();

            $batchSize = 1000;
            $batch = [];

            foreach ($rows as $r) {

                $tstamp          = (int)($r['tstamp'] ?? 0);
                $sensorID        = $masterDb->real_escape_string((string)($r['sensorID'] ?? ''));
                $sensorValue     = $masterDb->real_escape_string((string)($r['sensorValue'] ?? ''));
                $sensorEinheit   = $masterDb->real_escape_string((string)($r['sensorEinheit'] ?? ''));
                $sensorValueType = $masterDb->real_escape_string((string)($r['sensorValueType'] ?? ''));
                $sensorSource    = $masterDb->real_escape_string((string)($r['sensorSource'] ?? ''));

                if ($tstamp <= 0 || $sensorID === '') {
                    continue;
                }

                $batch[] = "($tstamp,'$sensorID','$sensorValue','$sensorEinheit','$sensorValueType','$sensorSource')";

                if (count($batch) >= $batchSize) {

                    $sql = "
                        INSERT INTO tl_coh_sensorvalue 
                        (tstamp, sensorID, sensorValue, sensorEinheit, sensorValueType, sensorSource)
                        VALUES " . implode(',', $batch) . "
                        ON DUPLICATE KEY UPDATE
                            sensorValue = VALUES(sensorValue),
                            sensorEinheit = VALUES(sensorEinheit),
                            sensorValueType = VALUES(sensorValueType),
                            sensorSource = VALUES(sensorSource)
                    ";

                    if (!$masterDb->query($sql)) {
                        $this->logger->Error("Bulk Insert Fehler: " . $masterDb->error);
                    } else {
                        $i += count($batch);
                    }

                    $batch = [];
                }
            }

            if (!empty($batch)) {
                $sql = "
                    INSERT INTO tl_coh_sensorvalue 
                    (tstamp, sensorID, sensorValue, sensorEinheit, sensorValueType, sensorSource)
                    VALUES " . implode(',', $batch) . "
                    ON DUPLICATE KEY UPDATE
                        sensorValue = VALUES(sensorValue),
                        sensorEinheit = VALUES(sensorEinheit),
                        sensorValueType = VALUES(sensorValueType),
                        sensorSource = VALUES(sensorSource)
                ";

                if (!$masterDb->query($sql)) {
                    $this->logger->Error("Bulk Insert Fehler (Rest): " . $masterDb->error);
                } else {
                    $i += count($batch);
                }
            }

            $masterDb->commit();

            // Sync Log
            $masterDb->query("
                UPDATE tl_coh_sync_log
                SET last_sync = NOW(), tstamp = UNIX_TIMESTAMP()
                WHERE sync_type = 'sensorvalue_pull'
            ");

            $this->logger->debugMe("Pull fertig: $i Sensorwerte übernommen.");
            if ($this->logger->isDebug()) {             // statistik ausgeben
                $this->logger->debugMe("Statistik\n");            
                $resTotal = $masterDb->query("SELECT COUNT(*) AS cnt FROM tl_coh_sensorvalue");
                $rowTotal = $resTotal->fetch_assoc();
                $this->logger->debugMe("Anzahl Sätze in DB: " . $rowTotal['cnt']."\n");            
                $this->logger->debugMe("die 50 meisten Sensoren\n");
                $res = $masterDb->query("
                    SELECT sensorID, DATE(FROM_UNIXTIME(tstamp)) AS day, COUNT(*) AS cnt
                    FROM tl_coh_sensorvalue
                    GROUP BY sensorID, day
                    ORDER BY day DESC, cnt DESC
                    LIMIT 50
                ");
                while ($row = $res->fetch_assoc()) {
                    $this->logger->debugMe("{$row['day']} SensorId {$row['sensorID']}: {$row['cnt']}");
                }            
            }

        } else {
            $this->logger->debugMe("Pull nicht fällig");
        }
        // ===================== CLEANUP ======================
        if (strtotime($lastSyncBeforeUpdate) < time() - 1 * 60) {    // im 1 Minuten rythmus auf alle Fääle cleaning
            $this->runCleanup($masterDb);
        }
        return null;
    }
    private function runCleanup(\mysqli $masterDb): void
    {
        $cleanupSql = " DELETE FROM tl_coh_sensorvalue WHERE tstamp < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 1 YEAR)) ORDER BY tstamp ASC LIMIT 1000 ";
        if (!$masterDb->query($cleanupSql)) {
            $this->logger->Error("Cleanup Fehler: " . $masterDb->error);
        } else {
            //$this->logger->debugMe("Cleanup ausgeführt (max 1000)");
            $deleted = $masterDb->affected_rows;
            $this->logger->debugMe("Cleanup gelöscht: $deleted Datensätze");
        }
    }
    private function getRaspiBaseUrl(string $env): string
    {
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
        ]);

        $body = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false || $http !== 200) {
            throw new \RuntimeException("API GET failed HTTP=$http err=$err");
        }

        $json = json_decode((string)$body, true);
        if (!is_array($json) || empty($json['ok'])) {
            throw new \RuntimeException("API GET invalid JSON");
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
                return [$db, $c['name'], $cfg];
            }
        }
        return [null, null, null];
    }
}