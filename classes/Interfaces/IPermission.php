<?php

namespace Shasoft\Rbac\Interfaces;

interface IPermission
{
    public function delete(): void;
    public function restore(): void;
    public function hasExists(): bool;

    public function name(): string;

    public function setDescription(string $value): IPermission;
    public function description(): string;

    public function setLinkToBan(bool $value): IPermission;
    public function hasLinkToBan(): bool;

    public function setPrefixValue(string $value): IPermission;
    public function getPrefixValue(): string;
}
