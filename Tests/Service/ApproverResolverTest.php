<?php

namespace KimaiPlugin\HolidayBundle\Tests\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use KimaiPlugin\HolidayBundle\Service\ApproverAccess;
use KimaiPlugin\HolidayBundle\Service\ApproverResolver;
use KimaiPlugin\HolidayBundle\Tests\Support\UserBuilder;
use PHPUnit\Framework\TestCase;

final class ApproverResolverTest extends TestCase
{
    public function testBothAdminsAreApproversOfEmployee(): void
    {
        [$a, $b, $employee] = $this->users();

        $this->assertSame([$a, $b], $this->resolver([$a, $b, $employee], [$a, $b])->approversFor($employee));
    }

    public function testSubjectIsNeverOwnApprover(): void
    {
        [$a, $b] = $this->users();

        $this->assertSame([$b], $this->resolver([$a, $b], [$a, $b])->approversFor($a));
    }

    public function testDisabledUserIsNotApprover(): void
    {
        [$a, $b, $employee] = $this->users();
        $b->setEnabled(false);

        $this->assertSame([$a], $this->resolver([$a, $b, $employee], [$a, $b])->approversFor($employee));
    }

    public function testSubjectsOfApproverExcludeSelf(): void
    {
        [$a, $b, $employee] = $this->users();

        $this->assertSame([$b, $employee], $this->resolver([$a, $b, $employee], [$a, $b])->subjectsFor($a));
    }

    /** @return list<User> */
    private function users(): array
    {
        return [UserBuilder::make()->build(), UserBuilder::make()->build(), UserBuilder::make()->build()];
    }

    /**
     * @param list<User> $enabled all users the repository returns
     * @param list<User> $approvers users with the approval permission (they access everyone)
     */
    private function resolver(array $enabled, array $approvers): ApproverResolver
    {
        $repository = $this->createStub(UserRepository::class);
        $repository->method('findBy')->willReturn($enabled);

        $access = $this->createStub(ApproverAccess::class);
        $access->method('mayApprove')->willReturnCallback(static fn (User $u): bool => \in_array($u, $approvers, true));
        $access->method('mayAccess')->willReturn(true);

        return new ApproverResolver($repository, $access);
    }
}
