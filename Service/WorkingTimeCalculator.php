<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;
use App\Repository\TimesheetRepository;
use KimaiPlugin\HolidayBundle\Entity\Absence;
use KimaiPlugin\HolidayBundle\Entity\PublicHoliday;
use KimaiPlugin\HolidayBundle\Enum\AbsenceType;
use KimaiPlugin\HolidayBundle\Enum\CalculationMode;
use KimaiPlugin\HolidayBundle\Repository\AbsenceRepository;
use KimaiPlugin\HolidayBundle\Repository\ManualBookingRepository;
use KimaiPlugin\HolidayBundle\Repository\PublicHolidayRepository;

/**
 * Computes expected / actual / delta working times per day and year balances.
 * Expected hours and vacation entitlement come from Kimai's built-in user contract preferences.
 */
class WorkingTimeCalculator
{
    public function __construct(
        private readonly UserWorkContract $userWorkContract,
        private readonly AbsenceRepository $absenceRepository,
        private readonly PublicHolidayRepository $publicHolidayRepository,
        private readonly ManualBookingRepository $manualBookingRepository,
        private readonly TimesheetRepository $timesheetRepository,
        private readonly HolidayConfiguration $configuration,
        private readonly AbsenceWorkdayHelper $workdayHelper,
        private readonly AbsenceTimesheetService $absenceTimesheetService,
        private readonly VacationAccountService $vacationAccountService,
        private readonly VacationYearResolver $vacationYearResolver,
    ) {
    }

    /**
     * @return array{
     *   year: int,
     *   months: array<int, array{
     *     month: int,
     *     days: array<string, array{
     *       date: string,
     *       expected: int,
     *       actual: int,
     *       delta: int,
     *       timesheet: int,
     *       absence: int,
     *       publicHoliday: bool,
     *       publicHolidayName: string|null,
     *       absences: list<array{type: string, halfDay: bool}>,
     *       marker: bool
     *     }>,
     *     expected: int,
     *     actual: int,
     *     delta: int
     *   }>,
     *   expected: int,
     *   actual: int,
     *   delta: int,
     *   manualTime: int,
     *   vacationUsed: float,
     *   vacationEntitlement: float,
     *   vacationBalance: float,
     *   vacationCarryOver: float,
     *   vacationYearLabel: string
     * }
     */
    public function calculateYear(User $user, int $year, ?\DateTimeInterface $until = null): array
    {
        $until = $until ?? new \DateTimeImmutable('now');
        $untilDay = $until instanceof \DateTimeImmutable
            ? $until->setTime(23, 59, 59)
            : \DateTimeImmutable::createFromInterface($until)->setTime(23, 59, 59);

        $yearStart = new \DateTimeImmutable(sprintf('%d-01-01', $year));
        $yearEnd = new \DateTimeImmutable(sprintf('%d-12-31', $year));

        $absences = $this->absenceRepository->findApprovedBetween($user, $yearStart, $yearEnd);

        $publicHolidays = [];
        $group = $this->userWorkContract->getPublicHolidayGroup($user);
        if ($group !== null) {
            foreach ($this->publicHolidayRepository->findByGroupAndYear($group, $year) as $ph) {
                $publicHolidays[$ph->getDate()?->format('Y-m-d')] = $ph;
            }
        }

        $timesheetDurations = $this->getTimesheetDurationsByDay($user, $yearStart, $yearEnd);
        $absenceTimesheetDays = $this->absenceTimesheetService->getAbsenceTimesheetDays($user, $yearStart, $yearEnd);

        $months = [];
        $yearExpected = 0;
        $yearActual = 0;

        for ($month = 1; $month <= 12; ++$month) {
            $daysInMonth = (int) (new \DateTimeImmutable(sprintf('%d-%02d-01', $year, $month)))->format('t');
            $monthData = [
                'month' => $month,
                'days' => [],
                'expected' => 0,
                'actual' => 0,
                'delta' => 0,
            ];

            for ($day = 1; $day <= $daysInMonth; ++$day) {
                $date = new \DateTimeImmutable(sprintf('%d-%02d-%02d', $year, $month, $day));
                $key = $date->format('Y-m-d');
                $includeInBalance = $date <= $untilDay;

                $dayResult = $this->calculateDay(
                    $user,
                    $date,
                    $timesheetDurations[$key] ?? 0,
                    $absences,
                    $publicHolidays[$key] ?? null,
                    $includeInBalance,
                    $absenceTimesheetDays
                );

                $monthData['days'][$key] = $dayResult;

                if ($includeInBalance) {
                    $monthData['expected'] += $dayResult['expected'];
                    $monthData['actual'] += $dayResult['actual'];
                }
            }

            $monthData['delta'] = $monthData['actual'] - $monthData['expected'];
            $yearExpected += $monthData['expected'];
            $yearActual += $monthData['actual'];
            $months[$month] = $monthData;
        }

        $manualTime = $this->manualBookingRepository->sumTimeSecondsUntil($user, $untilDay);
        // Vacation fields come from the vacation year that contains the report day (capped to this calendar year).
        $account = $this->vacationAccountService->calculate(
            $user,
            $this->vacationYearResolver->forDate($user, min($untilDay, $yearEnd))
        );

        return [
            'year' => $year,
            'months' => $months,
            'expected' => $yearExpected,
            'actual' => $yearActual + $manualTime,
            'delta' => ($yearActual + $manualTime) - $yearExpected,
            'manualTime' => $manualTime,
            'vacationUsed' => $account->used,
            'vacationEntitlement' => $account->entitlement + $account->corrections,
            'vacationBalance' => $account->balance,
            'vacationCarryOver' => $account->carryOver,
            'vacationYearLabel' => $account->year->label(),
        ];
    }

    /**
     * @param Absence[] $absences
     * @param array<int, array<string, int>> $absenceTimesheetDays generated absence timesheets (absence id => [Y-m-d => seconds]);
     *                                                            these are already part of $timesheetSeconds and must not be credited again
     * @return array{
     *   date: string,
     *   expected: int,
     *   actual: int,
     *   delta: int,
     *   timesheet: int,
     *   absence: int,
     *   publicHoliday: bool,
     *   publicHolidayName: string|null,
     *   absences: list<array{type: string, halfDay: bool}>,
     *   marker: bool
     * }
     */
    public function calculateDay(
        User $user,
        \DateTimeImmutable $date,
        int $timesheetSeconds,
        array $absences,
        ?PublicHoliday $publicHoliday,
        bool $includeInBalance = true,
        array $absenceTimesheetDays = [],
    ): array {
        $baseExpected = $this->userWorkContract->getExpectedSecondsForDate($user, $date);
        $expected = $baseExpected;
        $absenceSeconds = 0;
        $dayAbsences = [];
        $key = $date->format('Y-m-d');

        foreach ($absences as $absence) {
            if (!$absence->coversDate($date) || !$this->workdayHelper->isAbsenceApplicableDay($user, $date)) {
                continue;
            }

            $dayAbsences[] = [
                'type' => $absence->getType()->value,
                'halfDay' => $absence->isHalfDay(),
            ];

            // Auto-created absence timesheet already counts as worked time for this day.
            if (isset($absenceTimesheetDays[$absence->getId()][$key])) {
                continue;
            }

            $absenceSeconds += $this->absenceContribution($absence, $baseExpected, $timesheetSeconds);
            $expected = $this->applyAbsenceToExpected($absence, $baseExpected, $expected, $timesheetSeconds);
        }

        if ($publicHoliday !== null && $baseExpected > 0) {
            $mode = $this->configuration->getPublicHolidayCalculationMode();
            $phSeconds = $publicHoliday->isHalfDay() ? (int) floor($baseExpected / 2) : $baseExpected;

            if ($mode === CalculationMode::COMPENSATE) {
                $absenceSeconds += $phSeconds;
            } else {
                $expected = max(0, $expected - $phSeconds);
            }
        }

        if (!$includeInBalance) {
            $expected = 0;
            $absenceSeconds = 0;
            $timesheetSeconds = 0;
        }

        $actual = $timesheetSeconds + $absenceSeconds;

        return [
            'date' => $date->format('Y-m-d'),
            'expected' => $expected,
            'actual' => $actual,
            'delta' => $actual - $expected,
            'timesheet' => $timesheetSeconds,
            'absence' => $absenceSeconds,
            'publicHoliday' => $publicHoliday !== null,
            'publicHolidayName' => $publicHoliday?->getName(),
            'absences' => $dayAbsences,
            'marker' => $publicHoliday !== null || $dayAbsences !== [],
        ];
    }

    private function absenceContribution(
        Absence $absence,
        int $baseExpected,
        int $timesheetSeconds,
    ): int {
        if ($absence->getType() === AbsenceType::TIME_OFF) {
            return 0;
        }

        $mode = $this->configuration->getCalculationMode($absence->getType());
        if ($mode !== CalculationMode::COMPENSATE) {
            return 0;
        }

        return $this->absenceDurationSeconds($absence, $baseExpected, $timesheetSeconds);
    }

    private function applyAbsenceToExpected(
        Absence $absence,
        int $baseExpected,
        int $currentExpected,
        int $timesheetSeconds,
    ): int {
        if ($absence->getType() === AbsenceType::TIME_OFF) {
            return $currentExpected;
        }

        $mode = $this->configuration->getCalculationMode($absence->getType());
        if ($mode !== CalculationMode::REDUCE) {
            return $currentExpected;
        }

        $reduceBy = $this->absenceDurationSeconds($absence, $baseExpected, $timesheetSeconds);

        return max(0, $currentExpected - $reduceBy);
    }

    private function absenceDurationSeconds(Absence $absence, int $baseExpected, int $timesheetSeconds): int
    {
        if ($absence->getType() === AbsenceType::OTHER && $absence->getDuration() !== null) {
            return $absence->getDuration();
        }

        if (\in_array($absence->getType(), [AbsenceType::SICKNESS, AbsenceType::SICKNESS_RELATIVE], true)) {
            $target = $absence->isHalfDay() ? (int) floor($baseExpected / 2) : $baseExpected;

            return max(0, $target - $timesheetSeconds);
        }

        if ($absence->isHalfDay()) {
            return (int) floor($baseExpected / 2);
        }

        return $baseExpected;
    }

    /**
     * @return array<string, int>
     */
    private function getTimesheetDurationsByDay(User $user, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $timesheets = $this->timesheetRepository->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->andWhere('t.begin >= :from')
            ->andWhere('t.begin <= :to')
            ->setParameter('user', $user)
            ->setParameter('from', $from->setTime(0, 0))
            ->setParameter('to', $to->setTime(23, 59, 59))
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($timesheets as $timesheet) {
            $begin = $timesheet->getBegin();
            if ($begin === null) {
                continue;
            }
            $key = $begin->format('Y-m-d');
            $result[$key] = ($result[$key] ?? 0) + (int) ($timesheet->getDuration() ?? 0);
        }

        return $result;
    }
}
