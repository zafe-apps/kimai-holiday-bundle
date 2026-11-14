<?php

namespace KimaiPlugin\HolidayBundle\Tests\Service;

use App\Entity\Timesheet;
use KimaiPlugin\HolidayBundle\Service\TimesheetSnapshot;
use PHPUnit\Framework\TestCase;

final class TimesheetSnapshotTest extends TestCase
{
    public function testArrayRoundTrip(): void
    {
        $snapshot = TimesheetSnapshot::fromTimesheet($this->timesheet('2026-10-20 08:00', '2026-10-20 12:30', 900));
        $copy = TimesheetSnapshot::fromArray(json_decode((string) json_encode($snapshot->toArray()), true));

        self::assertEquals($snapshot, $copy);
        self::assertSame('2026-10-20T08:00:00+02:00', $snapshot->begin);
        self::assertSame(900, $copy->break);
        self::assertSame('Büro', $copy->description);
    }

    public function testSameTimesIgnoresDescription(): void
    {
        $a = $this->timesheet('2026-10-20 08:00', '2026-10-20 12:00', 0);
        $b = $this->timesheet('2026-10-20 08:00', '2026-10-20 12:00', 0);
        $b->setDescription('anders');

        self::assertTrue(TimesheetSnapshot::fromTimesheet($a)->sameTimes(TimesheetSnapshot::fromTimesheet($b)));
    }

    public function testSameTimesSeesBreakAndEnd(): void
    {
        $base = TimesheetSnapshot::fromTimesheet($this->timesheet('2026-10-20 08:00', '2026-10-20 12:00', 0));

        self::assertFalse($base->sameTimes(TimesheetSnapshot::fromTimesheet($this->timesheet('2026-10-20 08:00', '2026-10-20 12:00', 600))));
        self::assertFalse($base->sameTimes(TimesheetSnapshot::fromTimesheet($this->timesheet('2026-10-20 08:00', '2026-10-20 13:00', 0))));
    }

    public function testOriginalDataGivesTheOldTimes(): void
    {
        $current = $this->timesheet('2026-10-20 09:00', '2026-10-20 12:00', 0);
        $original = [
            'begin' => new \DateTime('2026-10-20 06:00', new \DateTimeZone('UTC')),
            'end' => new \DateTime('2026-10-20 10:00', new \DateTimeZone('UTC')),
            'duration' => 14400,
            'break' => 0,
            'timezone' => 'Europe/Vienna',
            'description' => 'alt',
            'billable' => true,
        ];

        $before = TimesheetSnapshot::fromOriginal($original, $current);

        self::assertSame('2026-10-20T08:00:00+02:00', $before->begin);
        self::assertSame('2026-10-20T12:00:00+02:00', $before->end);
        self::assertSame(14400, $before->duration);
        self::assertSame('alt', $before->description);
    }

    public function testSummaryNamesTheDayOnlyWhenItMoved(): void
    {
        $third = TimesheetSnapshot::fromTimesheet($this->timesheet('2026-10-03 08:00', '2026-10-03 16:00', 0));
        $tenth = TimesheetSnapshot::fromTimesheet($this->timesheet('2026-10-10 08:00', '2026-10-10 16:00', 0));

        self::assertSame('08:00–16:00 (8:00 h)', $third->summary());
        self::assertSame('08:00–16:00 (8:00 h)', $third->summary($third));
        self::assertSame('2026-10-03 08:00–16:00 (8:00 h)', $third->summary($tenth));
        self::assertSame('2026-10-10 08:00–16:00 (8:00 h)', $tenth->summary($third));
    }

    public function testDayInTimezone(): void
    {
        $snapshot = TimesheetSnapshot::fromTimesheet($this->timesheet('2026-10-20 00:30', '2026-10-20 02:00', 0));

        self::assertSame('2026-10-20', $snapshot->day(new \DateTimeZone('Europe/Vienna'))->format('Y-m-d'));
        self::assertSame('2026-10-19', $snapshot->day(new \DateTimeZone('UTC'))->format('Y-m-d'));
    }

    private function timesheet(string $begin, string $end, int $break): Timesheet
    {
        $zone = new \DateTimeZone('Europe/Vienna');
        $timesheet = new Timesheet();
        $timesheet->setTimezone('Europe/Vienna');
        $timesheet->setBegin(new \DateTime($begin, $zone));
        $timesheet->setEnd(new \DateTime($end, $zone));
        $timesheet->setDuration((new \DateTime($end, $zone))->getTimestamp() - (new \DateTime($begin, $zone))->getTimestamp() - $break);
        $timesheet->setBreak($break);
        $timesheet->setDescription('Büro');

        return $timesheet;
    }
}
