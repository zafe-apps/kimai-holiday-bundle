<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\User;

/** Decides whether a timesheet day is old enough to need an approval. */
class TimesheetChangePolicy
{
    public function __construct(private readonly HolidayConfiguration $configuration)
    {
    }

    public function isLate(\DateTimeInterface $begin, User $owner, \DateTimeImmutable $now): bool
    {
        $days = $this->configuration->getTimesheetApprovalDays();
        if ($days === 0) {
            return false;
        }

        // the day counts where the person lives; the day exactly at the limit is still free
        $zone = new \DateTimeZone($owner->getTimezone());
        $day = \DateTimeImmutable::createFromInterface($begin)->setTimezone($zone)->format('Y-m-d');
        $limit = $now->setTimezone($zone)->modify('-' . $days . ' days')->format('Y-m-d');

        return $day < $limit;
    }
}
