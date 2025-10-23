<?php

namespace Shasoft\Rbac;

use Shasoft\Rbac\Trait\Name;
use Shasoft\Rbac\Trait\Description;
use Shasoft\Rbac\Interfaces\IPermission;

class Permission extends Item implements IPermission
{
    use Name;
    use Description;

    public function setLinkToBan(bool $value): static
    {
        $this->context->setRowValue('linkToBan', $value ? 1 : 0);
        //
        return $this;
    }

    public function hasLinkToBan(): bool
    {
        return $this->context->getRowValue('linkToBan') == 1;
    }

    public function setPrefixValue(string $value): static
    {
        if (!str_starts_with($this->name(), $value)) {
            throw new \Exception("The prefix does not match the beginning of the name");
        }
        $this->context->setRowValue('offsetValue', strlen($value));
        //
        return $this;
    }

    public function getPrefixValue(): string
    {
        return substr($this->name(), 0, $this->context->getRowValue('offsetValue'));
    }
}
