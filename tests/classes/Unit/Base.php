<?php

namespace Shasoft\Rbac\Tests\Unit;

use Shasoft\Rbac\Rbac;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Shasoft\Rbac\Storage\SQLiteDatabase;
use Shasoft\Rbac\Tests\Trait\RbacAssert;

class Base extends TestCase
{
    use RbacAssert;
    protected SQLiteDatabase $storage;
    protected ?CacheItemPoolInterface $cache = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = (new SQLiteDatabase($this->getFileLog('sqlite3')))->create();
        $this->rbac = new Rbac($this->storage, $this->cache);
    }

    protected function tearDown(): void
    {
        $this->storage->close();
        parent::tearDown();
    }
}
