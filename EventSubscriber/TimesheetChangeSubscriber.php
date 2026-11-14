<?php

namespace KimaiPlugin\HolidayBundle\EventSubscriber;

use App\Entity\Timesheet;
use App\Entity\User;
use App\Event\TimesheetCreatePreEvent;
use App\Event\TimesheetDeleteMultiplePreEvent;
use App\Event\TimesheetDeletePreEvent;
use App\Event\TimesheetStopPreEvent;
use App\Event\TimesheetUpdateMultiplePreEvent;
use App\Event\TimesheetUpdatePreEvent;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\ManagerRegistry;
use KimaiPlugin\HolidayBundle\Enum\AbsenceStatus;
use KimaiPlugin\HolidayBundle\Enum\TimesheetChangeKind;
use KimaiPlugin\HolidayBundle\Service\AbsenceTimesheetMarker;
use KimaiPlugin\HolidayBundle\Service\TimesheetChangeBatch;
use KimaiPlugin\HolidayBundle\Service\TimesheetChangeGate;
use KimaiPlugin\HolidayBundle\Service\TimesheetChangeService;
use KimaiPlugin\HolidayBundle\Service\TimesheetSnapshot;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Watches every way Kimai creates, changes, stops or deletes a timesheet entry. The old state is taken
 * before the write, the new one after the flush (Kimai computes duration and rounding in onFlush).
 */
#[AsDoctrineListener(event: Events::postFlush)]
class TimesheetChangeSubscriber implements EventSubscriberInterface
{
    private const FLASH_TYPE = 'warning';
    private const FLASH_KEY = 'holiday.change.needs_approval';

    /** @var list<array{kind: TimesheetChangeKind, timesheet: Timesheet, id: ?int, owner: User, before: ?TimesheetSnapshot, actor: User}> */
    private array $pending = [];
    private bool $recording = false;
    private bool $flashed = false;

    public function __construct(
        private readonly TimesheetChangeService $service,
        private readonly TimesheetChangeBatch $batch,
        private readonly TimesheetChangeGate $gate,
        private readonly AbsenceTimesheetMarker $marker,
        private readonly ManagerRegistry $registry,
        private readonly TokenStorageInterface $tokens,
        private readonly RequestStack $requests,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TimesheetCreatePreEvent::class => 'onCreate',
            TimesheetUpdatePreEvent::class => 'onUpdate',
            TimesheetStopPreEvent::class => 'onStop',
            TimesheetDeletePreEvent::class => 'onDelete',
            TimesheetUpdateMultiplePreEvent::class => 'onUpdateMultiple',
            TimesheetDeleteMultiplePreEvent::class => 'onDeleteMultiple',
            KernelEvents::TERMINATE => 'onTerminate',
            ConsoleEvents::TERMINATE => 'onTerminate',
        ];
    }

    public function onCreate(TimesheetCreatePreEvent $event): void
    {
        $this->remember($event->getTimesheet(), TimesheetChangeKind::CREATE);
    }

    public function onUpdate(TimesheetUpdatePreEvent $event): void
    {
        $this->rememberSaved($event->getTimesheet());
    }

    public function onStop(TimesheetStopPreEvent $event): void
    {
        $this->rememberSaved($event->getTimesheet());
    }

    public function onDelete(TimesheetDeletePreEvent $event): void
    {
        $this->remember($event->getTimesheet(), TimesheetChangeKind::DELETE);
    }

    public function onUpdateMultiple(TimesheetUpdateMultiplePreEvent $event): void
    {
        foreach ($event->getTimesheets() as $timesheet) {
            $this->rememberSaved($timesheet);
        }
    }

    public function onDeleteMultiple(TimesheetDeleteMultiplePreEvent $event): void
    {
        foreach ($event->getTimesheets() as $timesheet) {
            $this->remember($timesheet, TimesheetChangeKind::DELETE);
        }
    }

    /** Doctrine: the write is done, the entries carry their final values. */
    public function postFlush(): void
    {
        if ($this->recording || $this->pending === []) {
            return;
        }

        $manager = $this->manager();
        $pending = $this->pending;
        $this->pending = [];
        $now = new \DateTimeImmutable();

        // record() saves its own rows; that flush must not come back here
        $this->recording = true;
        try {
            foreach ($pending as $item) {
                $this->recordItem($item, $manager, $now);
            }
        } finally {
            $this->recording = false;
        }
    }

    public function onTerminate(): void
    {
        $this->pending = [];
        $this->batch->flushMails();
    }

    /** Quick entry sends new rows through the update event: without an id it is a create. */
    private function rememberSaved(Timesheet $timesheet): void
    {
        $kind = $timesheet->getId() === null ? TimesheetChangeKind::CREATE : TimesheetChangeKind::UPDATE;
        $this->remember($timesheet, $kind);
    }

    private function remember(Timesheet $timesheet, TimesheetChangeKind $kind): void
    {
        $actor = $this->tokens->getToken()?->getUser();
        $owner = $timesheet->getUser();
        if (!($actor instanceof User) || $owner === null) {
            return;
        }
        if ($this->gate->isSuspended() || $this->marker->isAbsenceEntry($timesheet)) {
            return;
        }

        $this->pending[] = [
            'kind' => $kind,
            'timesheet' => $timesheet,
            'id' => $timesheet->getId(),
            'owner' => $owner,
            'before' => $this->stateBefore($timesheet, $kind),
            'actor' => $actor,
        ];
    }

    private function stateBefore(Timesheet $timesheet, TimesheetChangeKind $kind): ?TimesheetSnapshot
    {
        if ($kind === TimesheetChangeKind::CREATE) {
            return null;
        }

        // what Doctrine loaded; the form has already changed the entity itself
        $original = $this->manager()->getUnitOfWork()->getOriginalEntityData($timesheet);
        if ($original === []) {
            return TimesheetSnapshot::fromTimesheet($timesheet);
        }

        return TimesheetSnapshot::fromOriginal($original, $timesheet);
    }

    /**
     * @param array{kind: TimesheetChangeKind, timesheet: Timesheet, id: ?int, owner: User, before: ?TimesheetSnapshot, actor: User} $item
     */
    private function recordItem(array $item, EntityManagerInterface $manager, \DateTimeImmutable $now): void
    {
        $timesheet = $item['timesheet'];
        $deleted = $item['kind'] === TimesheetChangeKind::DELETE;

        // the write did not happen (validation failed, another flush came first): nothing to record
        if ($deleted === $manager->contains($timesheet)) {
            return;
        }
        if (!$deleted && $timesheet->getId() === null) {
            return;
        }

        $change = $this->service->record(
            $item['kind'],
            $item['owner'],
            $item['id'] ?? $timesheet->getId(),
            $item['before'],
            $deleted ? null : TimesheetSnapshot::fromTimesheet($timesheet),
            $item['actor'],
            $now,
        );

        if ($change?->getStatus() === AbsenceStatus::REQUESTED) {
            $this->flashOnce();
        }
    }

    /** Tells the person in the browser; API calls have no page to show it on. */
    private function flashOnce(): void
    {
        $request = $this->requests->getMainRequest();
        if ($this->flashed || $request === null || !$request->hasSession() || str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $this->flashed = true;
        try {
            $request->getSession()->getFlashBag()->add(self::FLASH_TYPE, self::FLASH_KEY);
        } catch (\Throwable) {
            // no usable session: the request page still shows the state
        }
    }

    private function manager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $manager */
        $manager = $this->registry->getManagerForClass(Timesheet::class);

        return $manager;
    }
}
