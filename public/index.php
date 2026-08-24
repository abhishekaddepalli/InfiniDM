<?php

if (php_sapi_name() === 'cli-server') {
    $url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $file = __DIR__ . $url;
    if ($url !== '/' && is_file($file)) {
        return false;
    }
}

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Serve the maintenance page if the application is down for maintenance...
if (file_exists($maintenance = __DIR__ . '/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__ . '/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
(require_once __DIR__ . '/../bootstrap/app.php')
    ->handleRequest(Request::capture());
