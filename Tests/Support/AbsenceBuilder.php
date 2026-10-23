<?php

namespace KimaiPlugin\HolidayBundle\Tests\Support;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Entity\Absence;
use KimaiPlugin\HolidayBundle\Enum\AbsenceStatus;
use KimaiPlugin\HolidayBundle\Enum\AbsenceType;

/** Builds approved Absence objects for unit tests. */
final class AbsenceBuilder
{
    public static function vacation(User $user, string $startYmd, string $endYmd): Absence
    {
        return (new Absence())
            ->setUser($user)
            ->setType(AbsenceType::VACATION)
            ->setStatus(AbsenceStatus::APPROVED)
            ->setStartDate(new \DateTimeImmutable($startYmd))
            ->setEndDate(new \DateTimeImmutable($endYmd))
            ->setHalfDay(false);
    }

    public static function requested(User $user, string $startYmd, string $endYmd): Absence
    {
        return self::vacation($user, $startYmd, $endYmd)->setStatus(AbsenceStatus::REQUESTED);
    }
}
