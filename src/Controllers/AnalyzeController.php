<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Attributes\RouteAttribute;
use App\DTOs\AnalyzeRequestDTO;
use App\Exceptions\AppException;
use App\Exceptions\ValidationException;
use App\Services\MealAnalysisService;
use App\Validators\AnalyzeValidator;

class AnalyzeController
{
    public function __construct(
        private readonly MealAnalysisService $mealAnalysisService
    ) {}

    #[RouteAttribute('/api/upload', 'POST')]
    public function upload(): string
    {
        try {
            //Валидация входных данных
            AnalyzeValidator::validate($_POST, $_FILES);

            //Создание DTO
            $dto = AnalyzeRequestDTO::fromPost($_POST, $_FILES);

            //Вызов модели 
            $result = $this->mealAnalysisService->analyzeMeal($dto);

            return json_encode($result);

        } catch (ValidationException $e) {
            http_response_code(400);
            return json_encode($e->toArray());
        } catch (AppException $e) {
            http_response_code($e->getCode() ?: 500);
            return json_encode($e->toArray());
        } catch (\Exception $e) {
            http_response_code(500);
            return json_encode(['error' => 'Internal server error']);
        }
    }

    #[RouteAttribute('/api/history', 'GET')]
    public function history(): string
    {
        try {
            //Валидация tg_id
            $tgId = AnalyzeValidator::validateTgId($_GET['tg_id'] ?? null);

            //Получаем историю приемов пищи
            $meals = $this->mealAnalysisService->getMealHistory($tgId);

            return json_encode([
                'status' => 'success',
                'data' => $meals
            ]);

        } catch (ValidationException $e) {
            http_response_code(400);
            return json_encode($e->toArray());
        } catch (AppException $e) {
            http_response_code($e->getCode() ?: 500);
            return json_encode($e->toArray());
        } catch (\Exception $e) {
            http_response_code(500);
            return json_encode(['error' => 'Internal server error']);
        }
    }

    #[RouteAttribute('/api/delete-meal', 'POST')]
    public function deleteMeal(): string
    {
        try {
            //Получаем данные из запроса
            $data = json_decode(file_get_contents('php://input'), true);
            
            //Валидация
            $tgId = AnalyzeValidator::validateTgId($data['tg_id'] ?? null);
            $mealId = $data['meal_id'] ?? null;
            
            if (!$mealId || !is_numeric($mealId)) {
                throw new ValidationException('Invalid meal_id parameter');
            }

            // 3. Удаляем запись
            $result = $this->mealAnalysisService->deleteMeal((int)$mealId, $tgId);

            return json_encode($result);

        } catch (ValidationException $e) {
            http_response_code(400);
            return json_encode($e->toArray());
        } catch (AppException $e) {
            http_response_code($e->getCode() ?: 500);
            return json_encode($e->toArray());
        } catch (\Exception $e) {
            http_response_code(500);
            return json_encode(['error' => 'Internal server error']);
        }
    }
}