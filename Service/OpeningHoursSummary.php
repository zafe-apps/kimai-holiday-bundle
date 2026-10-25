<?php

namespace KimaiPlugin\HolidayBundle\Service;

use KimaiPlugin\HolidayBundle\Entity\Location;

/**
 * Short form of the weekly opening hours: consecutive weekdays with the same times are one range,
 * e.g. "Mi–Fr 14:00–21:00 · Sa 12:00–21:00".
 */
final class OpeningHoursSummary
{
    private const SEPARATOR = ' · ';

    /**
     * @param array<int, string> $dayNames short names by ISO weekday (1 = Monday ... 7 = Sunday)
     */
    public function format(Location $location, array $dayNames): string
    {
        $groups = [];
        foreach (range(1, 7) as $weekday) {
            $hours = $location->getOpeningHoursFor($weekday);
            if ($hours === null || $hours->getOpensAt() === null || $hours->getClosesAt() === null) {
                continue;
            }

            $times = $hours->getOpensAt()->format('H:i') . '–' . $hours->getClosesAt()->format('H:i');
            $last = array_key_last($groups);
            if ($last !== null && $groups[$last]['times'] === $times && $groups[$last]['to'] === $weekday - 1) {
                $groups[$last]['to'] = $weekday;

                continue;
            }

            $groups[] = ['from' => $weekday, 'to' => $weekday, 'times' => $times];
        }

        $parts = [];
        foreach ($groups as $group) {
            $days = $dayNames[$group['from']];
            if ($group['to'] > $group['from']) {
                $days .= '–' . $dayNames[$group['to']];
            }
            $parts[] = $days . ' ' . $group['times'];
        }

        return implode(self::SEPARATOR, $parts);
    }
}
