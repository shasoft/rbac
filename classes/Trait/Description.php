<?php

namespace Shasoft\Rbac\Trait;

use Shasoft\Rbac\Interfaces\IPermission;

trait Description
{
    public function setDescription(string $value): static
    {
        $this->context->setRowValue('description', $value);
        return $this;
    }

    public function description(): string
    {
        return $this->context->getRowValue('description');
    }
}
