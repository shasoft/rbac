<?php

namespace Shasoft\Rbac\Interfaces;

interface IRole
{
    public function delete(): void;
    public function restore(): void;
    public function hasExists(): bool;

    public function name(): string;

    public function setDescription(string $value): IRole;
    public function description(): string;

    public function roleAdd(IRole|string $role): IRole;
    public function roleRemove(IRole|string $role): IRole;
    public function roles(bool $all = false): array;

    public function permissionAdd(IPermission|string $permission): IRole;
    public function permissionRemove(IPermission|string $permission): IRole;
    public function permissions(bool $all = false): array;
}
