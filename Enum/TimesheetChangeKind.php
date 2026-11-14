<?php

namespace KimaiPlugin\HolidayBundle\Enum;

/** What a late change did to a timesheet entry. */
enum TimesheetChangeKind: string
{
    case CREATE = 'create';
    case UPDATE = 'update';
    case DELETE = 'delete';

    public function label(): string
    {
        return 'holiday.change.kind.' . $this->value;
    }
}
