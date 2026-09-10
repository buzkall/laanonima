<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
 | PHP's own error log, set before anything exists that could catch an error.
 |
 | A fatal that lands after the response has been finished -- in a deferred
 | callback, in a terminating middleware, in a shutdown function -- takes the
 | PHP-FPM worker with it and exits it with code 255. FPM writes the message to
 | the worker's stderr, and unless the pool sets `catch_workers_output` or an
 | `error_log` of its own it is discarded: the FPM log records that a child
 | died and never says why, nginx reports a 502, and `storage/logs` has nothing
 | in it at all because Laravel's handler never ran.
 |
 | Two lines here fix that without root, without an FPM restart, and without
 | depending on how the box happens to be configured -- and because they run
 | above `vendor/autoload.php`, they also catch the one class of failure no
 | application code can ever log: a release directory swapped out from under a
 | worker that is still holding paths into it.
 |
 | Its own file, not `laravel.log`. What lands here is only ever what Laravel
 | could not report itself, so anything in it at all is worth reading.
 */
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../storage/logs/php-fatal.log');

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__ . '/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__ . '/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__ . '/../bootstrap/app.php';

$app->handleRequest(Request::capture());
