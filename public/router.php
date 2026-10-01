<?php

// PHP's built-in server does not load .env automatically. Configure these values
// in the process environment, or use a dotenv package in your deployment.
foreach (['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'] as $key) {
    if (isset($_ENV[$key])) putenv($key . '=' . $_ENV[$key]);
}

require dirname(__DIR__) . '/public/index.php';
