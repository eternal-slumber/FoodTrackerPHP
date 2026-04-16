<?php

declare(strict_types=1);

namespace App\Exceptions;

class ValidationException extends AppException
{
    public function __construct(string $message, array $errors = [])
    {
        parent::__construct($message, 400, ['validation_errors' => $errors]);
    }
}
