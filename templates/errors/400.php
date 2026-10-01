<?php
/**
 * HTTP 400 - Bad request. Delegates to the shared error layout.
 *
 * @var int         $status
 * @var string      $errorTitle
 * @var string|null $errorDetail
 */
$status = $status ?? 400;
require __DIR__ . '/generic.php';