<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Meal;
use App\Models\MealProduct;
use App\Models\User;
use App\Repositories\MealProductRepository;
use App\Repositories\MealRepository;
use App\Repositories\UserRepository;

class MealService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly MealRepository $meals,
        private readonly MealProductRepository $mealProducts,
        private readonly MealNutritionService $nutrition,
        private readonly UploadedFileStorage $storage
    ) {}

    public function saveAnalyzedMeal(int $telegramId, array $draft): Meal
    {
        $user = $this->findUser($telegramId);

        $meal = new Meal(
            userId: (int)$user->id,
            description: $draft['data']['meal_name'] ?? 'Не определено',
            calories: (int)($draft['data']['totals']['calories'] ?? 0),
            totalWeight: $draft['data']['totals']['weight'] ?? null,
            imagePath: $draft['data']['draft_image_path'] ?? ''
        );

        if (!$this->meals->save($meal)) {
            throw new \RuntimeException('Failed to save meal to database');
        }

        return $meal;
    }

    public function getMealHistory(int $tgId): array
    {
        $user = $this->findUser($tgId);
        $history = [];

        foreach ($this->meals->findByUserId((int)$user->id) as $meal) {
            $history[] = [
                'id' => $meal->id,
                'description' => $meal->description,
                'calories' => $meal->calories,
                'proteins' => $meal->proteins,
                'fats' => $meal->fats,
                'carbs' => $meal->carbs,
                'weight' => $meal->totalWeight,
                'image_url' => $meal->imagePath ? '/api/meals/' . $meal->id . '/image' : null,
                'created_at' => $meal->createdAt,
            ];
        }

        return $history;
    }

    public function deleteMeal(int $mealId, int $tgId): array
    {
        $user = $this->findUser($tgId);
        $meal = $this->findOwnedMeal($mealId, (int)$user->id);

        $this->storage->deleteIfExists($meal->imagePath);

        if (!$this->meals->delete($mealId)) {
            throw new \RuntimeException('Failed to delete meal');
        }

        return [
            'status' => 'success',
            'message' => 'Meal deleted successfully',
        ];
    }

    public function saveManualMeal(int $tgId, string $mealName, array $products, ?string $imagePath = null): array
    {
        $mealName = substr(trim($mealName), 0, 120);
        if ($mealName === '') {
            $mealName = 'Прием пищи';
        }

        $user = $this->findUser($tgId);
        $processedProducts = $this->nutrition->processProducts($products);
        $totals = $this->nutrition->sumProducts($processedProducts);
        $totalWeight = $this->nutrition->sumWeight($processedProducts);
        $imagePath = $this->storage->sanitizeDraftImagePath($imagePath, $tgId);

        $meal = new Meal(
            userId: (int)$user->id,
            description: $mealName,
            calories: $totals['calories'],
            proteins: $totals['proteins'],
            fats: $totals['fats'],
            carbs: $totals['carbs'],
            totalWeight: $totalWeight,
            imagePath: $imagePath
        );

        $this->meals->beginTransaction();

        try {
            if (!$this->meals->save($meal) || !$meal->id) {
                throw new \RuntimeException('Failed to save meal');
            }

            $this->mealProducts->saveMany(
                $meal->id,
                array_map(
                    fn(array $product): MealProduct => MealProduct::fromProcessedProduct($meal->id, $product),
                    $processedProducts
                )
            );

            $this->meals->commit();
        } catch (\Throwable $e) {
            $this->meals->rollBack();
            throw $e;
        }

        return [
            'status' => 'success',
            'today_calories' => $this->users->getTodayCalories((int)$user->id),
            'meal' => [
                'id' => $meal->id,
                'description' => $mealName,
                'calories' => $totals['calories'],
                'proteins' => $totals['proteins'],
                'fats' => $totals['fats'],
                'carbs' => $totals['carbs'],
                'weight' => $totalWeight,
            ],
        ];
    }

    public function getMealDetails(int $mealId, int $tgId): array
    {
        $user = $this->findUser($tgId);
        $meal = $this->findOwnedMeal($mealId, (int)$user->id);

        return [
            'id' => $meal->id,
            'description' => $meal->description,
            'calories' => $meal->calories,
            'proteins' => $meal->proteins,
            'fats' => $meal->fats,
            'carbs' => $meal->carbs,
            'weight' => $meal->totalWeight,
            'image_url' => $meal->imagePath ? '/api/meals/' . $meal->id . '/image' : null,
            'created_at' => $meal->createdAt,
            'products' => array_map(
                fn(MealProduct $product): array => [
                    'name' => $product->name,
                    'weight' => $product->weight,
                    'processing' => $product->processing,
                    'calories' => $product->calories,
                    'proteins' => $product->proteins,
                    'fats' => $product->fats,
                    'carbs' => $product->carbs,
                ],
                $this->mealProducts->findByMealId($mealId)
            ),
        ];
    }

    public function getMealImage(int $mealId, int $tgId): array
    {
        $user = $this->findUser($tgId);
        $meal = $this->findOwnedMeal($mealId, (int)$user->id);

        if (!$meal->imagePath || !is_file($this->storage->fullPath($meal->imagePath))) {
            throw new \RuntimeException('Meal image not found');
        }

        return [
            'path' => $this->storage->fullPath($meal->imagePath),
            'mime_type' => $this->storage->mimeType($meal->imagePath),
        ];
    }

    private function findUser(int $tgId): User
    {
        $user = $this->users->findByTelegramId($tgId);
        if (!$user) {
            throw new \RuntimeException('User not found. Register first!');
        }

        return $user;
    }

    private function findOwnedMeal(int $mealId, int $userId): Meal
    {
        $meal = $this->meals->findById($mealId);
        if (!$meal) {
            throw new \RuntimeException('Meal not found');
        }

        if ($meal->userId !== $userId) {
            throw new \RuntimeException('Access denied');
        }

        return $meal;
    }
}
