<?php

declare(strict_types=1);

namespace App\Core;

final class ValidationException extends \RuntimeException
{
    public function __construct(private readonly array $errors)
    {
        parent::__construct('The given data was invalid.');
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
