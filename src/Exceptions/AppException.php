<?php

declare(strict_types=1);

namespace App\Exceptions;

class AppException extends \Exception
{
    private array $context;

    public function __construct(string $message, int $code = 0, array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function toArray(): array
    {
        return [
            'error' => $this->getMessage(),
            'code' => $this->getCode(),
            'context' => $this->context
        ];
    }
}
