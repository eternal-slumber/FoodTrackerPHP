<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\AnalyzeRequestDTO;

class MealAnalysisService
{
    public function __construct(
        private readonly MealDraftService $drafts,
        private readonly MealService $meals
    ) {}

    public function analyzeMeal(AnalyzeRequestDTO $dto): array
    {
        $meal = $this->meals->saveAnalyzedMeal($dto->telegramId, $this->analyzeDraft($dto));

        return [
            'status' => 'success',
            'data' => [
                'food' => $meal->description,
                'kcal' => $meal->calories,
            ],
        ];
    }

    public function analyzeDraft(AnalyzeRequestDTO $dto): array
    {
        return $this->drafts->analyzeDraft($dto);
    }

    public function getMealHistory(int $tgId): array
    {
        return $this->meals->getMealHistory($tgId);
    }

    public function deleteMeal(int $mealId, int $tgId): array
    {
        return $this->meals->deleteMeal($mealId, $tgId);
    }

    public function saveManualMeal(int $tgId, string $mealName, array $products, ?string $imagePath = null): array
    {
        return $this->meals->saveManualMeal($tgId, $mealName, $products, $imagePath);
    }

    public function getMealImage(int $mealId, int $tgId): array
    {
        return $this->meals->getMealImage($mealId, $tgId);
    }

    public function getMealThumbnail(int $mealId, int $tgId): array
    {
        return $this->meals->getMealThumbnail($mealId, $tgId);
    }

    public function getMealDetails(int $mealId, int $tgId): array
    {
        return $this->meals->getMealDetails($mealId, $tgId);
    }
}
