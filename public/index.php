<?php

if (!extension_loaded('pdo_sqlite')) {
    $extPath = '/tmp/pdo_sqlite_ext/usr/lib/php/20230831/pdo_sqlite.so';
    if (function_exists('dl') && file_exists($extPath)) {
        @dl($extPath);
    }
}

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
