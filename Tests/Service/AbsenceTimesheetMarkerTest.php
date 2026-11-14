<?php

namespace KimaiPlugin\HolidayBundle\Tests\Service;

use App\Entity\Activity;
use App\Entity\Project;
use App\Entity\Timesheet;
use App\Entity\TimesheetMeta;
use KimaiPlugin\HolidayBundle\Service\AbsenceTimesheetMarker;
use KimaiPlugin\HolidayBundle\Service\AbsenceTimesheetService;
use KimaiPlugin\HolidayBundle\Service\HolidayConfiguration;
use PHPUnit\Framework\TestCase;

final class AbsenceTimesheetMarkerTest extends TestCase
{
    public function testMetaFieldMarksAnAbsenceEntry(): void
    {
        $timesheet = new Timesheet();
        $timesheet->setMetaField((new TimesheetMeta())->setName(AbsenceTimesheetService::META_KEY)->setValue('12'));

        self::assertTrue($this->marker()->isAbsenceEntry($timesheet));
    }

    public function testLegacyEntryNeedsDescriptionAndConfiguredProject(): void
    {
        $legacy = $this->entry('Absence (#12)', 5, 9);
        $otherProject = $this->entry('Absence (#12)', 6, 9);
        $otherText = $this->entry('Absence (#12) nachgetragen', 5, 9);

        self::assertTrue($this->marker()->isAbsenceEntry($legacy));
        self::assertFalse($this->marker()->isAbsenceEntry($otherProject));
        self::assertFalse($this->marker()->isAbsenceEntry($otherText));
    }

    public function testOrdinaryEntryIsNotMarked(): void
    {
        self::assertFalse($this->marker()->isAbsenceEntry($this->entry('Büro', 5, 9)));
    }

    private function entry(string $description, int $projectId, int $activityId): Timesheet
    {
        $project = new Project();
        (new \ReflectionProperty(Project::class, 'id'))->setValue($project, $projectId);
        $activity = new Activity();
        (new \ReflectionProperty(Activity::class, 'id'))->setValue($activity, $activityId);

        $timesheet = new Timesheet();
        $timesheet->setDescription($description);
        $timesheet->setProject($project);
        $timesheet->setActivity($activity);

        return $timesheet;
    }

    private function marker(): AbsenceTimesheetMarker
    {
        $configuration = $this->createStub(HolidayConfiguration::class);
        $configuration->method('getAbsenceProjectId')->willReturn(5);
        $configuration->method('getAbsenceActivityId')->willReturn(9);

        return new AbsenceTimesheetMarker($configuration);
    }
}
