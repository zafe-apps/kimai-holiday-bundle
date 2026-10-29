<?php

namespace KimaiPlugin\HolidayBundle\Enum;

enum LocationExceptionKind: string
{
    case CLOSED = 'closed';
    case OPEN = 'open';

    /** Translation key for this kind. */
    public function label(): string
    {
        return 'holiday.location.exception.' . $this->value;
    }
}
