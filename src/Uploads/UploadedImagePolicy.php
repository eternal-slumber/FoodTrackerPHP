<?php

declare(strict_types=1);

namespace App\Uploads;

final class UploadedImagePolicy
{
    public const MAX_BYTES = 10 * 1024 * 1024;
    public const MAX_WIDTH = 8000;
    public const MAX_HEIGHT = 8000;
    public const MAX_PIXELS = 25_000_000;

    public const MIME_TO_EXTENSION = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    private function __construct() {}
}
