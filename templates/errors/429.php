<?php
/**
 * HTTP 429 - Too many requests. Delegates to the shared error layout.
 *
 * @var int         $status
 * @var string      $errorTitle
 * @var string|null $errorDetail
 */
$status = $status ?? 429;
require __DIR__ . '/generic.php';