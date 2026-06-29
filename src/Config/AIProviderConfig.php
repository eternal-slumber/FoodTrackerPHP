<?php

declare(strict_types=1);

namespace App\Config;

class AIProviderConfig
{
    public function __construct(
        public readonly string $provider,
        public readonly string $baseUrl,
        public readonly string $apiKey,
        public readonly string $model,
        public readonly string $textModel,
        public readonly string $visionModel
    ) {}

    public static function fromEnv(array $env): self
    {
        $provider = strtolower(trim((string)($env['AI_PROVIDER'] ?? 'openrouter')));

        $baseUrl = trim((string)($env['AI_BASE_URL'] ?? ''));
        if ($baseUrl === '') {
            $baseUrl = $provider === 'lmstudio'
                ? 'http://127.0.0.1:1234/v1'
                : 'https://openrouter.ai/api/v1';
        }

        $apiKey = trim((string)($env['AI_API_KEY'] ?? ''));
        if ($apiKey === '') {
            $apiKey = $provider === 'lmstudio'
                ? 'lm-studio'
                : trim((string)($env['OPENROUTER_API_KEY'] ?? ''));
        }

        $model = trim((string)($env['AI_MODEL'] ?? ''));
        if ($model === '') {
            $model = $provider === 'lmstudio'
                ? 'local-model'
                : 'nvidia/nemotron-nano-12b-v2-vl:free';
        }

        $textModel = trim((string)($env['AI_TEXT_MODEL'] ?? '')) ?: $model;
        $visionModel = trim((string)($env['AI_VISION_MODEL'] ?? '')) ?: $model;

        return new self(
            provider: $provider,
            baseUrl: rtrim($baseUrl, '/'),
            apiKey: $apiKey,
            model: $model,
            textModel: $textModel,
            visionModel: $visionModel
        );
    }

    public function modelForPurpose(string $purpose): string
    {
        return $purpose === 'vision' ? $this->visionModel : $this->textModel;
    }
}
