<?php

namespace Shasoft\Rbac\Trait;

use Shasoft\Rbac\Permission;
use Shasoft\Rbac\RoleContext;
use Shasoft\Rbac\Interfaces\IPermission;

trait Permissions
{
    public function permissionAdd(IPermission|string $permission): static
    {
        /** @var RoleContext $context */
        $context = $this->context;
        if (is_string($permission)) {
            $permission = $context->context->obj->permission($permission);
        }
        $context->readItem();
        if (!array_key_exists($permission->name(), $context->roles)) {
            $context->permissions[$permission->name()] = $permission;
            $context->setModify();
        }
        return $this;
    }

    public function permissionRemove(IPermission|string $permission): static
    {
        /** @var RoleContext $context */
        $context = $this->context;
        if (is_string($permission)) {
            $permission = $context->context->obj->permission($permission);
        }
        $context->readItem();
        if (array_key_exists($permission->name(), $context->permissions)) {
            unset($context->permissions[$permission->name()]);
            $context->setModify();
        }
        return $this;
    }

    public function permissions(bool $all = false): array
    {
        /** @var RoleContext $context */
        $context = $this->context;
        $context->readItem();
        if ($all) {
            return array_values($context->permissions);
        }
        return array_values(array_filter(
            $context->permissions,
            function (Permission $permission) {
                return $permission->hasExists();
            }
        ));
    }
}
