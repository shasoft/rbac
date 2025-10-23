<?php

namespace Shasoft\Rbac\Tests\Unit;

use Shasoft\Rbac\Role;
use PHPUnit\Framework\Attributes\TestWith;
use Shasoft\Rbac\Permission;

class RoleTest extends Base
{
    public function testStatus()
    {
        $role = $this->rbac->role('rol.name');
        $this->assertStatus($role, 'read');

        $role->setDescription('тестовая роль');
        $this->assertStatus($role, 'insert');

        $this->rbac->flush();
        $this->assertStatus($role, 'readed');

        $role->setDescription('тестовая роль');
        $this->assertStatus($role, 'readed');

        $role->setDescription('тестовая роль 2');
        $this->assertStatus($role, 'update');

        $this->rbac->flush();
        $this->assertStatus($role, 'readed');

        $role->delete();
        $this->assertFalse($role->hasExists());

        $this->rbac->flush();

        $role->restore();
        $this->assertTrue($role->hasExists());
        $this->rbac->flush();
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testReadAll(bool $hasClear)
    {
        $maxCount = 10;
        $n = 0;
        while ($n < $maxCount) {
            $role = $this->rbac->role('rol.name.#' . $n);
            $role->setDescription('тестовая роль #' . $n);
            $n++;
        }
        $this->rbac->flush();

        if ($hasClear) {
            $this->rbac->clear();
        }
        // Старые разрешения
        $n = 0;
        while ($n < $maxCount / 2) {
            $role = $this->rbac->role('rol.name.#' . $n);
            $role->setDescription('тестовая роль #' . $n);
            $n++;
        }
        // Новые разрешения
        $n = 0;
        while ($n < $maxCount) {
            $role = $this->rbac->role('rol.name.' . $n);
            $role->setDescription('тестовая роль ' . $n);
            $n++;
        }
        //

        $calcCount = 0;
        $this->rbac->roles(3, function (array $roles) use (&$calcCount) {
            $this->assertArrayInstanceof(Role::class, $roles);
            foreach ($roles as $role) {
                self::assertTrue($role instanceof Role);
            }
            $calcCount += count($roles);
        });

        self::assertEquals($maxCount * 2, $calcCount);
        $this->rbac->flush();
    }

    public function testInsertAndDelete()
    {
        $roleRoot = $this->rbac->role('rol.root');
        $roleRoot->delete();
        $this->rbac->flush();
        self::assertFalse($roleRoot->hasExists());

        $this->rbac->clear();

        $roleRoot = $this->rbac->role('rol.root');
        self::assertFalse($roleRoot->hasExists());
    }

    protected function getCountRoles(): int
    {
        $ret = 0;
        $this->rbac->roles(3, function (array $roles) use (&$ret) {
            $this->assertArrayInstanceof(Role::class, $roles);
            foreach ($roles as $role) {
                self::assertTrue($role instanceof Role);
            }
            $ret += count($roles);
        });
        return $ret;
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testReset(bool $hasClear)
    {
        $maxCount = 10;
        $n = 0;
        while ($n < $maxCount) {
            $role = $this->rbac->role('rol.name.#' . $n);
            $role->setDescription('тестовая роль #' . $n);
            $n++;
        }
        $this->rbac->flush();
        if ($hasClear) {
            $this->rbac->clear();
        }
        $maxCount = 10;
        $n = 0;
        while ($n < $maxCount) {
            $role = $this->rbac->role('rol.name.' . $n);
            $role->setDescription('тестовая роль ' . $n);
            $n++;
        }

        //self::assertEquals($maxCount * 2, $this->getCountRoles());

        $this->rbac->reset();

        //self::assertEquals(0, $this->getCountRoles());

        $role = $this->rbac->role('rol.name.#1');

        self::assertEquals(1, $this->getCountRoles());

        $this->rbac->flush();
    }

    //#[TestWith([false])]
    #[TestWith([true])]
    public function testRoleAddRemove(bool $hasClear)
    {
        $maxCount = 10;

        $roleRoot = $this->rbac->role('rol.root');

        $n = $maxCount - 1;
        while ($n >= 0) {
            $role = $this->rbac->role('rol.name.' . $n);
            $roleRoot->roleAdd($role);
            $n--;
        }
        $this->rbac->flush();
        self::assertCount($maxCount, $roleRoot->roles());
        if ($hasClear) {
            $this->rbac->clear();
        }
        $roleRoot = $this->rbac->role('rol.root');
        self::assertCount($maxCount, $roleRoot->roles());
        //
        $n = 0;
        while ($n < $maxCount) {
            $role = $this->rbac->role('rol.name.' . $n);
            $roleRoot->roleRemove($role);
            $n += 2;
        }
        $this->rbac->flush();
        $this->assertArrayInstanceof(Role::class, $roleRoot->roles());
        self::assertCount($maxCount / 2, $roleRoot->roles());
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testRoleDelete(bool $hasClear)
    {
        $maxCount = 10;

        $roleRoot = $this->rbac->role('rol.root');
        $n = 0;
        while ($n < $maxCount) {
            $role = $this->rbac->role('rol.name.' . $n);
            $roleRoot->roleAdd($role);
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

        $roleRoot = $this->rbac->role('rol.root');

        $this->rbac->flush();
        $this->assertArrayInstanceof(Role::class, $roleRoot->roles());
        self::assertCount($maxCount - $existsCount, $roleRoot->roles());
        self::assertCount($maxCount, $roleRoot->roles(true));
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testPermissionAddRemove(bool $hasClear)
    {
        $maxCount = 10;

        $roleRoot = $this->rbac->role('rol.root');

        $n = $maxCount - 1;
        while ($n >= 0) {
            $permission = $this->rbac->permission('p.' . $n);
            $roleRoot->permissionAdd($permission);
            $n--;
        }
        $this->rbac->flush();
        self::assertCount($maxCount, $roleRoot->permissions());
        if ($hasClear) {
            $this->rbac->clear();
        }
        $roleRoot = $this->rbac->role('rol.root');
        self::assertCount($maxCount, $roleRoot->permissions());
        //
        $n = 0;
        while ($n < $maxCount) {
            $permission = $this->rbac->permission('p.' . $n);
            $roleRoot->permissionRemove($permission);
            $n += 2;
        }
        $this->rbac->flush();
        $this->assertArrayInstanceof(Permission::class, $roleRoot->permissions());
        self::assertCount($maxCount / 2, $roleRoot->permissions());
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testPermissionDelete(bool $hasClear)
    {
        $maxCount = 10;

        $roleRoot = $this->rbac->role('rol.root');
        $n = 0;
        while ($n < $maxCount) {
            $permission = $this->rbac->permission('p.' . $n);
            $roleRoot->permissionAdd($permission);
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

        $roleRoot = $this->rbac->role('rol.root');

        $this->rbac->flush();
        $this->assertArrayInstanceof(Permission::class, $roleRoot->permissions());
        self::assertCount($maxCount - $existsCount, $roleRoot->permissions());
        self::assertCount($maxCount, $roleRoot->permissions(true));
    }
}
