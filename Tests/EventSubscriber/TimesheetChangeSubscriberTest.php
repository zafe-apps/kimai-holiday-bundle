<?php

namespace KimaiPlugin\HolidayBundle\Tests\EventSubscriber;

use App\Entity\Timesheet;
use App\Entity\TimesheetMeta;
use App\Entity\User;
use App\Event\TimesheetCreatePreEvent;
use App\Event\TimesheetDeletePreEvent;
use App\Event\TimesheetUpdateMultiplePreEvent;
use App\Event\TimesheetUpdatePreEvent;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\ManagerRegistry;
use KimaiPlugin\HolidayBundle\EventSubscriber\TimesheetChangeSubscriber;
use KimaiPlugin\HolidayBundle\Enum\TimesheetChangeKind;
use KimaiPlugin\HolidayBundle\Service\AbsenceTimesheetMarker;
use KimaiPlugin\HolidayBundle\Service\AbsenceTimesheetService;
use KimaiPlugin\HolidayBundle\Service\HolidayConfiguration;
use KimaiPlugin\HolidayBundle\Service\TimesheetChangeBatch;
use KimaiPlugin\HolidayBundle\Service\TimesheetChangeGate;
use KimaiPlugin\HolidayBundle\Service\TimesheetChangeService;
use KimaiPlugin\HolidayBundle\Service\TimesheetSnapshot;
use KimaiPlugin\HolidayBundle\Tests\Support\TimesheetChangeBuilder;
use KimaiPlugin\HolidayBundle\Tests\Support\UserBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class TimesheetChangeSubscriberTest extends TestCase
{
    private User $owner;
    private ?User $actor = null;
    /** @var array<int, array<string, mixed>> original data by object id */
    private array $original = [];
    /** @var list<Timesheet> */
    private array $managed = [];
    /** @var list<array{TimesheetChangeKind, ?int, ?TimesheetSnapshot, ?TimesheetSnapshot}> */
    private array $recorded = [];
    private TimesheetChangeGate $gate;
    private bool $recordFails = false;

    protected function setUp(): void
    {
        $this->owner = UserBuilder::make()->build();
        $this->actor = $this->owner;
        $this->gate = new TimesheetChangeGate();
    }

    public function testUpdateIsRecordedAfterTheFlushWithOldAndNewTimes(): void
    {
        $timesheet = $this->existing(7, '2026-10-20 09:00', '2026-10-20 12:00');
        $this->loadedAs($timesheet, '2026-10-20 08:00', '2026-10-20 12:00');
        $subscriber = $this->subscriber();

        $subscriber->onUpdate(new TimesheetUpdatePreEvent($timesheet));
        self::assertSame([], $this->recorded);

        $subscriber->postFlush();

        self::assertCount(1, $this->recorded);
        [$kind, $id, $before, $after] = $this->recorded[0];
        self::assertSame(TimesheetChangeKind::UPDATE, $kind);
        self::assertSame(7, $id);
        self::assertStringContainsString('T08:00', (string) $before?->begin);
        self::assertStringContainsString('T09:00', (string) $after?->begin);

        // the same change is not recorded twice
        $subscriber->postFlush();
        self::assertCount(1, $this->recorded);
    }

    public function testQuickEntrySendsNewRowsThroughTheUpdateEvent(): void
    {
        $new = TimesheetChangeBuilder::timesheet($this->owner, '2026-10-20 08:00', '2026-10-20 12:00');
        $old = $this->existing(7, '2026-10-21 09:00', '2026-10-21 12:00');
        $this->loadedAs($old, '2026-10-21 08:00', '2026-10-21 12:00');
        $subscriber = $this->subscriber();

        $subscriber->onUpdateMultiple(new TimesheetUpdateMultiplePreEvent([$new, $old]));
        (new \ReflectionProperty(Timesheet::class, 'id'))->setValue($new, 8);
        $this->managed[] = $new;
        $subscriber->postFlush();

        self::assertSame(TimesheetChangeKind::CREATE, $this->recorded[0][0]);
        self::assertSame(8, $this->recorded[0][1]);
        self::assertNull($this->recorded[0][2]);
        self::assertSame(TimesheetChangeKind::UPDATE, $this->recorded[1][0]);
    }

    public function testDeleteKeepsIdAndOldState(): void
    {
        $timesheet = $this->existing(7, '2026-10-20 08:00', '2026-10-20 12:00');
        $subscriber = $this->subscriber();

        $subscriber->onDelete(new TimesheetDeletePreEvent($timesheet));
        $this->managed = [];
        $subscriber->postFlush();

        [$kind, $id, $before, $after] = $this->recorded[0];
        self::assertSame(TimesheetChangeKind::DELETE, $kind);
        self::assertSame(7, $id);
        self::assertNotNull($before);
        self::assertNull($after);
    }

    public function testCreateThatNeverReachedTheDatabaseIsNotRecorded(): void
    {
        $timesheet = TimesheetChangeBuilder::timesheet($this->owner, '2026-10-20 08:00', '2026-10-20 12:00');
        $subscriber = $this->subscriber();

        $subscriber->onCreate(new TimesheetCreatePreEvent($timesheet));
        $subscriber->postFlush();

        self::assertSame([], $this->recorded);
    }

    public function testNoUserNoRecord(): void
    {
        $this->actor = null;
        $timesheet = $this->existing(7, '2026-10-20 09:00', '2026-10-20 12:00');
        $subscriber = $this->subscriber();

        $subscriber->onUpdate(new TimesheetUpdatePreEvent($timesheet));
        $subscriber->postFlush();

        self::assertSame([], $this->recorded);
    }

    public function testAbsenceEntryIsNotRecorded(): void
    {
        $timesheet = $this->existing(7, '2026-10-20 09:00', '2026-10-20 12:00');
        $timesheet->setMetaField((new TimesheetMeta())->setName(AbsenceTimesheetService::META_KEY)->setValue('3'));
        $subscriber = $this->subscriber();

        $subscriber->onUpdate(new TimesheetUpdatePreEvent($timesheet));
        $subscriber->postFlush();

        self::assertSame([], $this->recorded);
    }

    public function testSuspendedGateRecordsNothing(): void
    {
        $timesheet = $this->existing(7, '2026-10-20 09:00', '2026-10-20 12:00');
        $subscriber = $this->subscriber();

        $this->gate->suspend(function () use ($subscriber, $timesheet): void {
            $subscriber->onUpdate(new TimesheetUpdatePreEvent($timesheet));
            $subscriber->postFlush();
        });
        $subscriber->postFlush();

        self::assertSame([], $this->recorded);
        self::assertFalse($this->gate->isSuspended());
    }

    public function testFailingRecorderNeverBreaksTheTimesheetWrite(): void
    {
        $this->recordFails = true;
        $timesheet = $this->existing(7, '2026-10-20 09:00', '2026-10-20 12:00');
        $subscriber = $this->subscriber();

        $subscriber->onUpdate(new TimesheetUpdatePreEvent($timesheet));
        $subscriber->postFlush();

        self::assertSame([], $this->recorded);
    }

    private function existing(int $id, string $begin, string $end): Timesheet
    {
        $timesheet = TimesheetChangeBuilder::timesheet($this->owner, $begin, $end, 0, $id);
        $this->managed[] = $timesheet;

        return $timesheet;
    }

    private function loadedAs(Timesheet $timesheet, string $begin, string $end): void
    {
        $zone = new \DateTimeZone(TimesheetChangeBuilder::ZONE);
        $from = new \DateTime($begin, $zone);
        $to = new \DateTime($end, $zone);
        $this->original[spl_object_id($timesheet)] = [
            'begin' => $from,
            'end' => $to,
            'duration' => $to->getTimestamp() - $from->getTimestamp(),
            'break' => 0,
            'timezone' => TimesheetChangeBuilder::ZONE,
        ];
    }

    private function subscriber(): TimesheetChangeSubscriber
    {
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getOriginalEntityData')->willReturnCallback(
            fn (object $entity): array => $this->original[spl_object_id($entity)] ?? []
        );

        $manager = $this->createStub(EntityManagerInterface::class);
        $manager->method('getUnitOfWork')->willReturn($unitOfWork);
        $manager->method('contains')->willReturnCallback(fn (object $entity): bool => \in_array($entity, $this->managed, true));

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($manager);

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturnCallback(fn (): ?User => $this->actor);
        $tokens = $this->createStub(TokenStorageInterface::class);
        $tokens->method('getToken')->willReturn($token);

        $service = $this->createStub(TimesheetChangeService::class);
        $service->method('record')->willReturnCallback(
            function (TimesheetChangeKind $kind, User $owner, ?int $id, ?TimesheetSnapshot $before, ?TimesheetSnapshot $after) {
                if ($this->recordFails) {
                    throw new \RuntimeException('table missing');
                }
                $this->recorded[] = [$kind, $id, $before, $after];

                return null;
            }
        );

        return new TimesheetChangeSubscriber(
            $service,
            $this->createStub(TimesheetChangeBatch::class),
            $this->gate,
            new AbsenceTimesheetMarker($this->createStub(HolidayConfiguration::class)),
            $registry,
            $tokens,
            new RequestStack(),
            new NullLogger(),
        );
    }
}
