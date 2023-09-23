<?php

use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class synchronize_test extends TestCase
{
    public function testSynchronizeModule()
    {
        //create module
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('module'));
        $sql->setValue('id', '1');
        $sql->setValue('key', 'unittest');
        $sql->setValue('name', 'unittest');
        $sql->insert();

        //snchronize to filesystem
        rex_developer_manager::synchronize();

        //check if module folder exists
        self::assertDirectoryExists('../../../data/addons/developer/modules/unittest [1]','Module folder does not exist');

        //delete module
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('module'));
        $sql->setWhere('id=1');
        $sql->delete();
    }
}
