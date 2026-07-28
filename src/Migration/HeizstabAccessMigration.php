<?php

declare(strict_types=1);

namespace PbdKn\ContaoContaohabBundle\Migration;

use Contao\CoreBundle\Migration\MigrationInterface;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

final class HeizstabAccessMigration implements MigrationInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function getName(): string
    {
        return 'Heizstabzugriff auf exklusive Auswahl migrieren';
    }

    public function shouldRun(): bool
    {
        $columns = $this->columns();

        if (!isset($columns['heizstabaccess'], $columns['heizstablocalenabled'], $columns['heizstabcloudenabled'])) {
            return false;
        }

        return (bool) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM tl_coh_sensorcollector_settings WHERE heizstabAccess = '' OR heizstabLocalEnabled <> '' OR heizstabCloudEnabled <> ''"
        );
    }

    public function run(): MigrationResult
    {
        $affected = $this->connection->executeStatement(
            "UPDATE tl_coh_sensorcollector_settings SET heizstabAccess = CASE
                WHEN heizstabCloudEnabled <> '' THEN 'cloud'
                WHEN heizstabLocalEnabled <> '' THEN 'local'
                ELSE 'disabled'
            END, heizstabLocalEnabled = '', heizstabCloudEnabled = ''
            WHERE heizstabAccess = '' OR heizstabLocalEnabled <> '' OR heizstabCloudEnabled <> ''"
        );

        return new MigrationResult(true, sprintf('%d Heizstab-Einstellungssaetze migriert.', $affected));
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
