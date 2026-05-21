<?php

declare(strict_types=1);

namespace App\Validators;

use App\Exceptions\ValidationException;

class AnalyzeValidator
{
    public static function validate(array $postData, array $files): void
    {
        $errors = [];

        // Валидация фото
        if (empty($files['photo'])) {
            $errors['photo'] = 'Photo is required';
        } elseif ($files['photo']['error'] !== UPLOAD_ERR_OK) {
            $errors['photo'] = 'File upload error: ' . $files['photo']['error'];
        } elseif ($files['photo']['size'] > 10 * 1024 * 1024) { // 10MB
            $errors['photo'] = 'File size exceeds 10MB limit';
        } else {
            $mimeType = self::detectMimeType($files['photo']['tmp_name']);
            if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                $errors['photo'] = 'Only JPEG, PNG and WebP images are allowed';
            }
        }

        if (!empty($errors)) {
            throw new ValidationException('Validation failed', $errors);
        }
    }

    public static function validateTgId(mixed $tgId): int
    {
        if (!$tgId) {
            throw new ValidationException('Missing tg_id parameter');
        }

        if (!is_numeric($tgId)) {
            throw new ValidationException('tg_id must be numeric');
        }

        $tgId = (int)$tgId;

        if ($tgId < 1 || $tgId > 10000000000) {
            throw new ValidationException('tg_id out of valid range');
        }

        return $tgId;
    }

    public static function detectMimeType(string $path): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        return (string)$finfo->file($path);
    }
}
