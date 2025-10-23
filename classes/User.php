<?php

namespace Shasoft\Rbac;

use Shasoft\Rbac\RbacContext;
use Shasoft\Rbac\Trait\Roles;
use Shasoft\Rbac\Interfaces\IUser;
use Shasoft\Rbac\Trait\Permissions;

class User extends Item implements IUser
{
    use Roles;
    use Permissions;

    public function id(): int
    {
        return $this->context->row[$this->context->key];
    }

    public function rolesAll(): array
    {
        $access = $this->context->context->access(RbacContext::$PREFIX_USER, $this->context);
        return $access->roles;
    }

    public function permissionsAll(): array
    {
        $access = $this->context->context->access(RbacContext::$PREFIX_USER, $this->context);
        return $access->permissions;
    }


    protected function canResult(int $rc): bool
    {
        switch ($rc & 3) {
            case 1: {
                    return true;
                }
                break;
            case 3: {
                    if (!$this->ban()) {
                        return true;
                    }
                }
                break;
        }
        return false;
    }

    public function can(string $permissionName): bool
    {
        $access = $this->context->context->access(RbacContext::$PREFIX_USER, $this->context);
        $rc =
            (($this->context->accessPermissions[$permissionName] ?? 0) |
                ($access->permissions[$permissionName] ?? 0));
        return $this->canResult($rc);
    }

    public function hasRole(string $roleName): bool
    {
        $access = $this->context->context->access(RbacContext::$PREFIX_USER, $this->context);
        //
        return ($access->roles[$roleName] ?? 0);
    }

    public function ban(): bool
    {
        $userData = $this->context->context->userData(RbacContext::$PREFIX_USER, $this->context);
        if (is_null($userData[2])) {
            return false;
        }
        //
        $now = new \Datetime;
        return $now < $userData[2];
    }

    public function setBan(?\DateTime $dateTimeTo): static
    {
        $this->context->setRowValue('ban', $dateTimeTo);
        //
        return $this;
    }

    public function getBan(): ?\DateTime
    {
        return $this->context->getRowValue('ban');
    }

    public function values(string $prefix): array
    {
        $access = $this->context->context->access(RbacContext::$PREFIX_USER, $this->context);
        $userData = $this->context->context->userData(RbacContext::$PREFIX_USER, $this->context);
        return array_keys(array_filter(
            array_merge($access->values[$prefix] ?? [], $userData[3][$prefix] ?? []),
            function (int $state) {
                return $this->canResult($state);
            }
        ));
    }
}
