<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Entity\TimesheetChange;
use KimaiPlugin\HolidayBundle\Enum\AbsenceStatus;
use KimaiPlugin\HolidayBundle\Enum\TimesheetChangeKind;
use KimaiPlugin\HolidayBundle\Repository\TimesheetChangeRepository;

/** Late timesheet changes: recording them as requests and deciding them. */
class TimesheetChangeService
{
    public const PERMISSION = 'approve_timesheet_change';

    public function __construct(
        private readonly TimesheetChangeRepository $repository,
        private readonly TimesheetChangePolicy $policy,
        private readonly ApproverResolver $approverResolver,
        private readonly TimesheetChangeBatch $batch,
    ) {
    }

    /**
     * Called after a timesheet entry was created, changed or deleted.
     *
     * @return TimesheetChange|null null when no request is needed or the open one dissolved
     */
    public function record(
        TimesheetChangeKind $kind,
        User $owner,
        ?int $timesheetId,
        ?TimesheetSnapshot $before,
        ?TimesheetSnapshot $after,
        User $actor,
        \DateTimeImmutable $now,
    ): ?TimesheetChange {
        // a further change to an entry with an open request belongs to that request
        $open = $timesheetId === null ? null : $this->repository->findOpenForTimesheet($timesheetId);
        if ($open !== null) {
            return $this->merge($open, $kind, $after);
        }

        if ($kind === TimesheetChangeKind::UPDATE && $before !== null && $after !== null && $before->sameTimes($after)) {
            return null;
        }

        $day = $this->lateDay($owner, $before, $now) ?? $this->lateDay($owner, $after, $now);
        if ($day === null) {
            return null;
        }

        $change = (new TimesheetChange())
            ->setUser($owner)
            ->setTimesheetId($timesheetId)
            ->setDay($day)
            ->setKind($kind)
            ->setBefore($before)
            ->setAfter($after)
            ->setRequestedBy($actor);

        $event = $this->decideAtOnce($change, $owner, $actor, $now);
        $this->repository->save($change);
        if ($event !== null) {
            $this->batch->add($change, $event);
        }

        return $change;
    }

    private function merge(TimesheetChange $open, TimesheetChangeKind $kind, ?TimesheetSnapshot $after): ?TimesheetChange
    {
        $before = $open->getBefore();

        if ($kind === TimesheetChangeKind::DELETE) {
            // the entry never existed before the request: nothing is left to approve
            if ($before === null) {
                $this->repository->remove($open);

                return null;
            }

            $open->setKind(TimesheetChangeKind::DELETE)->setAfter(null);
            $this->repository->save($open);

            return $open;
        }

        // back to where it started
        if ($before !== null && $after !== null && $before->sameTimes($after)) {
            $this->repository->remove($open);

            return null;
        }

        $open->setAfter($after);
        $this->repository->save($open);

        return $open;
    }

    /** The day of the snapshot when it is past the limit, in the timezone of the person. */
    private function lateDay(User $owner, ?TimesheetSnapshot $snapshot, \DateTimeImmutable $now): ?\DateTimeImmutable
    {
        $begin = $snapshot?->beginAt();
        if ($snapshot === null || $begin === null || !$this->policy->isLate($begin, $owner, $now)) {
            return null;
        }

        return $snapshot->day(new \DateTimeZone($owner->getTimezone()));
    }

    /**
     * @return string|null the mail event, null when nobody has to be told
     */
    private function decideAtOnce(TimesheetChange $change, User $owner, User $actor, \DateTimeImmutable $now): ?string
    {
        // a team lead entering times for the team has decided already
        if ($this->approverResolver->isApproverOf($actor, $owner, self::PERMISSION)) {
            $change->setStatus(AbsenceStatus::APPROVED)->setApprovedBy($actor)->setApprovedAt($now);

            return TimesheetChangeBatch::APPROVED;
        }

        // nobody else could decide: the request would stay open forever
        if ($this->approverResolver->approversFor($owner, self::PERMISSION) === []) {
            $change->setStatus(AbsenceStatus::APPROVED)->setApprovedAt($now);

            return null;
        }

        $change->setStatus(AbsenceStatus::REQUESTED);

        return TimesheetChangeBatch::REQUESTED;
    }
}
