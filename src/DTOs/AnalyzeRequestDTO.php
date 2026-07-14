<?php

declare(strict_types=1);

namespace App\DTOs;

class AnalyzeRequestDTO
{
    public function __construct(
        public readonly int $telegramId,
        public readonly string $imagePath
    ) {}

    /**
     * @param array<string, mixed> $postData
     * @param array<string, mixed> $files
     */
    public static function fromPost(array $postData, array $files): self
    {
        $photo = $files['photo'] ?? null;
        if (!is_array($photo) || !isset($photo['tmp_name']) || !is_string($photo['tmp_name'])) {
            throw new \InvalidArgumentException('Missing photo');
        }

        return new self(
            telegramId: (int)($postData['telegram_id'] ?? 0),
            imagePath: $photo['tmp_name']
        );
    }
}
