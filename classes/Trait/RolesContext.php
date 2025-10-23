<?php

namespace Shasoft\Rbac\Trait;

use Shasoft\Rbac\Role;

trait RolesContext
{
    public array $roles;

    protected function initRoles(): void
    {
        $this->roles = [];
        foreach ($this->row['roles'] as $name) {
            $this->roles[$name] = $this->context->create(Role::class, $name, null);
        }
    }

    protected function flushRoles(): void
    {
        $roles = array_keys($this->roles);
        sort($roles);
        $this->row['roles'] = $roles;
        if (array_key_exists('group', $this->row)) {
            $this->row['group'] = empty($roles) ? '' : md5(serialize($roles));
        }
    }
}
