<?php

namespace KimaiPlugin\HolidayBundle\Tests\Service;

use App\Entity\User;
use KimaiPlugin\HolidayBundle\Service\ExtraShiftPermissions;
use KimaiPlugin\HolidayBundle\Tests\Support\ExtraShiftBuilder;
use KimaiPlugin\HolidayBundle\Tests\Support\UserBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class ExtraShiftPermissionsTest extends TestCase
{
    public function testOwnAllowedWithExtraShiftOwn(): void
    {
        $me = UserBuilder::make()->build();

        self::assertTrue($this->permissions($me, ['extra_shift_own'])->canCreateFor($me));
        self::assertFalse($this->permissions($me, [])->canCreateFor($me));
    }

    public function testOtherDeniedWithoutExtraShiftOther(): void
    {
        $me = UserBuilder::make()->build();
        $other = UserBuilder::make()->build();

        self::assertFalse($this->permissions($me, ['extra_shift_own', 'access_user'])->canCreateFor($other));
        self::assertTrue($this->permissions($me, ['extra_shift_other', 'access_user'])->canCreateFor($other));
        self::assertFalse($this->permissions($me, ['extra_shift_other'])->canCreateFor($other));
    }

    public function testDeleteByCreator(): void
    {
        $me = UserBuilder::make()->build();
        $other = UserBuilder::make()->build();
        $shift = ExtraShiftBuilder::on($other, '2026-10-29', $me);

        self::assertTrue($this->permissions($me, [])->canDelete($shift));
        self::assertFalse($this->permissions($me, [])->canDelete(ExtraShiftBuilder::on($other, '2026-10-29', $other)));
    }

    /**
     * @param list<string> $granted
     */
    private function permissions(User $current, array $granted): ExtraShiftPermissions
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($current);
        $security->method('isGranted')->willReturnCallback(static fn (string $attr): bool => \in_array($attr, $granted, true));

        return new ExtraShiftPermissions($security);
    }
}
