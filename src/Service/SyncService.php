<?php

namespace PbdKn\ContaoContaohabBundle\Service;

use mysqli;
use Symfony\Component\Console\Output\OutputInterface;
use PbdKn\ContaoContaohabBundle\Service\LoggerService;

class SyncService
{
    /*  Es gilt:
        Die aktuelle datenbank wird geprüft, ob sie auf lima oder lokal läuft Routine)
        ist keine der beiden möglich fehlerausgabe
        zugriff auf slave (Raspbery festlegen ob über tunnel oder direkt         
        $slaveCfg = ($env === 'LIMA') ? 
        $this->raspiTunnel : zugriff über tunnel
        $this->raspiDirect;) zugriff über hostname
        Der tunnel witrd auf dem raspberry durch den service raspi-lima-tunnel.service eingerichtet
        sudo systemctl status (start/stop) raspi-lima-tunnel.service
        Wenn keine Slaveverbindung Error.
        logfile:
        debugMe nur wenn im debugmodus
        Error Fehlerausgabe
        logfile default cohdebug.log in var/logs 
    */
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

    private array $raspiTunnel = [
        'host' => '127.0.0.1',
        'port' => 3307,                   // Tunnelport von Lima zum Raspi funktioniet leider nicht conection refused beim tunnel von lima aus
        'user' => 'peter',
        'pass' => 'sql666sql',
        'db'   => 'co5_solar',
    ];

//        'host' => 'raspberrypi',          // direkter Hostname im LAN
//        'host' => '127.0.0.1',          // direkter Hostname im LAN
    private array $raspiDirect = [
        'host' => 'raspberrypi',          // direkter Hostname im LAN
        'port' => 3306,
        'user' => 'peter',
        'pass' => 'sql666sql',
        'db'   => 'co5_solar',
    ];
    private ?LoggerService $logger = null;
        
    public function __construct(LoggerService $logger)
    {
        $this->logger = $logger;        
    }
    public function sync(?OutputInterface $output = null): ?string
    {
        $output?->writeln("<info>Starte Synchronisation </info>");
        $this->logger->debugMe("Start Synchronisation");

        mysqli_report(MYSQLI_REPORT_OFF);

        // --- MASTER: Lima oder Local ---
        [$masterDb, $env, $masterCfg] = $this->connectFirstAvailable([
            ['name' => 'LIMA',  'cfg' => $this->lima],
            ['name' => 'LOCAL', 'cfg' => $this->local],
        ], $output);

        if (!$masterDb) {
            $msg = 'Keine Master-DB Verbindung möglich (Lima & Local fehlgeschlagen).';
            $output?->writeln("<error>$msg</error>");
            $this->logger->Error($msg);
            return $msg;
        }

        // --- SLAVE: immer Raspberry ---
        $slaveCfg = ($env === 'LIMA') ? $this->raspiTunnel : $this->raspiDirect;
        //$slaveCfg = $this->raspiTunnel;
        $slaveDb  = mysqli_init();
        $slaveDb->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
        if (!@$slaveDb->real_connect($slaveCfg['host'], $slaveCfg['user'], $slaveCfg['pass'], $slaveCfg['db'], $slaveCfg['port'],null)) {
            $msg = "sync läuift auf $env Slave-Verbindung fehlgeschlagen Vielleicht läuft Backup auf dem Raspbery oder Service mariadb läuft nicht ({$slaveCfg['host']}:{$slaveCfg['port']}) ? {$slaveDb->connect_error}";
            $output?->writeln("<error>$msg</error>");
            $this->logger->Error($msg);
            return $msg;
        }

        // --- Schutz: Master darf nicht gleich Slave sein ---
        if (
            $masterCfg['host'] === $slaveCfg['host'] &&
            $masterCfg['port'] === $slaveCfg['port'] &&
            $masterCfg['db']   === $slaveCfg['db']
        ) {
            $msg = "ABBRUCH: Master und Slave sind identisch ({$masterCfg['host']}:{$masterCfg['port']} ? {$masterCfg['db']})";
            $output?->writeln("<error>$msg</error>");
            $this->logger->Error($msg);
            return $msg;
        }

        $output?->writeln("<info>Starte Synchronisation (MASTER={$env}, SLAVE={$slaveCfg['host']}:{$slaveCfg['port']})...</info>");
        $this->logger->debugMe('Starte Synchronisation (MASTER= '.$env.', SLAVE= '.$slaveCfg['host'].':'.$slaveCfg['port']);

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

        // --- PULL: Sensorwerte vom Raspberry ? Master ---
        $res = $masterDb->query("SELECT last_sync FROM tl_coh_sync_log WHERE sync_type='sensorvalue_pull'");
        $row = $res?->fetch_assoc();
        $lastSync = $row['last_sync'] ?? '1970-01-01 00:00:00';

        if (strtotime($lastSync) < time() - 5 * 60) {
            $stmt = $slaveDb->prepare("SELECT * FROM tl_coh_sensorvalue WHERE tstamp > ?");
            if ($stmt) {
                $ts = strtotime($lastSync);
                $stmt->bind_param('i', $ts);
                $stmt->execute();
                $result = $stmt->get_result();

                $i = 0;
                while ($r = $result->fetch_assoc()) {
                    $columns = array_keys($r);
                    $escaped = array_map([$masterDb, 'real_escape_string'], array_values($r));
                    $colList = implode(',', array_map(fn($c) => "`$c`", $columns));
                    $valList = "'" . implode("','", $escaped) . "'";
                    $masterDb->query("REPLACE INTO tl_coh_sensorvalue ($colList) VALUES ($valList)");
                    $i++;
                }

                $masterDb->query("UPDATE tl_coh_sync_log SET last_sync=NOW(), tstamp=UNIX_TIMESTAMP() WHERE sync_type='sensorvalue_pull'");
                $output?->writeln("<comment>Pull fertig: $i Sensorwerte übernommen.</comment>");
                $this->logger->debugMe("Pull fertig: $i Sensorwerte übernommen.");
            }
        } else {
                $this->logger->debugMe("Pull wegen time nicht noetig. Last Sync $lastSync ");        
        }

        // --- PUSH: Config vom Master ? Raspberry ---
        $res = $masterDb->query("SELECT last_sync FROM tl_coh_sync_log WHERE sync_type='config_push'");
        $row = $res?->fetch_assoc();
        $lastSync = $row['last_sync'] ?? '1970-01-01 00:00:00';

        if (strtotime($lastSync) < time() - 10 * 60) {
            foreach (['tl_coh_sensors', 'tl_coh_cfgcollect', 'tl_coh_geraete'] as $table) {
                $output?->writeln("<info>Push: $table</info>");
                $this->logger->debugMe("Push: Tabelle $table");

                $check = $slaveDb->query("SHOW TABLES LIKE '$table'");
                if (!$check || $check->num_rows === 0) {
                    $cr = $masterDb->query("SHOW CREATE TABLE $table");
                    if ($cr && $def = $cr->fetch_assoc()) {
                        $createSql = $def['Create Table'];
                        $slaveDb->query($createSql);
                    }
                }

                $slaveDb->query("DELETE FROM $table");
                $rs = $masterDb->query("SELECT * FROM $table");
                while ($r = $rs->fetch_assoc()) {
                    $columns = array_keys($r);
                    $escaped = array_map([$slaveDb, 'real_escape_string'], array_values($r));
                    $colList = implode(',', array_map(fn($c) => "`$c`", $columns));
                    $valList = "'" . implode("','", $escaped) . "'";
                    $slaveDb->query("REPLACE INTO $table ($colList) VALUES ($valList)");
                }
            }

            $masterDb->query("UPDATE tl_coh_sync_log SET last_sync=NOW(), tstamp=UNIX_TIMESTAMP() WHERE sync_type='config_push'");
            $this->logger->debugMe("Push: fertig");
            $output?->writeln("<info>Push fertig.</info>");
        } else {
                $this->logger->debugMe("push wegen time nicht noetig.  Last Sync $lastSync ");        
        }

        $this->logger->debugMe("Synchronisation erfolgreich abgeschlossen.");
        $output?->writeln('<info>Synchronisation erfolgreich abgeschlossen.</info>');
        return null;
    }

    private function connectFirstAvailable(array $candidates, ?OutputInterface $output): array
    {
        foreach ($candidates as $c) {
            $cfg = $c['cfg'];
            $db = mysqli_init();
            $db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);

            // Fehlerunterdrückung mit @
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
