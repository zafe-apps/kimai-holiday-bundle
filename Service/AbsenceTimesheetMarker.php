<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\Timesheet;

/** Recognises a single timesheet entry the plugin generated for an absence. */
class AbsenceTimesheetMarker
{
    // entries of older plugin versions carry no meta field, only this description
    private const LEGACY_DESCRIPTION = '/^Absence \(#(\d+)\)$/';

    public function __construct(private readonly HolidayConfiguration $configuration)
    {
    }

    public function isAbsenceEntry(Timesheet $timesheet): bool
    {
        $meta = $timesheet->getMetaField(AbsenceTimesheetService::META_KEY);
        if ($meta !== null && $meta->getValue() !== null && $meta->getValue() !== '') {
            return true;
        }

        if (preg_match(self::LEGACY_DESCRIPTION, (string) $timesheet->getDescription()) !== 1) {
            return false;
        }

        $projectId = $this->configuration->getAbsenceProjectId();
        $activityId = $this->configuration->getAbsenceActivityId();
        if ($projectId === null || $activityId === null) {
            return false;
        }

        return $timesheet->getProject()?->getId() === $projectId && $timesheet->getActivity()?->getId() === $activityId;
    }
}
