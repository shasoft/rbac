@echo off
cls
php vendor\bin\phpunit --filter UserTest::testStateReadAll
rem  --filter CacheTest::testDeleteCache
rem --filter UserAccessTest::testUserDoesNotExist
rem --filter CacheTest::testSetPrefixValue