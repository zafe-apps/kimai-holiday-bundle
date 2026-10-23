<?php

namespace KimaiPlugin\HolidayBundle\Tests\Support;

use App\Entity\User;
use App\WorkingTime\Mode\WorkingTimeModeDay;

/** Builds a User with a "hours per weekday" work contract for unit tests. */
final class UserBuilder
{
    private User $user;

    private function __construct()
    {
        $this->user = new User();
        $this->user->setWorkContractMode(WorkingTimeModeDay::ID);
    }

    public static function make(): self
    {
        return new self();
    }

    public function wednesdayToSaturday(int $secWeekday, int $secSaturday): self
    {
        $this->user->setWorkHoursWednesday($secWeekday);
        $this->user->setWorkHoursThursday($secWeekday);
        $this->user->setWorkHoursFriday($secWeekday);
        $this->user->setWorkHoursSaturday($secSaturday);

        return $this;
    }

    public function fridaySaturday(int $seconds): self
    {
        $this->user->setWorkHoursFriday($seconds);
        $this->user->setWorkHoursSaturday($seconds);

        return $this;
    }

    public function startingOn(string $ymd): self
    {
        $this->user->setWorkStartingDay(new \DateTimeImmutable($ymd));

        return $this;
    }

    public function endingOn(string $ymd): self
    {
        $this->user->setLastWorkingDay(new \DateTimeImmutable($ymd));

        return $this;
    }

    public function vacationDays(float $days): self
    {
        $this->user->setHolidaysPerYear($days);

        return $this;
    }

    public function holidayGroup(string $id): self
    {
        $this->user->setPublicHolidayGroup($id);

        return $this;
    }

    public function build(): User
    {
        return $this->user;
    }
}
