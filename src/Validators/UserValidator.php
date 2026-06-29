<?php

declare(strict_types=1);

namespace App\Validators;

use App\Exceptions\ValidationException;
use App\Enums\ActivityLevel;
use App\Enums\Gender;
use App\Enums\Goal;
use App\ValueObjects\BodyMetrics;

class UserValidator
{
    public static function validateRegistration(array $data): array
    {
        $errors = [];

        // tg_id
        if (empty($data['tg_id']) || !is_numeric($data['tg_id'])) {
            $errors['tg_id'] = 'Telegram ID обязателен';
        }

        // BodyMetrics (валидация встроена)
        $bodyMetrics = null;
        try {
            $gender = Gender::fromValue($data['gender'] ?? '');
            
            $bodyMetrics = new BodyMetrics(
                age: (int)($data['age'] ?? 0),
                height: (int)($data['height'] ?? 0),
                weight: (float)($data['weight'] ?? 0),
                gender: $gender
            );
        } catch (\InvalidArgumentException $e) {
            $errors['metrics'] = $e->getMessage();
        }

        // activity_level (опционально)
        $activityLevel = null;
        if (!empty($data['activity_level'])) {
            $activityValue = trim((string)$data['activity_level']);
            $allowedActivityValues = [
                'minimal', 'low', 'medium', 'high', 'extra',
                '1.2', '1.375', '1.55', '1.725', '1.9',
            ];
            if (in_array($activityValue, $allowedActivityValues, true)) {
                $activityLevel = ActivityLevel::fromValue((string)$data['activity_level']);
            } else {
                $errors['activity_level'] = 'Неверный уровень активности';
            }
        }

        // goal (опционально)
        $goal = null;
        if (!empty($data['goal'])) {
            $goalValue = trim((string)$data['goal']);
            $allowedGoalValues = ['deficit', 'maintenance', 'surplus', '0.8', '1.0', '1.15'];
            if (in_array($goalValue, $allowedGoalValues, true)) {
                $goal = Goal::fromValue((string)$data['goal']);
            } else {
                $errors['goal'] = 'Неверная цель';
            }
        }

        if (!empty($errors)) {
            throw new ValidationException('Ошибка валидации', $errors);
        }

        return [
            'tg_id' => (int)$data['tg_id'],
            'age' => (int)$data['age'],
            'height' => (int)$data['height'],
            'weight' => (float)$data['weight'],
            'gender' => $data['gender'],
            'activity_level' => $activityLevel?->value ?? ActivityLevel::MEDIUM->value,
            'goal' => $goal?->value ?? Goal::MAINTENANCE->value,
        ];
    }
}
