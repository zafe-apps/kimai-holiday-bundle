<?php

namespace KimaiPlugin\HolidayBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use KimaiPlugin\HolidayBundle\Entity\Location;
use KimaiPlugin\HolidayBundle\Entity\LocationException;

/**
 * @extends ServiceEntityRepository<LocationException>
 */
class LocationExceptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LocationException::class);
    }

    /**
     * @return LocationException[]
     */
    public function findBetween(Location $location, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.location = :location')
            ->andWhere('e.date >= :from')
            ->andWhere('e.date <= :to')
            ->setParameter('location', $location)
            ->setParameter('from', $from, 'date_immutable')
            ->setParameter('to', $to, 'date_immutable')
            ->orderBy('e.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneOn(Location $location, \DateTimeImmutable $date): ?LocationException
    {
        return $this->findOneBy(['location' => $location, 'date' => $date]);
    }

    public function save(LocationException $exception): void
    {
        $this->getEntityManager()->persist($exception);
        $this->getEntityManager()->flush();
    }

    public function remove(LocationException $exception): void
    {
        $exception->getLocation()?->removeException($exception);
        $this->getEntityManager()->remove($exception);
        $this->getEntityManager()->flush();
    }
}
