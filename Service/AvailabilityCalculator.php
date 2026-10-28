<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Entity\Absence;
use KimaiPlugin\HolidayBundle\Entity\ExtraShift;
use KimaiPlugin\HolidayBundle\Entity\Location;
use KimaiPlugin\HolidayBundle\Enum\AbsenceStatus;
use KimaiPlugin\HolidayBundle\Enum\PresenceKind;
use KimaiPlugin\HolidayBundle\Repository\AbsenceRepository;
use KimaiPlugin\HolidayBundle\Repository\ExtraShiftRepository;
use KimaiPlugin\HolidayBundle\Repository\LocationUserRepository;

/** Who is present, absent or has an open request at a location, per calendar day. */
final class AvailabilityCalculator
{
    public function __construct(
        private readonly LocationSchedule $schedule,
        private readonly LocationUserRepository $locationUsers,
        private readonly AbsenceWorkdayHelper $workdays,
        private readonly AbsenceRepository $absences,
        private readonly ExtraShiftRepository $extraShifts,
    ) {
    }

    /** @return array<string, AvailabilityDay> keyed Y-m-d, one entry per calendar day */
    public function forPeriod(Location $location, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $from = $from->setTime(0, 0);
        $to = $to->setTime(0, 0);

        $slots = $this->schedule->slotsBetween($location, $from, $to);
        $users = $this->locationUsers->findUsersOf($location);
        $absences = $this->absences->findVisibleForUsersBetween($users, $from, $to);
        $shifts = $this->extraShifts->findForUsersBetween($users, $from, $to);

        /** @var array<string, array<int, ExtraShift>> $shiftIndex day => user object id => shift */
        $shiftIndex = [];
        foreach ($shifts as $shift) {
            $shiftIndex[$shift->getDate()->format('Y-m-d')][spl_object_id($shift->getUser())] ??= $shift;
        }

        $result = [];
        foreach ($slots as $key => $slot) {
            $date = $slot->date;
            $present = [];
            $absent = [];
            $pending = [];

            foreach ($users as $user) {
                $approved = null;
                $requested = null;
                foreach ($absences as $absence) {
                    if ($absence->getUser() !== $user || !$absence->coversDate($date)) {
                        continue;
                    }
                    if ($absence->getStatus() === AbsenceStatus::APPROVED) {
                        $approved ??= $absence;
                    } elseif ($absence->getStatus() === AbsenceStatus::REQUESTED) {
                        $requested ??= $absence;
                    }
                }

                $shift = $shiftIndex[$key][spl_object_id($user)] ?? null;
                $applicable = $this->workdays->isAbsenceApplicableDay($user, $date);

                if ($approved !== null && !$approved->isHalfDay()) {
                    $absent[] = ['user' => $user, 'absence' => $approved];
                } else {
                    $halfDay = $approved !== null;
                    if ($applicable) {
                        $present[] = new PresenceEntry($user, PresenceKind::FIXED, $halfDay, null);
                    } elseif ($shift !== null) {
                        $present[] = new PresenceEntry($user, PresenceKind::EXTRA_SHIFT, $halfDay, $shift);
                    }
                }

                if ($requested !== null) {
                    $pending[] = ['user' => $user, 'absence' => $requested];
                }
            }

            usort($present, static fn (PresenceEntry $a, PresenceEntry $b): int => [$a->kind === PresenceKind::FIXED ? 0 : 1, $a->user->getDisplayName()]
                <=> [$b->kind === PresenceKind::FIXED ? 0 : 1, $b->user->getDisplayName()]);
            $byName = static fn (array $a, array $b): int => $a['user']->getDisplayName() <=> $b['user']->getDisplayName();
            usort($absent, $byName);
            usort($pending, $byName);

            $result[$key] = new AvailabilityDay($date, $slot, $present, $absent, $pending);
        }

        return $result;
    }
}
