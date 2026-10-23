<?php

namespace KimaiPlugin\HolidayBundle\Service;

/** Vacation balance of one vacation year (days in the person's own working days). */
final class VacationAccount
{
    public readonly float $balance;

    public function __construct(
        public readonly VacationYear $year,
        public readonly float $entitlement,
        public readonly float $carryOver,
        public readonly float $corrections,
        public readonly float $used,
    ) {
        $this->balance = $entitlement + $carryOver + $corrections - $used;
    }
}
