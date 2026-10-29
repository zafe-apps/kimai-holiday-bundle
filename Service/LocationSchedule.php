<?php

namespace KimaiPlugin\HolidayBundle\Service;

use KimaiPlugin\HolidayBundle\Entity\Location;
use KimaiPlugin\HolidayBundle\Enum\LocationExceptionKind;
use KimaiPlugin\HolidayBundle\Repository\LocationExceptionRepository;
use KimaiPlugin\HolidayBundle\Repository\PublicHolidayRepository;

/**
 * Answers "is this date an opening day of the location, with which hours?".
 * Order: inactive, date exception, full-day public holiday of the location's group, weekly pattern.
 */
class LocationSchedule
{
    public function __construct(
        private readonly LocationExceptionRepository $exceptionRepository,
        private readonly PublicHolidayRepository $publicHolidayRepository,
    ) {
    }

    /**
     * Loads exceptions and holidays once per call.
     *
     * @return array<string, OpeningSlot> keyed by Y-m-d
     */
    public function slotsBetween(Location $location, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $from = $from->setTime(0, 0);
        $to = $to->setTime(0, 0);

        $exceptions = [];
        foreach ($this->exceptionRepository->findBetween($location, $from, $to) as $exception) {
            $day = $exception->getDate()?->format('Y-m-d');
            if ($day !== null) {
                $exceptions[$day] = $exception;
            }
        }

        $holidays = [];
        $group = $location->getPublicHolidayGroup();
        if ($group !== null) {
            foreach ($this->publicHolidayRepository->findByGroupBetween($group, $from, $to) as $holiday) {
                $day = $holiday->getDate()?->format('Y-m-d');
                if ($day !== null && !$holiday->isHalfDay()) {
                    $holidays[$day] = $holiday->getName();
                }
            }
        }

        $slots = [];
        for ($cursor = $from; $cursor <= $to; $cursor = $cursor->modify('+1 day')) {
            $key = $cursor->format('Y-m-d');
            $weekly = $location->getOpeningHoursFor((int) $cursor->format('N'));

            if (!$location->isActive()) {
                $slots[$key] = new OpeningSlot($cursor, null, null, false, 'inactive', null);
            } elseif (isset($exceptions[$key])) {
                $exception = $exceptions[$key];
                if ($exception->getKind() === LocationExceptionKind::OPEN) {
                    $slots[$key] = new OpeningSlot(
                        $cursor,
                        $exception->getOpensAt() ?? $weekly?->getOpensAt(),
                        $exception->getClosesAt() ?? $weekly?->getClosesAt(),
                        true,
                        'exception_open',
                        null
                    );
                } else {
                    $slots[$key] = new OpeningSlot($cursor, null, null, false, 'exception_closed', null);
                }
            } elseif (isset($holidays[$key])) {
                $slots[$key] = new OpeningSlot($cursor, null, null, false, 'public_holiday', $holidays[$key]);
            } elseif ($weekly !== null) {
                $slots[$key] = new OpeningSlot($cursor, $weekly->getOpensAt(), $weekly->getClosesAt(), true, 'weekly', null);
            } else {
                $slots[$key] = new OpeningSlot($cursor, null, null, false, 'weekly', null);
            }
        }

        return $slots;
    }

    public function slotFor(Location $location, \DateTimeImmutable $date): OpeningSlot
    {
        return $this->slotsBetween($location, $date, $date)[$date->format('Y-m-d')];
    }
}
