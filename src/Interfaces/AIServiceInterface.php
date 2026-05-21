<?php

declare(strict_types=1);

namespace App\Interfaces;

interface AIServiceInterface
{
    public function analyze(string $imagePath): array;

    public function getProductNutrients(string $productName): array;
}
