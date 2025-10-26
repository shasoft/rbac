<?php

namespace Shasoft\Rbac\Tests\Trait;

use Shasoft\Rbac\Item;
use Shasoft\Rbac\ItemContext;
use Shasoft\Rbac\Permission;
use Shasoft\Rbac\Rbac;
use Shasoft\Rbac\RbacContext;
use Shasoft\Rbac\UserContext;
use Shasoft\Terminal\Terminal;

trait RbacAssert
{
    protected ?Rbac $rbac = null;

    protected function getFileLog(string $ext): string
    {
        $tmp = explode('/', str_replace(['/', '\\'], '/', $this->toString()));
        $filename = str_replace('::', DIRECTORY_SEPARATOR, array_pop($tmp));
        $filename = str_replace(' with data set #', '-', $filename);
        $filename = str_replace(' ', '_', $filename);
        $fOk = false;
        $refClass = new \ReflectionClass($this);
        $path = dirname($refClass->getFileName());
        while (!empty($path) && $path != dirname($path)) {
            if (file_exists($path . '/composer.json')) {
                $fOk = true;
                break;
            }
            $path = dirname($path);
        }
        if (!$fOk) {
            $path = dirname($refClass->getFileName());
        }
        if (!empty($ext)) {
            $ext = '.' . $ext;
        }
        $ret = $path . DIRECTORY_SEPARATOR . '.test-logs' . DIRECTORY_SEPARATOR . $filename . $ext;
        return $ret;
    }

    protected function getObjectPropertyValue(object|string $object, string $name, mixed $default = null): mixed
    {
        $refProperty = null;
        $refClass = new \ReflectionClass($object);
        while (is_null($refProperty) && $refClass !== false) {
            // Свойство существует?
            if ($refClass->hasProperty($name)) {
                $refProperty = $refClass->getProperty($name);
            } else {
                $refClass = $refClass->getParentClass();
            }
        }
        //$refProperty = self::getObjectProperty($object, $name);
        if (is_null($refProperty)) {
            return $default;
        }
        return $refProperty->getValue($refProperty->isStatic() ? null : $object);
    }


    protected function context(mixed $item): ItemContext
    {
        return $this->getObjectPropertyValue($item, 'context');
    }

    protected function row(mixed $item): array
    {
        return $this->context($item)->row;
    }

    protected function dump(): void
    {
        s_call_fn(function () {
            Terminal::writeLn('<Title>' . get_class($this->rbac) . '</Title>');
            $tab = '  ';
            /** @var RbacContext $refContext */
            $refContext = $this->getObjectPropertyValue($this->rbac, 'context');
            foreach ($refContext->all as $classname => $contexts) {
                Terminal::writeLn($tab . "<Info>" . $classname);
                /** @var ItemContext $context */
                $maxLenId = 0;
                $maxLenSplId = 0;
                $maxLenStatus = 0;
                foreach ($contexts as $context) {
                    $maxLenId = max($maxLenId, strlen($context->row[$context->key]));
                    $maxLenSplId = max($maxLenSplId, strlen(spl_object_id($context->obj)));
                    $maxLenStatus = max($maxLenStatus, strlen($context->status));
                }
                foreach ($contexts as $context) {
                    $title = $context->row[$context->key];
                    Terminal::write($tab . $tab . $tab . RbacContext::formatString("<Fail>" . $title . "</Fail>", $maxLenId));
                    $colorInit = $context->onInit ? 'FgGreen' : 'FgRed';
                    Terminal::write('<' . $colorInit . '>I</' . $colorInit . '> ');
                    $color = $context->row['exists']  ? 'Disable' : 'Number';
                    Terminal::write(RbacContext::formatString('<' . $color . '>#' . spl_object_id($context->obj) . '</> ', $maxLenSplId + 1));
                    if ($context->row['exists']) {
                        Terminal::write(RbacContext::formatString('<fgYellow>' . $context->status . '</fgYellow> ', $maxLenStatus));
                    } else {
                        Terminal::write(RbacContext::formatString(' ', $maxLenStatus));
                    }
                    $skipKeys = [
                        $context->key => 1,
                        'exists' => 1
                    ];
                    foreach ($context->row as $key => $value) {
                        if (!array_key_exists($key, $skipKeys)) {
                            if (is_array($value)) {
                                $value = json_encode($value);
                            } else if (is_object($value) && $value instanceof \Datetime) {
                                $value = $value->format('Y-m-d H:i:s');
                            } else if (is_null($value)) {
                                $value = '<FgRed>null</FgRed>';
                            }
                            Terminal::write('<String>' . $key . '</String>=<FgGreen>' . (string)$value . '</FgGreen>; ');
                        }
                    }
                    Terminal::writeLn();
                }
            }
        }, [1]);
    }

    protected function assertStatus(object $obj, string $status): void
    {
        /** @var ItemContext $context */
        $context = $this->getObjectPropertyValue($obj, 'context');
        $this->assertThat(
            $context->status,
            $this->equalTo($status),
            'Должен быть статус `' . $status . '` , а не `' . $context->status . '`'
        );
        //self::assertEquals($context->status, $status, 'Статус `' . $status . '` не соответствует');
    }

    protected function assertArrayInstanceof(string $typeName, iterable $items): void
    {
        foreach ($items as $item) {
            self::assertInstanceOf($typeName, $item);
        }
    }

    protected function assertValues(int $userId, string $prefix, string $valuesExpected): void
    {
        $values = $this->rbac->user($userId)->values($prefix);
        sort($values);
        self::assertEquals(implode(',', $values), $valuesExpected);
    }

    protected function getStates(array $items): string
    {
        $ret = [];
        $keys = array_keys($items);
        sort($keys);
        foreach ($keys as $key) {
            $ret[] = $key . '=' . $items[$key];
        }
        return empty($ret) ? '_' : implode(',', $ret);
    }

    protected function assertAccess(int $userId, string $stateExpected): void
    {
        $user = $this->rbac->user($userId);
        /** @var UserContext $refContext */
        $refContext = $this->getObjectPropertyValue($user, 'context');
        $access = $refContext->context->access(RbacContext::$PREFIX_USER, $refContext);
        $stateActual =
            $this->getStates($access->roles) .
            '~' .
            $this->getStates(
                array_merge($access->permissions, $refContext->accessPermissions)
            );
        self::assertEquals($stateExpected, $stateActual);

        //
        $accessSplit = explode('~', $stateExpected);
        //
        $permissionsAll = [];
        $this->rbac->permissions(
            1024,
            function (array $permissions) use (&$permissionsAll) {
                foreach ($permissions as $permission) {
                    $permissionsAll[$permission->name()] = false;
                }
            }
        );
        foreach (explode(',', $accessSplit[1]) as $str) {
            if ($str !== '_') {
                $tmp = explode('=', $str);
                $permissionsAll[$tmp[0]] = intval($tmp[1]) == 1 ? true : false;
            }
        }
        foreach ($permissionsAll as $name => $can) {
            self::assertEquals($can, $user->can($name));
        }
        //
        $rolesAll = [];
        $this->rbac->permissions(
            1024,
            function (array $roles) use (&$rolesAll) {
                foreach ($roles as $role) {
                    $rolesAll[$role->name()] = false;
                }
            }
        );
        for ($i = 1; $i < 4; $i++) {
            $rolesAll['R' . (count($rolesAll) + $i)] = false;
        }
        foreach (explode(',', $accessSplit[0]) as $str) {
            if ($str !== '_') {
                $tmp = explode('=', $str);
                $rolesAll[$tmp[0]] = intval($tmp[1]) == 1 ? true : false;
            }
        }
        foreach ($rolesAll as $name => $hasRole) {
            self::assertEquals($hasRole, $user->hasRole($name));
        }
    }

    protected function createTheme(callable|null $cb): void
    {
        $pX = $this->rbac->permission('pX');
        $p1 = $this->rbac->permission('p1');
        $p2 = $this->rbac->permission('p2');
        $p3 = $this->rbac->permission('p3');
        $p4 = $this->rbac->permission('p4');
        $p5 = $this->rbac->permission('p5');
        $p6 = $this->rbac->permission('p6');
        $p71 = $this->rbac->permission('p71');
        $p72 = $this->rbac->permission('p72');
        $p81 = $this->rbac->permission('p81');
        $p82 = $this->rbac->permission('p82');
        $p83 = $this->rbac->permission('p83');

        $p9 = $this->rbac->permission('p9');
        $p10 = $this->rbac->permission('p10');
        $p11 = $this->rbac->permission('p11');
        $p12 = $this->rbac->permission('p12');

        $R1 = $this->rbac->role('R1');
        $R2 = $this->rbac->role('R2');
        $R3 = $this->rbac->role('R3');
        $R4 = $this->rbac->role('R4');
        $R5 = $this->rbac->role('R5');
        $R6 = $this->rbac->role('R6');
        $R7 = $this->rbac->role('R7');
        $R8 = $this->rbac->role('R8');

        $R9 = $this->rbac->role('R9');
        $R10 = $this->rbac->role('R10');
        $R11 = $this->rbac->role('R11');
        $R12 = $this->rbac->role('R12');

        $R1->roleAdd($R2)->roleAdd($R5)->permissionAdd($p1);
        $R2->roleAdd($R3)->roleAdd($R4)->roleAdd($R7)->permissionAdd($p2);
        $R3->permissionAdd($p3)->permissionAdd($pX);
        $R4->roleAdd($R8)->permissionAdd($pX)->permissionAdd($p4);
        $R5->roleAdd($R6)->permissionAdd($p5);
        $R6->permissionAdd($p6);
        $R7->permissionAdd($p71)->permissionAdd($p72);
        $R8->permissionAdd($p81)->permissionAdd($p82)->permissionAdd($p83);

        $R9->roleAdd($R10)->permissionAdd($p9);
        $R10->roleAdd($R11)->permissionAdd($p10);
        $R11->roleAdd($R12)->permissionAdd($p11);
        $R12->roleAdd($R10)->permissionAdd($p12);

        if (is_callable($cb)) {
            call_user_func($cb, $this->rbac);
        }
        $this->rbac->flush();
    }
}
