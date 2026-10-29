<?php

namespace KimaiPlugin\HolidayBundle\Tests\Service;

use KimaiPlugin\HolidayBundle\Entity\Location;
use KimaiPlugin\HolidayBundle\Entity\PublicHoliday;
use KimaiPlugin\HolidayBundle\Entity\PublicHolidayGroup;
use KimaiPlugin\HolidayBundle\Repository\LocationExceptionRepository;
use KimaiPlugin\HolidayBundle\Repository\PublicHolidayRepository;
use KimaiPlugin\HolidayBundle\Service\LocationSchedule;
use KimaiPlugin\HolidayBundle\Tests\Support\LocationBuilder;
use PHPUnit\Framework\TestCase;

final class LocationScheduleTest extends TestCase
{
    public function testViennaWeekPattern(): void
    {
        $location = LocationBuilder::vienna(new PublicHolidayGroup());
        $schedule = $this->schedule($location);

        $wed = $schedule->slotFor($location, new \DateTimeImmutable('2026-10-28'));
        self::assertTrue($wed->open);
        self::assertSame('weekly', $wed->reason);
        self::assertSame('14:00–21:00', $wed->label());

        $sat = $schedule->slotFor($location, new \DateTimeImmutable('2026-10-31'));
        self::assertTrue($sat->open);
        self::assertSame('12:00–21:00', $sat->label());

        $mon = $schedule->slotFor($location, new \DateTimeImmutable('2026-10-26'));
        self::assertFalse($mon->open);
        self::assertSame('weekly', $mon->reason);
        self::assertSame('', $mon->label());
    }

    public function testNationalHolidayClosesLocation(): void
    {
        $location = LocationBuilder::vienna(new PublicHolidayGroup());
        $schedule = $this->schedule($location, [$this->holiday('2026-05-01', 'Staatsfeiertag')]);

        $slot = $schedule->slotFor($location, new \DateTimeImmutable('2026-05-01'));
        self::assertFalse($slot->open);
        self::assertSame('public_holiday', $slot->reason);
        self::assertSame('Staatsfeiertag', $slot->holidayName);
    }

    public function testOpenExceptionOverridesHoliday(): void
    {
        $location = LocationBuilder::make()->holidayGroup(new PublicHolidayGroup())
            ->open(3, '14:00', '21:00')
            ->openOn('2026-05-01', '12:00', '18:00')
            ->build();
        $schedule = $this->schedule($location, [$this->holiday('2026-05-01', 'Staatsfeiertag')]);

        $slot = $schedule->slotFor($location, new \DateTimeImmutable('2026-05-01'));
        self::assertTrue($slot->open);
        self::assertSame('exception_open', $slot->reason);
        self::assertSame('12:00–18:00', $slot->label());
    }

    public function testClosedExceptionOnRegularDay(): void
    {
        $location = LocationBuilder::make()->holidayGroup(new PublicHolidayGroup())
            ->open(6, '12:00', '21:00')
            ->closedOn('2026-11-07')
            ->build();
        $schedule = $this->schedule($location);

        $slot = $schedule->slotFor($location, new \DateTimeImmutable('2026-11-07'));
        self::assertFalse($slot->open);
        self::assertSame('exception_closed', $slot->reason);
    }

    public function testInactiveLocationIsAlwaysClosed(): void
    {
        $location = LocationBuilder::vienna(new PublicHolidayGroup());
        $location->setActive(false);
        $schedule = $this->schedule($location);

        $slot = $schedule->slotFor($location, new \DateTimeImmutable('2026-10-28'));
        self::assertFalse($slot->open);
        self::assertSame('inactive', $slot->reason);
    }

    public function testSlotsBetweenLoadsExceptionsOnce(): void
    {
        $location = LocationBuilder::vienna(new PublicHolidayGroup());
        $exceptions = $this->createMock(LocationExceptionRepository::class);
        $exceptions->expects(self::once())->method('findBetween')->willReturn([]);
        $holidays = $this->createMock(PublicHolidayRepository::class);
        $holidays->expects(self::once())->method('findByGroupBetween')->willReturn([]);

        $slots = (new LocationSchedule($exceptions, $holidays))->slotsBetween(
            $location,
            new \DateTimeImmutable('2026-10-01'),
            new \DateTimeImmutable('2026-10-31')
        );

        self::assertCount(31, $slots);
        self::assertArrayHasKey('2026-10-15', $slots);
    }

    public function testSlotsBetweenNormalisesBoundsToMidnight(): void
    {
        $location = LocationBuilder::vienna(new PublicHolidayGroup());

        $slots = $this->schedule($location)->slotsBetween(
            $location,
            new \DateTimeImmutable('2026-10-01 10:00'),
            new \DateTimeImmutable('2026-10-31 00:00')
        );

        self::assertCount(31, $slots);
        self::assertArrayHasKey('2026-10-31', $slots);
        self::assertSame('00:00', $slots['2026-10-31']->date->format('H:i'));
        self::assertSame('00:00', $slots['2026-10-01']->date->format('H:i'));
    }

    /** @param list<PublicHoliday> $holidays */
    private function schedule(Location $location, array $holidays = []): LocationSchedule
    {
        $exceptions = $this->createStub(LocationExceptionRepository::class);
        $exceptions->method('findBetween')->willReturn($location->getExceptions()->toArray());
        $repo = $this->createStub(PublicHolidayRepository::class);
        $repo->method('findByGroupBetween')->willReturn($holidays);

        return new LocationSchedule($exceptions, $repo);
    }

    private function holiday(string $ymd, string $name, bool $half = false): PublicHoliday
    {
        return (new PublicHoliday())->setDate(new \DateTimeImmutable($ymd))->setName($name)->setHalfDay($half);
    }
}
