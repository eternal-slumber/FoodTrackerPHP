<?php

declare(strict_types=1);

namespace App\DTOs;

class AnalyzeRequestDTO
{
    public function __construct(
        public readonly int $tgId,
        public readonly string $imagePath
    ) {}

    public static function fromPost(array $postData, array $files): self
    {
        $tgId = $postData['tg_id'] ?? null;
        
        if (!$tgId) {
            throw new \InvalidArgumentException('Missing tg_id');
        }

        if (!isset($files['photo'])) {
            throw new \InvalidArgumentException('Missing photo');
        }

        return new self(
            tgId: (int)$tgId,
            imagePath: $files['photo']['tmp_name']
        );
    }
}
