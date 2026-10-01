<?php

/**
 * iPharmaLink :: Validation exception
 */

declare(strict_types=1);

namespace App;

use RuntimeException;

final class ValidationException extends RuntimeException
{
    /** @param array<string,list<string>> $errors */
    public function __construct(private array $errors)
    {
        $first = $this->first();
        parent::__construct($first ?? 'Please correct the highlighted fields.');
    }

    /** @return array<string,list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function first(): ?string
    {
        foreach ($this->errors as $messages) {
            if ($messages !== []) {
                return $messages[0];
            }
        }
        return null;
    }
}
