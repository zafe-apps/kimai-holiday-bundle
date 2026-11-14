<?php

namespace KimaiPlugin\HolidayBundle\Service;

use App\Entity\Timesheet;

/** The state of a timesheet entry at one moment, storable as JSON. */
final class TimesheetSnapshot
{
    /**
     * @param list<string> $tags
     */
    private function __construct(
        public readonly ?string $begin,
        public readonly ?string $end,
        public readonly ?int $duration,
        public readonly int $break,
        public readonly ?string $timezone,
        public readonly ?int $projectId,
        public readonly ?int $activityId,
        public readonly ?string $description,
        public readonly array $tags,
        public readonly bool $billable,
    ) {
    }

    public static function fromTimesheet(Timesheet $timesheet): self
    {
        $zone = self::zone($timesheet->getTimezone());

        return new self(
            self::iso($timesheet->getBegin(), $zone),
            self::iso($timesheet->getEnd(), $zone),
            $timesheet->getDuration(false),
            $timesheet->getBreak(),
            $timesheet->getTimezone(),
            $timesheet->getProject()?->getId(),
            $timesheet->getActivity()?->getId(),
            $timesheet->getDescription(),
            array_values($timesheet->getTagsAsArray()),
            $timesheet->isBillable(),
        );
    }

    /**
     * The state Doctrine loaded, before the current request changed the entity. Tags are not part of
     * Doctrine's original data and are taken from the current entity.
     *
     * @param array<string, mixed> $original UnitOfWork::getOriginalEntityData()
     */
    public static function fromOriginal(array $original, Timesheet $current): self
    {
        $timezone = \is_string($original['timezone'] ?? null) ? $original['timezone'] : $current->getTimezone();
        $zone = self::zone($timezone);
        $project = $original['project'] ?? null;
        $activity = $original['activity'] ?? null;

        return new self(
            self::iso($original['begin'] ?? null, $zone),
            self::iso($original['end'] ?? null, $zone),
            isset($original['duration']) ? (int) $original['duration'] : null,
            (int) ($original['break'] ?? 0),
            $timezone,
            \is_object($project) ? $project->getId() : null,
            \is_object($activity) ? $activity->getId() : null,
            isset($original['description']) ? (string) $original['description'] : null,
            array_values($current->getTagsAsArray()),
            (bool) ($original['billable'] ?? true),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['begin'] ?? null,
            $data['end'] ?? null,
            isset($data['duration']) ? (int) $data['duration'] : null,
            (int) ($data['break'] ?? 0),
            $data['timezone'] ?? null,
            isset($data['projectId']) ? (int) $data['projectId'] : null,
            isset($data['activityId']) ? (int) $data['activityId'] : null,
            $data['description'] ?? null,
            array_values($data['tags'] ?? []),
            (bool) ($data['billable'] ?? true),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /** Only begin, end, duration and break need an approval. */
    public function sameTimes(self $other): bool
    {
        return $this->instant($this->begin) === $this->instant($other->begin)
            && $this->instant($this->end) === $this->instant($other->end)
            && $this->duration === $other->duration
            && $this->break === $other->break;
    }

    public function beginAt(): ?\DateTimeImmutable
    {
        return $this->begin === null ? null : new \DateTimeImmutable($this->begin);
    }

    public function endAt(): ?\DateTimeImmutable
    {
        return $this->end === null ? null : new \DateTimeImmutable($this->end);
    }

    /** The day the entry begins on, seen from the given timezone. */
    public function day(\DateTimeZone $zone): ?\DateTimeImmutable
    {
        return $this->beginAt()?->setTimezone($zone)->setTime(0, 0);
    }

    /** "08:00–12:30 (4:15 h)" for lists and mails. */
    public function summary(): string
    {
        $begin = $this->beginAt()?->format('H:i') ?? '?';
        $end = $this->endAt()?->format('H:i') ?? '…';
        $seconds = max(0, (int) $this->duration);

        return sprintf('%s–%s (%d:%02d h)', $begin, $end, intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
    }

    private function instant(?string $iso): ?int
    {
        return $iso === null ? null : (new \DateTimeImmutable($iso))->getTimestamp();
    }

    private static function iso(mixed $date, \DateTimeZone $zone): ?string
    {
        if (!($date instanceof \DateTimeInterface)) {
            return null;
        }

        return \DateTimeImmutable::createFromInterface($date)->setTimezone($zone)->format(\DATE_ATOM);
    }

    private static function zone(?string $timezone): \DateTimeZone
    {
        try {
            return new \DateTimeZone($timezone ?? date_default_timezone_get());
        } catch (\Exception) {
            return new \DateTimeZone(date_default_timezone_get());
        }
    }
}
