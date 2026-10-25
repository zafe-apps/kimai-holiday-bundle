<?php

namespace KimaiPlugin\HolidayBundle\Tests\Support;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Entity\ExtraShift;

/** Builds ExtraShift objects for unit tests. */
final class ExtraShiftBuilder
{
    public static function on(User $user, string $ymd, ?User $createdBy = null): ExtraShift
    {
        return (new ExtraShift())
            ->setUser($user)
            ->setDate(new \DateTimeImmutable($ymd))
            ->setCreatedBy($createdBy);
    }
}
