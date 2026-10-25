<?php

namespace KimaiPlugin\HolidayBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use KimaiPlugin\HolidayBundle\Entity\Location;

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
    public function findActiveOrdered(): array
    {
        return $this->findBy(['active' => true], ['name' => 'ASC']);
    }

    /**
     * @return Location[]
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['name' => 'ASC']);
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
