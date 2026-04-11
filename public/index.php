<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Force Laravel to ignore any stale cached config baked by older deploy flows.
// This prevents APP_KEY from being stuck at an empty cached value.
$runtimeConfigCache = __DIR__.'/../bootstrap/cache/config-runtime.php';
putenv('APP_CONFIG_CACHE='.$runtimeConfigCache);
$_ENV['APP_CONFIG_CACHE'] = $runtimeConfigCache;
$_SERVER['APP_CONFIG_CACHE'] = $runtimeConfigCache;

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
