<?php

namespace Shasoft\Rbac;

class SaveContext
{
    public array $all;
    public array $states;
    public array $userData;
    public array $access;

    public function clear(): void
    {
        $this->all = [];
        $this->states = [];
        $this->userData = [];
        $this->access = [];
    }

    public function save(self $storage): void
    {
        $storage->all = $this->all;
        $storage->states = $this->states;
        $storage->userData = $this->userData;
        $storage->access = $this->access;
    }

    public function restore(self $storage): void
    {
        $this->all = $storage->all;
        // Сформируем корректное состояние
        $this->states = [];
        foreach ($this->all as $classname => $contexts) {
            $this->states[$classname] = [];
            foreach ($contexts as $context) {
                if (!array_key_exists($context->status, $this->states[$classname])) {
                    $this->states[$classname][$context->status] = [];
                }
                $this->states[$classname][$context->status][$context->row[$context->key]] = $context;
            }
        }
        $this->userData = $storage->userData;
        $this->access = $storage->access;
    }
}
