<?php

namespace KimaiPlugin\HolidayBundle\Tests\Service;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Service\HolidayConfiguration;
use KimaiPlugin\HolidayBundle\Service\TimesheetChangePolicy;
use PHPUnit\Framework\TestCase;

final class TimesheetChangePolicyTest extends TestCase
{
    private const NOW = '2026-11-14 10:00:00';

    public function testDayAtTheLimitIsFree(): void
    {
        self::assertFalse($this->isLate('2026-10-31 09:00:00', 14));
    }

    public function testDayBeforeTheLimitIsLate(): void
    {
        self::assertTrue($this->isLate('2026-10-30 09:00:00', 14));
    }

    public function testDayCountsInTheTimezoneOfThePerson(): void
    {
        // 30.10. 23:30 UTC is 31.10. 00:30 in Vienna
        $begin = new \DateTime('2026-10-30 23:30:00', new \DateTimeZone('UTC'));

        self::assertFalse($this->policy(14)->isLate($begin, $this->user(), $this->now()));
    }

    public function testZeroDaysSwitchesOff(): void
    {
        self::assertFalse($this->isLate('2020-01-01 09:00:00', 0));
    }

    private function isLate(string $begin, int $days): bool
    {
        return $this->policy($days)->isLate(new \DateTime($begin, new \DateTimeZone('Europe/Vienna')), $this->user(), $this->now());
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::NOW, new \DateTimeZone('Europe/Vienna'));
    }

    private function user(): User
    {
        $user = new User();
        $user->setTimezone('Europe/Vienna');

        return $user;
    }

    private function policy(int $days): TimesheetChangePolicy
    {
        $configuration = $this->createStub(HolidayConfiguration::class);
        $configuration->method('getTimesheetApprovalDays')->willReturn($days);

        return new TimesheetChangePolicy($configuration);
    }
}
