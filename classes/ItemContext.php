<?php

namespace Shasoft\Rbac;

abstract class ItemContext
{

    public ?object $obj;
    public array $row;
    public string $status = '';
    public bool $onInit = false;

    public function __construct(
        public RbacContext $context,
        public string $classname,
        public string $key,
        mixed $id,
        ?array $row
    ) {
        $this->obj = null;
        if (is_null($row)) {
            $this->row = $this->onDefault($id);
            $status = 'read';
        } else {
            $this->row = $row;
            $this->onInit();
            $status = 'readed';
        }
        if (!array_key_exists($this->classname, $this->context->states)) {
            $this->context->states[$this->classname] = [];
        }
        $this->setStatus($status);
    }

    abstract public function onDefault(mixed $id): array;
    abstract public function onInit(): void;
    abstract public function onFlush(): void;

    public function itemName(): string
    {
        $tmp = explode('\\', $this->classname);
        return array_pop($tmp);
    }

    public function setStatus(string $status): void
    {
        if ($this->status != $status) {
            $id = $this->row[$this->key];
            if (!empty($this->status)) {
                unset($this->context->states[$this->classname][$this->status][$id]);
            }
            if (!array_key_exists($status, $this->context->states[$this->classname])) {
                $this->context->states[$this->classname][$status] = [];
            }
            if (!array_key_exists($id, $this->context->states[$this->classname][$status])) {
                $this->context->states[$this->classname][$status][$id] = $this;
            }
            //
            $this->status = $status;
        }
    }

    public function setModify(): void
    {
        if ($this->status == 'readed') {
            $this->setStatus('update');
        }
    }

    public function setRowValue(string $fieldName, mixed $value): void
    {
        $this->readItem();
        if ($this->row[$fieldName] != $value) {
            $this->row[$fieldName] = $value;
            $this->setModify();
        }
    }

    public function getRowValue(string $fieldName): mixed
    {
        $this->readItem();
        return $this->row[$fieldName];
    }

    public function readItem(): void
    {
        if ($this->status == 'read') {
            $this->context->invokeEvent(
                'onRead' . $this->itemName(),
                $this->context->states[$this->classname]['read']
            );
        }
    }

    public function setReadOk(?array $row): void
    {
        if ($this->status == 'read') {
            if (is_null($row)) {
                $this->setStatus('insert');
            } else {
                $this->setStatus('readed');
                $this->row = $row;
            }
            $this->onInit();
        } else {
            throw new \Exception("Not status `read`[" . $this->status . "]!", 1);
        }
    }

    public function setInsertOk(): void
    {
        if ($this->status == 'insert') {
            $this->setStatus('readed');
        } else {
            throw new \Exception("Not status `insert`[" . $this->status . "]!", 2);
        }
    }

    public function setUpdateOk(): void
    {
        if ($this->status == 'update') {
            $this->setStatus('readed');
        } else {
            throw new \Exception("Not status `update`[" . $this->status . "]!", 3);
        }
    }
}
