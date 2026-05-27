<?php

declare(strict_types=1);

namespace App\Services;

use App\AI\MealPhotoAnalysisAIService;
use App\DTOs\AnalyzeRequestDTO;
use App\Repositories\UserRepository;

class MealDraftService
{
    public function __construct(
        private readonly MealPhotoAnalysisAIService $photoAnalysis,
        private readonly UserRepository $users,
        private readonly UploadedFileStorage $storage,
        private readonly MealNutritionService $nutrition
    ) {}

    public function analyzeDraft(AnalyzeRequestDTO $dto): array
    {
        $user = $this->users->findByTelegramId($dto->telegramId);
        if (!$user) {
            throw new \RuntimeException('User not found. Register first!');
        }

        $relativePath = $this->storage->saveUploadedFile($dto->imagePath, $dto->telegramId, $dto->mimeType);
        $analysis = $this->photoAnalysis->analyze($this->storage->fullPath($relativePath));
        $product = $this->nutrition->createAiDraftProduct($analysis);

        return [
            'status' => 'success',
            'data' => [
                'meal_name' => $product['name'] !== '' ? $product['name'] : 'Прием пищи',
                'products' => [$product],
                'totals' => [
                    'calories' => $product['calories'],
                    'proteins' => $product['proteins'],
                    'fats' => $product['fats'],
                    'carbs' => $product['carbs'],
                    'weight' => $product['weight'],
                ],
                'confidence' => null,
                'draft_image_path' => $relativePath,
            ],
        ];
    }
}
