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
        private readonly TimesheetStore $store,
        private readonly PeriodLockGuard $periodLockGuard,
        private readonly TimesheetChangeGate $gate,
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

    /**
     * @throws \InvalidArgumentException flashmessages key
     */
    public function approve(TimesheetChange $change, User $approver): void
    {
        $this->assertDecidable($change, $approver);
        $this->close($change, AbsenceStatus::APPROVED, $approver, TimesheetChangeBatch::APPROVED);
    }

    /**
     * Puts the entry back to the state before the change.
     *
     * @throws \InvalidArgumentException|\RuntimeException flashmessages key
     */
    public function reject(TimesheetChange $change, User $approver): void
    {
        $this->assertDecidable($change, $approver);

        // the plugin's own write is no new late change
        $this->gate->suspend(fn () => $this->restore($change));
        $this->close($change, AbsenceStatus::REJECTED, $approver, TimesheetChangeBatch::REJECTED);
    }

    private function assertDecidable(TimesheetChange $change, User $approver): void
    {
        $owner = $change->getUser();
        $requester = $change->getRequestedBy();
        $own = $owner === null
            || $this->approverResolver->isSame($approver, $owner)
            || ($requester !== null && $this->approverResolver->isSame($approver, $requester));
        if ($own) {
            throw new \InvalidArgumentException('holiday.error.change_own');
        }
        if (!$change->isOpen()) {
            throw new \InvalidArgumentException('holiday.error.change_not_open');
        }
    }

    private function close(TimesheetChange $change, AbsenceStatus $status, User $approver, string $event): void
    {
        $change->setStatus($status)->setApprovedBy($approver)->setApprovedAt(new \DateTimeImmutable());
        $this->repository->save($change);
        $this->batch->add($change, $event);
    }

    private function restore(TimesheetChange $change): void
    {
        $owner = $change->getUser();
        $before = $change->getBefore();
        if ($owner === null) {
            return;
        }

        if ($change->getKind() === TimesheetChangeKind::DELETE) {
            $this->restoreDeleted($change, $owner, $before);

            return;
        }

        // the entry is gone by other means: nothing is left to put back
        $timesheet = $change->getTimesheetId() === null ? null : $this->store->find($change->getTimesheetId());
        if ($timesheet === null) {
            return;
        }
        if ($timesheet->isExported()) {
            throw new \InvalidArgumentException('holiday.error.change_exported');
        }
        $this->assertMonthsOpen($owner, TimesheetSnapshot::fromTimesheet($timesheet), $before);

        if ($change->getKind() === TimesheetChangeKind::CREATE || $before === null) {
            $this->store->delete($timesheet);

            return;
        }

        $this->store->applyTimes($timesheet, $before);
        $this->store->update($timesheet);
    }

    private function restoreDeleted(TimesheetChange $change, User $owner, ?TimesheetSnapshot $before): void
    {
        if ($before === null) {
            return;
        }
        $this->assertMonthsOpen($owner, $before, null);

        $timesheet = $this->store->restore($owner, $before);
        if ($timesheet === null) {
            throw new \InvalidArgumentException('holiday.error.change_target_missing');
        }

        $change->setTimesheetId($timesheet->getId());
    }

    /** Both the day the entry is on now and the day it goes back to must be open. */
    private function assertMonthsOpen(User $owner, ?TimesheetSnapshot ...$states): void
    {
        foreach ($states as $state) {
            $day = $state?->beginAt();
            if ($day !== null) {
                $this->periodLockGuard->assertOpen($owner, $day, $day);
            }
        }
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
