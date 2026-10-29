<?php

namespace KimaiPlugin\HolidayBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use KimaiPlugin\HolidayBundle\Entity\Location;
use KimaiPlugin\HolidayBundle\Entity\PublicHolidayGroup;

/**
 * @extends ServiceEntityRepository<Location>
 */
class LocationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Location::class);
    }

    /**
     * @return Location[]
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['name' => 'ASC']);
    }

    /**
     * Locations that use the holiday group (it cannot be deleted while there are any).
     *
     * @return Location[]
     */
    public function findByHolidayGroup(PublicHolidayGroup $group): array
    {
        return $this->findBy(['publicHolidayGroup' => $group], ['name' => 'ASC']);
    }

    public function save(Location $location, bool $flush = true): void
    {
        $this->getEntityManager()->persist($location);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Location $location, bool $flush = true): void
    {
        $this->getEntityManager()->remove($location);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
