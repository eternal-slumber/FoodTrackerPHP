<?php

declare(strict_types=1);

namespace App\Enums;

enum Goal: string
{
    // Используем семантические имена для ключей
    case DEFICIT = 'deficit';
    case MAINTENANCE = 'maintenance';
    case SURPLUS = 'surplus';

    /**
     * Возвращает численный модификатор калорий
     */
    public function getModifier(): float
    {
        return match ($this) {
            self::DEFICIT     => 0.8,
            self::MAINTENANCE => 1.0,
            self::SURPLUS     => 1.15,
        };
    }

    public static function fromValue(string $value): self
    {
        $value = trim($value);
        
        $converted = self::tryFrom($value);
        if ($converted !== null) {
            return $converted;
        }
        
        return self::fromFloat($value);
    }

    public static function fromFloat(string $modifier): self
    {
        $modifier = trim($modifier);
        
        return match ($modifier) {
            '0.8', 'deficit' => self::DEFICIT,
            '1.0', 'maintenance' => self::MAINTENANCE,
            '1.15', 'surplus' => self::SURPLUS,
            default => self::MAINTENANCE,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::DEFICIT     => 'Похудение',
            self::MAINTENANCE => 'Поддержание',
            self::SURPLUS     => 'Набор массы',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::DEFICIT     => '-20% от нормы',
            self::MAINTENANCE => 'Поддержание веса',
            self::SURPLUS     => '+15% к норме',
        };
    }
}