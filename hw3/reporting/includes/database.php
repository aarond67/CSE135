<?php

declare(strict_types=1);

function database(): PDO
{
    static $connection = null;

    if ($connection instanceof PDO) {
        return $connection;
    }

    $config = require '/etc/cse135/reporting-db.php';

    $connection = new PDO(
        $config['dsn'],
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE =>
                PDO::ERRMODE_EXCEPTION,

            PDO::ATTR_EMULATE_PREPARES =>
                false,

            PDO::ATTR_DEFAULT_FETCH_MODE =>
                PDO::FETCH_ASSOC
        ]
    );

    return $connection;
}