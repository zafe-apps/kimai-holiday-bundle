<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\Timesheet;
use App\Entity\User;
use App\Repository\ActivityRepository;
use App\Repository\ProjectRepository;
use App\Repository\TimesheetRepository;
use App\Timesheet\TimesheetService;

/** Thin seam over Kimai's final TimesheetService: what a rejected timesheet change needs to restore an entry. */
class TimesheetStore
{
    public function __construct(
        private readonly TimesheetService $timesheets,
        private readonly TimesheetRepository $repository,
        private readonly ProjectRepository $projects,
        private readonly ActivityRepository $activities,
    ) {
    }

    public function find(int $id): ?Timesheet
    {
        return $this->repository->find($id);
    }

    public function applyTimes(Timesheet $timesheet, TimesheetSnapshot $state): void
    {
        $zone = new \DateTimeZone($state->timezone ?? $timesheet->getTimezone() ?? date_default_timezone_get());
        $begin = $state->beginAt();
        $end = $state->endAt();

        if ($begin !== null) {
            $timesheet->setBegin(\DateTime::createFromImmutable($begin->setTimezone($zone)));
        }
        $timesheet->setEnd($end === null ? null : \DateTime::createFromImmutable($end->setTimezone($zone)));
        $timesheet->setDuration($state->duration);
        $timesheet->setBreak($state->break);
    }

    public function update(Timesheet $timesheet): void
    {
        $this->timesheets->saveTimesheet($timesheet);
    }

    public function delete(Timesheet $timesheet): void
    {
        $this->timesheets->deleteTimesheet($timesheet);
    }

    /**
     * Brings a deleted entry back. Written past Kimai's validation: the state existed before and
     * must come back even if today's rules would refuse a new entry like it.
     *
     * @return Timesheet|null null when the project or activity is gone
     */
    public function restore(User $owner, TimesheetSnapshot $state): ?Timesheet
    {
        $project = $state->projectId === null ? null : $this->projects->find($state->projectId);
        $activity = $state->activityId === null ? null : $this->activities->find($state->activityId);
        if ($project === null || $activity === null) {
            return null;
        }

        $timesheet = new Timesheet();
        $timesheet->setUser($owner);
        if ($state->timezone !== null) {
            $timesheet->setTimezone($state->timezone);
        }
        $timesheet->setProject($project);
        $timesheet->setActivity($activity);
        $timesheet->setDescription($state->description);
        $timesheet->setBillable($state->billable);
        $this->applyTimes($timesheet, $state);
        $this->repository->save($timesheet);

        return $timesheet;
    }
}
