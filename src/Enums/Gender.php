<?php

declare(strict_types=1);

namespace App\Enums;

enum Gender: string
{
    case MALE = 'male';
    case FEMALE = 'female';

    public function label(): string
    {
        return match ($this) {
            self::MALE => 'Мужской',
            self::FEMALE => 'Женский',
        };
    }
    public function getBmrAdjustment(): int
    {
        return match ($this) {
            self::MALE => 5,
            self::FEMALE => -161,
        };
    }

    public static function fromValue(string $value): self
    {
        $converted = self::tryFrom($value);
        if ($converted !== null) {
            return $converted;
        }
        
        return match (strtolower($value)) {
            'm', 'male', 'м' => self::MALE,
            'f', 'female', 'ж' => self::FEMALE,
            default => throw new \InvalidArgumentException("Неизвестный пол: $value"),
        };
    }
}
