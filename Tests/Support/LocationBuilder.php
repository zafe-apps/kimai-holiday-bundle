<?php

namespace KimaiPlugin\HolidayBundle\Tests\Support;

use KimaiPlugin\HolidayBundle\Entity\Location;
use KimaiPlugin\HolidayBundle\Entity\LocationException;
use KimaiPlugin\HolidayBundle\Entity\LocationOpeningHours;
use KimaiPlugin\HolidayBundle\Entity\PublicHolidayGroup;
use KimaiPlugin\HolidayBundle\Enum\LocationExceptionKind;

/** Builds Location objects (opening hours, exceptions) for unit tests. */
final class LocationBuilder
{
    private Location $location;

    private function __construct()
    {
        $this->location = new Location();
        $this->location->setName('Standort');
    }

    public static function make(): self
    {
        return new self();
    }

    /** Wed-Fri 14:00-21:00, Sat 12:00-21:00. */
    public static function vienna(PublicHolidayGroup $group): Location
    {
        return self::make()
            ->named('Wien')
            ->holidayGroup($group)
            ->open(3, '14:00', '21:00')
            ->open(4, '14:00', '21:00')
            ->open(5, '14:00', '21:00')
            ->open(6, '12:00', '21:00')
            ->build();
    }

    public function named(string $name): self
    {
        $this->location->setName($name);

        return $this;
    }

    public function holidayGroup(PublicHolidayGroup $group): self
    {
        $this->location->setPublicHolidayGroup($group);

        return $this;
    }

    public function open(int $isoWeekday, string $from, string $to): self
    {
        $this->location->addOpeningHours(
            (new LocationOpeningHours())
                ->setWeekday($isoWeekday)
                ->setOpensAt(new \DateTimeImmutable($from))
                ->setClosesAt(new \DateTimeImmutable($to))
        );

        return $this;
    }

    public function closedOn(string $ymd, ?string $comment = null): self
    {
        $this->location->addException(
            (new LocationException())
                ->setDate(new \DateTimeImmutable($ymd))
                ->setKind(LocationExceptionKind::CLOSED)
                ->setComment($comment)
        );

        return $this;
    }

    public function openOn(string $ymd, string $from, string $to): self
    {
        $this->location->addException(
            (new LocationException())
                ->setDate(new \DateTimeImmutable($ymd))
                ->setKind(LocationExceptionKind::OPEN)
                ->setOpensAt(new \DateTimeImmutable($from))
                ->setClosesAt(new \DateTimeImmutable($to))
        );

        return $this;
    }

    public function id(int $id): self
    {
        $property = new \ReflectionProperty(Location::class, 'id');
        $property->setValue($this->location, $id);

        return $this;
    }

    public function build(): Location
    {
        return $this->location;
    }
}
