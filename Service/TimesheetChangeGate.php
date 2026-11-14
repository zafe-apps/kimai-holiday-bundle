<?php

namespace KimaiPlugin\HolidayBundle\Service;

/** Switches the recording of timesheet changes off while the plugin itself restores an entry. */
class TimesheetChangeGate
{
    private int $depth = 0;

    public function suspend(callable $work): mixed
    {
        ++$this->depth;
        try {
            return $work();
        } finally {
            --$this->depth;
        }
    }

    public function isSuspended(): bool
    {
        return $this->depth > 0;
    }
}
