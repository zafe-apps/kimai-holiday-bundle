<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Repository\AbsenceRepository;
use KimaiPlugin\HolidayBundle\Repository\TimesheetChangeRepository;
use KimaiPlugin\HolidayBundle\Repository\WorkdayShiftRepository;

/** Open (requested) absences, workday shifts and late timesheet changes an approver is responsible for: menu badge and request page. */
class OpenRequestCounter
{
    public function __construct(
        private readonly ApproverResolver $resolver,
        private readonly AbsenceRepository $absenceRepository,
        private readonly WorkdayShiftRepository $shiftRepository,
        private readonly TimesheetChangeRepository $changeRepository,
    ) {
    }

    public function countFor(User $approver): int
    {
        $absences = $this->absenceRepository->findRequestedForUsers($this->resolver->subjectsFor($approver, ApproverAccess::PERMISSION));
        $shifts = $this->shiftRepository->findRequestedForUsers($this->resolver->subjectsFor($approver, WorkdayShiftService::PERMISSION));

        $changes = $this->changeRepository->findRequestedForUsers($this->resolver->subjectsFor($approver, TimesheetChangeService::PERMISSION));

        return \count($absences) + \count($shifts) + \count($changes);
    }
}
