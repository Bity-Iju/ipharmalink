<?php
/**
 * HTTP 419 - Session expired. Delegates to the shared error layout.
 *
 * @var int         $status
 * @var string      $errorTitle
 * @var string|null $errorDetail
 */
$status = $status ?? 419;
require __DIR__ . '/generic.php';