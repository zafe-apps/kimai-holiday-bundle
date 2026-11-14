<?php

namespace KimaiPlugin\HolidayBundle\Tests\Service;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Entity\TimesheetChange;
use KimaiPlugin\HolidayBundle\Service\ApproverResolver;
use KimaiPlugin\HolidayBundle\Service\TimesheetChangePermissions;
use KimaiPlugin\HolidayBundle\Tests\Support\UserBuilder;
use PHPUnit\Framework\TestCase;

final class TimesheetChangePermissionsTest extends TestCase
{
    public function testApproverOfThePersonMayDecide(): void
    {
        $owner = UserBuilder::make()->build();
        $lead = UserBuilder::make()->build();
        $change = (new TimesheetChange())->setUser($owner)->setRequestedBy($owner);

        self::assertTrue($this->permissions([$lead])->canDecide($lead, $change));
        self::assertFalse($this->permissions([])->canDecide($lead, $change));
    }

    public function testNeitherOwnerNorRequesterMayDecide(): void
    {
        $owner = UserBuilder::make()->build();
        $lead = UserBuilder::make()->build();
        $own = (new TimesheetChange())->setUser($lead)->setRequestedBy($lead);
        $requested = (new TimesheetChange())->setUser($owner)->setRequestedBy($lead);

        self::assertFalse($this->permissions([$lead])->canDecide($lead, $own));
        self::assertFalse($this->permissions([$lead])->canDecide($lead, $requested));
    }

    /** @param list<User> $approvers */
    private function permissions(array $approvers): TimesheetChangePermissions
    {
        $resolver = $this->createStub(ApproverResolver::class);
        $resolver->method('isApproverOf')->willReturnCallback(
            static fn (User $actor, User $subject): bool => $actor !== $subject && \in_array($actor, $approvers, true)
        );
        $resolver->method('isSame')->willReturnCallback(static fn (User $a, User $b): bool => $a === $b);

        return new TimesheetChangePermissions($resolver);
    }
}
