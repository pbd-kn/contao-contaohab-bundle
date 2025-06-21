<?php

namespace PbdKn\ContaoSyncBundle\Command;

use DateTime;
use mysqli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SyncSensorDataCommand extends Command
{
    protected static $defaultName = 'pbdkn:sync-sensor-data';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $masterDb = new mysqli('localhost', 'master_user', 'password', 'contao_db');
        $slaveDb = new mysqli('192.168.1.100', 'slave_user', 'password', 'raspi_db');

        if ($masterDb->connect_error || $slaveDb->connect_error) {
            $output->writeln('DB connection failed');
            return Command::FAILURE;
        }

        $output->writeln('Starting synchronization...');

        // SENSORVALUE PULL (alle 5 Minuten)
        $res = $masterDb->query("SELECT last_sync FROM tl_sync_log WHERE sync_type='sensorvalue_pull'");
        $row = $res->fetch_assoc();
        $lastSync = $row['last_sync'] ?? '1970-01-01 00:00:00';

        if (strtotime($lastSync) < time() - 5 * 60) {
            $stmt = $slaveDb->prepare("SELECT * FROM tl_sensorvalue WHERE tstamp > ?");
            $timestamp = strtotime($lastSync);
            $stmt->bind_param('i', $timestamp);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $columns = implode(',', array_keys($row));
                $values = implode("','", array_map([$masterDb, 'real_escape_string'], array_values($row)));
                $sql = "REPLACE INTO tl_sensorvalue ($columns) VALUES ('$values')";
                $masterDb->query($sql);
            }

            $masterDb->query("UPDATE tl_sync_log SET last_sync=NOW() WHERE sync_type='sensorvalue_pull'");
        }

        // CONFIG PUSH (alle 10 Minuten)
        $res = $masterDb->query("SELECT last_sync FROM tl_sync_log WHERE sync_type='config_push'");
        $row = $res->fetch_assoc();
        $lastSync = $row['last_sync'] ?? '1970-01-01 00:00:00';

        if (strtotime($lastSync) < time() - 10 * 60) {
            foreach (['tl_sensor', 'tl_remoteconfig'] as $table) {
                $slaveDb->query("DELETE FROM $table");
                $result = $masterDb->query("SELECT * FROM $table");

                while ($row = $result->fetch_assoc()) {
                    $columns = implode(',', array_keys($row));
                    $values = implode("','", array_map([$slaveDb, 'real_escape_string'], array_values($row)));
                    $sql = "REPLACE INTO $table ($columns) VALUES ('$values')";
                    $slaveDb->query($sql);
                }
            }

            $masterDb->query("UPDATE tl_sync_log SET last_sync=NOW() WHERE sync_type='config_push'");
        }

        $output->writeln('Synchronization complete.');
        return Command::SUCCESS;
    }
}
