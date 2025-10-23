<?php

namespace Shasoft\Rbac;

use Shasoft\Rbac\ItemContext;
use Shasoft\Rbac\RbacContext;
use Shasoft\Rbac\SaveContext;
use Shasoft\Rbac\Interfaces\IRbac;
use Shasoft\Rbac\Interfaces\IRole;
use Shasoft\Rbac\Interfaces\IUser;
use Shasoft\Rbac\Storage\IStorage;
use Psr\Cache\CacheItemPoolInterface;
use Shasoft\Rbac\Interfaces\IPermission;

class Rbac implements IRbac
{
    protected RbacContext $context;

    public function __construct(IStorage $storage, ?CacheItemPoolInterface $cache = null)
    {
        $this->context = new RbacContext($storage, $this, $cache);
    }

    private function readAll(string $classname, int $maxRecords, callable $cb): void
    {
        // Объект для сохранения состояния
        $saveContext = new SaveContext;
        $this->context->save($saveContext);
        // Читать объекты из хранилища 
        $tmp = explode('\\', $classname);
        $this->context->invokeEvent(
            'onReadAll' . array_pop($tmp),
            $maxRecords,
            function (array $rows) use ($classname, $cb, $saveContext) {
                call_user_func(
                    $cb,
                    array_map(function (array $row) use ($classname) {
                        $context = $this->context->createContext($classname, null, $row);
                        return $context->obj;
                    }, $rows)
                );
                // Восстановить состояние
                $this->context->restore($saveContext);
            }
        );
        // Вновь созданные объекты
        if (array_key_exists($classname, $this->context->all)) {
            $items = [];
            /** @var ItemContext $context */
            $statuses = [];
            foreach ($this->context->all[$classname] as $context) {
                $statuses[] = $context->status;
                // Если объект остался в статусе read после чтения всех записей из БД,
                // значит это НОВЫЙ объект
                if ($context->status == 'read') {
                    $context->onInit();
                    $context->setStatus('insert');
                }
                if ($context->status == 'insert') {
                    $items[] = $context->obj;
                    if (count($items) == $maxRecords) {
                        call_user_func($cb, $items);
                        $items = [];
                    }
                }
            }
            if (!empty($items)) {
                call_user_func($cb, $items);
                $items = [];
            }
        }
    }

    public function permission(string $name): IPermission
    {
        return $this->context->create(Permission::class, $name, null);
    }

    public function permissions(int $maxRecords, callable $cb): void
    {
        $this->readAll(Permission::class, $maxRecords, $cb);
    }

    public function role(string $name): IRole
    {
        return $this->context->create(Role::class, $name, null);
    }
    public function roles(int $maxRecords, callable $cb): void
    {
        $this->readAll(Role::class, $maxRecords, $cb);
    }

    public function user(int $userId): IUser
    {
        return $this->context->create(User::class, $userId, null);
    }

    public function users(int $maxRecords, callable $cb): void
    {
        $this->readAll(User::class, $maxRecords, $cb);
    }

    public function reset(): IRbac
    {
        $this->context->invokeEvent('onReset');
        $this->context->clear();
        return $this;
    }

    private function actions(string $classname): array
    {
        if (array_key_exists($classname, $this->context->states)) {
            $items = $this->context->states[$classname];
            //
            $contextsRead = $items['read'] ?? [];
            if (!empty($contextsRead)) {
                $tmp = explode('\\', $classname);
                $this->context->invokeEvent(
                    'onRead' . array_pop($tmp),
                    $contextsRead
                );
                $items = $this->context->states[$classname];
            }
            //
            $ret = [
                'insert' => $items['insert'] ?? [],
                'update' => $items['update'] ?? []
            ];
            foreach (['insert', 'update'] as $key) {
                foreach ($ret[$key] as $context) {
                    $context->onFlush();
                }
            }
        } else {
            $ret = [
                'insert' => [],
                'update' => []
            ];
        }
        return $ret;
    }

    public function getUpdateItems(string $classname): array
    {
        $ret = [];
        if (array_key_exists($classname, $this->context->states)) {
            if (array_key_exists('update', $this->context->states[$classname])) {
                $ret = array_keys($this->context->states[$classname]['update']);
            }
        }
        return $ret;
    }

    public function flush(): IRbac
    {
        $actions = [
            Permission::class => $this->actions(Permission::class),
            Role::class => $this->actions(Role::class),
            User::class => $this->actions(User::class)
        ];
        $fModify = false;
        foreach ($actions as $action) {
            $fModify = !empty($action['insert']) || !empty($action['update']) || !empty($action['delete']);
            if ($fModify) break;
        }
        if ($fModify) {
            $updatePermissions = $this->getUpdateItems(Permission::class);
            $updateRoles = $this->getUpdateItems(Role::class);
            $updateUsers = $this->getUpdateItems(User::class);
            //
            $this->context->invokeEvent('onFlush', $actions);
            //
            $updatePermissionsNames = array_map(function (string $name) {
                return RbacContext::$PREFIX_PERMISSION . $name;
            }, $updatePermissions);
            //
            if (!empty($updateRoles) || !empty($updatePermissions)) {
                // Удалить КЭШ групп
                $this->context->storage->onCacheGets(
                    RbacContext::$PREFIX_GROUP,
                    array_merge(
                        array_map(function (string $name) {
                            return RbacContext::$PREFIX_ROLE . $name;
                        }, $updateRoles),
                        $updatePermissionsNames
                    ),
                    RbacContext::$MAX_RECORDS,
                    function (array $groups) {
                        $this->context->storage->onCacheRemove(RbacContext::$PREFIX_GROUP, $groups);
                        if ($this->context->cache) {
                            $this->context->cache->deleteItems(
                                array_map(function (string $name) {
                                    return RbacContext::$PREFIX_GROUP . $name;
                                }, $groups)
                            );
                        }
                        foreach ($groups as $group) {
                            if (array_key_exists($group, $this->context->access)) {
                                unset($this->context->access[$group]);
                            }
                        }
                    }
                );
            }
            if (!empty($updatePermissions)) {
                // Удалить КЭШ пользователей
                $this->context->storage->onCacheGets(
                    RbacContext::$PREFIX_USER,
                    $updatePermissionsNames,
                    RbacContext::$MAX_RECORDS,
                    function (array $users) {
                        $this->context->storage->onCacheRemove(RbacContext::$PREFIX_USER, $users);
                        if ($this->context->cache) {
                            $this->context->cache->deleteItems(
                                array_map(function (string $userId) {
                                    return RbacContext::$PREFIX_USER . $userId;
                                }, $users)
                            );
                        }
                        foreach ($users as $userId) {
                            if (array_key_exists($userId, $this->context->userData)) {
                                unset($this->context->userData[$userId]);
                            }
                        }
                    }
                );
            }
            if ($this->context->cache) {
                if (!empty($updateUsers)) {
                    $this->context->storage->onCacheRemove(RbacContext::$PREFIX_USER, $updateUsers);
                    $this->context->cache->deleteItems(array_map(function (string $userId) {
                        return RbacContext::$PREFIX_USER . $userId;
                    }, $updateUsers));
                    foreach ($updateUsers as $userId) {
                        if (array_key_exists($userId, $this->context->userData)) {
                            unset($this->context->userData[$userId]);
                        }
                    }
                }
            }
        }
        return $this;
    }

    public function clear(): IRbac
    {
        $this->context->clear();
        return $this;
    }
}
