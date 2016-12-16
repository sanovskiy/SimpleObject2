<?php
/**
 * Model generation file example
 */

require __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';

use sanovskiy\SimpleObject\Util;

Util::init([
    'dbcon' => [
        'host' => 'localhost',
        'user' => 'root',
        'password' => '',
        'database' => 'database',
        'charset' => 'utf8'
    ],
    'path_models' => __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'models',
    'models_namespace' => 'project\\models\\',
    'read_connection' => null,
    'write_connection' => null
]);

Util::reverseEngineerModels();