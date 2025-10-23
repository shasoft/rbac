<?php

namespace Shasoft\Rbac;

use Shasoft\Rbac\Role;
use Shasoft\Rbac\Trait\PermissionsContext;
use Shasoft\Rbac\Trait\RolesContext;

class RoleContext extends ItemContext
{
    use PermissionsContext;
    use RolesContext;

    public function __construct(
        public RbacContext $context,
        mixed $id,
        ?array $row
    ) {
        parent::__construct($context, Role::class, 'name', $id, $row);
    }

    public function onDefault(mixed $name): array
    {
        return [
            'name' => $name,
            'description' => $name,
            'roles' => [],
            //'group' => '',
            'permissions' => []
        ];
    }

    public function onInit(): void
    {
        $this->onInit = true;
        $this->initPermissions();
        $this->initRoles();
    }

    public function onFlush(): void
    {
        $this->flushPermissions();
        $this->flushRoles();
    }
}
