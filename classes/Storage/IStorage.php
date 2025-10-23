<?php

namespace Shasoft\Rbac\Storage;

interface IStorage
{
    public function onFlush(array $actions): void;
    public function onReset(): void;

    public function onReadPermission(array $contexts): void;
    public function onReadAllPermission(int $maxRecords, callable $cb): void;

    public function onReadRole(array $contexts): void;
    public function onReadAllRole(int $maxRecords, callable $cb): void;

    public function onReadUser(array $contexts): void;
    public function onReadAllUser(int $maxRecords, callable $cb): void;

    public function onCacheRead(string $type, string $name): ?array;
    public function onCacheWrite(string $type, string $name, array $refs): void;
    public function onCacheGets(string $type, array $refs, int $maxRecords, callable $cb): void;
    public function onCacheRemove(string $type, array $names): void;
}
