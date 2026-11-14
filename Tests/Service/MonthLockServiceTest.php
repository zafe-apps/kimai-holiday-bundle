<?php

namespace KimaiPlugin\HolidayBundle\Tests\Service;

use KimaiPlugin\HolidayBundle\Entity\MonthLock;
use KimaiPlugin\HolidayBundle\Repository\MonthLockRepository;
use KimaiPlugin\HolidayBundle\Repository\TimesheetChangeRepository;
use KimaiPlugin\HolidayBundle\Service\MonthLockService;
use KimaiPlugin\HolidayBundle\Tests\Support\UserBuilder;
use PHPUnit\Framework\TestCase;

final class MonthLockServiceTest extends TestCase
{
    /** @var list<MonthLock> */
    private array $saved = [];
    /** @var list<string> */
    private array $asked = [];

    public function testOpenTimesheetChangeBlocksTheLock(): void
    {
        $user = UserBuilder::make()->build();

        try {
            $this->service(true)->lock($user, 2026, 10, $user);
            self::fail('expected an exception');
        } catch (\RuntimeException $e) {
            self::assertSame('holiday.error.month_open_changes', $e->getMessage());
        }

        self::assertSame([], $this->saved);
        // earlier months are locked too, so the whole year up to the month counts
        self::assertSame(['2026-01-01 2026-10-31'], $this->asked);
    }

    public function testWithoutOpenChangesTheMonthsAreLocked(): void
    {
        $user = UserBuilder::make()->build();

        $this->service(false)->lock($user, 2026, 2, $user);

        self::assertCount(2, $this->saved);
    }

    private function service(bool $openChanges): MonthLockService
    {
        $locks = $this->createStub(MonthLockRepository::class);
        $locks->method('isLocked')->willReturn(false);
        $locks->method('save')->willReturnCallback(function (MonthLock $lock): void {
            $this->saved[] = $lock;
        });

        $changes = $this->createStub(TimesheetChangeRepository::class);
        $changes->method('hasOpenInRange')->willReturnCallback(
            function ($user, \DateTimeInterface $from, \DateTimeInterface $to) use ($openChanges): bool {
                $this->asked[] = $from->format('Y-m-d') . ' ' . $to->format('Y-m-d');

                return $openChanges;
            }
        );

        return new MonthLockService($locks, $changes);
    }
}
