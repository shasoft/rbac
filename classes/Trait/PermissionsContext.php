<?php

namespace Shasoft\Rbac\Trait;

use Shasoft\Rbac\Permission;

trait PermissionsContext
{
    public array $permissions;
    public ?array $accessPermissions = null;

    protected function initPermissions(): void
    {
        $this->permissions = [];
        foreach ($this->row['permissions'] as $name) {
            $this->permissions[$name] = $this->context->create(Permission::class, $name, null);
        }
    }

    protected function flushPermissions(): void
    {
        $this->row['permissions'] = array_keys($this->permissions);
    }
}
