<?php

namespace Shasoft\Rbac;

use Shasoft\Rbac\Trait\Name;
use Shasoft\Rbac\Trait\Roles;
use Shasoft\Rbac\Interfaces\IRole;
use Shasoft\Rbac\Trait\Description;
use Shasoft\Rbac\Trait\Permissions;

class Role extends Item implements IRole
{
    use Name;
    use Description;
    use Roles;
    use Permissions;
}
