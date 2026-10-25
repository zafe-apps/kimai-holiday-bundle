<?php

namespace KimaiPlugin\HolidayBundle\Tests\Entity;

use KimaiPlugin\HolidayBundle\Entity\LocationOpeningHours;
use KimaiPlugin\HolidayBundle\Entity\PublicHolidayGroup;
use KimaiPlugin\HolidayBundle\Enum\LocationExceptionKind;
use KimaiPlugin\HolidayBundle\Tests\Support\LocationBuilder;
use PHPUnit\Framework\TestCase;

final class LocationTest extends TestCase
{
    public function testOpeningHoursForWeekday(): void
    {
        $loc = LocationBuilder::make()->open(3, '14:00', '21:00')->open(6, '12:00', '21:00')->build();

        self::assertSame('12:00', $loc->getOpeningHoursFor(6)?->getOpensAt()->format('H:i'));
        self::assertSame('21:00', $loc->getOpeningHoursFor(3)?->getClosesAt()->format('H:i'));
        self::assertNull($loc->getOpeningHoursFor(1));
    }

    public function testSetOpeningHoursMergesByWeekday(): void
    {
        $builder = LocationBuilder::make();
        for ($day = 1; $day <= 3; ++$day) {
            $builder->open($day, '09:00', '17:00');
        }
        $loc = $builder->build();
        $kept = $loc->getOpeningHoursFor(3);
        $removed = $loc->getOpeningHoursFor(1);

        $loc->setOpeningHours([
            self::row(2, '10:00', '12:00'),
            self::row(3, '11:00', '19:00'),
            self::row(7, '08:00', '09:00'),
        ]);

        self::assertCount(3, $loc->getOpeningHours());
        self::assertSame($kept, $loc->getOpeningHoursFor(3));
        self::assertSame('11:00', $kept?->getOpensAt()->format('H:i'));
        self::assertSame('19:00', $kept?->getClosesAt()->format('H:i'));
        self::assertNull($loc->getOpeningHoursFor(1));
        self::assertFalse($loc->getOpeningHours()->contains($removed));
        self::assertSame($loc, $loc->getOpeningHoursFor(7)?->getLocation());
    }

    private static function row(int $weekday, string $from, string $to): LocationOpeningHours
    {
        return (new LocationOpeningHours())
            ->setWeekday($weekday)
            ->setOpensAt(new \DateTimeImmutable($from))
            ->setClosesAt(new \DateTimeImmutable($to));
    }

    public function testVienna(): void
    {
        $loc = LocationBuilder::vienna(new PublicHolidayGroup());

        self::assertCount(4, $loc->getOpeningHours());
        self::assertSame('14:00', $loc->getOpeningHoursFor(5)?->getOpensAt()->format('H:i'));
        self::assertSame('12:00', $loc->getOpeningHoursFor(6)?->getOpensAt()->format('H:i'));
        self::assertNull($loc->getOpeningHoursFor(7));
    }

    public function testExceptions(): void
    {
        $loc = LocationBuilder::make()->named('Wien')->closedOn('2026-12-24', 'Heiligabend')->openOn('2026-12-27', '10:00', '14:00')->build();

        self::assertSame('Wien', (string) $loc);
        self::assertCount(2, $loc->getExceptions());
        $first = $loc->getExceptions()->first();
        self::assertSame(LocationExceptionKind::CLOSED, $first->getKind());
        self::assertSame('Heiligabend', $first->getComment());
        $second = $loc->getExceptions()->last();
        self::assertSame(LocationExceptionKind::OPEN, $second->getKind());
        self::assertSame('14:00', $second->getClosesAt()?->format('H:i'));
    }
}
