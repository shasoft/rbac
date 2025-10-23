<?php

namespace Shasoft\Rbac\Trait;

trait Name
{
    public function name(): string
    {
        return $this->context->row[$this->context->key];
    }
}
