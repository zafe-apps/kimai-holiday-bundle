<?php

namespace KimaiPlugin\HolidayBundle\Tests\Service;

use App\Repository\WorkingTimeRepository;
use KimaiPlugin\HolidayBundle\Entity\Absence;
use KimaiPlugin\HolidayBundle\Enum\AbsenceType;
use KimaiPlugin\HolidayBundle\Repository\AbsenceRepository;
use KimaiPlugin\HolidayBundle\Repository\MonthLockRepository;
use KimaiPlugin\HolidayBundle\Service\AbsenceApprovalService;
use KimaiPlugin\HolidayBundle\Service\AbsenceNotifier;
use KimaiPlugin\HolidayBundle\Service\AbsenceTimesheetService;
use KimaiPlugin\HolidayBundle\Service\HolidayConfiguration;
use KimaiPlugin\HolidayBundle\Tests\Support\AbsenceBuilder;
use KimaiPlugin\HolidayBundle\Tests\Support\UserBuilder;
use PHPUnit\Framework\TestCase;

final class AbsenceApprovalServiceTest extends TestCase
{
    public function testHalfDayVacationRejectedWhenNotAllowed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('holiday.error.half_day_not_allowed');

        $this->service(false)->validate($this->absence(AbsenceType::VACATION, true));
    }

    public function testHalfDayVacationAcceptedWhenAllowed(): void
    {
        $this->service(true)->validate($this->absence(AbsenceType::VACATION, true));
        $this->addToAssertionCount(1);
    }

    public function testFullDayVacationAcceptedWhenHalfDaysNotAllowed(): void
    {
        $this->service(false)->validate($this->absence(AbsenceType::VACATION, false));
        $this->addToAssertionCount(1);
    }

    public function testHalfDaySicknessAcceptedRegardless(): void
    {
        $this->service(false)->validate($this->absence(AbsenceType::SICKNESS, true));
        $this->addToAssertionCount(1);
    }

    private function absence(AbsenceType $type, bool $half): Absence
    {
        $user = UserBuilder::make()->build();

        return AbsenceBuilder::vacation($user, '2026-10-21', '2026-10-21')->setType($type)->setHalfDay($half);
    }

    private function service(bool $allowHalfDay): AbsenceApprovalService
    {
        $config = $this->createStub(HolidayConfiguration::class);
        $config->method('allowHalfDayVacation')->willReturn($allowHalfDay);

        return new AbsenceApprovalService(
            $this->createStub(AbsenceRepository::class),
            $this->createStub(MonthLockRepository::class),
            $this->createStub(AbsenceNotifier::class),
            $config,
            $this->createStub(AbsenceTimesheetService::class),
            $this->createStub(WorkingTimeRepository::class),
        );
    }
}
