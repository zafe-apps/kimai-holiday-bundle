<?php

namespace KimaiPlugin\HolidayBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'kimai2_ext_holiday_location_opening_hours')]
#[ORM\UniqueConstraint(name: 'uniq_holiday_location_weekday', columns: ['location_id', 'weekday'])]
class LocationOpeningHours
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Location::class, inversedBy: 'openingHours')]
    #[ORM\JoinColumn(name: 'location_id', nullable: false, onDelete: 'CASCADE')]
    private ?Location $location = null;

    /** ISO weekday: Monday = 1 ... Sunday = 7. */
    #[ORM\Column(type: Types::SMALLINT)]
    private int $weekday = 1;

    #[ORM\Column(name: 'opens_at', type: Types::TIME_IMMUTABLE)]
    private ?\DateTimeImmutable $opensAt = null;

    #[ORM\Column(name: 'closes_at', type: Types::TIME_IMMUTABLE)]
    private ?\DateTimeImmutable $closesAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLocation(): ?Location
    {
        return $this->location;
    }

    public function setLocation(?Location $location): self
    {
        $this->location = $location;

        return $this;
    }

    public function getWeekday(): int
    {
        return $this->weekday;
    }

    public function setWeekday(int $weekday): self
    {
        $this->weekday = $weekday;

        return $this;
    }

    public function getOpensAt(): ?\DateTimeImmutable
    {
        return $this->opensAt;
    }

    public function setOpensAt(\DateTimeImmutable $opensAt): self
    {
        $this->opensAt = $opensAt;

        return $this;
    }

    public function getClosesAt(): ?\DateTimeImmutable
    {
        return $this->closesAt;
    }

    public function setClosesAt(\DateTimeImmutable $closesAt): self
    {
        $this->closesAt = $closesAt;

        return $this;
    }
}
