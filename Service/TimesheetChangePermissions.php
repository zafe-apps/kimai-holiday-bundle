<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Entity\TimesheetChange;

/** Who may decide a late timesheet change. */
class TimesheetChangePermissions
{
    public function __construct(private readonly ApproverResolver $approverResolver)
    {
    }

    public function canDecide(User $actor, TimesheetChange $change): bool
    {
        $owner = $change->getUser();
        if ($owner === null || $this->approverResolver->isSame($actor, $owner)) {
            return false;
        }

        // whoever made the change does not approve it either
        $requester = $change->getRequestedBy();
        if ($requester !== null && $this->approverResolver->isSame($actor, $requester)) {
            return false;
        }

        return $this->approverResolver->isApproverOf($actor, $owner, TimesheetChangeService::PERMISSION);
    }
}
