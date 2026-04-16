<?php

declare(strict_types=1);

namespace App\Validators;

use App\Exceptions\ValidationException;

class AnalyzeValidator
{
    public static function validate(array $postData, array $files): void
    {
        $errors = [];

        // Валидация tg_id
        if (empty($postData['tg_id'])) {
            $errors['tg_id'] = 'Telegram ID is required';
        } elseif (!is_numeric($postData['tg_id'])) {
            $errors['tg_id'] = 'Telegram ID must be numeric';
        }

        // Валидация фото
        if (empty($files['photo'])) {
            $errors['photo'] = 'Photo is required';
        } elseif ($files['photo']['error'] !== UPLOAD_ERR_OK) {
            $errors['photo'] = 'File upload error: ' . $files['photo']['error'];
        } elseif ($files['photo']['size'] > 20 * 1024 * 1024) { // 20MB
            $errors['photo'] = 'File size exceeds 20MB limit';
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
}
