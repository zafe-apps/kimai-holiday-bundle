<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Entity\MonthLock;
use KimaiPlugin\HolidayBundle\Repository\MonthLockRepository;
use KimaiPlugin\HolidayBundle\Repository\TimesheetChangeRepository;

class MonthLockService
{
    public function __construct(
        private readonly MonthLockRepository $monthLockRepository,
        private readonly TimesheetChangeRepository $timesheetChanges,
    ) {
    }

    /**
     * Locking a month also locks all earlier months in the same year.
     *
     * @throws \RuntimeException holiday.error.month_open_changes
     */
    public function lock(User $user, int $year, int $month, User $actor): void
    {
        // a late timesheet change that is still open may yet be rejected and change these months
        $from = new \DateTimeImmutable(sprintf('%04d-01-01', $year));
        $to = (new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->modify('last day of this month');
        if ($this->timesheetChanges->hasOpenInRange($user, $from, $to)) {
            throw new \RuntimeException('holiday.error.month_open_changes');
        }

        for ($m = 1; $m <= $month; ++$m) {
            if ($this->monthLockRepository->isLocked($user, $year, $m)) {
                continue;
            }

            $lock = new MonthLock();
            $lock->setUser($user);
            $lock->setYear($year);
            $lock->setMonth($m);
            $lock->setLockedBy($actor);
            $lock->setLockedAt(new \DateTimeImmutable());
            $this->monthLockRepository->save($lock, false);
        }

        $this->monthLockRepository->flush();
    }

    /**
     * Unlocking a month also unlocks all later months in the same year.
     */
    public function unlock(User $user, int $year, int $month): void
    {
        $locks = $this->monthLockRepository->findByUserAndYear($user, $year);
        foreach ($locks as $lock) {
            if ($lock->getMonth() >= $month) {
                $this->monthLockRepository->remove($lock, false);
            }
        }

        $this->monthLockRepository->flush();
    }

    /**
     * @return array<int, bool> month => locked
     */
    public function getLocksMap(User $user, int $year): array
    {
        $map = array_fill(1, 12, false);
        foreach ($this->monthLockRepository->findByUserAndYear($user, $year) as $lock) {
            $map[$lock->getMonth()] = true;
        }

        return $map;
    }
}
