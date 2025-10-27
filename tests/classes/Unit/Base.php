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
    protected ?CacheItemPoolInterface $cache = null;

    protected function onCreateRbac(?CacheItemPoolInterface $cache): IRbac
    {
        return new Rbac(
            (new SQLiteDatabase($this->getFileLog('sqlite3')))->create(),
            $cache
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->rbac = $this->onCreateRbac($this->cache);
    }

    protected function tearDown(): void
    {
        $this->rbac = null;
        parent::tearDown();
    }
}
