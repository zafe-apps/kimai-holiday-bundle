<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Enum\AbsenceType;
use KimaiPlugin\HolidayBundle\Repository\AbsenceRepository;
use KimaiPlugin\HolidayBundle\Repository\ManualBookingRepository;

/**
 * Vacation account per vacation year: entitlement + carry-over + manual corrections − used days.
 * The carry-over is the balance of the previous year, chained from the contract start.
 */
class VacationAccountService
{
    public function __construct(
        private readonly VacationYearResolver $resolver,
        private readonly UserWorkContract $userWorkContract,
        private readonly AbsenceRepository $absenceRepository,
        private readonly ManualBookingRepository $manualBookingRepository,
        private readonly AbsenceWorkdayHelper $workdayHelper,
    ) {
    }

    public function calculate(User $user, VacationYear $year): VacationAccount
    {
        // Without a start date there is no first year to chain from: no carry-over.
        $chain = [$year];
        if (!$this->resolver->isCalendarFallback($user)) {
            for ($prev = $this->resolver->previous($user, $year); $prev !== null; $prev = $this->resolver->previous($user, $prev)) {
                array_unshift($chain, $prev);
            }
        }

        $carryOver = 0.0;
        $account = null;
        foreach ($chain as $step) {
            $account = $this->accountFor($user, $step, $carryOver);
            $carryOver = $account->balance;
        }

        return $account;
    }

    private function accountFor(User $user, VacationYear $year, float $carryOver): VacationAccount
    {
        return new VacationAccount(
            $year,
            $this->userWorkContract->getVacationDaysForYear($user, $year),
            $carryOver,
            $this->manualBookingRepository->sumHolidayDaysBetween($user, $year->start, $year->end),
            $this->usedDays($user, $year),
        );
    }

    private function usedDays(User $user, VacationYear $year): float
    {
        $used = 0.0;
        foreach ($this->absenceRepository->findApprovedVacationsBetween($user, $year->start, $year->end) as $absence) {
            if ($absence->getType() !== AbsenceType::VACATION) {
                continue;
            }
            $used += $this->workdayHelper->countDaysBetween($absence, $year->start, $year->end);
        }

        return $used;
    }
}
