<?php

namespace KimaiPlugin\HolidayBundle\Service;

/** One shown period of the availability view: an ISO week or a calendar month. */
final class AvailabilityPeriod
{
    public const WEEK = 'week';
    public const MONTH = 'month';

    private function __construct(
        public readonly string $unit,
        public readonly \DateTimeImmutable $from,
        public readonly \DateTimeImmutable $to,
    ) {
    }

    public static function week(int $isoYear, int $isoWeek): self
    {
        $from = (new \DateTimeImmutable('today'))->setISODate($isoYear, $isoWeek)->setTime(0, 0);

        return new self(self::WEEK, $from, $from->modify('+6 days'));
    }

    public static function month(int $year, int $month): self
    {
        $from = (new \DateTimeImmutable('today'))->setDate($year, $month, 1)->setTime(0, 0);

        return new self(self::MONTH, $from, $from->modify('last day of this month'));
    }

    public static function containing(\DateTimeImmutable $day, string $unit): self
    {
        if ($unit === self::MONTH) {
            return self::month((int) $day->format('Y'), (int) $day->format('n'));
        }

        return self::week((int) $day->format('o'), (int) $day->format('W'));
    }

    public function prev(): self
    {
        return $this->shifted(-1);
    }

    public function next(): self
    {
        return $this->shifted(1);
    }

    /**
     * @param \Closure(\DateTimeImmutable): string|null $monthName localized month name, English-neutral fallback via Intl
     */
    public function label(?\Closure $monthName = null): string
    {
        if ($this->unit === self::WEEK) {
            return sprintf(
                'KW %d/%s · %s–%s',
                (int) $this->from->format('W'),
                $this->from->format('o'),
                $this->from->format('d.m.'),
                $this->to->format('d.m.Y')
            );
        }

        $name = $monthName !== null
            ? $monthName($this->from)
            : (string) (new \IntlDateFormatter('de', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, null, null, 'LLLL'))->format($this->from);

        return $name . ' ' . $this->from->format('Y');
    }

    /** @return array{year: int, week: int}|array{year: int, month: int} */
    public function routeParams(): array
    {
        if ($this->unit === self::WEEK) {
            return ['year' => (int) $this->from->format('o'), 'week' => (int) $this->from->format('W')];
        }

        return ['year' => (int) $this->from->format('Y'), 'month' => (int) $this->from->format('n')];
    }

    private function shifted(int $step): self
    {
        if ($this->unit === self::MONTH) {
            return self::containing($this->from->modify(sprintf('%+d month', $step)), self::MONTH);
        }

        return self::containing($this->from->modify(sprintf('%+d week', $step)), self::WEEK);
    }
}
