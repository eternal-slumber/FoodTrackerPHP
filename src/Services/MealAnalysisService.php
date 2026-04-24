<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\AnalyzeRequestDTO;
use App\Interfaces\AIServiceInterface;
use App\Models\Meal;
use App\Models\User;

class MealAnalysisService
{
    public function __construct(
        private readonly AIServiceInterface $aiService,
        private readonly string $uploadPath
    ) {}

    public function analyzeMeal(AnalyzeRequestDTO $dto): array
    {
        // 1. Находим пользователя
        $user = User::findById($dto->tgId);
        if (!$user) {
            throw new \RuntimeException('User not found. Register first!');
        }

        // 2. Сохраняем файл (получаем относительный путь)
        $relativePath = $this->saveUploadedFile($dto->imagePath, $dto->tgId);

        // 3. Формируем полный путь для AI сервиса
        $fullPath = $this->uploadPath . $relativePath;

        // 4. Анализируем изображение через AI
        $analysis = $this->aiService->analyze($fullPath);

        // 5. Сохраняем результат в БД (используем относительный путь)
        $meal = $this->saveMeal($user->id, $analysis, $relativePath);

        return [
            'status' => 'success',
            'data' => [
                'food' => $meal->description,
                'kcal' => $meal->calories
            ]
        ];
    }

    private function saveUploadedFile(string $tmpPath, int $tgId): string
    {
        // Создаем папку для пользователя
        $userFolder = $this->uploadPath . 'user_' . $tgId;
        
        if (!file_exists($userFolder)) {
            if (!mkdir($userFolder, 0755, true)) {
                throw new \RuntimeException('Failed to create user folder');
            }
        }

        // Генерируем безопасное имя файла
        $timestamp = time();
        $randomString = bin2hex(random_bytes(8));
        $extension = 'jpg';
        
        $fileName = $timestamp . '_' . $randomString . '.' . $extension;
        $uploadPath = $userFolder . '/' . $fileName;

        if (!move_uploaded_file($tmpPath, $uploadPath)) {
            throw new \RuntimeException('Failed to save uploaded file');
        }

        // Устанавливаем права на файл
        chmod($uploadPath, 0644);

        // Возвращаем относительный путь для сохранения в БД
        return 'user_' . $tgId . '/' . $fileName;
    }

    private function saveMeal(int $userId, array $analysis, string $imagePath): Meal
    {
        $meal = new Meal(
            userId: $userId,
            description: $analysis['food'] ?? 'Не определено',
            calories: (int)($analysis['kcal'] ?? 0),
            imagePath: $imagePath  // Сохраняем полный относительный путь
        );

        if (!$meal->save()) {
            throw new \RuntimeException('Failed to save meal to database');
        }

        return $meal;
    }

    public function getMealHistory(int $tgId): array
    {
        // 1. Находим пользователя
        $user = User::findById($tgId);
        if (!$user) {
            throw new \RuntimeException('User not found. Register first!');
        }

        // 2. Получаем все приемы пищи пользователя
        $meals = Meal::findByUserId($user->id);

        // 3. Формируем ответ
        $result = [];
        foreach ($meals as $meal) {
            $result[] = [
                'id' => $meal->id,
                'description' => $meal->description,
                'calories' => $meal->calories,
                'proteins' => $meal->proteins,
                'fats' => $meal->fats,
                'carbs' => $meal->carbs,
                'image_url' => '/storage/uploads/' . $meal->imagePath,
                'created_at' => $meal->createdAt
            ];
        }

        return $result;
    }

    public function deleteMeal(int $mealId, int $tgId): array
    {
        // 1. Находим пользователя
        $user = User::findById($tgId);
        if (!$user) {
            throw new \RuntimeException('User not found. Register first!');
        }

        // 2. Находим запись о приеме пищи
        $meal = Meal::findById($mealId);
        if (!$meal) {
            throw new \RuntimeException('Meal not found');
        }

        // 3. Проверяем, что запись принадлежит пользователю
        if ($meal->userId !== $user->id) {
            throw new \RuntimeException('Access denied');
        }

        // 4. Удаляем файл изображения
        $imagePath = $this->uploadPath . $meal->imagePath;
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }

        // 5. Удаляем запись из БД
        if (!$meal->delete()) {
            throw new \RuntimeException('Failed to delete meal');
        }

        return [
            'status' => 'success',
            'message' => 'Meal deleted successfully'
        ];
    }

    public function saveManualMeal(int $tgId, string $mealName, array $products): array
    {
        $user = User::findById($tgId);
        if (!$user) {
            throw new \RuntimeException('User not found. Register first!');
        }

        $calculator = new NutritionCalculatorService();
        $aiService = $this->aiService;

        $processedProducts = [];
        
        foreach ($products as $product) {
            $name = trim($product['name'] ?? '');
            $weight = (int)($product['weight'] ?? 100);
            $processing = $product['processing'] ?? '';
            
            $kbju100g = [
                'calories' => 0,
                'proteins' => 0,
                'fats' => 0,
                'carbs' => 0,
            ];

            if (!empty($product['kbju']['calories'])) {
                $kbju100g = [
                    'calories' => (float)$product['kbju']['calories'],
                    'proteins' => (float)($product['kbju']['proteins'] ?? 0),
                    'fats' => (float)($product['kbju']['fats'] ?? 0),
                    'carbs' => (float)($product['kbju']['carbs'] ?? 0),
                ];
            } else {
                $aiKbju = $aiService->getProductNutrients($name);
                $kbju100g = [
                    'calories' => (float)($aiKbju['calories'] ?? 0),
                    'proteins' => (float)($aiKbju['proteins'] ?? 0),
                    'fats' => (float)($aiKbju['fats'] ?? 0),
                    'carbs' => (float)($aiKbju['carbs'] ?? 0),
                ];
            }

            $kbju100g['calories'] = $calculator->applyProcessingCoefficient($kbju100g['calories'], $processing);
            $kbju100g['proteins'] = $calculator->applyProcessingCoefficient($kbju100g['proteins'], $processing);
            $kbju100g['fats'] = $calculator->applyProcessingCoefficient($kbju100g['fats'], $processing);
            $kbju100g['carbs'] = $calculator->applyProcessingCoefficient($kbju100g['carbs'], $processing);

            $kbjuPortion = $calculator->calculateForPortion($kbju100g, $weight);

            $processedProducts[] = [
                'name' => $name,
                'weight' => $weight,
                'processing' => $processing,
                'calories' => $kbjuPortion['calories'],
                'proteins' => $kbjuPortion['proteins'],
                'fats' => $kbjuPortion['fats'],
                'carbs' => $kbjuPortion['carbs'],
            ];
        }

        $totals = $calculator->sumProducts($processedProducts);

        $meal = new Meal(
            userId: $user->id,
            description: $mealName,
            calories: $totals['calories'],
            proteins: $totals['proteins'],
            fats: $totals['fats'],
            carbs: $totals['carbs']
        );

        if (!$meal->save()) {
            throw new \RuntimeException('Failed to save meal');
        }

        $todayCalories = User::getTodayCalories($user->id);

        return [
            'status' => 'success',
            'today_calories' => $todayCalories,
            'meal' => [
                'id' => $meal->id,
                'description' => $mealName,
                'calories' => $totals['calories'],
                'proteins' => $totals['proteins'],
                'fats' => $totals['fats'],
                'carbs' => $totals['carbs'],
            ]
        ];
    }
}