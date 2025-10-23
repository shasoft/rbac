<?php

namespace Shasoft\Rbac;

use Shasoft\Rbac\Trait\PermissionsContext;
use Shasoft\Rbac\Trait\RolesContext;

class UserContext extends ItemContext
{
    use PermissionsContext;
    use RolesContext;

    public function __construct(
        public RbacContext $context,
        mixed $id,
        ?array $row
    ) {
        parent::__construct($context, User::class, 'id', $id, $row);
    }

    public function onDefault(mixed $id): array
    {
        return [
            'id' => $id,
            'roles' => [],
            'group' => '',
            'permissions' => [],
            'ban' => null
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
