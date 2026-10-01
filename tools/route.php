<?php

/**
 * Local development helper: run one URL through the app from the CLI and print
 * the raw output plus any error. Not used by the application itself.
 *
 * Usage:  php tools/route.php /pharmacy/login
 */

declare(strict_types=1);

$path = $argv[1] ?? '/';

$_SERVER['REQUEST_METHOD']  = 'GET';
$_SERVER['REQUEST_URI']     = $path;
$_SERVER['HTTP_HOST']       = 'localhost';
$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../index.php';
$_SERVER['REMOTE_ADDR']     = '127.0.0.1';

ob_start();
require __DIR__ . '/../index.php';
$output = (string) ob_get_clean();

// With debug on, the error handler renders the trace into the page itself.
if (str_contains($output, 'Technical details')) {
    echo $output;
    exit(1);
}

echo substr($output, 0, 3000), "\n";
