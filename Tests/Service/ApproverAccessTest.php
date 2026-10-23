<?php

namespace KimaiPlugin\HolidayBundle\Tests\Service;

use App\Entity\User;
use App\Security\RolePermissionManager;
use App\User\PermissionService;
use KimaiPlugin\HolidayBundle\Service\ApproverAccess;
use KimaiPlugin\HolidayBundle\Tests\Support\UserBuilder;
use PHPUnit\Framework\TestCase;

/** Runs against Kimai's real RolePermissionManager (only its permission store is stubbed). */
final class ApproverAccessTest extends TestCase
{
    public function testRoleAdminWhoIsNotTheSessionUserMayAccessEveryone(): void
    {
        $employee = $this->withId(UserBuilder::make()->build(), 1);
        $admin = $this->withId(UserBuilder::make()->build(), 2);
        // the employee sits in a team the admin is not part of, so only "view all data" can grant access
        $team = new \App\Entity\Team('Other');
        $team->addUser($employee);
        $admin->setRoles([User::ROLE_ADMIN]);

        // the "see all data" flag is only initialised for the logged-in user, so it is false here
        $this->assertFalse($admin->canSeeAllData());
        $this->assertTrue($this->access()->mayAccess($employee, $admin));
    }

    public function testPlainUserMayNotAccessOthers(): void
    {
        $this->assertFalse($this->access()->mayAccess($this->withId(UserBuilder::make()->build(), 1), $this->withId(UserBuilder::make()->build(), 2)));
    }

    private function withId(User $user, int $id): User
    {
        // Kimai's checkUserAccess() short-circuits on equal ids, and unsaved users all have null
        (new \ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }

    private function access(): ApproverAccess
    {
        $manager = new RolePermissionManager(
            $this->createStub(PermissionService::class),
            [User::ROLE_ADMIN => ['view_all_data' => true, 'approve_other_absence' => true]],
            ['view_all_data' => true, 'approve_other_absence' => true],
        );

        return new ApproverAccess($manager);
    }
}
