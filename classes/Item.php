<?php

namespace Shasoft\Rbac;

abstract class Item
{
    public function __construct(
        public ItemContext $context
    ) {
        $this->context->row['exists'] = 1;
    }

    public function hasExists(): bool
    {
        return $this->context->getRowValue('exists') == 1;
    }

    public function delete(): void
    {
        $this->context->setRowValue('exists', 0);
    }

    public function restore(): void
    {
        $this->context->setRowValue('exists', 1);
    }
}
