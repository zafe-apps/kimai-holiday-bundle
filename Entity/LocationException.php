<?php

namespace KimaiPlugin\HolidayBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use KimaiPlugin\HolidayBundle\Enum\LocationExceptionKind;
use KimaiPlugin\HolidayBundle\Repository\LocationExceptionRepository;

/** A date on which a location is closed or opens with other hours than usual. */
#[ORM\Entity(repositoryClass: LocationExceptionRepository::class)]
#[ORM\Table(name: 'kimai2_ext_holiday_location_exception')]
#[ORM\UniqueConstraint(name: 'uniq_holiday_location_date', columns: ['location_id', 'exception_date'])]
class LocationException
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Location::class, inversedBy: 'exceptions')]
    #[ORM\JoinColumn(name: 'location_id', nullable: false, onDelete: 'CASCADE')]
    private ?Location $location = null;

    #[ORM\Column(name: 'exception_date', type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $date = null;

    #[ORM\Column(type: Types::STRING, length: 16, enumType: LocationExceptionKind::class)]
    private LocationExceptionKind $kind = LocationExceptionKind::CLOSED;

    #[ORM\Column(name: 'opens_at', type: Types::TIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $opensAt = null;

    #[ORM\Column(name: 'closes_at', type: Types::TIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $closesAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

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

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getKind(): LocationExceptionKind
    {
        return $this->kind;
    }

    public function setKind(LocationExceptionKind $kind): self
    {
        $this->kind = $kind;

        return $this;
    }

    public function getOpensAt(): ?\DateTimeImmutable
    {
        return $this->opensAt;
    }

    public function setOpensAt(?\DateTimeImmutable $opensAt): self
    {
        $this->opensAt = $opensAt;

        return $this;
    }

    public function getClosesAt(): ?\DateTimeImmutable
    {
        return $this->closesAt;
    }

    public function setClosesAt(?\DateTimeImmutable $closesAt): self
    {
        $this->closesAt = $closesAt;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }
}
