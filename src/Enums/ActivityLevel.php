<?php

declare(strict_types=1);

namespace App\Enums;

enum ActivityLevel: string
{
    case MINIMAL = 'minimal';
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case EXTRA = 'extra';

    public function getFactor(): float
    {
        return match ($this) {
            self::MINIMAL => 1.2,
            self::LOW     => 1.375,
            self::MEDIUM  => 1.55,
            self::HIGH    => 1.725,
            self::EXTRA   => 1.9,
        };
    }

    /// Создает enum из значения ( числовое '1.55' или семантическое 'medium')
    public static function fromValue(string $value): self
    {
        $value = trim($value);
        
        $converted = self::tryFrom($value);
        if ($converted !== null) {
            return $converted;
        }
        
        return self::fromFloat($value);
    }

    public static function fromFloat(string $value): self
    {
        $value = trim($value);
        
        return match ($value) {
            '1.2' => self::MINIMAL,
            '1.375' => self::LOW,
            '1.55' => self::MEDIUM,
            '1.725' => self::HIGH,
            '1.9' => self::EXTRA,
            default => self::MEDIUM,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::MINIMAL => 'Минимальная',
            self::LOW => 'Низкая',
            self::MEDIUM => 'Умеренная',
            self::HIGH => 'Высокая',
            self::EXTRA => 'Очень высокая',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::MINIMAL => 'Сидячий образ жизни',
            self::LOW => '1-3 дня в неделю',
            self::MEDIUM => '3-5 дней в неделю',
            self::HIGH => '6-7 дней в неделю',
            self::EXTRA => 'Физическая работа, спорт',
        };
    }
}
