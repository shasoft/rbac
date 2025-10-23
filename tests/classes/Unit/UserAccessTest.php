<?php

namespace Shasoft\Rbac\Tests\Unit;

use Shasoft\Rbac\Rbac;
use PHPUnit\Framework\Attributes\TestWith;

class UserAccessTest extends Base
{
    #[TestWith(['R1', 'R1=1,R2=1,R3=1,R4=1,R5=1,R6=1,R7=1,R8=1~p1=1,p2=1,p3=1,p4=1,p5=1,p6=1,p71=1,p72=1,p81=1,p82=1,p83=1,pX=1'])]
    #[TestWith(['R2', 'R2=1,R3=1,R4=1,R7=1,R8=1~p2=1,p3=1,p4=1,p71=1,p72=1,p81=1,p82=1,p83=1,pX=1'])]
    #[TestWith(['R3', 'R3=1~p3=1,pX=1'])]
    #[TestWith(['R4', 'R4=1,R8=1~p4=1,p81=1,p82=1,p83=1,pX=1'])]
    #[TestWith(['R5', 'R5=1,R6=1~p5=1,p6=1'])]
    #[TestWith(['R6', 'R6=1~p6=1'])]
    #[TestWith(['R7', 'R7=1~p71=1,p72=1'])]
    #[TestWith(['R8', 'R8=1~p81=1,p82=1,p83=1'])]
    #[TestWith(['R9', 'R10=1,R11=1,R12=1,R9=1~p10=1,p11=1,p12=1,p9=1'])]
    #[TestWith(['R10', 'R10=1,R11=1,R12=1~p10=1,p11=1,p12=1'])]
    #[TestWith(['R11', 'R10=1,R11=1,R12=1~p10=1,p11=1,p12=1'])]
    #[TestWith(['R12', 'R10=1,R11=1,R12=1~p10=1,p11=1,p12=1'])]
    public function testAccess(string $roleName, string $access)
    {
        $this->createTheme(null);

        $role = $this->rbac->role($roleName);

        $user = $this->rbac->user(1);
        $user->roleAdd($role);
        $this->rbac->flush();

        $this->assertAccess(
            1,
            $access
        );
    }

    public function testSwapAccess()
    {
        $this->createTheme(function (Rbac $rbac) {
            $rbac->user(1)->roleAdd('R6')->roleAdd('R7');
            $rbac->user(2)->roleAdd('R7')->roleAdd('R6');
        });

        $access = 'R6=1,R7=1~p6=1,p71=1,p72=1';

        $this->assertAccess(1, $access);
        $this->assertAccess(2, $access);
    }

    public function testPlusUserPermissions()
    {
        $this->createTheme(function (Rbac $rbac) {
            $rbac->user(1)->roleAdd('R5')->permissionAdd('pX');
        });

        $this->assertAccess(
            1,
            'R5=1,R6=1~p5=1,p6=1,pX=1'
        );
    }

    public function testOnlyUserPermissions()
    {
        $this->createTheme(function (Rbac $rbac) {
            $rbac->user(1)
                ->permissionAdd('p1')
                ->permissionAdd('p4')
                ->permissionAdd('pX');
            $rbac->user(2)
                ->permissionAdd('p4')
                ->permissionAdd('pX')
                ->permissionAdd('p1');
            $rbac->user(3)
                ->permissionAdd('pX')
                ->permissionAdd('p4')
                ->permissionAdd('p1');
            $rbac->user(4)
                ->permissionAdd('p1')
                ->permissionAdd('pX')
                ->permissionAdd('p4');
        });

        $access = '_~p1=1,p4=1,pX=1';

        $this->assertAccess(1, $access);
        $this->assertAccess(2, $access);
        $this->assertAccess(3, $access);
        $this->assertAccess(4, $access);
    }

    public function testUserDoesNotExist()
    {
        $this->createTheme(null);
        $this->assertAccess(1000, '_~_');
    }
}
