<?php

namespace KimaiPlugin\HolidayBundle\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use KimaiPlugin\HolidayBundle\Entity\TimesheetChange;
use KimaiPlugin\HolidayBundle\Enum\AbsenceStatus;

/**
 * @extends ServiceEntityRepository<TimesheetChange>
 */
class TimesheetChangeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TimesheetChange::class);
    }

    public function findOpenForTimesheet(int $timesheetId): ?TimesheetChange
    {
        return $this->findOneBy(['timesheetId' => $timesheetId, 'status' => AbsenceStatus::REQUESTED]);
    }

    /**
     * Open requests of the given users, oldest day first.
     *
     * @param User[] $users
     * @return TimesheetChange[]
     */
    public function findRequestedForUsers(array $users): array
    {
        if ($users === []) {
            return [];
        }

        return $this->createQueryBuilder('c')
            ->andWhere('c.user IN (:users)')
            ->andWhere('c.status = :status')
            ->setParameter('users', $users)
            ->setParameter('status', AbsenceStatus::REQUESTED->value)
            ->orderBy('c.day', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function hasOpenInRange(User $user, \DateTimeInterface $from, \DateTimeInterface $to): bool
    {
        $count = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.user = :user')
            ->andWhere('c.status = :status')
            ->andWhere('c.day >= :from AND c.day <= :to')
            ->setParameter('user', $user)
            ->setParameter('status', AbsenceStatus::REQUESTED->value)
            ->setParameter('from', $from, 'date_immutable')
            ->setParameter('to', $to, 'date_immutable')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    /**
     * The person's own latest requests, newest first.
     *
     * @return TimesheetChange[]
     */
    public function findOwnRecent(User $user, int $limit): array
    {
        return $this->findBy(['user' => $user], ['id' => 'DESC'], $limit);
    }

    public function save(TimesheetChange $change, bool $flush = true): void
    {
        $this->getEntityManager()->persist($change);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(TimesheetChange $change, bool $flush = true): void
    {
        $this->getEntityManager()->remove($change);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
