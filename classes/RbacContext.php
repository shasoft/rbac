<?php

namespace Shasoft\Rbac;

use Shasoft\Rbac\Permission;
use Shasoft\Rbac\ItemContext;
use Shasoft\Rbac\Storage\IStorage;
use Psr\Cache\CacheItemPoolInterface;

class RbacContext extends SaveContext
{
    static public string $PREFIX_USER = 'u.';
    static public string $PREFIX_ROLE = 'r.';
    static public string $PREFIX_PERMISSION = 'p.';
    static public string $PREFIX_GROUP = 'g.';
    static public int $MAX_RECORDS = 128;

    public function __construct(
        public IStorage $storage,
        public object $obj,
        public ?CacheItemPoolInterface $cache
    ) {
        $this->clear();
    }

    public function createContext(string $classname, mixed $id, ?array $row): object
    {
        $hasRowNotNull = !is_null($row);
        $classnameContext = $classname . 'Context';
        /** @var ItemContext $context */
        $context = new $classnameContext($this, $id, $row);
        if ($hasRowNotNull) {
            $id = $row[$context->key];
        }
        if (
            array_key_exists($classname, $this->all) &&
            array_key_exists($id, $this->all[$classname])
        ) {
            /** @var ItemContext $context */
            $context = $this->all[$classname][$id];
            if ($context->status == 'read' && $hasRowNotNull) {
                $context->row = $row;
                $context->onInit();
                $context->setStatus('readed');
            }
        } else {
            $context->obj = new $classname($context);
            if (!array_key_exists($classname, $this->all)) {
                $this->all[$classname] = [];
            }
            $this->all[$classname][$id] = $context;
        }
        return $context;
    }

    public function create(string $classname, mixed $id, ?array $row): object
    {
        return $this->createContext($classname, $id, $row)->obj;
    }

    public function invokeEvent(string $name, ...$args): mixed
    {
        return call_user_func_array(
            [$this->storage, $name],
            $args
        );
    }

    static public function formatString(string $str, int $maxLen): string
    {
        $len = strlen(strip_tags($str));
        return $str . str_repeat(' ', $maxLen - $len + 1);
    }

    protected function getCacheValue(string $key, callable $cb): mixed
    {
        $ret = null;
        if ($this->cache) {
            $itemCache = $this->cache->getItem($key);
            if ($itemCache->isHit()) {
                $ret = $itemCache->get();
            } else {
                $ret = call_user_func($cb);
                $itemCache->set($ret);
                $this->cache->save($itemCache);
            }
        } else {
            $ret = call_user_func($cb);
        }
        return $ret;
    }

    public function permissionState(Permission $permission): int
    {
        $prefixValue = $permission->getPrefixValue();
        if ($permission->name() != $prefixValue) {
            $offsetValue = strlen($prefixValue);
        } else {
            $offsetValue = 0;
        }
        return ($permission->hasExists() ? ($permission->hasLinkToBan() ? 3 : 1) : 0) + ($offsetValue * 4);
    }

    public function values(array $permissions): array
    {
        $ret = [];
        foreach ($permissions as $name => $exists) {
            if ($exists > 3) {
                $offset = intval($exists / 4);
                $prefix = substr($name, 0, $offset);
                if (!array_key_exists($prefix, $ret)) {
                    $ret[$prefix] = [];
                }
                $ret[$prefix][substr($name, $offset)] = $exists;
            }
        }
        return $ret;
    }

    public function userData(string $type, UserContext $context): array
    {
        $userId = $context->row[$context->key];
        if (array_key_exists($userId, $this->userData)) {
            return $this->userData[$userId];
        }
        // Читать данные пользователя
        $ret = $this->getCacheValue(
            $type . $context->row[$context->key],
            function () use ($context, $userId) {
                $accessPermissionsRet = [];
                foreach ($context->obj->permissions(true) as $permission) {
                    $accessPermissionsRet[$permission->name()] = $this->permissionState($permission);
                }
                // Сохранить в хранилище
                $refs = [];
                foreach ($accessPermissionsRet as $name => $state) {
                    $refs[self::$PREFIX_PERMISSION . $name] = $state;
                }
                $this->storage->onCacheWrite(self::$PREFIX_USER, $userId, $refs);
                //
                return [
                    $context->getRowValue('group'),
                    $accessPermissionsRet,
                    array_key_exists('ban', $context->row) ? $context->getRowValue('ban') : null,
                    $this->values($accessPermissionsRet)
                ];
            }
        );
        $this->userData[$userId] = $ret;
        return $ret;
    }

    public function access(string $type, UserContext $context): AccessContext
    {
        // Читать данные пользователя
        $userData = $this->userData($type, $context);
        $group = $userData[0];
        $context->accessPermissions = $userData[1];
        // А может данные уже определялись ранее?
        if (!array_key_exists($group, $this->access)) {
            $access = new AccessContext;
            // Читать данные группы
            if (empty($group)) {
                $groupData = [[], [], []];
            } else {
                $groupData = $this->getCacheValue(
                    RbacContext::$PREFIX_GROUP . $group,
                    function () use ($context, $group) {
                        $rolesRet = [];
                        $permissionsRet = [];
                        // А может данные есть в хранилище?
                        $refs = $this->storage->onCacheRead(self::$PREFIX_GROUP, $group);
                        if ($refs) {
                            foreach ($refs as $ref => $exists) {
                                if (str_starts_with($ref, self::$PREFIX_ROLE)) {
                                    $name = substr($ref, strlen(self::$PREFIX_ROLE));
                                    $rolesRet[$name] = $exists;
                                } else if (str_starts_with($ref, self::$PREFIX_PERMISSION)) {
                                    $name = substr($ref, strlen(self::$PREFIX_PERMISSION));
                                    $permissionsRet[$name] = $exists;
                                } else {
                                    throw new \Exception('Error prefix for ref `' . $ref . '`');
                                }
                            }
                        } else {
                            $rolesAll = [];
                            $rolesDeletedAll = [];
                            $roles = $context->obj->roles(true);
                            while (!empty($roles)) {
                                $rolesNext = [];
                                foreach ($roles as $role) {
                                    if ($role->hasExists()) {
                                        if (!array_key_exists($role->name(), $rolesAll)) {
                                            $rolesAll[$role->name()] = $role;
                                            $rolesNext = array_merge($rolesNext, $role->roles());
                                        }
                                    } else {
                                        $rolesDeletedAll[$role->name()] = 0;
                                    }
                                }
                                $roles = $rolesNext;
                            }
                            $permissionsAll = [];
                            foreach ($rolesAll as $role) {
                                foreach ($role->permissions(true) as $permission) {
                                    $permissionsAll[$permission->name()] = $permission;
                                }
                            }
                            //
                            $rolesRet  = array_merge($rolesDeletedAll, array_map(function (Role $role) {
                                return 1;
                            }, $rolesAll));
                            $permissionsRet  = array_map(function (Permission $permission) {
                                return $this->permissionState($permission);
                            }, $permissionsAll);
                            // Сохранить в хранилище
                            $refs = [];
                            foreach ($rolesRet as $name => $exists) {
                                $refs[self::$PREFIX_ROLE . $name] = $exists;
                            }
                            foreach ($permissionsRet as $name => $exists) {
                                $refs[self::$PREFIX_PERMISSION . $name] = $exists;
                            }
                            $this->storage->onCacheWrite(self::$PREFIX_GROUP, $group, $refs);
                        }

                        return [$rolesRet, $permissionsRet, $this->values($permissionsRet)];
                    }
                );
            }
            $access->roles = $groupData[0];
            $access->permissions = $groupData[1];
            $access->values = $groupData[2];
            $this->access[$group] = $access;
        }
        return $this->access[$group];
    }
}
