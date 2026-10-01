<?php
/**
 * HTTP 500 - Server error. Delegates to the shared error layout.
 *
 * @var int         $status
 * @var string      $errorTitle
 * @var string|null $errorDetail
 */
$status = $status ?? 500;
require __DIR__ . '/generic.php';