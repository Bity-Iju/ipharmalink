<?php
/**
 * HTTP 403 - Access denied. Delegates to the shared error layout.
 *
 * @var int         $status
 * @var string      $errorTitle
 * @var string|null $errorDetail
 */
$status = $status ?? 403;
require __DIR__ . '/generic.php';