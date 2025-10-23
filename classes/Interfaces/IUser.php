<?php

namespace Shasoft\Rbac\Interfaces;

interface IUser
{
    public function delete(): void;
    public function restore(): void;
    public function hasExists(): bool;

    public function id(): int;

    public function roleAdd(IRole|string $role): IUser;
    public function roleRemove(IRole|string $role): IUser;
    public function roles(bool $all = false): array;

    public function permissionAdd(IPermission|string $permission): IUser;
    public function permissionRemove(IPermission|string $permission): IUser;
    public function permissions(bool $all = false): array;

    public function rolesAll(): array;
    public function permissionsAll(): array;

    public function can(string $permissionName): bool;
    public function hasRole(string $roleName): bool;

    public function ban(): bool;
    public function setBan(?\DateTime $dtTo): IUser;
    public function getBan(): ?\DateTime;

    public function values(string $prefix): array;
}
