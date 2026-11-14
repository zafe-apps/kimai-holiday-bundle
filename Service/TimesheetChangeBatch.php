<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Entity\TimesheetChange;

/**
 * Collects the timesheet changes of one request, so quick entry and bulk actions send one mail
 * per person and event instead of one per entry.
 */
class TimesheetChangeBatch
{
    public const REQUESTED = 'requested';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    /** @var array<string, array{owner: User, event: string, changes: list<TimesheetChange>}> */
    private array $groups = [];

    public function __construct(private readonly AbsenceNotifier $notifier)
    {
    }

    public function add(TimesheetChange $change, string $event): void
    {
        $owner = $change->getUser();
        if ($owner === null) {
            return;
        }

        $key = $event . ':' . spl_object_id($owner);
        $this->groups[$key] ??= ['owner' => $owner, 'event' => $event, 'changes' => []];
        $this->groups[$key]['changes'][] = $change;
    }

    public function hasRequested(): bool
    {
        foreach ($this->groups as $group) {
            if ($group['event'] === self::REQUESTED) {
                return true;
            }
        }

        return false;
    }

    public function flushMails(): void
    {
        $groups = $this->groups;
        $this->groups = [];

        foreach ($groups as $group) {
            $first = $group['changes'][0];
            $actor = $group['event'] === self::REQUESTED ? $first->getRequestedBy() : $first->getApprovedBy();
            $this->notifier->notifyTimesheetChanges($group['owner'], $group['event'], $group['changes'], $actor);
        }
    }
}
