<?php

declare(strict_types=1);

namespace HolidayBundle\Migrations;

use App\Doctrine\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Late timesheet changes that a team lead has to approve.
 */
final class Version20261114100000 extends AbstractMigration
{
    private const CHANGE = 'kimai2_ext_holiday_timesheet_change';

    public function getDescription(): string
    {
        return 'Zeitnachträge';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable(self::CHANGE)) {
            $this->preventEmptyMigrationWarning();

            return;
        }

        $table = $schema->createTable(self::CHANGE);
        $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $table->addColumn('user_id', 'integer', ['notnull' => true]);
        $table->addColumn('timesheet_id', 'integer', ['notnull' => false]);
        $table->addColumn('day', 'date_immutable', ['notnull' => true]);
        $table->addColumn('kind', 'string', ['length' => 16, 'notnull' => true]);
        $table->addColumn('status', 'string', ['length' => 32, 'notnull' => true]);
        $table->addColumn('before_state', 'json', ['notnull' => false]);
        $table->addColumn('after_state', 'json', ['notnull' => false]);
        $table->addColumn('requested_by_id', 'integer', ['notnull' => false]);
        $table->addColumn('created_at', 'datetime_immutable', ['notnull' => true]);
        $table->addColumn('approved_by_id', 'integer', ['notnull' => false]);
        $table->addColumn('approved_at', 'datetime_immutable', ['notnull' => false]);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['user_id', 'status'], 'idx_holiday_tschange_user_status');
        $table->addIndex(['timesheet_id'], 'idx_holiday_tschange_timesheet');
        $table->addForeignKeyConstraint('kimai2_users', ['user_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_HOLIDAY_TSCHANGE_USER');
        $table->addForeignKeyConstraint('kimai2_users', ['requested_by_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_HOLIDAY_TSCHANGE_REQUESTER');
        $table->addForeignKeyConstraint('kimai2_users', ['approved_by_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_HOLIDAY_TSCHANGE_APPROVER');
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable(self::CHANGE)) {
            $this->preventEmptyMigrationWarning();

            return;
        }

        $schema->dropTable(self::CHANGE);
    }
}
