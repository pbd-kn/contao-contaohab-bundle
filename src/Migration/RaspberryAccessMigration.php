<?php

declare(strict_types=1);

namespace PbdKn\ContaoContaohabBundle\Migration;

use Contao\CoreBundle\Migration\MigrationInterface;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

final class RaspberryAccessMigration implements MigrationInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function getName(): string
    {
        return 'Raspberry-Zugriff auf exklusive Auswahl migrieren';
    }

    public function shouldRun(): bool
    {
        $columns = $this->columns();
        if (!isset($columns['raspberryaccess'], $columns['raspberryapienabled'])) {
            return false;
        }

        return (bool) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM tl_coh_sensorcollector_settings
             WHERE raspberryAccess NOT IN ('disabled', 'local', 'http') OR raspberryApiEnabled <> ''"
        );
    }

    public function run(): MigrationResult
    {
        $affected = $this->connection->executeStatement(
            "UPDATE tl_coh_sensorcollector_settings
             SET raspberryAccess = CASE
                 WHEN raspberryApiEnabled <> '' THEN 'local'
                 WHEN raspberryAccess IN ('disabled', 'local', 'http') THEN raspberryAccess
                 ELSE 'disabled'
             END,
             raspberryApiEnabled = ''
             WHERE raspberryAccess NOT IN ('disabled', 'local', 'http') OR raspberryApiEnabled <> ''"
        );

        return new MigrationResult(true, sprintf('%d Raspberry-Einstellungssaetze migriert.', $affected));
    }

    /** @return array<string, mixed> */
    private function columns(): array
    {
        $schemaManager = method_exists($this->connection, 'createSchemaManager')
            ? $this->connection->createSchemaManager()
            : $this->connection->getSchemaManager();

        if (!$schemaManager->tablesExist(['tl_coh_sensorcollector_settings'])) {
            return [];
        }

        return array_change_key_case($schemaManager->listTableColumns('tl_coh_sensorcollector_settings'), CASE_LOWER);
    }
}
