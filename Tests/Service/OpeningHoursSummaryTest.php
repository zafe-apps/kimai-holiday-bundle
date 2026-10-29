<?php

namespace KimaiPlugin\HolidayBundle\Tests\Service;

use KimaiPlugin\HolidayBundle\Entity\Location;
use KimaiPlugin\HolidayBundle\Entity\LocationOpeningHours;
use KimaiPlugin\HolidayBundle\Service\OpeningHoursSummary;
use PHPUnit\Framework\TestCase;

final class OpeningHoursSummaryTest extends TestCase
{
    public function testViennaPattern(): void
    {
        $location = $this->location([3 => ['14:00', '21:00'], 4 => ['14:00', '21:00'], 5 => ['14:00', '21:00'], 6 => ['12:00', '21:00']]);

        $this->assertSame('Mi–Fr 14:00–21:00 · Sa 12:00–21:00', (new OpeningHoursSummary())->format($location, self::names()));
    }

    public function testClosedLocationIsEmpty(): void
    {
        $this->assertSame('', (new OpeningHoursSummary())->format($this->location([]), self::names()));
    }

    public function testNonConsecutiveDaysAreListedSeparately(): void
    {
        $location = $this->location([1 => ['09:00', '17:00'], 3 => ['09:00', '17:00'], 7 => ['10:00', '14:00']]);

        $this->assertSame('Mo 09:00–17:00 · Mi 09:00–17:00 · So 10:00–14:00', (new OpeningHoursSummary())->format($location, self::names()));
    }

    /** @return array<int, string> short weekday names */
    private static function names(): array
    {
        return [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'];
    }

    /** @param array<int, array{string, string}> $days */
    private function location(array $days): Location
    {
        $location = new Location();
        foreach ($days as $weekday => [$open, $close]) {
            $location->addOpeningHours(
                (new LocationOpeningHours())
                    ->setWeekday($weekday)
                    ->setOpensAt(new \DateTimeImmutable($open))
                    ->setClosesAt(new \DateTimeImmutable($close))
            );
        }

        return $location;
    }
}
