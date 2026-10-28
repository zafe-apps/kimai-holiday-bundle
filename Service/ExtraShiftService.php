<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Entity\ExtraShift;
use KimaiPlugin\HolidayBundle\Repository\AbsenceRepository;
use KimaiPlugin\HolidayBundle\Repository\ExtraShiftRepository;
use KimaiPlugin\HolidayBundle\Repository\LocationUserRepository;

/**
 * Extra shifts: "user is additionally present on this day". No approval, no effect on expected hours.
 * Errors are \InvalidArgumentException with a holiday.error.* translation key.
 */
class ExtraShiftService
{
    public function __construct(
        private readonly ExtraShiftRepository $shifts,
        private readonly AbsenceWorkdayHelper $workdays,
        private readonly AbsenceRepository $absences,
        private readonly LocationUserRepository $members,
        private readonly LocationSchedule $schedule,
    ) {
    }

    public function create(User $user, \DateTimeImmutable $date, ?string $comment, User $actor): ExtraShift
    {
        $date = $date->setTime(0, 0);

        if ($this->shifts->findOneByUserAndDate($user, $date) !== null) {
            throw new \InvalidArgumentException('holiday.error.extra_shift_exists');
        }
        if ($this->workdays->isAbsenceApplicableDay($user, $date)) {
            throw new \InvalidArgumentException('holiday.error.extra_shift_on_workday');
        }
        if ($this->absences->findApprovedBetween($user, $date, $date) !== []) {
            throw new \InvalidArgumentException('holiday.error.extra_shift_absent');
        }

        // Only opening days of the own location; without a location nothing to check.
        $location = $this->members->findLocationOf($user);
        if ($location !== null && !$this->schedule->slotFor($location, $date)->open) {
            throw new \InvalidArgumentException('holiday.error.extra_shift_closed');
        }

        $comment = $comment !== null ? trim($comment) : null;
        $shift = (new ExtraShift())
            ->setUser($user)
            ->setDate($date)
            ->setComment($comment === '' ? null : $comment)
            ->setCreatedBy($actor);
        $this->shifts->save($shift);

        return $shift;
    }

    public function delete(ExtraShift $shift): void
    {
        $this->shifts->remove($shift);
    }
}
