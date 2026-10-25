<?php

namespace KimaiPlugin\HolidayBundle\Tests\Service;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Entity\Location;
use KimaiPlugin\HolidayBundle\Repository\LocationRepository;
use KimaiPlugin\HolidayBundle\Repository\LocationUserRepository;
use KimaiPlugin\HolidayBundle\Service\LocationPermissions;
use KimaiPlugin\HolidayBundle\Tests\Support\UserBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class LocationPermissionsTest extends TestCase
{
    public function testUserWithOnlyViewAvailabilitySeesOwnLocationOnly(): void
    {
        $own = new Location();
        $other = new Location();
        $user = UserBuilder::make()->build();

        $permissions = $this->permissions($user, ['view_availability'], [$own, $other], $own);

        $this->assertSame([$own], $permissions->visibleLocations());
        $this->assertTrue($permissions->canViewAvailabilityOf($own));
        $this->assertFalse($permissions->canViewAvailabilityOf($other));
        $this->assertFalse($permissions->canView());
        $this->assertFalse($permissions->canEdit());
    }

    public function testEditLocationSeesAllLocations(): void
    {
        $a = new Location();
        $b = new Location();
        $user = UserBuilder::make()->build();

        $permissions = $this->permissions($user, ['edit_location'], [$a, $b], null);

        $this->assertSame([$a, $b], $permissions->visibleLocations());
        $this->assertTrue($permissions->canView());
        $this->assertTrue($permissions->canEdit());
        $this->assertTrue($permissions->canViewAvailabilityOf($b));
    }

    public function testUserWithoutLocationSeesNothing(): void
    {
        $user = UserBuilder::make()->build();

        $permissions = $this->permissions($user, ['view_availability'], [new Location()], null);

        $this->assertSame([], $permissions->visibleLocations());
        $this->assertFalse($permissions->canViewAvailabilityOf(new Location()));
    }

    /**
     * @param list<string>   $granted
     * @param list<Location> $all
     */
    private function permissions(User $user, array $granted, array $all, ?Location $own): LocationPermissions
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);
        $security->method('isGranted')->willReturnCallback(static fn (string $attr): bool => \in_array($attr, $granted, true));

        $locations = $this->createStub(LocationRepository::class);
        $locations->method('findAllOrdered')->willReturn($all);

        $members = $this->createStub(LocationUserRepository::class);
        $members->method('findLocationOf')->willReturn($own);

        return new LocationPermissions($security, $locations, $members);
    }
}
