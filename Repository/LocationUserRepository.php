<?php

namespace KimaiPlugin\HolidayBundle\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use KimaiPlugin\HolidayBundle\Entity\Location;
use KimaiPlugin\HolidayBundle\Entity\LocationUser;

/**
 * @extends ServiceEntityRepository<LocationUser>
 */
class LocationUserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LocationUser::class);
    }

    /**
     * Enabled members of the location, ordered by display name.
     *
     * @return User[]
     */
    public function findUsersOf(Location $location): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('u')
            ->addSelect("COALESCE(NULLIF(u.alias, ''), u.username) AS HIDDEN sortname")
            ->from(User::class, 'u')
            ->join(LocationUser::class, 'lu', 'WITH', 'lu.user = u')
            ->andWhere('lu.location = :location')
            ->andWhere('u.enabled = true')
            ->setParameter('location', $location)
            ->orderBy('sortname', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findLocationOf(User $user): ?Location
    {
        if ($user->getId() === null) {
            return null;
        }

        return $this->find($user->getId())?->getLocation();
    }

    /** Moves the user to the location (a user has at most one). */
    public function assign(User $user, Location $location): void
    {
        $em = $this->getEntityManager();
        $existing = $user->getId() === null ? null : $this->find($user->getId());

        if ($existing instanceof LocationUser) {
            $existing->setLocation($location);
        } else {
            $em->persist(new LocationUser($user, $location));
        }

        $em->flush();
    }

    public function unassign(User $user): void
    {
        $existing = $user->getId() === null ? null : $this->find($user->getId());
        if (!$existing instanceof LocationUser) {
            return;
        }

        $this->getEntityManager()->remove($existing);
        $this->getEntityManager()->flush();
    }
}
