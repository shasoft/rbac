<?php

namespace Shasoft\Rbac\Tests\Unit;

use Shasoft\Rbac\Rbac;
use Shasoft\PsrCache\CacheItemPool;
use Shasoft\PsrCache\Adapter\CacheAdapterArray;
use Shasoft\Rbac\RbacContext;

class CacheTest extends Base
{
    protected CacheAdapterArray $cacheAdapter;

    protected function setUp(): void
    {
        $this->cacheAdapter = new CacheAdapterArray();
        $this->cache = new CacheItemPool($this->cacheAdapter);
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->cache = null;
    }

    protected function assertCache(int $userId): void
    {
        $dataCache = $this->cacheAdapter->all();
        self::assertTrue(
            array_key_exists(RbacContext::$PREFIX_USER . $userId, $dataCache),
            'В КЭШе нет пользователя № ' . $userId
        );
        $dataUser = $this->cache->getItem(RbacContext::$PREFIX_USER . $userId)->get();
        self::assertTrue(
            array_key_exists(RbacContext::$PREFIX_GROUP . $dataUser[0], $dataCache),
            'В КЭШе нет группы `' . $dataUser[0] . '` пользователя № ' . $userId
        );
    }

    protected function assertCacheCount(int $count): void
    {
        self::assertCount(
            $count,
            $this->cacheAdapter->all(),
            'Неверное количество данных в КЭШе (должно быть ' . $count . ')'
        );
    }

    public function testBase()
    {
        $this->createTheme(function (Rbac $rbac) {
            $rbac->permission('p5')->delete();
            $rbac->user(1)->roleAdd('R6')->permissionAdd('p1');
            $rbac->user(2)->roleAdd('R5')->permissionAdd('pX');
        });

        $this->assertAccess(
            1,
            'R6=1~p1=1,p6=1'
        );
        $this->assertAccess(
            2,
            'R5=1,R6=1~p5=0,p6=1,pX=1'
        );

        $this->assertCache(1);
        $this->assertCache(2);
        $this->assertCacheCount(4);
    }

    public function testShare()
    {
        $this->createTheme(function (Rbac $rbac) {
            $rbac->permission('p5')->delete();
            $rbac->user(1)->roleAdd('R5')->permissionAdd('pX');
            $rbac->user(2)->roleAdd('R5')->permissionAdd('pX');
        });

        $this->assertAccess(
            1,
            'R5=1,R6=1~p5=0,p6=1,pX=1'
        );
        $this->assertAccess(
            2,
            'R5=1,R6=1~p5=0,p6=1,pX=1'
        );


        $this->assertCache(1);
        $this->assertCache(2);
        $this->assertCacheCount(3);
    }

    public function testDeleteCache()
    {
        $this->createTheme(function (Rbac $rbac) {
            $rbac->permission('p5')->delete();
            $rbac->user(1)->roleAdd('R5')->permissionAdd('pX');
            $rbac->user(2)->roleAdd('R5')->permissionAdd('pX');
        });

        $this->assertAccess(
            1,
            'R5=1,R6=1~p5=0,p6=1,pX=1'
        );
        $this->assertCache(1);

        $dataUser = $this->cache->getItem(RbacContext::$PREFIX_USER . 1)->get();
        $this->cache->deleteItem(RbacContext::$PREFIX_GROUP . $dataUser[0]);
        /** @var RbacContext $context */
        $context = $this->getObjectPropertyValue($this->rbac, 'context');
        unset($context->access[$dataUser[0]]);
        $this->cacheAdapter->clear();

        $this->assertAccess(
            2,
            'R5=1,R6=1~p5=0,p6=1,pX=1'
        );

        $this->assertCache(2);
        $this->assertCacheCount(2);
    }

    public function testDeletePermission()
    {
        //self::markTestSkipped('Реализовать кеширование разрешений пользователей');
        $p1 = $this->rbac->permission('p1');
        $R1 = $this->rbac->role('R1')->permissionAdd($p1);
        $p2 = $this->rbac->permission('p2');
        $this->rbac->user(1)->roleAdd($R1)->permissionAdd($p2);
        $this->rbac->flush();

        $this->assertAccess(
            1,
            'R1=1~p1=1,p2=1'
        );

        $p1->delete();
        $this->rbac->flush();

        $this->assertAccess(
            1,
            'R1=1~p1=0,p2=1'
        );

        $p2->delete();
        $this->rbac->flush();

        $this->assertAccess(
            1,
            'R1=1~p1=0,p2=0'
        );
    }

    public function testBan()
    {
        $this->rbac->user(1);
        $this->rbac->flush();

        $this->rbac->user(1)->setBan(new \DateTime("+5 hours"));
        $this->rbac->flush();

        $this->rbac->clear();

        $user = $this->rbac->user(1);
        self::assertTrue($user->ban());
    }

    public function testLinkToBan()
    {
        $permission1 = $this->rbac->permission('pBan1');
        $permission2 = $this->rbac->permission('pBan2');
        $permission3 = $this->rbac->permission('pBan3')->setLinkToBan(true);
        $permission4 = $this->rbac->permission('pBan4')->setLinkToBan(true);
        $this
            ->rbac
            ->user(1)
            ->permissionAdd($permission1)
            ->permissionAdd($permission3)
            ->roleAdd(
                $this->rbac->role('R1')->permissionAdd($permission2)->permissionAdd($permission4)
            );
        $this->rbac->flush();


        $user = $this->rbac->user(1);
        // У пользователя есть права на все разрешения
        self::assertFalse($user->ban());
        self::assertTrue($user->can('pBan1'));
        self::assertTrue($user->can('pBan2'));
        self::assertTrue($user->can('pBan3'));
        self::assertTrue($user->can('pBan4'));
        // Отправить пользователя в бан
        $user->setBan(new \DateTime('+5 hours'));
        $this->rbac->flush();

        // У пользователя есть права только на разрешения, которые не связаны с баном
        self::assertTrue($user->ban());
        self::assertTrue($user->can('pBan1'));
        self::assertTrue($user->can('pBan2'));
        self::assertFalse($user->can('pBan3'));
        self::assertFalse($user->can('pBan4'));

        $this->assertAccess(
            1,
            'R1=1~pBan1=1,pBan2=1,pBan3=3,pBan4=3'
        );
    }

    public function testSetPrefixValue()
    {
        $permission1 = $this->rbac->permission('test.p1')->setPrefixValue('test.');
        $permission2 = $this->rbac->permission('test.p2')->setPrefixValue('test.');
        $permission3 = $this->rbac->permission('test.p3')->setPrefixValue('test');
        $permission4 = $this->rbac->permission('test.p4')->setPrefixValue('test.')->setLinkToBan(true);
        $user = $this->rbac->user(1);

        $user
            ->permissionAdd($permission1)
            ->permissionAdd($permission3)
            ->roleAdd(
                $this->rbac->role('R1')->permissionAdd($permission2)->permissionAdd($permission4)
            );
        $this->rbac->flush();

        $this->assertValues(1, 'test.', 'p1,p2,p4');

        $user->setBan(new \DateTime('+5 hours'));
        $this->rbac->flush();

        $this->assertValues(1, 'test.', 'p1,p2');

        $user->setBan(null);
        $this->rbac->flush();

        $this->assertValues(1, 'test.', 'p1,p2,p4');

        $permission2->delete();
        $this->rbac->flush();
        $this->assertValues(1, 'test.', 'p1,p4');

        $permission2->restore();
        $this->rbac->flush();
        $this->assertValues(1, 'test.', 'p1,p2,p4');
    }
}
