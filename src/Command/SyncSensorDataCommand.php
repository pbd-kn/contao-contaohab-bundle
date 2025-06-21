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
        $error = $this->syncService->sync($output);

        if ($error !== null) {
            $output->writeln("<error>Fehler bei der Synchronisation:</error>");
            $output->writeln("<error>$error</error>");
            return Command::FAILURE;
        }
        return Command::SUCCESS;
    }
}
