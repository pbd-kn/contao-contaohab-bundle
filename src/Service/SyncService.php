<?php

namespace PbdKn\ContaoContaohabBundle\Service;

use mysqli;
use Symfony\Component\Console\Output\OutputInterface;

class SyncService
{

    public function sync(?OutputInterface $output = null): ?string
    {
        mysqli_report(MYSQLI_REPORT_OFF);

        // Master DB verbinden
        $masterDb = mysqli_init();
        $masterDb->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
        if (!$masterDb->real_connect('localhost', 'peter', 'sql666sql', 'co5_solar')) {
            $msg = 'Master DB-Verbindung fehlgeschlagen: ' . $masterDb->connect_error;
            $output?->writeln("<error>$msg</error>");
            return $msg;
        }

        // Slave DB (Raspberry) verbinden
        $slaveDb = mysqli_init();
        $slaveDb->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
        if (!$slaveDb->real_connect('raspberrypi', 'peter', 'sql666sql', 'co5_solar')) {
            $msg = 'Slave DB-Verbindung fehlgeschlagen: ' . $slaveDb->connect_error;
            $output?->writeln("<error>$msg</error>");
            return $msg;
        }

        $output?->writeln('<info>Starte Synchronisation...</info>');

        // Auto-Insert in tl_coh_sync_log
        foreach (['sensorvalue_pull', 'config_push'] as $type) {
            $res = $masterDb->query("SELECT COUNT(*) FROM tl_coh_sync_log WHERE sync_type='$type'");
            if ($res && ($res->fetch_row()[0] == 0)) {
                $masterDb->query("INSERT INTO tl_coh_sync_log (sync_type, last_sync, tstamp) VALUES ('$type', '1970-01-01 00:00:00', UNIX_TIMESTAMP())");
                $output?->writeln("<comment>Sync-Eintrag für '$type' automatisch angelegt.</comment>");
            }
        }

        // --- Pull (Sensorwerte vom Slave holen) ---
        $res = $masterDb->query("SELECT last_sync FROM tl_coh_sync_log WHERE sync_type='sensorvalue_pull'");
        $row = $res?->fetch_assoc();
        $lastSync = $row['last_sync'] ?? '1970-01-01 00:00:00';

        if (strtotime($lastSync) < time() - 5 * 60) {
            $stmt = $slaveDb->prepare("SELECT * FROM tl_coh_sensorvalue WHERE tstamp > ?");
            if (!$stmt) {
                $msg = 'Fehler beim Vorbereiten der Pull-Abfrage.';
                $output?->writeln("<error>$msg</error>");
                return $msg;
            }

            $timestamp = strtotime($lastSync);
            $stmt->bind_param('i', $timestamp);
            $stmt->execute();
            $result = $stmt->get_result();
            $i = 0;

            while ($row = $result->fetch_assoc()) {
                $columns = array_keys($row);
                $escapedValues = array_map([$masterDb, 'real_escape_string'], array_values($row));
                $columnList = implode(',', array_map(fn($col) => "`$col`", $columns));
                $valueList  = "'" . implode("','", $escapedValues) . "'";
                $sql = "REPLACE INTO tl_coh_sensorvalue ($columnList) VALUES ($valueList)";
                $masterDb->query($sql);
                $i++;
            }

            $masterDb->query("UPDATE tl_coh_sync_log SET last_sync=NOW(), tstamp=UNIX_TIMESTAMP() WHERE sync_type='sensorvalue_pull'");
            $output?->writeln("<info>Sensorwerte synchronisiert: $i Zeilen</info>");
        }

        // --- Push (Tabellen an Slave senden) ---
        $res = $masterDb->query("SELECT last_sync FROM tl_coh_sync_log WHERE sync_type='config_push'");
        $row = $res?->fetch_assoc();
        $lastSync = $row['last_sync'] ?? '1970-01-01 00:00:00';

        if (strtotime($lastSync) < time() - 10 * 60) {
            foreach (['tl_coh_sensors', 'tl_coh_cfgcollect', 'tl_coh_geraete'] as $table) {
                $output?->writeln("<info>Push: $table</info>");

                // ggf. Tabelle auf Slave erstellen
                $check = $slaveDb->query("SHOW TABLES LIKE '$table'");
                if (!$check || $check->num_rows === 0) {
                    $res = $masterDb->query("SHOW CREATE TABLE $table");
                    if ($res && $row = $res->fetch_assoc()) {
                        $createSql = $row['Create Table'];
                        $slaveDb->query($createSql);
                    }
                }

                $slaveDb->query("DELETE FROM $table");
                $result = $masterDb->query("SELECT * FROM $table");
                while ($row = $result->fetch_assoc()) {
                    $columns = array_keys($row);
                    $escapedValues = array_map([$slaveDb, 'real_escape_string'], array_values($row));
                    $columnList = implode(',', array_map(fn($col) => "`$col`", $columns));
                    $valueList  = "'" . implode("','", $escapedValues) . "'";
                    $sql = "REPLACE INTO $table ($columnList) VALUES ($valueList)";
                    $slaveDb->query($sql);
                }
            }

            $masterDb->query("UPDATE tl_coh_sync_log SET last_sync=NOW(), tstamp=UNIX_TIMESTAMP() WHERE sync_type='config_push'");
        }

        $output?->writeln('<info>Synchronisation erfolgreich abgeschlossen.</info>');
        return null;
    }
}
