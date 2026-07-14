<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\TransactionManager;
use App\Exceptions\NotFoundException;
use App\Models\Meal;
use App\Models\MealProduct;
use App\Models\User;
use App\Repositories\MealProductRepository;
use App\Repositories\MealRepository;
use App\Repositories\UserRepository;
use DateTimeImmutable;
use DateTimeZone;

class MealService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly MealRepository $meals,
        private readonly MealProductRepository $mealProducts,
        private readonly TransactionManager $transactions,
        private readonly MealNutritionService $nutrition,
        private readonly ReminderScheduleService $reminderSchedule,
        private readonly EveningSummaryScheduleService $eveningSummarySchedule,
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
                'thumbnail_url' => $meal->imagePath
                    ? '/api/meals/' . $meal->id . '/thumbnail?v=' . $this->imageCacheToken($meal->imagePath)
                    : null,
                'created_at' => $meal->createdAt,
            ];
        }

        return $history;
    }

    public function deleteMeal(int $mealId, int $tgId): array
    {
        $user = $this->findUser($tgId);
        $meal = $this->findOwnedMeal($mealId, (int)$user->id);

        $this->transactions->transactional(function () use ($mealId): void {
            if (!$this->meals->delete($mealId)) {
                throw new \RuntimeException('Failed to delete meal');
            }
        });

        try {
            $this->storage->deleteImageSet($meal->imagePath);
        } catch (\Throwable $error) {
            error_log(sprintf(
                'Meal image cleanup failed after deleting meal %d (%s)',
                $mealId,
                $error::class
            ));
        }

        return [
            'status' => 'success',
            'message' => 'Meal deleted successfully',
        ];
    }

    public function saveManualMeal(
        int $tgId,
        string $mealName,
        array $products,
        ?string $imagePath = null,
        ?string $mealType = null,
        ?DateTimeImmutable $eatenAtUtc = null,
        int $timezoneOffsetMinutes = 0
    ): array {
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

        $this->transactions->transactional(function () use ($meal, $processedProducts): void {
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
        });

        $this->scheduleReminder(
            (int)$user->id,
            $mealType,
            $eatenAtUtc,
            $timezoneOffsetMinutes
        );
        $this->scheduleEveningSummary((int)$user->id, $eatenAtUtc, $timezoneOffsetMinutes);

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

    public function saveManualMealsAsCards(
        int $tgId,
        string $mealName,
        array $products,
        ?string $imagePath = null,
        ?string $mealType = null,
        ?DateTimeImmutable $eatenAtUtc = null,
        int $timezoneOffsetMinutes = 0
    ): array {
        $user = $this->findUser($tgId);
        $mealTypeLabel = $this->mealTypePrefix($mealName);
        $mainImagePath = $this->storage->sanitizeDraftImagePath($imagePath, $tgId);
        $savedMeals = $this->transactions->transactional(function () use (
            $products,
            $mealTypeLabel,
            $mainImagePath,
            $tgId,
            $user
        ): array {
            $savedMeals = [];

            foreach (array_values($products) as $index => $product) {
                $processedProducts = $this->nutrition->processProducts([$product]);
                $processedProduct = $processedProducts[0];
                $totals = $this->nutrition->sumProducts($processedProducts);
                $totalWeight = $this->nutrition->sumWeight($processedProducts);
                $productName = substr(trim((string)($processedProduct['name'] ?? 'Продукт')), 0, 120);
                $description = substr($mealTypeLabel . ': ' . $productName, 0, 120);
                $productImagePath = $index === 0
                    ? $mainImagePath
                    : $this->storage->sanitizeDraftImagePath(
                        isset($product['draft_image_path']) ? (string)$product['draft_image_path'] : null,
                        $tgId
                    );

                $meal = new Meal(
                    userId: (int)$user->id,
                    description: $description,
                    calories: $totals['calories'],
                    proteins: $totals['proteins'],
                    fats: $totals['fats'],
                    carbs: $totals['carbs'],
                    totalWeight: $totalWeight,
                    imagePath: $productImagePath
                );

                if (!$this->meals->save($meal) || !$meal->id) {
                    throw new \RuntimeException('Failed to save meal card');
                }

                $this->mealProducts->saveMany(
                    $meal->id,
                    [MealProduct::fromProcessedProduct($meal->id, $processedProduct)]
                );

                $savedMeals[] = [
                    'id' => $meal->id,
                    'description' => $description,
                    'calories' => $totals['calories'],
                    'proteins' => $totals['proteins'],
                    'fats' => $totals['fats'],
                    'carbs' => $totals['carbs'],
                    'weight' => $totalWeight,
                ];
            }

            return $savedMeals;
        });

        $this->scheduleReminder(
            (int)$user->id,
            $mealType,
            $eatenAtUtc,
            $timezoneOffsetMinutes
        );
        $this->scheduleEveningSummary((int)$user->id, $eatenAtUtc, $timezoneOffsetMinutes);

        return [
            'status' => 'success',
            'today_calories' => $this->users->getTodayCalories((int)$user->id),
            'meal' => $savedMeals[0] ?? null,
            'meals' => $savedMeals,
        ];
    }

    private function mealTypePrefix(string $mealName): string
    {
        $prefix = trim(explode(':', $mealName, 2)[0]);

        return $prefix !== '' ? substr($prefix, 0, 40) : 'Прием пищи';
    }

    private function scheduleReminder(
        int $userId,
        ?string $mealType,
        ?DateTimeImmutable $eatenAtUtc,
        int $timezoneOffsetMinutes
    ): void {
        if (!in_array($mealType, ['breakfast', 'lunch', 'dinner'], true)) {
            return;
        }

        try {
            $this->reminderSchedule->scheduleFromMeal(
                $userId,
                $mealType,
                $eatenAtUtc ?? new DateTimeImmutable('now', new DateTimeZone('UTC')),
                $timezoneOffsetMinutes
            );
        } catch (\Throwable $error) {
            error_log(sprintf(
                'Meal reminder scheduling failed for user %d: %s',
                $userId,
                $error->getMessage()
            ));
        }
    }

    private function scheduleEveningSummary(
        int $userId,
        ?DateTimeImmutable $eatenAtUtc,
        int $timezoneOffsetMinutes
    ): void {
        try {
            $this->eveningSummarySchedule->scheduleFromMeal(
                $userId,
                $eatenAtUtc ?? new DateTimeImmutable('now', new DateTimeZone('UTC')),
                $timezoneOffsetMinutes
            );
        } catch (\Throwable $error) {
            error_log(sprintf(
                'Evening summary scheduling failed for user %d: %s',
                $userId,
                $error->getMessage()
            ));
        }
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
            throw new NotFoundException();
        }

        return [
            'path' => $this->storage->fullPath($meal->imagePath),
            'mime_type' => $this->storage->mimeType($meal->imagePath),
        ];
    }

    public function getMealThumbnail(int $mealId, int $tgId): array
    {
        $user = $this->findUser($tgId);
        $meal = $this->findOwnedMeal($mealId, (int)$user->id);

        if (!$meal->imagePath || !is_file($this->storage->fullPath($meal->imagePath))) {
            throw new NotFoundException();
        }

        if (!$this->storage->hasThumbnail($meal->imagePath)) {
            try {
                $this->storage->createThumbnail($meal->imagePath);
            } catch (\Throwable $e) {
                error_log('Lazy thumbnail generation failed: ' . $e->getMessage());
            }
        }

        $isThumbnail = $this->storage->hasThumbnail($meal->imagePath);
        $relativePath = $isThumbnail
            ? $this->storage->thumbnailRelativePath($meal->imagePath)
            : $meal->imagePath;

        return [
            'path' => $this->storage->fullPath($relativePath),
            'mime_type' => $isThumbnail ? 'image/jpeg' : $this->storage->mimeType($meal->imagePath),
        ];
    }

    private function findUser(int $tgId): User
    {
        $user = $this->users->findByTelegramId($tgId);
        if (!$user) {
            throw new NotFoundException();
        }

        return $user;
    }

    private function findOwnedMeal(int $mealId, int $userId): Meal
    {
        $meal = $this->meals->findById($mealId);
        if (!$meal) {
            throw new NotFoundException();
        }

        if ($meal->userId !== $userId) {
            throw new NotFoundException();
        }

        return $meal;
    }

    private function imageCacheToken(string $relativePath): int
    {
        foreach ([$this->storage->thumbnailRelativePath($relativePath), $relativePath] as $path) {
            $fullPath = $this->storage->fullPath($path);
            if (!is_file($fullPath)) {
                continue;
            }

            $modifiedAt = filemtime($fullPath);
            if ($modifiedAt !== false) {
                return $modifiedAt;
            }
        }

        return time();
    }
}
