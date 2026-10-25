<?php

namespace KimaiPlugin\HolidayBundle\Service;

/** Opening state of one location on one date. */
final class OpeningSlot
{
    public function __construct(
        public readonly \DateTimeImmutable $date,
        public readonly ?\DateTimeImmutable $opensAt,
        public readonly ?\DateTimeImmutable $closesAt,
        public readonly bool $open,
        /** 'inactive'|'exception_open'|'exception_closed'|'public_holiday'|'weekly' */
        public readonly ?string $reason,
        public readonly ?string $holidayName,
    ) {
    }

    public function label(): string
    {
        if (!$this->open || $this->opensAt === null || $this->closesAt === null) {
            return '';
        }

        return $this->opensAt->format('H:i') . '–' . $this->closesAt->format('H:i');
    }
}
