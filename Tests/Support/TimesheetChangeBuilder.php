<?php

namespace KimaiPlugin\HolidayBundle\Tests\Support;

use App\Entity\Timesheet;
use App\Entity\User;
use KimaiPlugin\HolidayBundle\Entity\TimesheetChange;
use KimaiPlugin\HolidayBundle\Service\TimesheetSnapshot;

/** Builds timesheets, snapshots and TimesheetChange ids for unit tests. */
final class TimesheetChangeBuilder
{
    public const ZONE = 'Europe/Vienna';

    public static function timesheet(User $user, string $begin, string $end, int $break = 0, ?int $id = null): Timesheet
    {
        $zone = new \DateTimeZone(self::ZONE);
        $from = new \DateTime($begin, $zone);
        $to = new \DateTime($end, $zone);

        $timesheet = new Timesheet();
        $timesheet->setUser($user);
        $timesheet->setTimezone(self::ZONE);
        $timesheet->setBegin($from);
        $timesheet->setEnd($to);
        $timesheet->setDuration($to->getTimestamp() - $from->getTimestamp() - $break);
        $timesheet->setBreak($break);
        if ($id !== null) {
            (new \ReflectionProperty(Timesheet::class, 'id'))->setValue($timesheet, $id);
        }

        return $timesheet;
    }

    public static function snapshot(string $begin, string $end, int $break = 0): TimesheetSnapshot
    {
        return TimesheetSnapshot::fromTimesheet(self::timesheet(new User(), $begin, $end, $break));
    }

    public static function id(TimesheetChange $change, int $id): TimesheetChange
    {
        (new \ReflectionProperty(TimesheetChange::class, 'id'))->setValue($change, $id);

        return $change;
    }
}
