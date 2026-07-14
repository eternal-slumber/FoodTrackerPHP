<?php

declare(strict_types=1);

namespace App\Exceptions;

final class NotFoundException extends AppException
{
    public function __construct()
    {
        parent::__construct('Not Found', 404);
    }
}
