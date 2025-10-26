<?php

namespace Shasoft\Rbac\Tests\Unit;

use Shasoft\Rbac\Rbac;
use PHPUnit\Framework\TestCase;
use Shasoft\Rbac\Interfaces\IRbac;
use Shasoft\Rbac\Storage\IStorage;
use Psr\Cache\CacheItemPoolInterface;
use Shasoft\Rbac\Storage\SQLiteDatabase;
use Shasoft\Rbac\Tests\Trait\RbacAssert;

class Base extends TestCase
{
    use RbacAssert;
    protected ?IStorage  $storage = null;
    protected ?CacheItemPoolInterface $cache = null;

    protected function onCreateStorage(): IStorage
    {
        return (new SQLiteDatabase($this->getFileLog('sqlite3')))->create();
    }

    protected function onDestroyStorage(IStorage $storage): void
    {
        /** @var SQLiteDatabase $storage */
        $storage->close();
    }

    protected function onCreateRbac(IStorage $storage, ?CacheItemPoolInterface $cache): ?IRbac
    {
        return new Rbac($storage, $cache);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = $this->onCreateStorage();
        $this->rbac = $this->onCreateRbac($this->storage, $this->cache);
    }

    protected function tearDown(): void
    {
        $this->rbac = null;
        $this->onDestroyStorage($this->storage);
        $this->storage = null;
        parent::tearDown();
    }
}
