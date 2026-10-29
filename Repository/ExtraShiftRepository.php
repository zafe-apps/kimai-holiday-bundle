<?php

namespace KimaiPlugin\HolidayBundle\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use KimaiPlugin\HolidayBundle\Entity\ExtraShift;

/**
 * @extends ServiceEntityRepository<ExtraShift>
 */
class ExtraShiftRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExtraShift::class);
    }

    /**
     * @param User[] $users
     * @return ExtraShift[]
     */
    public function findForUsersBetween(array $users, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        if ($users === []) {
            return [];
        }

        return $this->createQueryBuilder('s')
            ->andWhere('s.user IN (:users)')
            ->andWhere('s.date >= :from')
            ->andWhere('s.date <= :to')
            ->setParameter('users', $users)
            ->setParameter('from', $from, 'date_immutable')
            ->setParameter('to', $to, 'date_immutable')
            ->orderBy('s.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByUserAndDate(User $user, \DateTimeInterface $date): ?ExtraShift
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.user = :user')
            ->andWhere('s.date = :date')
            ->setParameter('user', $user)
            ->setParameter('date', $date, 'date_immutable')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function save(ExtraShift $shift, bool $flush = true): void
    {
        $this->getEntityManager()->persist($shift);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ExtraShift $shift, bool $flush = true): void
    {
        $this->getEntityManager()->remove($shift);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
