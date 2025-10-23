<?php

namespace Shasoft\Rbac\Tests\Unit;

use Shasoft\Rbac\Role;
use Shasoft\Rbac\User;
use Shasoft\Rbac\Permission;
use PHPUnit\Framework\Attributes\TestWith;

class UserTest extends Base
{
    public function testStatus()
    {
        $user = $this->rbac->user(1);
        $this->assertStatus($user, 'read');

        $this->rbac->flush();
        $this->assertStatus($user, 'readed');

        $user->delete();
        $this->assertFalse($user->hasExists());

        $this->rbac->flush();

        $user->restore();
        $this->assertTrue($user->hasExists());
        $this->rbac->flush();
    }

    protected function getCountUsers(): int
    {
        $ret = 0;
        $this->rbac->users(3, function (array $users) use (&$ret) {
            $this->assertArrayInstanceof(User::class, $users);
            foreach ($users as $user) {
                self::assertTrue($user instanceof User);
            }
            $ret += count($users);
        });
        return $ret;
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testReadAll(bool $hasClear)
    {
        $maxCount = 10;
        $n = 1;
        while ($n <= $maxCount) {
            $user = $this->rbac->user($n);
            $n++;
        }
        $this->rbac->flush();

        if ($hasClear) {
            $this->rbac->clear();
        }
        // Старые
        $n = 1;
        while ($n <= $maxCount / 2) {
            $user = $this->rbac->user($n);
            $n++;
        }
        // Новые
        $n = 1;
        while ($n <= $maxCount * 2) {
            $this->rbac->user($n);
            $n++;
        }
        //
        self::assertEquals($maxCount * 2, $this->getCountUsers());
    }

    public function testStateReadAll()
    {
        $maxCount = 10;
        $n = 1;
        while ($n <= $maxCount) {
            $user = $this->rbac->user($n);
            $n++;
        }
        $this->rbac->flush();
        $this->rbac->clear();
        $n = 1;
        while ($n <= $maxCount / 2) {
            $user = $this->rbac->user($n);
            $n++;
        }
        $count = 0;
        $this->rbac->users(3, function (array $users) use (&$count) {
            $count += count($users);
            foreach ($users as $user) {
                $user->setBan(new \DateTime('+1 hours'));
            }
            $this->rbac->flush();
        });
        self::assertEquals($maxCount, $count);
    }

    public function testInsertAndDelete()
    {
        $userRoot = $this->rbac->user(666);
        $userRoot->delete();
        $this->rbac->flush();
        self::assertFalse($userRoot->hasExists());

        $this->rbac->clear();

        $userRoot = $this->rbac->user(666);
        self::assertFalse($userRoot->hasExists());
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testReset(bool $hasClear)
    {
        $maxCount = 10;
        $n = 1;
        while ($n <= $maxCount) {
            $user = $this->rbac->user($n);
            $n++;
        }
        $this->rbac->flush();
        if ($hasClear) {
            $this->rbac->clear();
        }
        $maxCount = 10;
        $n = 1;
        while ($n <= $maxCount * 2) {
            $user = $this->rbac->user($n);
            $n++;
        }

        self::assertEquals($maxCount * 2, $this->getCountUsers());

        $this->rbac->reset();

        self::assertEquals(0, $this->getCountUsers());

        $user = $this->rbac->user(1);

        self::assertEquals(1, $this->getCountUsers());

        $this->rbac->flush();
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testRoleAddRemove(bool $hasClear)
    {
        $maxCount = 10;

        $userRoot = $this->rbac->user(1);

        $n = $maxCount - 1;
        while ($n >= 0) {
            $role = $this->rbac->role('rol.name.' . $n);
            $userRoot->roleAdd($role);
            $n--;
        }
        $this->rbac->flush();
        self::assertCount($maxCount, $userRoot->roles());
        if ($hasClear) {
            $this->rbac->clear();
        }
        $userRoot = $this->rbac->user(1);
        self::assertCount($maxCount, $userRoot->roles());
        //
        $n = 0;
        while ($n < $maxCount) {
            $role = $this->rbac->role('rol.name.' . $n);
            $userRoot->roleRemove($role);
            $n += 2;
        }
        $this->rbac->flush();
        $this->assertArrayInstanceof(Role::class, $userRoot->roles());
        self::assertCount($maxCount / 2, $userRoot->roles());
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testRoleDelete(bool $hasClear)
    {
        $maxCount = 10;

        $userRoot = $this->rbac->user(1);
        $n = 0;
        while ($n < $maxCount) {
            $role = $this->rbac->role('rol.name.' . $n);
            $userRoot->roleAdd($role);
            $n++;
        }
        $this->rbac->flush();
        if ($hasClear) {
            $this->rbac->clear();
        }

        // Удалить
        $existsCount = 0;
        for ($i = 1; $i < $maxCount; $i += 3) {
            $this->rbac->role('rol.name.' . $i)->delete();
            $existsCount++;
        }

        $userRoot = $this->rbac->user(1);

        $this->rbac->flush();
        $this->assertArrayInstanceof(Role::class, $userRoot->roles());
        self::assertCount($maxCount - $existsCount, $userRoot->roles());
        self::assertCount($maxCount, $userRoot->roles(true));
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testPermissionAddRemove(bool $hasClear)
    {
        $maxCount = 10;

        $userRoot = $this->rbac->user(1);

        $n = $maxCount - 1;
        while ($n >= 0) {
            $permission = $this->rbac->permission('p.' . $n);
            $userRoot->permissionAdd($permission);
            $n--;
        }
        $this->rbac->flush();
        self::assertCount($maxCount, $userRoot->permissions());
        if ($hasClear) {
            $this->rbac->clear();
        }
        $userRoot = $this->rbac->user(1);
        self::assertCount($maxCount, $userRoot->permissions());
        //
        $n = 0;
        while ($n < $maxCount) {
            $permission = $this->rbac->permission('p.' . $n);
            $userRoot->permissionRemove($permission);
            $n += 2;
        }
        $this->rbac->flush();
        $this->assertArrayInstanceof(Permission::class, $userRoot->permissions());
        self::assertCount($maxCount / 2, $userRoot->permissions());
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testPermissionDelete(bool $hasClear)
    {
        $maxCount = 10;

        $userRoot = $this->rbac->user(1);
        $n = 0;
        while ($n < $maxCount) {
            $permission = $this->rbac->permission('p.' . $n);
            $userRoot->permissionAdd($permission);
            $n++;
        }
        $this->rbac->flush();
        if ($hasClear) {
            $this->rbac->clear();
        }

        // Удалить
        $existsCount = 0;
        for ($i = 1; $i < $maxCount; $i += 3) {
            $this->rbac->permission('p.' . $i)->delete();
            $existsCount++;
        }

        $userRoot = $this->rbac->user(1);

        $this->rbac->flush();
        $this->assertArrayInstanceof(Permission::class, $userRoot->permissions());
        self::assertCount($maxCount - $existsCount, $userRoot->permissions());
        self::assertCount($maxCount, $userRoot->permissions(true));
    }
}
