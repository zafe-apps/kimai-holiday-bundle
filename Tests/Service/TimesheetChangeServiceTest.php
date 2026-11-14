<?php

namespace KimaiPlugin\HolidayBundle\Tests\Service;

use App\Entity\Timesheet;
use App\Entity\User;
use KimaiPlugin\HolidayBundle\Entity\TimesheetChange;
use KimaiPlugin\HolidayBundle\Enum\AbsenceStatus;
use KimaiPlugin\HolidayBundle\Enum\TimesheetChangeKind;
use KimaiPlugin\HolidayBundle\Repository\TimesheetChangeRepository;
use KimaiPlugin\HolidayBundle\Service\ApproverResolver;
use KimaiPlugin\HolidayBundle\Service\HolidayConfiguration;
use KimaiPlugin\HolidayBundle\Service\PeriodLockGuard;
use KimaiPlugin\HolidayBundle\Service\TimesheetChangeBatch;
use KimaiPlugin\HolidayBundle\Service\TimesheetChangeGate;
use KimaiPlugin\HolidayBundle\Service\TimesheetChangePolicy;
use KimaiPlugin\HolidayBundle\Service\TimesheetChangeService;
use KimaiPlugin\HolidayBundle\Service\TimesheetSnapshot;
use KimaiPlugin\HolidayBundle\Service\TimesheetStore;
use KimaiPlugin\HolidayBundle\Tests\Support\TimesheetChangeBuilder;
use KimaiPlugin\HolidayBundle\Tests\Support\UserBuilder;
use PHPUnit\Framework\TestCase;

final class TimesheetChangeServiceTest extends TestCase
{
    private const OLD_DAY = '2026-10-20';
    private const TIMESHEET = 7;

    private User $owner;
    private User $lead;
    private User $otherLead;
    /** @var list<User> */
    private array $approvers = [];
    private ?TimesheetChange $open = null;
    /** @var list<TimesheetChange> */
    private array $saved = [];
    /** @var list<TimesheetChange> */
    private array $removed = [];
    /** @var list<array{TimesheetChange, string}> */
    private array $batched = [];

    private ?Timesheet $stored = null;
    private ?Timesheet $restored = null;
    private bool $locked = false;
    private int $days = 14;
    private int $lookups = 0;
    /** @var list<string> */
    private array $storeCalls = [];
    private TimesheetChangeGate $gate;

    protected function setUp(): void
    {
        $this->gate = new TimesheetChangeGate();
        $this->owner = $this->user();
        $this->lead = $this->user();
        $this->otherLead = $this->user();
        $this->approvers = [$this->lead, $this->otherLead];
    }

    public function testRecentDayNeedsNothing(): void
    {
        $change = $this->record(TimesheetChangeKind::UPDATE, $this->at('2026-11-10', '08:00', '12:00'), $this->at('2026-11-10', '09:00', '12:00'));

        self::assertNull($change);
        self::assertSame([], $this->saved);
    }

    public function testOnlyTheDescriptionChangedNeedsNothing(): void
    {
        $change = $this->record(TimesheetChangeKind::UPDATE, $this->old('08:00', '12:00'), $this->old('08:00', '12:00'));

        self::assertNull($change);
    }

    public function testLateUpdateIsRequested(): void
    {
        $change = $this->record(TimesheetChangeKind::UPDATE, $this->old('08:00', '12:00'), $this->old('08:00', '13:00'));

        self::assertNotNull($change);
        self::assertSame(AbsenceStatus::REQUESTED, $change->getStatus());
        self::assertSame(TimesheetChangeKind::UPDATE, $change->getKind());
        self::assertSame(self::OLD_DAY, $change->getDay()?->format('Y-m-d'));
        self::assertSame(self::TIMESHEET, $change->getTimesheetId());
        self::assertSame($this->owner, $change->getRequestedBy());
        self::assertSame([[$change, 'requested']], $this->batched);
    }

    public function testMovingARecentEntryToAnOldDayIsLate(): void
    {
        $change = $this->record(TimesheetChangeKind::UPDATE, $this->at('2026-11-10', '08:00', '12:00'), $this->old('08:00', '12:00'));

        self::assertSame(self::OLD_DAY, $change?->getDay()?->format('Y-m-d'));
    }

    public function testLateCreateAndDeleteAreRequested(): void
    {
        $created = $this->record(TimesheetChangeKind::CREATE, null, $this->old('08:00', '12:00'));
        $deleted = $this->record(TimesheetChangeKind::DELETE, $this->old('08:00', '12:00'), null);

        self::assertNull($created?->getBefore());
        self::assertNull($deleted?->getAfter());
        self::assertSame(AbsenceStatus::REQUESTED, $deleted?->getStatus());
    }

    public function testApproverChangingForTheTeamIsApprovedAtOnce(): void
    {
        $change = $this->record(TimesheetChangeKind::UPDATE, $this->old('08:00', '12:00'), $this->old('08:00', '13:00'), $this->lead);

        self::assertSame(AbsenceStatus::APPROVED, $change?->getStatus());
        self::assertSame($this->lead, $change?->getApprovedBy());
        self::assertSame([[$change, 'approved']], $this->batched);
    }

    public function testLeadChangingOwnTimeNeedsTheOtherLead(): void
    {
        $this->owner = $this->lead;
        $this->approvers = [$this->otherLead];

        $change = $this->record(TimesheetChangeKind::UPDATE, $this->old('08:00', '12:00'), $this->old('08:00', '13:00'), $this->lead);

        self::assertSame(AbsenceStatus::REQUESTED, $change?->getStatus());
    }

    public function testNoOtherApproverIsApprovedWithoutDecider(): void
    {
        $this->approvers = [];

        $change = $this->record(TimesheetChangeKind::UPDATE, $this->old('08:00', '12:00'), $this->old('08:00', '13:00'));

        self::assertSame(AbsenceStatus::APPROVED, $change?->getStatus());
        self::assertNull($change?->getApprovedBy());
        self::assertSame([], $this->batched);
    }

    public function testSecondChangeKeepsTheFirstBefore(): void
    {
        $this->open = $this->record(TimesheetChangeKind::UPDATE, $this->old('08:00', '12:00'), $this->old('08:00', '13:00'));
        $this->batched = [];

        $change = $this->record(TimesheetChangeKind::UPDATE, $this->old('08:00', '13:00'), $this->old('08:00', '14:00'));

        self::assertSame($this->open, $change);
        self::assertTrue($change->getBefore()?->sameTimes($this->old('08:00', '12:00')));
        self::assertTrue($change->getAfter()?->sameTimes($this->old('08:00', '14:00')));
        self::assertSame([], $this->batched);
    }

    public function testBackToTheFirstStateDropsTheRequest(): void
    {
        $this->open = $this->record(TimesheetChangeKind::UPDATE, $this->old('08:00', '12:00'), $this->old('08:00', '13:00'));

        $change = $this->record(TimesheetChangeKind::UPDATE, $this->old('08:00', '13:00'), $this->old('08:00', '12:00'));

        self::assertNull($change);
        self::assertCount(1, $this->removed);
        self::assertNull($this->open);
    }

    public function testDeletingAnOpenCreateDropsTheRequest(): void
    {
        $this->open = $this->record(TimesheetChangeKind::CREATE, null, $this->old('08:00', '12:00'));

        $change = $this->record(TimesheetChangeKind::DELETE, $this->old('08:00', '12:00'), null);

        self::assertNull($change);
        self::assertCount(1, $this->removed);
        self::assertNull($this->open);
    }

    public function testDeletingAnOpenUpdateBecomesADelete(): void
    {
        $this->open = $this->record(TimesheetChangeKind::UPDATE, $this->old('08:00', '12:00'), $this->old('08:00', '13:00'));

        $change = $this->record(TimesheetChangeKind::DELETE, $this->old('08:00', '13:00'), null);

        self::assertSame($this->open, $change);
        self::assertSame(TimesheetChangeKind::DELETE, $change->getKind());
        self::assertNull($change->getAfter());
        self::assertTrue($change->getBefore()?->sameTimes($this->old('08:00', '12:00')));
    }

    public function testOpenRequestStaysEvenIfTheSecondChangeAloneWouldBeFree(): void
    {
        // first change moved the entry to a recent day; changing it again there still belongs to the request
        $this->open = $this->record(TimesheetChangeKind::UPDATE, $this->old('08:00', '12:00'), $this->at('2026-11-10', '08:00', '12:00'));

        $change = $this->record(TimesheetChangeKind::UPDATE, $this->at('2026-11-10', '08:00', '12:00'), $this->at('2026-11-10', '09:00', '12:00'));

        self::assertSame($this->open, $change);
        self::assertTrue($change->getAfter()?->sameTimes($this->at('2026-11-10', '09:00', '12:00')));
    }

    public function testSwitchedOffAsksTheDatabaseNothing(): void
    {
        $this->days = 0;

        $change = $this->record(TimesheetChangeKind::UPDATE, $this->old('08:00', '12:00'), $this->old('08:00', '13:00'));

        self::assertNull($change);
        self::assertSame(0, $this->lookups);
    }

    public function testApproveLeavesTheEntryAlone(): void
    {
        $change = $this->openChange(TimesheetChangeKind::UPDATE);

        $this->service()->approve($change, $this->lead);

        self::assertSame(AbsenceStatus::APPROVED, $change->getStatus());
        self::assertSame($this->lead, $change->getApprovedBy());
        self::assertSame([], $this->storeCalls);
        self::assertSame([[$change, 'approved']], $this->batched);
    }

    public function testRejectedUpdateGetsTheOldTimesBack(): void
    {
        $change = $this->openChange(TimesheetChangeKind::UPDATE);
        $this->stored = TimesheetChangeBuilder::timesheet($this->owner, self::OLD_DAY . ' 08:00', self::OLD_DAY . ' 13:00', 0, self::TIMESHEET);

        $this->service()->reject($change, $this->lead);

        self::assertSame(['apply 12:00', 'update suspended'], $this->storeCalls);
        self::assertSame(AbsenceStatus::REJECTED, $change->getStatus());
        // the restore itself is no new request and sends exactly one mail event
        self::assertSame([[$change, 'rejected']], $this->batched);
        self::assertCount(1, $this->saved);
    }

    public function testRejectedCreateIsDeleted(): void
    {
        $change = $this->openChange(TimesheetChangeKind::CREATE);
        $this->stored = TimesheetChangeBuilder::timesheet($this->owner, self::OLD_DAY . ' 08:00', self::OLD_DAY . ' 13:00', 0, self::TIMESHEET);

        $this->service()->reject($change, $this->lead);

        self::assertSame(['delete suspended'], $this->storeCalls);
        self::assertSame(AbsenceStatus::REJECTED, $change->getStatus());
    }

    public function testRejectedDeleteComesBackWithANewId(): void
    {
        $change = $this->openChange(TimesheetChangeKind::DELETE);
        $this->restored = TimesheetChangeBuilder::timesheet($this->owner, self::OLD_DAY . ' 08:00', self::OLD_DAY . ' 12:00', 0, 99);

        $this->service()->reject($change, $this->lead);

        self::assertSame(['restore suspended'], $this->storeCalls);
        self::assertSame(99, $change->getTimesheetId());
        self::assertSame(AbsenceStatus::REJECTED, $change->getStatus());
    }

    public function testRejectedDeleteWithoutProjectStaysOpen(): void
    {
        $change = $this->openChange(TimesheetChangeKind::DELETE);
        $this->restored = null;

        $this->assertFails('holiday.error.change_target_missing', fn () => $this->service()->reject($change, $this->lead));

        self::assertSame(AbsenceStatus::REQUESTED, $change->getStatus());
        self::assertSame([], $this->batched);
    }

    public function testExportedEntryCannotBeRejected(): void
    {
        $change = $this->openChange(TimesheetChangeKind::UPDATE);
        $this->stored = TimesheetChangeBuilder::timesheet($this->owner, self::OLD_DAY . ' 08:00', self::OLD_DAY . ' 13:00', 0, self::TIMESHEET);
        $this->stored->setExported(true);

        $this->assertFails('holiday.error.change_exported', fn () => $this->service()->reject($change, $this->lead));

        self::assertSame(AbsenceStatus::REQUESTED, $change->getStatus());
        self::assertSame([], $this->storeCalls);
    }

    public function testLockedMonthCannotBeRejectedButApproved(): void
    {
        $this->locked = true;
        $change = $this->openChange(TimesheetChangeKind::UPDATE);
        $this->stored = TimesheetChangeBuilder::timesheet($this->owner, self::OLD_DAY . ' 08:00', self::OLD_DAY . ' 13:00', 0, self::TIMESHEET);

        $this->assertFails('holiday.error.month_locked', fn () => $this->service()->reject($change, $this->lead));
        self::assertSame(AbsenceStatus::REQUESTED, $change->getStatus());

        $this->service()->approve($change, $this->lead);
        self::assertSame(AbsenceStatus::APPROVED, $change->getStatus());
    }

    public function testVanishedEntryClosesTheRequest(): void
    {
        $change = $this->openChange(TimesheetChangeKind::UPDATE);
        $this->stored = null;

        $this->service()->reject($change, $this->lead);

        self::assertSame([], $this->storeCalls);
        self::assertSame(AbsenceStatus::REJECTED, $change->getStatus());
    }

    public function testNobodyDecidesTheirOwnRequest(): void
    {
        $change = $this->openChange(TimesheetChangeKind::UPDATE);

        $this->assertFails('holiday.error.change_own', fn () => $this->service()->approve($change, $this->owner));
        $this->assertFails('holiday.error.change_own', fn () => $this->service()->reject($change, $this->owner));
    }

    public function testDecidedRequestCannotBeDecidedAgain(): void
    {
        $change = $this->openChange(TimesheetChangeKind::UPDATE)->setStatus(AbsenceStatus::APPROVED);

        $this->assertFails('holiday.error.change_not_open', fn () => $this->service()->reject($change, $this->lead));
    }

    private function openChange(TimesheetChangeKind $kind): TimesheetChange
    {
        return (new TimesheetChange())
            ->setUser($this->owner)
            ->setRequestedBy($this->owner)
            ->setTimesheetId(self::TIMESHEET)
            ->setDay(new \DateTimeImmutable(self::OLD_DAY))
            ->setKind($kind)
            ->setBefore($kind === TimesheetChangeKind::CREATE ? null : $this->old('08:00', '12:00'))
            ->setAfter($kind === TimesheetChangeKind::DELETE ? null : $this->old('08:00', '13:00'));
    }

    private function assertFails(string $key, callable $work): void
    {
        try {
            $work();
            self::fail('expected ' . $key);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            self::assertSame($key, $e->getMessage());
        }
    }

    private function record(TimesheetChangeKind $kind, ?TimesheetSnapshot $before, ?TimesheetSnapshot $after, ?User $actor = null): ?TimesheetChange
    {
        return $this->service()->record(
            $kind,
            $this->owner,
            self::TIMESHEET,
            $before,
            $after,
            $actor ?? $this->owner,
            new \DateTimeImmutable('2026-11-14 10:00', new \DateTimeZone(TimesheetChangeBuilder::ZONE))
        );
    }

    private function old(string $from, string $to): TimesheetSnapshot
    {
        return $this->at(self::OLD_DAY, $from, $to);
    }

    private function at(string $day, string $from, string $to): TimesheetSnapshot
    {
        return TimesheetChangeBuilder::snapshot($day . ' ' . $from, $day . ' ' . $to);
    }

    private function user(): User
    {
        $user = UserBuilder::make()->build();
        $user->setTimezone(TimesheetChangeBuilder::ZONE);

        return $user;
    }

    private function service(): TimesheetChangeService
    {
        $repository = $this->createStub(TimesheetChangeRepository::class);
        $repository->method('findOpenForTimesheet')->willReturnCallback(function (): ?TimesheetChange {
            ++$this->lookups;

            return $this->open;
        });
        $repository->method('save')->willReturnCallback(function (TimesheetChange $change): void {
            $this->saved[] = $change;
        });
        $repository->method('remove')->willReturnCallback(function (TimesheetChange $change): void {
            $this->removed[] = $change;
            $this->open = null;
        });

        $configuration = $this->createStub(HolidayConfiguration::class);
        $configuration->method('getTimesheetApprovalDays')->willReturnCallback(fn (): int => $this->days);

        $resolver = $this->createStub(ApproverResolver::class);
        $resolver->method('approversFor')->willReturnCallback(fn (): array => $this->approvers);
        $resolver->method('isApproverOf')->willReturnCallback(
            fn (User $actor, User $subject): bool => $actor !== $subject && \in_array($actor, $this->approvers, true)
        );
        $resolver->method('isSame')->willReturnCallback(static fn (User $a, User $b): bool => $a === $b);

        $batch = $this->createStub(TimesheetChangeBatch::class);
        $batch->method('add')->willReturnCallback(function (TimesheetChange $change, string $event): void {
            $this->batched[] = [$change, $event];
        });

        $store = $this->createStub(TimesheetStore::class);
        $store->method('find')->willReturnCallback(fn (): ?Timesheet => $this->stored);
        $store->method('applyTimes')->willReturnCallback(function (Timesheet $timesheet, TimesheetSnapshot $state): void {
            $this->storeCalls[] = 'apply ' . substr((string) $state->end, 11, 5);
        });
        $store->method('update')->willReturnCallback(function (): void {
            $this->storeCalls[] = 'update' . ($this->gate->isSuspended() ? ' suspended' : '');
        });
        $store->method('delete')->willReturnCallback(function (): void {
            $this->storeCalls[] = 'delete' . ($this->gate->isSuspended() ? ' suspended' : '');
        });
        $store->method('restore')->willReturnCallback(function (): ?Timesheet {
            $this->storeCalls[] = 'restore' . ($this->gate->isSuspended() ? ' suspended' : '');

            return $this->restored;
        });

        $guard = $this->createStub(PeriodLockGuard::class);
        if ($this->locked) {
            $guard->method('assertOpen')->willThrowException(new \RuntimeException('holiday.error.month_locked'));
        }

        return new TimesheetChangeService($repository, new TimesheetChangePolicy($configuration), $resolver, $batch, $store, $guard, $this->gate);
    }
}
