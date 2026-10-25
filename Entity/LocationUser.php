<?php

namespace KimaiPlugin\HolidayBundle\Entity;

use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use KimaiPlugin\HolidayBundle\Repository\LocationUserRepository;

/** Assigns a user to one location (a user belongs to at most one). */
#[ORM\Entity(repositoryClass: LocationUserRepository::class)]
#[ORM\Table(name: 'kimai2_ext_holiday_location_user')]
class LocationUser
{
    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Location::class)]
    #[ORM\JoinColumn(name: 'location_id', nullable: false, onDelete: 'CASCADE')]
    private Location $location;

    public function __construct(User $user, Location $location)
    {
        $this->user = $user;
        $this->location = $location;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getLocation(): Location
    {
        return $this->location;
    }

    public function setLocation(Location $location): self
    {
        $this->location = $location;

        return $this;
    }
}
