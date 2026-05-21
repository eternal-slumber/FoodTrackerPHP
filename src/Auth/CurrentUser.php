<?php

declare(strict_types=1);

namespace App\Auth;

class CurrentUser
{
    public function __construct(
        public readonly int $telegramId,
        public readonly ?string $username = null,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null
    ) {}
}
