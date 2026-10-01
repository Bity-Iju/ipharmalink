<?php
/**
 * HTTP 401 - Sign in required. Delegates to the shared error layout.
 *
 * @var int         $status
 * @var string      $errorTitle
 * @var string|null $errorDetail
 */
$status = $status ?? 401;
require __DIR__ . '/generic.php';