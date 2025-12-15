<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Maintenance mode
if (file_exists($maintenance = __DIR__.'/ccr-rnf/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Autoloader
require __DIR__.'/ccr-rnf/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__.'/ccr-rnf/bootstrap/app.php';

$app->handleRequest(Request::capture());
