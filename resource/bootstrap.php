<?php

/**
 * phalcon.php
 *
 * @author liqi created_at 2017/8/12上午11:29
 */


use Phalcon\Db\Adapter\Pdo\Factory;

use Phalcon\Di\FactoryDefault\Cli as CliDI;

$di = new CliDI();

$di->set('db', function () {
    $options = [
        'host'     => 'localhost',
        'dbname'   => 'test',
        'port'     => 3306,
        'username' => 'root',
        'password' => '123456',
        'adapter'  => 'mysql',
    ];

    return Factory::load($options);
});

$di->set('tree', \PreOrderTree\PreOrderTree::class);

return $di;
