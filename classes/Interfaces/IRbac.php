<?php

namespace Shasoft\Rbac\Interfaces;

interface IRbac
{
    public function permission(string $name): IPermission;
    public function permissions(int $maxRecords, callable $cb): void;

    public function role(string $name): IRole;
    public function roles(int $maxRecords, callable $cb): void;

    public function user(int $userId): IUser;
    public function users(int $maxRecords, callable $cb): void;

    public function reset(): IRbac;
    public function flush(): IRbac;
}
