<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Entity\Location;
use KimaiPlugin\HolidayBundle\Repository\LocationRepository;
use KimaiPlugin\HolidayBundle\Repository\LocationUserRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Who sees which locations: view_location/edit_location see all, view_availability only the own one.
 */
final class LocationPermissions
{
    public function __construct(
        private readonly Security $security,
        private readonly LocationRepository $locations,
        private readonly LocationUserRepository $members,
    ) {
    }

    public function canView(): bool
    {
        return $this->security->isGranted('view_location') || $this->security->isGranted('edit_location');
    }

    public function canEdit(): bool
    {
        return $this->security->isGranted('edit_location');
    }

    public function canViewAvailabilityOf(Location $location): bool
    {
        if ($this->canView()) {
            return true;
        }
        if (!$this->security->isGranted('view_availability')) {
            return false;
        }

        $own = $this->ownLocation();

        return $own !== null && ($own === $location || ($own->getId() !== null && $own->getId() === $location->getId()));
    }

    /**
     * @return Location[]
     */
    public function visibleLocations(): array
    {
        if ($this->canView()) {
            return $this->locations->findAllOrdered();
        }
        if (!$this->security->isGranted('view_availability')) {
            return [];
        }

        $own = $this->ownLocation();

        return $own !== null ? [$own] : [];
    }

    private function ownLocation(): ?Location
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $this->members->findLocationOf($user) : null;
    }
}
