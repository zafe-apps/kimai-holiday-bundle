<?php

namespace KimaiPlugin\HolidayBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use KimaiPlugin\HolidayBundle\Repository\LocationRepository;

#[ORM\Entity(repositoryClass: LocationRepository::class)]
#[ORM\Table(name: 'kimai2_ext_holiday_location')]
class Location
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $name = '';

    #[ORM\ManyToOne(targetEntity: PublicHolidayGroup::class)]
    #[ORM\JoinColumn(name: 'public_holiday_group_id', nullable: false, onDelete: 'RESTRICT')]
    private ?PublicHolidayGroup $publicHolidayGroup = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $active = true;

    /** @var Collection<int, LocationOpeningHours> */
    #[ORM\OneToMany(mappedBy: 'location', targetEntity: LocationOpeningHours::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $openingHours;

    /** @var Collection<int, LocationException> */
    #[ORM\OneToMany(mappedBy: 'location', targetEntity: LocationException::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $exceptions;

    public function __construct()
    {
        $this->openingHours = new ArrayCollection();
        $this->exceptions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getPublicHolidayGroup(): ?PublicHolidayGroup
    {
        return $this->publicHolidayGroup;
    }

    public function setPublicHolidayGroup(PublicHolidayGroup $group): self
    {
        $this->publicHolidayGroup = $group;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    /**
     * @return Collection<int, LocationOpeningHours>
     */
    public function getOpeningHours(): Collection
    {
        return $this->openingHours;
    }

    /** ISO weekday: Monday = 1 ... Sunday = 7. */
    public function getOpeningHoursFor(int $isoWeekday): ?LocationOpeningHours
    {
        foreach ($this->openingHours as $hours) {
            if ($hours->getWeekday() === $isoWeekday) {
                return $hours;
            }
        }

        return null;
    }

    public function addOpeningHours(LocationOpeningHours $hours): self
    {
        if (!$this->openingHours->contains($hours)) {
            $this->openingHours->add($hours);
            $hours->setLocation($this);
        }

        return $this;
    }

    /**
     * Merges the rows by weekday: existing rows keep their instance and take the new times, new weekdays
     * are added, weekdays missing in the input are removed (orphanRemoval). Reusing the instance avoids an
     * INSERT before the DELETE, which would violate the unique index (location, weekday).
     *
     * @param iterable<LocationOpeningHours> $hours
     */
    public function setOpeningHours(iterable $hours): self
    {
        $incoming = [];
        foreach ($hours as $row) {
            $incoming[$row->getWeekday()] = $row;
        }

        foreach ($this->openingHours->toArray() as $existing) {
            if (!isset($incoming[$existing->getWeekday()])) {
                $this->openingHours->removeElement($existing);
            }
        }

        foreach ($incoming as $weekday => $row) {
            $existing = $this->getOpeningHoursFor($weekday);
            if ($existing === null) {
                $this->addOpeningHours($row);

                continue;
            }
            if ($existing === $row) {
                continue;
            }

            $existing->setOpensAt($row->getOpensAt())->setClosesAt($row->getClosesAt());
        }

        return $this;
    }

    /**
     * @return Collection<int, LocationException>
     */
    public function getExceptions(): Collection
    {
        return $this->exceptions;
    }

    public function addException(LocationException $exception): self
    {
        if (!$this->exceptions->contains($exception)) {
            $this->exceptions->add($exception);
            $exception->setLocation($this);
        }

        return $this;
    }

    public function removeException(LocationException $exception): self
    {
        $this->exceptions->removeElement($exception);

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
