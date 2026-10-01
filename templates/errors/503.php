<?php
/**
 * HTTP 503 - Service unavailable. Delegates to the shared error layout.
 *
 * @var int         $status
 * @var string      $errorTitle
 * @var string|null $errorDetail
 */
$status = $status ?? 503;
require __DIR__ . '/generic.php';