<?php

namespace KimaiPlugin\HolidayBundle\Tests\Service;

use App\WorkingTime\Mode\WorkingTimeModeDay;
use App\WorkingTime\Mode\WorkingTimeModeFactory;
use App\WorkingTime\Mode\WorkingTimeModeNone;
use KimaiPlugin\HolidayBundle\Repository\PublicHolidayGroupRepository;
use KimaiPlugin\HolidayBundle\Service\UserWorkContract;
use KimaiPlugin\HolidayBundle\Tests\Support\UserBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class UserWorkContractTest extends TestCase
{
    public function testSaturdayWorkerHasExpectedSecondsOnSaturday(): void
    {
        $user = UserBuilder::make()->wednesdayToSaturday(7 * 3600, 9 * 3600)->build();
        $contract = $this->contract();

        self::assertSame(9 * 3600, $contract->getExpectedSecondsForDate($user, new \DateTimeImmutable('2026-10-24'))); // Sa
        self::assertSame(0, $contract->getExpectedSecondsForDate($user, new \DateTimeImmutable('2026-10-26'))); // Mo
    }

    public function testBeforeStartIsZero(): void
    {
        $user = UserBuilder::make()->wednesdayToSaturday(7 * 3600, 9 * 3600)->startingOn('2026-11-01')->build();

        self::assertSame(0, $this->contract()->getExpectedSecondsForDate($user, new \DateTimeImmutable('2026-10-24')));
    }

    private function contract(): UserWorkContract
    {
        $factory = new WorkingTimeModeFactory([new WorkingTimeModeNone(), new WorkingTimeModeDay()], new NullLogger());

        return new UserWorkContract($factory, $this->createStub(PublicHolidayGroupRepository::class));
    }
}
