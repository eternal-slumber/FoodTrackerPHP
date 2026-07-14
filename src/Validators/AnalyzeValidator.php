<?php

declare(strict_types=1);

namespace App\Validators;

use App\Exceptions\ValidationException;
use App\Uploads\UploadedImagePolicy;

class AnalyzeValidator
{
    /**
     * @param array<string, mixed> $postData
     * @param array<string, mixed> $files
     */
    public static function validate(array $postData, array $files): void
    {
        $errors = [];
        $photo = $files['photo'] ?? null;

        // Валидация фото
        if (!is_array($photo)) {
            $errors['photo'] = 'Photo is required';
        } elseif (($photo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors['photo'] = 'File upload error: ' . (string)($photo['error'] ?? UPLOAD_ERR_NO_FILE);
        } elseif (!isset($photo['tmp_name']) || !is_string($photo['tmp_name'])) {
            $errors['photo'] = 'Uploaded file is unavailable';
        } elseif (isset($photo['size']) && is_numeric($photo['size']) && (int)$photo['size'] > UploadedImagePolicy::MAX_BYTES) {
            $errors['photo'] = 'File size exceeds 10MB limit';
        } else {
            $mimeType = self::detectMimeType($photo['tmp_name']);
            if (!isset(UploadedImagePolicy::MIME_TO_EXTENSION[$mimeType])) {
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
