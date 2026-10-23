<?php

namespace KimaiPlugin\HolidayBundle\Tests\Service;

use App\Entity\User;
use App\WorkingTime\Mode\WorkingTimeModeDay;
use App\WorkingTime\Mode\WorkingTimeModeFactory;
use App\WorkingTime\Mode\WorkingTimeModeNone;
use KimaiPlugin\HolidayBundle\Entity\Absence;
use KimaiPlugin\HolidayBundle\Repository\AbsenceRepository;
use KimaiPlugin\HolidayBundle\Repository\ManualBookingRepository;
use KimaiPlugin\HolidayBundle\Repository\PublicHolidayGroupRepository;
use KimaiPlugin\HolidayBundle\Repository\PublicHolidayRepository;
use KimaiPlugin\HolidayBundle\Service\AbsenceWorkdayHelper;
use KimaiPlugin\HolidayBundle\Service\UserWorkContract;
use KimaiPlugin\HolidayBundle\Service\VacationAccountService;
use KimaiPlugin\HolidayBundle\Service\VacationYear;
use KimaiPlugin\HolidayBundle\Service\VacationYearResolver;
use KimaiPlugin\HolidayBundle\Tests\Support\AbsenceBuilder;
use KimaiPlugin\HolidayBundle\Tests\Support\UserBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class VacationAccountServiceTest extends TestCase
{
    public function testBalanceWithoutHistory(): void
    {
        // Eintritt 15.03.2026, 20 Tage, Mi–Sa; Urlaub 21.–24.10.2026 (4 Arbeitstage)
        $user = UserBuilder::make()->startingOn('2026-03-15')->vacationDays(20)->wednesdayToSaturday(7 * 3600, 9 * 3600)->build();
        $acc = $this->service([AbsenceBuilder::vacation($user, '2026-10-21', '2026-10-24')])->calculate($user, $this->year($user, '2026-10-23'));

        self::assertSame(20.0, $acc->entitlement);
        self::assertSame(0.0, $acc->carryOver);
        self::assertSame(0.0, $acc->corrections);
        self::assertSame(4.0, $acc->used);
        self::assertSame(16.0, $acc->balance);
    }

    public function testCarryOverFromPreviousYear(): void
    {
        // Eintritt 15.03.2024, 20 Tage/Jahr, Mi–Sa. Jahr 0 verbraucht 15, Jahr 1 verbraucht 20 → Jahr 2 Übertrag 5
        $user = UserBuilder::make()->startingOn('2024-03-15')->vacationDays(20)->wednesdayToSaturday(7 * 3600, 9 * 3600)->build();
        $absences = [
            AbsenceBuilder::vacation($user, '2024-05-01', '2024-05-24'), // 4+4+4+3 = 15
            AbsenceBuilder::vacation($user, '2025-05-07', '2025-06-07'), // 4*5 = 20
        ];
        $service = $this->service($absences);

        $y0 = $service->calculate($user, $this->year($user, '2024-06-01'));
        self::assertSame(15.0, $y0->used);
        self::assertSame(5.0, $y0->balance);

        $y1 = $service->calculate($user, $this->year($user, '2025-06-01'));
        self::assertSame(5.0, $y1->carryOver);
        self::assertSame(20.0, $y1->used);
        self::assertSame(5.0, $y1->balance);

        $acc = $service->calculate($user, $this->year($user, '2026-10-23'));
        self::assertSame(5.0, $acc->carryOver);
        self::assertSame(25.0, $acc->balance); // 20 + 5 − 0
    }

    public function testAbsenceSpanningYearBoundary(): void
    {
        // Eintritt 15.03.2025, Mi–Sa; Urlaub 11.03.–18.03.2026: 11.–14.03. (Mi–Sa) in Jahr 0, 18.03. (Mi) in Jahr 1
        $user = UserBuilder::make()->startingOn('2025-03-15')->vacationDays(20)->wednesdayToSaturday(7 * 3600, 9 * 3600)->build();
        $absences = [AbsenceBuilder::vacation($user, '2026-03-11', '2026-03-18')];
        $service = $this->service($absences);

        $year0 = $this->year($user, '2026-03-11');
        $year1 = $this->year($user, '2026-03-18');

        self::assertSame(0, $year0->index);
        self::assertSame(1, $year1->index);
        self::assertSame(4.0, $service->calculate($user, $year0)->used);
        self::assertSame(1.0, $service->calculate($user, $year1)->used);
        // jeder Tag genau einmal: 16 Rest aus Jahr 0 + 20 − 1
        self::assertSame(35.0, $service->calculate($user, $year1)->balance);
    }

    public function testManualBookingIsCorrection(): void
    {
        $user = UserBuilder::make()->startingOn('2026-03-15')->vacationDays(20)->wednesdayToSaturday(7 * 3600, 9 * 3600)->build();
        $acc = $this->service([], -3.0)->calculate($user, $this->year($user, '2026-10-23'));

        self::assertSame(-3.0, $acc->corrections);
        self::assertSame(20.0, $acc->entitlement);
        self::assertSame(17.0, $acc->balance);
    }

    public function testProRataWhenContractEndsInYear(): void
    {
        // Eintritt 15.03.2024, Austritt 14.09.2026, 20 Tage → Jahr 2: 15.03.–14.09. = 6 volle Monate → 10
        $user = UserBuilder::make()->startingOn('2024-03-15')->endingOn('2026-09-14')->vacationDays(20)->wednesdayToSaturday(7 * 3600, 9 * 3600)->build();
        $acc = $this->service([])->calculate($user, $this->year($user, '2026-06-01'));

        self::assertSame(10.0, $acc->entitlement);
    }

    public function testProRataRoundsUpToHalfDays(): void
    {
        // 25 Tage, 5 Monate → 10,4167 → 10,5
        $user = UserBuilder::make()->startingOn('2024-03-15')->endingOn('2026-08-14')->vacationDays(25)->wednesdayToSaturday(7 * 3600, 9 * 3600)->build();

        self::assertSame(10.5, $this->service([])->calculate($user, $this->year($user, '2026-06-01'))->entitlement);
    }

    public function testNoCarryOverInCalendarFallback(): void
    {
        $user = UserBuilder::make()->vacationDays(20)->wednesdayToSaturday(7 * 3600, 9 * 3600)->build();
        $acc = $this->service([])->calculate($user, $this->year($user, '2026-10-23'));

        self::assertSame(0.0, $acc->carryOver);
        self::assertSame(20.0, $acc->balance);
    }

    private function year(User $user, string $ymd): VacationYear
    {
        return (new VacationYearResolver())->forDate($user, new \DateTimeImmutable($ymd));
    }

    /**
     * @param Absence[] $absences
     */
    private function service(array $absences, float $manualDays = 0.0): VacationAccountService
    {
        $factory = new WorkingTimeModeFactory([new WorkingTimeModeNone(), new WorkingTimeModeDay()], new NullLogger());
        $contract = new UserWorkContract($factory, $this->createStub(PublicHolidayGroupRepository::class));
        $helper = new AbsenceWorkdayHelper($contract, $this->createStub(PublicHolidayRepository::class));

        $absenceRepo = $this->createStub(AbsenceRepository::class);
        $absenceRepo->method('findApprovedVacationsBetween')->willReturn($absences);
        $manualRepo = $this->createStub(ManualBookingRepository::class);
        $manualRepo->method('sumHolidayDaysBetween')->willReturnCallback(
            // Korrektur nur im Fenster des laufenden Jahres, damit Vorjahre unberührt bleiben
            fn (User $u, \DateTimeInterface $from, \DateTimeInterface $to): float => $from->format('Y-m-d') === '2026-03-15' ? $manualDays : 0.0
        );

        return new VacationAccountService(new VacationYearResolver(), $contract, $absenceRepo, $manualRepo, $helper);
    }
}
