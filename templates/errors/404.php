<?php
/**
 * HTTP 404 - Page not found. Delegates to the shared error layout.
 *
 * @var int         $status
 * @var string      $errorTitle
 * @var string|null $errorDetail
 */
$status = $status ?? 404;
require __DIR__ . '/generic.php';