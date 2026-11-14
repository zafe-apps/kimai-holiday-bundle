<?php

namespace KimaiPlugin\HolidayBundle\Entity;

use App\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use KimaiPlugin\HolidayBundle\Enum\AbsenceStatus;
use KimaiPlugin\HolidayBundle\Enum\TimesheetChangeKind;
use KimaiPlugin\HolidayBundle\Repository\TimesheetChangeRepository;
use KimaiPlugin\HolidayBundle\Service\TimesheetSnapshot;

/**
 * A timesheet entry was created, changed or deleted long after its day: a team lead has to approve it.
 * The entry itself is already changed; before/after keep both states so a rejection can restore the old one.
 */
#[ORM\Entity(repositoryClass: TimesheetChangeRepository::class)]
#[ORM\Table(name: 'kimai2_ext_holiday_timesheet_change')]
#[ORM\Index(columns: ['user_id', 'status'], name: 'idx_holiday_tschange_user_status')]
#[ORM\Index(columns: ['timesheet_id'], name: 'idx_holiday_tschange_timesheet')]
class TimesheetChange
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    // plain column: the entry may be gone (deleted), the snapshots carry everything needed
    #[ORM\Column(name: 'timesheet_id', type: Types::INTEGER, nullable: true)]
    private ?int $timesheetId = null;

    #[ORM\Column(name: 'day', type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $day = null;

    #[ORM\Column(type: Types::STRING, length: 16, enumType: TimesheetChangeKind::class)]
    private TimesheetChangeKind $kind = TimesheetChangeKind::UPDATE;

    #[ORM\Column(type: Types::STRING, length: 32, enumType: AbsenceStatus::class)]
    private AbsenceStatus $status = AbsenceStatus::REQUESTED;

    /** @var array<string, mixed>|null */
    #[ORM\Column(name: 'before_state', type: Types::JSON, nullable: true)]
    private ?array $before = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(name: 'after_state', type: Types::JSON, nullable: true)]
    private ?array $after = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'requested_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $requestedBy = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'approved_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $approvedBy = null;

    #[ORM\Column(name: 'approved_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $approvedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getTimesheetId(): ?int
    {
        return $this->timesheetId;
    }

    public function setTimesheetId(?int $timesheetId): self
    {
        $this->timesheetId = $timesheetId;

        return $this;
    }

    public function getDay(): ?\DateTimeImmutable
    {
        return $this->day;
    }

    public function setDay(\DateTimeImmutable $day): self
    {
        $this->day = $day;

        return $this;
    }

    public function getKind(): TimesheetChangeKind
    {
        return $this->kind;
    }

    public function setKind(TimesheetChangeKind $kind): self
    {
        $this->kind = $kind;

        return $this;
    }

    public function getStatus(): AbsenceStatus
    {
        return $this->status;
    }

    public function setStatus(AbsenceStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getBefore(): ?TimesheetSnapshot
    {
        return $this->before === null ? null : TimesheetSnapshot::fromArray($this->before);
    }

    public function setBefore(?TimesheetSnapshot $before): self
    {
        $this->before = $before?->toArray();

        return $this;
    }

    public function getAfter(): ?TimesheetSnapshot
    {
        return $this->after === null ? null : TimesheetSnapshot::fromArray($this->after);
    }

    public function setAfter(?TimesheetSnapshot $after): self
    {
        $this->after = $after?->toArray();

        return $this;
    }

    public function getRequestedBy(): ?User
    {
        return $this->requestedBy;
    }

    public function setRequestedBy(?User $requestedBy): self
    {
        $this->requestedBy = $requestedBy;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getApprovedBy(): ?User
    {
        return $this->approvedBy;
    }

    public function setApprovedBy(?User $approvedBy): self
    {
        $this->approvedBy = $approvedBy;

        return $this;
    }

    public function getApprovedAt(): ?\DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function setApprovedAt(?\DateTimeImmutable $approvedAt): self
    {
        $this->approvedAt = $approvedAt;

        return $this;
    }

    public function isOpen(): bool
    {
        return $this->status === AbsenceStatus::REQUESTED;
    }
}
