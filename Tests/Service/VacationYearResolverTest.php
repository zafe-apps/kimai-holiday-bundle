<?php

namespace KimaiPlugin\HolidayBundle\Tests\Service;

use KimaiPlugin\HolidayBundle\Service\VacationYearResolver;
use KimaiPlugin\HolidayBundle\Tests\Support\UserBuilder;
use PHPUnit\Framework\TestCase;

final class VacationYearResolverTest extends TestCase
{
    public function testYearStartsOnAnniversary(): void
    {
        $user = UserBuilder::make()->startingOn('2024-03-15')->build();
        $y = $this->resolver()->forDate($user, new \DateTimeImmutable('2026-10-23'));

        self::assertSame('2026-03-15', $y->start->format('Y-m-d'));
        self::assertSame('2027-03-14', $y->end->format('Y-m-d'));
        self::assertSame(2, $y->index);
        self::assertSame('15.03.2026 – 14.03.2027', $y->label());
    }

    public function testDayBeforeAnniversaryBelongsToPreviousYear(): void
    {
        $user = UserBuilder::make()->startingOn('2024-03-15')->build();

        self::assertSame(1, $this->resolver()->forDate($user, new \DateTimeImmutable('2026-03-14'))->index);
        self::assertSame(2, $this->resolver()->forDate($user, new \DateTimeImmutable('2026-03-15'))->index);
    }

    public function testPreviousIsNullBeforeStart(): void
    {
        $user = UserBuilder::make()->startingOn('2024-03-15')->build();
        $first = $this->resolver()->forDate($user, new \DateTimeImmutable('2024-06-01'));

        self::assertSame(0, $first->index);
        self::assertNull($this->resolver()->previous($user, $first));
    }

    public function testDateBeforeStartFallsIntoFirstYear(): void
    {
        $user = UserBuilder::make()->startingOn('2024-03-15')->build();

        self::assertSame(0, $this->resolver()->forDate($user, new \DateTimeImmutable('2023-01-01'))->index);
    }

    public function testPreviousAndNextAreAdjacent(): void
    {
        $user = UserBuilder::make()->startingOn('2024-03-15')->build();
        $y = $this->resolver()->forDate($user, new \DateTimeImmutable('2026-10-23'));
        $prev = $this->resolver()->previous($user, $y);
        $next = $this->resolver()->next($user, $y);

        self::assertSame('2025-03-15', $prev?->start->format('Y-m-d'));
        self::assertSame('2027-03-15', $next->start->format('Y-m-d'));
        self::assertSame(3, $next->index);
    }

    public function testContains(): void
    {
        $user = UserBuilder::make()->startingOn('2024-03-15')->build();
        $y = $this->resolver()->forDate($user, new \DateTimeImmutable('2026-10-23'));

        self::assertTrue($y->contains(new \DateTimeImmutable('2027-03-14 18:00')));
        self::assertFalse($y->contains(new \DateTimeImmutable('2027-03-15')));
        self::assertFalse($y->contains(new \DateTimeImmutable('2026-03-14')));
    }

    public function testFallsBackToCalendarYearWithoutStart(): void
    {
        $user = UserBuilder::make()->build();
        $y = $this->resolver()->forDate($user, new \DateTimeImmutable('2026-10-23'));

        self::assertTrue($this->resolver()->isCalendarFallback($user));
        self::assertSame('2026-01-01', $y->start->format('Y-m-d'));
        self::assertSame('2026-12-31', $y->end->format('Y-m-d'));
        self::assertSame(2026, $y->index);
        self::assertSame(2025, $this->resolver()->previous($user, $y)?->index);
        self::assertSame(2027, $this->resolver()->next($user, $y)->index);
    }

    public function testLeapDayAnniversary(): void
    {
        $user = UserBuilder::make()->startingOn('2024-02-29')->build();
        $y = $this->resolver()->forDate($user, new \DateTimeImmutable('2025-06-01'));

        self::assertSame('2025-02-28', $y->start->format('Y-m-d')); // kein 29.02. → letzter Februartag
        self::assertSame('2026-02-27', $y->end->format('Y-m-d'));
        self::assertSame(1, $y->index);

        $leap = $this->resolver()->forDate($user, new \DateTimeImmutable('2028-03-01'));
        self::assertSame('2028-02-29', $leap->start->format('Y-m-d'));
        self::assertSame('2029-02-27', $leap->end->format('Y-m-d'));
    }

    public function testForIndex(): void
    {
        $user = UserBuilder::make()->startingOn('2024-03-15')->build();

        self::assertSame('2026-03-15', $this->resolver()->forIndex($user, 2)->start->format('Y-m-d'));
        self::assertSame(0, $this->resolver()->forIndex($user, -3)->index);
    }

    private function resolver(): VacationYearResolver
    {
        return new VacationYearResolver();
    }
}
