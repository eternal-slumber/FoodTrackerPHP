<?php

declare(strict_types=1);

namespace App\DTOs;

class AnalyzeRequestDTO
{
    public function __construct(
        public readonly int $telegramId,
        public readonly string $imagePath,
        public readonly string $mimeType
    ) {}

    public static function fromPost(array $postData, array $files): self
    {
        if (!isset($files['photo'])) {
            throw new \InvalidArgumentException('Missing photo');
        }

        return new self(
            telegramId: (int)($postData['telegram_id'] ?? 0),
            imagePath: $files['photo']['tmp_name'],
            mimeType: (string)($postData['mime_type'] ?? '')
        );
    }
}
