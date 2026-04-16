<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Enums\Gender;

class BodyMetrics
{
    public const MIN_WEIGHT = 30;
    public const MAX_WEIGHT = 250;
    public const MIN_HEIGHT = 100;
    public const MAX_HEIGHT = 220;
    public const MIN_AGE = 10;
    public const MAX_AGE = 100;

    public function __construct(
        public readonly int $age,
        public readonly int $height,
        public readonly float $weight,
        public Gender $gender
        )
    {
        match(true){
            $this->age <= self::MIN_AGE || $this->age > self::MAX_AGE 
                => throw new \InvalidArgumentException("Возраст должен быть от " . self::MIN_AGE . " до " . self::MAX_AGE),
            $this->height <= self::MIN_HEIGHT || $this->height > self::MAX_HEIGHT 
                => throw new \InvalidArgumentException("Рост должен быть от " . self::MIN_HEIGHT . " до " . self::MAX_HEIGHT),
            $this->weight <= self::MIN_WEIGHT || $this->weight > self::MAX_WEIGHT 
                => throw new \InvalidArgumentException("Вес должен быть от " . self::MIN_WEIGHT . " до " . self::MAX_WEIGHT),
            default => true,          
        };
    }
}

