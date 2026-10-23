<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;
use App\Security\RolePermissionManager;

/**
 * Thin seam over Kimai's final RolePermissionManager: who may approve whose absences.
 */
class ApproverAccess
{
    public const PERMISSION = 'approve_other_absence';

    public function __construct(private readonly RolePermissionManager $permissions)
    {
    }

    public function mayApprove(User $candidate): bool
    {
        return $this->permissions->hasRolePermission($candidate, self::PERMISSION);
    }

    public function mayAccess(User $subject, User $candidate): bool
    {
        return $this->permissions->checkUserAccess($subject, $candidate);
    }
}
