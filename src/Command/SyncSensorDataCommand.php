<?php

namespace PbdKn\ContaoContaohabBundle\Command;

use PbdKn\ContaoContaohabBundle\Service\SyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'pbdkn:sync-sensor-data',
    description: 'Synchronisiert Sensordaten zwischen Master- und Slave-Datenbank.'
)]
class SyncSensorDataCommand extends Command
{
    public function __construct(private readonly SyncService $syncService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->syncService->sync($output);

        if (($result['status'] ?? 'NOK') !== 'OK') {
            $output->writeln('<error>Fehler bei der Synchronisation:</error>');
            $output->writeln('<error>'.json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).'</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>Synchronisation erfolgreich abgeschlossen.</info>');
        return Command::SUCCESS;
    }
}
