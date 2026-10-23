<?php

declare(strict_types=1);

namespace HolidayBundle\Migrations;

use App\Doctrine\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Holiday names a group's ICS import skips (observances in the feed, holidays an admin deleted).
 */
final class Version20261023100000 extends AbstractMigration
{
    private const TABLE = 'kimai2_ext_holiday_ph_group';
    private const COLUMN = 'excluded_names';

    public function getDescription(): string
    {
        return 'Exclusion list for ICS holiday import per public holiday group';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable(self::TABLE) || $schema->getTable(self::TABLE)->hasColumn(self::COLUMN)) {
            $this->preventEmptyMigrationWarning();

            return;
        }

        $schema->getTable(self::TABLE)->addColumn(self::COLUMN, 'json', ['notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable(self::TABLE) && $schema->getTable(self::TABLE)->hasColumn(self::COLUMN)) {
            $schema->getTable(self::TABLE)->dropColumn(self::COLUMN);

            return;
        }

        $this->preventEmptyMigrationWarning();
    }
}
