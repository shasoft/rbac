<?php

namespace Shasoft\Rbac\Tests\Unit;

use Shasoft\Rbac\Permission;
use Shasoft\Rbac\Tests\Unit\Base;
use PHPUnit\Framework\Attributes\TestWith;

class PermissionTest extends Base
{
    public function testStatus()
    {
        $perm = $this->rbac->permission('perm.name');
        $this->assertStatus($perm, 'read');

        $perm->setDescription('тестовое разрешение');
        $this->assertStatus($perm, 'insert');

        $this->rbac->flush();
        $this->assertStatus($perm, 'readed');

        $perm->setDescription('тестовое разрешение');
        $this->assertStatus($perm, 'readed');

        $perm->setDescription('тестовое разрешение 2');
        $this->assertStatus($perm, 'update');

        $this->rbac->flush();
        $this->assertStatus($perm, 'readed');

        $perm->delete();
        $this->assertFalse($perm->hasExists());

        $this->rbac->flush();

        $perm->restore();
        $this->assertTrue($perm->hasExists());
        $this->rbac->flush();
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testReadAll(bool $hasClear)
    {
        $maxCount = 10;
        //
        $n = 0;
        while ($n < $maxCount) {
            $perm = $this->rbac->permission('perm.name.#' . $n);
            $perm->setDescription('тестовое разрешение #' . $n);
            $n++;
        }
        $this->rbac->flush();
        if ($hasClear) {
            $this->rbac->clear();
        }
        // Старые разрешения
        $n = 0;
        while ($n < $maxCount / 2) {
            $perm = $this->rbac->permission('perm.name.#' . $n);
            $perm->setDescription('тестовое разрешение #' . $n);
            $n++;
        }
        // Новые разрешения
        $n = 1;
        while ($n <= $maxCount) {
            $perm = $this->rbac->permission('perm.name.' . $n);
            $perm->setDescription('тестовое разрешение ' . $n);
            $n++;
        }
        //
        $calcCount = 0;
        $this->rbac->permissions(3, function (array $permissions) use (&$calcCount) {
            $this->assertArrayInstanceof(Permission::class, $permissions);
            foreach ($permissions as $perm) {
                self::assertTrue($perm instanceof Permission);
            }
            $calcCount += count($permissions);
        });
        self::assertEquals($maxCount * 2, $calcCount);
        $this->rbac->flush();
    }
}
