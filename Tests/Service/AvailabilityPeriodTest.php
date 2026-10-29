<?php

namespace KimaiPlugin\HolidayBundle\Tests\Service;

use KimaiPlugin\HolidayBundle\Service\AvailabilityPeriod;
use PHPUnit\Framework\TestCase;

final class AvailabilityPeriodTest extends TestCase
{
    public function testWeekAcrossYearBoundary(): void
    {
        $week = AvailabilityPeriod::week(2026, 53);

        self::assertSame('2026-12-28', $week->from->format('Y-m-d'));
        self::assertSame('2027-01-03', $week->to->format('Y-m-d'));
        self::assertEquals(AvailabilityPeriod::week(2027, 1), $week->next());
        self::assertEquals(AvailabilityPeriod::week(2026, 52), $week->prev());
        self::assertSame(['year' => 2026, 'week' => 53], $week->routeParams());
        self::assertSame('KW 53/2026 · 28.12.–03.01.2027', $week->label());
    }

    public function testMonthPrevNext(): void
    {
        $month = AvailabilityPeriod::month(2026, 1);

        self::assertEquals(AvailabilityPeriod::month(2025, 12), $month->prev());
        self::assertEquals(AvailabilityPeriod::month(2026, 2), $month->next());
        self::assertSame('2026-01-31', $month->to->format('Y-m-d'));
        self::assertSame('2026-02-28', $month->next()->to->format('Y-m-d'));
        self::assertSame(['year' => 2026, 'month' => 1], $month->routeParams());
        self::assertSame('Mai 2026', AvailabilityPeriod::month(2026, 5)->label(static fn (\DateTimeImmutable $d): string => 'Mai'));
    }

    public function testContainingWeekUsesIsoWeek(): void
    {
        $sunday = new \DateTimeImmutable('2026-11-01');
        $week = AvailabilityPeriod::containing($sunday, 'week');

        self::assertEquals(AvailabilityPeriod::week(2026, 44), $week);
        self::assertSame('2026-10-26', $week->from->format('Y-m-d'));
        self::assertEquals(AvailabilityPeriod::month(2026, 11), AvailabilityPeriod::containing($sunday, 'month'));
    }
}
