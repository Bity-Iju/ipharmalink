<?php

declare(strict_types=1);

$appRoot = realpath(dirname(__DIR__));
$requestPath = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

if ($appRoot !== false && str_starts_with($requestPath, '/assets/')) {
    $assetsRoot = realpath($appRoot . DIRECTORY_SEPARATOR . 'assets');
    $assetPath = realpath($appRoot . str_replace('/', DIRECTORY_SEPARATOR, $requestPath));
    if (
        $assetsRoot !== false
        && $assetPath !== false
        && str_starts_with($assetPath, $assetsRoot . DIRECTORY_SEPARATOR)
        && is_file($assetPath)
    ) {
        return false;
    }
}

require dirname(__DIR__) . '/index.php';