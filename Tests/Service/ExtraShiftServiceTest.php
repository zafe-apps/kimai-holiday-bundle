<?php

namespace KimaiPlugin\HolidayBundle\Tests\Service;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Entity\ExtraShift;
use KimaiPlugin\HolidayBundle\Entity\Location;
use KimaiPlugin\HolidayBundle\Repository\AbsenceRepository;
use KimaiPlugin\HolidayBundle\Repository\ExtraShiftRepository;
use KimaiPlugin\HolidayBundle\Repository\LocationUserRepository;
use KimaiPlugin\HolidayBundle\Service\AbsenceWorkdayHelper;
use KimaiPlugin\HolidayBundle\Service\ExtraShiftService;
use KimaiPlugin\HolidayBundle\Service\LocationSchedule;
use KimaiPlugin\HolidayBundle\Service\OpeningSlot;
use KimaiPlugin\HolidayBundle\Tests\Support\AbsenceBuilder;
use KimaiPlugin\HolidayBundle\Tests\Support\ExtraShiftBuilder;
use KimaiPlugin\HolidayBundle\Tests\Support\UserBuilder;
use PHPUnit\Framework\TestCase;

final class ExtraShiftServiceTest extends TestCase
{
    public function testCreateOnFreeOpenDay(): void
    {
        $moritz = UserBuilder::make()->build();
        $actor = UserBuilder::make()->build();

        $shift = $this->service(workday: false, open: true, location: new Location())
            ->create($moritz, new \DateTimeImmutable('2026-10-29 13:45'), 'Hilfe', $actor);

        self::assertSame($moritz, $shift->getUser());
        self::assertSame($actor, $shift->getCreatedBy());
        self::assertSame('2026-10-29 00:00', $shift->getDate()->format('Y-m-d H:i'));
        self::assertSame('Hilfe', $shift->getComment());
    }

    public function testRejectsOnContractWorkday(): void
    {
        $this->expectExceptionMessage('holiday.error.extra_shift_on_workday');

        $moritz = UserBuilder::make()->build();
        $this->service(workday: true, open: true, location: new Location())
            ->create($moritz, new \DateTimeImmutable('2026-10-30'), null, $moritz);
    }

    public function testRejectsWhenApprovedAbsenceCoversDay(): void
    {
        $this->expectExceptionMessage('holiday.error.extra_shift_absent');

        $moritz = UserBuilder::make()->build();
        $absence = AbsenceBuilder::vacation($moritz, '2026-10-29', '2026-10-29');
        $this->service(workday: false, open: true, location: new Location(), absences: [$absence])
            ->create($moritz, new \DateTimeImmutable('2026-10-29'), null, $moritz);
    }

    public function testRejectsOnClosedDayOfOwnLocation(): void
    {
        $this->expectExceptionMessage('holiday.error.extra_shift_closed');

        $moritz = UserBuilder::make()->build();
        $this->service(workday: false, open: false, location: new Location())
            ->create($moritz, new \DateTimeImmutable('2026-11-02'), null, $moritz);
    }

    public function testAllowsWithoutLocation(): void
    {
        $user = UserBuilder::make()->build();

        $shift = $this->service(workday: false, open: false, location: null)
            ->create($user, new \DateTimeImmutable('2026-11-02'), null, $user);

        self::assertSame('2026-11-02', $shift->getDate()->format('Y-m-d'));
    }

    public function testRejectsDuplicate(): void
    {
        $this->expectExceptionMessage('holiday.error.extra_shift_exists');

        $moritz = UserBuilder::make()->build();
        $existing = ExtraShiftBuilder::on($moritz, '2026-10-29');
        $this->service(workday: false, open: true, location: new Location(), existing: $existing)
            ->create($moritz, new \DateTimeImmutable('2026-10-29'), null, $moritz);
    }

    /**
     * @param list<\KimaiPlugin\HolidayBundle\Entity\Absence> $absences
     */
    private function service(bool $workday, bool $open, ?Location $location, array $absences = [], ?ExtraShift $existing = null): ExtraShiftService
    {
        $shifts = $this->createStub(ExtraShiftRepository::class);
        $shifts->method('findOneByUserAndDate')->willReturn($existing);

        $helper = $this->createStub(AbsenceWorkdayHelper::class);
        $helper->method('isAbsenceApplicableDay')->willReturn($workday);

        $absenceRepo = $this->createStub(AbsenceRepository::class);
        $absenceRepo->method('findApprovedBetween')->willReturn($absences);

        $members = $this->createStub(LocationUserRepository::class);
        $members->method('findLocationOf')->willReturn($location);

        $schedule = $this->createStub(LocationSchedule::class);
        $schedule->method('slotFor')->willReturnCallback(
            static fn (Location $l, \DateTimeImmutable $d): OpeningSlot => new OpeningSlot($d, null, null, $open, 'weekly', null)
        );

        return new ExtraShiftService($shifts, $helper, $absenceRepo, $members, $schedule);
    }
}
