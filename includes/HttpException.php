<?php

/**
 * iPharmaLink :: HTTP exception
 * ---------------------------------------------------------------------------
 * Throwing one of these anywhere in the stack renders the matching friendly
 * error page and halts execution. Used by the router and middleware.
 */

declare(strict_types=1);

namespace App;

use RuntimeException;

final class HttpException extends RuntimeException
{
    public function __construct(private int $statusCode, string $message = '')
    {
        parent::__construct($message !== '' ? $message : Response::statusTitle($statusCode));
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public static function notFound(string $message = 'The page you are looking for does not exist.'): self
    {
        return new self(404, $message);
    }

    public static function forbidden(string $message = 'You do not have permission to perform this action.'): self
    {
        return new self(403, $message);
    }

    public static function unauthorized(string $message = 'Please sign in to continue.'): self
    {
        return new self(401, $message);
    }

    public static function expired(string $message = 'Your session has expired. Please sign in again.'): self
    {
        return new self(419, $message);
    }

    public static function tooManyRequests(string $message = 'Too many requests. Please slow down and try again shortly.'): self
    {
        return new self(429, $message);
    }

    public static function badRequest(string $message = 'The request could not be understood.'): self
    {
        return new self(400, $message);
    }

    public static function serverError(string $message = 'Something went wrong on our side.'): self
    {
        return new self(500, $message);
    }

    public static function serviceUnavailable(string $message = 'The service is temporarily unavailable.'): self
    {
        return new self(503, $message);
    }
}
