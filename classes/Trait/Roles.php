<?php

namespace Shasoft\Rbac\Trait;

use Shasoft\Rbac\Role;
use Shasoft\Rbac\RoleContext;
use Shasoft\Rbac\Interfaces\IRole;

trait Roles
{
    public function roleAdd(IRole|string $role): static
    {
        /** @var RoleContext $context */
        $context = $this->context;
        if (is_string($role)) {
            $role = $context->context->obj->role($role);
        }
        $context->readItem();
        if (!array_key_exists($role->name(), $context->roles)) {
            $context->roles[$role->name()] = $role;
            $context->setModify();
        }
        return $this;
    }

    public function roleRemove(IRole|string $role): static
    {
        /** @var RoleContext $context */
        $context = $this->context;
        if (is_string($role)) {
            $role = $context->context->obj->role($role);
        }
        $context->readItem();
        if (array_key_exists($role->name(), $context->roles)) {
            unset($context->roles[$role->name()]);
            $context->setModify();
        }
        return $this;
    }

    public function roles(bool $all = false): array
    {
        /** @var RoleContext $context */
        $context = $this->context;
        $context->readItem();
        if ($all) {
            return array_values($context->roles);
        }
        return array_values(array_filter(
            $context->roles,
            function (Role $role) {
                return $role->hasExists();
            }
        ));
    }
}
