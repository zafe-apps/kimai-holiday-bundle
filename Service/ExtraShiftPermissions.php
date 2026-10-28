<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Entity\ExtraShift;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Who may create or delete extra shifts. For other users the permission only applies to users
 * the current user may access (access_user).
 */
final class ExtraShiftPermissions
{
    public function __construct(private readonly Security $security)
    {
    }

    public function canCreateFor(?User $user): bool
    {
        return $this->check($user, 'extra_shift_own', 'extra_shift_other');
    }

    public function canCreateForOthers(): bool
    {
        return $this->security->isGranted('extra_shift_other');
    }

    public function canDelete(ExtraShift $shift): bool
    {
        $current = $this->current();
        if ($current !== null && $shift->getCreatedBy() === $current) {
            return true;
        }

        return $this->check($shift->getUser(), 'extra_shift_own', 'extra_shift_other');
    }

    private function check(?User $user, string $own, string $other): bool
    {
        if ($user === null) {
            return false;
        }
        if ($user === $this->current()) {
            return $this->security->isGranted($own);
        }

        return $this->security->isGranted($other) && $this->security->isGranted('access_user', $user);
    }

    private function current(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }
}
