<?php

declare(strict_types=1);

namespace HolidayBundle\Migrations;

use App\Doctrine\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Locations (opening hours, date exceptions, members) and extra shifts.
 */
final class Version20261024100000 extends AbstractMigration
{
    private const LOCATION = 'kimai2_ext_holiday_location';
    private const HOURS = 'kimai2_ext_holiday_location_opening_hours';
    private const EXCEPTION = 'kimai2_ext_holiday_location_exception';
    private const MEMBER = 'kimai2_ext_holiday_location_user';
    private const SHIFT = 'kimai2_ext_holiday_extra_shift';

    public function getDescription(): string
    {
        return 'Locations with opening hours, exceptions and members; extra shifts';
    }

    public function up(Schema $schema): void
    {
        $created = false;

        if (!$schema->hasTable(self::LOCATION)) {
            $table = $schema->createTable(self::LOCATION);
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('name', 'string', ['length' => 100, 'notnull' => true]);
            $table->addColumn('public_holiday_group_id', 'integer', ['notnull' => true]);
            $table->addColumn('active', 'boolean', ['notnull' => true, 'default' => true]);
            $table->setPrimaryKey(['id']);
            $table->addForeignKeyConstraint('kimai2_ext_holiday_ph_group', ['public_holiday_group_id'], ['id'], ['onDelete' => 'RESTRICT'], 'FK_HOLIDAY_LOCATION_GROUP');
            $created = true;
        }

        if (!$schema->hasTable(self::HOURS)) {
            $table = $schema->createTable(self::HOURS);
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('location_id', 'integer', ['notnull' => true]);
            $table->addColumn('weekday', 'smallint', ['notnull' => true]);
            $table->addColumn('opens_at', 'time_immutable', ['notnull' => true]);
            $table->addColumn('closes_at', 'time_immutable', ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['location_id', 'weekday'], 'uniq_holiday_location_weekday');
            $table->addForeignKeyConstraint(self::LOCATION, ['location_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_HOLIDAY_HOURS_LOCATION');
            $created = true;
        }

        if (!$schema->hasTable(self::EXCEPTION)) {
            $table = $schema->createTable(self::EXCEPTION);
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('location_id', 'integer', ['notnull' => true]);
            $table->addColumn('exception_date', 'date_immutable', ['notnull' => true]);
            $table->addColumn('kind', 'string', ['length' => 16, 'notnull' => true]);
            $table->addColumn('opens_at', 'time_immutable', ['notnull' => false]);
            $table->addColumn('closes_at', 'time_immutable', ['notnull' => false]);
            $table->addColumn('comment', 'text', ['notnull' => false]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['location_id', 'exception_date'], 'uniq_holiday_location_date');
            $table->addForeignKeyConstraint(self::LOCATION, ['location_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_HOLIDAY_EXCEPTION_LOCATION');
            $created = true;
        }

        if (!$schema->hasTable(self::MEMBER)) {
            $table = $schema->createTable(self::MEMBER);
            $table->addColumn('user_id', 'integer', ['notnull' => true]);
            $table->addColumn('location_id', 'integer', ['notnull' => true]);
            $table->setPrimaryKey(['user_id']);
            $table->addForeignKeyConstraint('kimai2_users', ['user_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_HOLIDAY_MEMBER_USER');
            $table->addForeignKeyConstraint(self::LOCATION, ['location_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_HOLIDAY_MEMBER_LOCATION');
            $created = true;
        }

        if (!$schema->hasTable(self::SHIFT)) {
            $table = $schema->createTable(self::SHIFT);
            $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('user_id', 'integer', ['notnull' => true]);
            $table->addColumn('shift_date', 'date_immutable', ['notnull' => true]);
            $table->addColumn('comment', 'text', ['notnull' => false]);
            $table->addColumn('created_by_id', 'integer', ['notnull' => false]);
            $table->addColumn('created_at', 'datetime_immutable', ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['user_id', 'shift_date'], 'uniq_holiday_extra_shift_user_date');
            $table->addForeignKeyConstraint('kimai2_users', ['user_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_HOLIDAY_SHIFT_USER');
            $table->addForeignKeyConstraint('kimai2_users', ['created_by_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_HOLIDAY_SHIFT_CREATOR');
            $created = true;
        }

        if (!$created) {
            $this->preventEmptyMigrationWarning();
        }
    }

    public function down(Schema $schema): void
    {
        $dropped = false;

        // Children first: foreign keys point at the location table
        foreach ([self::SHIFT, self::MEMBER, self::EXCEPTION, self::HOURS, self::LOCATION] as $name) {
            if ($schema->hasTable($name)) {
                $schema->dropTable($name);
                $dropped = true;
            }
        }

        if (!$dropped) {
            $this->preventEmptyMigrationWarning();
        }
    }
}
