<?php

use Shasoft\Rbac\Rbac;
use Shasoft\Rbac\Storage\SQLiteDatabase;

include __DIR__ . '/../../vendor/autoload.php';


$storage = (new SQLiteDatabase(__DIR__ . '/database.sqlite3'))->create();
$rbac = new Rbac($storage);

//*/
$p71 = $rbac->permission('p71');

$p72 = $rbac->permission('p72');

$R7 = $rbac->role('R7');
$R7->delete();

$R7->permissionAdd($p71)->permissionAdd($p72);

$rbac->permission('p71')->delete();

$user = $rbac->user(1);
$user->roleAdd('R7');

$rbac->flush();

s_dump($rbac);

$user->hasRole('R7');
