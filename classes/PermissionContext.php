<?php

namespace Shasoft\Rbac;

use Shasoft\Rbac\Permission;

class PermissionContext extends ItemContext
{
    public function __construct(
        public RbacContext $context,
        mixed $id,
        ?array $row
    ) {
        parent::__construct($context, Permission::class, 'name', $id, $row);
    }

    public function onDefault(mixed $name): array
    {
        return [
            'name' => $name,
            'description' => $name,
            'linkToBan' => 0,
            'offsetValue' => 0
        ];
    }

    public function onInit(): void
    {
        $this->onInit = true;
    }
    public function onFlush(): void {}
}
