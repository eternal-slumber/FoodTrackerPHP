<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\CurrentUser;
use App\Attributes\RouteAttribute;
use App\DTOs\AnalyzeRequestDTO;
use App\Exceptions\AppException;
use App\Exceptions\ValidationException;
use App\Http\Middleware\TelegramAuthMiddleware;
use App\Http\ResponseResponder;
use App\Services\MealAnalysisService;
use App\Services\MealNutritionService;
use App\Services\NutritionCalculatorService;
use App\Services\RateLimiterService;
use App\Services\UploadedFileStorage;
use App\Validators\AnalyzeValidator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AnalyzeController
{
    public function __construct(
        private readonly MealAnalysisService $mealAnalysisService,
        private readonly NutritionCalculatorService $nutritionCalculator,
        private readonly RateLimiterService $rateLimiter,
        private readonly UploadedFileStorage $uploadedFileStorage
    ) {}

    #[RouteAttribute('/api/processing-options', 'GET')]
    public function processingOptions(Request $request, Response $response): Response
    {
        return ResponseResponder::json($response, [
            'status' => 'success',
            'data' => $this->nutritionCalculator->getProcessingOptions(),
        ]);
    }

    #[RouteAttribute('/api/analyze-draft', 'POST')]
    #[RouteAttribute('/api/upload', 'POST')]
    public function analyzeDraft(Request $request, Response $response): Response
    {
        try {
            $currentUser = $this->currentUser($request);
            $postData = $request->getParsedBody();
            $postData = is_array($postData) ? $postData : [];

            //Валидация входных данных
            AnalyzeValidator::validate($postData, $_FILES);
            $mimeType = AnalyzeValidator::detectMimeType($_FILES['photo']['tmp_name']);

            if (!$this->rateLimiter->consume('tg:' . $currentUser->telegramId, 'upload', 10, 3600)) {
                return ResponseResponder::json($response, ['error' => 'Too Many Requests'], 429);
            }

            if (!$this->rateLimiter->consume('tg:' . $currentUser->telegramId, 'ai_daily', 20, 86400)) {
                return ResponseResponder::json($response, ['error' => 'Daily AI quota exceeded'], 429);
            }

            //Создание DTO
            $dto = AnalyzeRequestDTO::fromPost([
                'telegram_id' => $currentUser->telegramId,
                'mime_type' => $mimeType,
            ], $_FILES);

            $result = $this->mealAnalysisService->analyzeDraft($dto);

            return ResponseResponder::json($response, $result);

        } catch (ValidationException $e) {
            return ResponseResponder::json($response, $e->toArray(), 400);
        } catch (AppException $e) {
            return ResponseResponder::json($response, $e->toArray(), $e->getCode() ?: 500);
        } catch (\Exception $e) {
            error_log('Upload error: ' . $e->getMessage());
            return ResponseResponder::json($response, ['error' => 'Internal server error'], 500);
        }
    }

    #[RouteAttribute('/api/upload-draft-image', 'POST')]
    public function uploadDraftImage(Request $request, Response $response): Response
    {
        try {
            $currentUser = $this->currentUser($request);
            $postData = $request->getParsedBody();
            $postData = is_array($postData) ? $postData : [];

            AnalyzeValidator::validate($postData, $_FILES);
            $mimeType = AnalyzeValidator::detectMimeType($_FILES['photo']['tmp_name']);

            if (!$this->rateLimiter->consume('tg:' . $currentUser->telegramId, 'upload', 10, 3600)) {
                return ResponseResponder::json($response, ['error' => 'Too Many Requests'], 429);
            }

            $relativePath = $this->uploadedFileStorage->saveUploadedFile(
                $_FILES['photo']['tmp_name'],
                $currentUser->telegramId,
                $mimeType
            );

            return ResponseResponder::json($response, [
                'status' => 'success',
                'data' => [
                    'draft_image_path' => $relativePath,
                ],
            ]);
        } catch (ValidationException $e) {
            return ResponseResponder::json($response, $e->toArray(), 400);
        } catch (AppException $e) {
            return ResponseResponder::json($response, $e->toArray(), $e->getCode() ?: 500);
        } catch (\Exception $e) {
            error_log('Draft image upload error: ' . $e->getMessage());
            return ResponseResponder::json($response, ['error' => 'Internal server error'], 500);
        }
    }

    #[RouteAttribute('/api/history', 'GET')]
    public function history(Request $request, Response $response): Response
    {
        try {
            $currentUser = $this->currentUser($request);

            //Валидация tg_id
            $tgId = $currentUser->telegramId;

            //Получаем историю приемов пищи
            $meals = $this->mealAnalysisService->getMealHistory($tgId);

            return ResponseResponder::json($response, [
                'status' => 'success',
                'data' => $meals
            ]);

        } catch (ValidationException $e) {
            return ResponseResponder::json($response, $e->toArray(), 400);
        } catch (AppException $e) {
            return ResponseResponder::json($response, $e->toArray(), $e->getCode() ?: 500);
        } catch (\Exception $e) {
            error_log('History error: ' . $e->getMessage());
            return ResponseResponder::json($response, ['error' => 'Internal server error'], 500);
        }
    }

    #[RouteAttribute('/api/delete-meal', 'POST')]
    public function deleteMeal(Request $request, Response $response): Response
    {
        try {
            $currentUser = $this->currentUser($request);
            //Получаем данные из запроса
            $data = $request->getParsedBody();
            $data = is_array($data) ? $data : [];
            
            //Валидация
            $tgId = $currentUser->telegramId;
            $mealId = $data['meal_id'] ?? null;
            
            if (!$mealId || !is_numeric($mealId)) {
                throw new ValidationException('Invalid meal_id parameter');
            }

            if (!$this->rateLimiter->consume('tg:' . $currentUser->telegramId, 'delete_meal', 30, 3600)) {
                return ResponseResponder::json($response, ['error' => 'Too Many Requests'], 429);
            }

            // 3. Удаляем запись
            $result = $this->mealAnalysisService->deleteMeal((int)$mealId, $tgId);
 
            return ResponseResponder::json($response, $result);

        } catch (ValidationException $e) {
            return ResponseResponder::json($response, $e->toArray(), 400);
        } catch (AppException $e) {
            return ResponseResponder::json($response, $e->toArray(), $e->getCode() ?: 500);
        } catch (\Exception $e) {
            error_log('Delete meal error: ' . $e->getMessage());
            return ResponseResponder::json($response, ['error' => 'Internal server error'], 500);
        }
    }

    #[RouteAttribute('/api/save-meal', 'POST')]
    public function saveMeal(Request $request, Response $response): Response
    {
        try {
            $currentUser = $this->currentUser($request);
            $data = $request->getParsedBody();
            $data = is_array($data) ? $data : [];
            
            $tgId = $currentUser->telegramId;
            $mealName = trim($data['meal_name'] ?? 'Прием пищи');
            $products = $data['products'] ?? [];
            $draftImagePath = isset($data['draft_image_path']) ? (string)$data['draft_image_path'] : null;

            if (empty($products)) {
                throw new ValidationException('Список продуктов пуст');
            }

            if (!is_array($products)) {
                throw new ValidationException('Некорректный список продуктов');
            }

            if (count($products) > MealNutritionService::MAX_PRODUCTS_PER_MEAL) {
                throw new ValidationException('В одном приеме можно сохранить не больше ' . MealNutritionService::MAX_PRODUCTS_PER_MEAL . ' продуктов');
            }

            if (!$this->rateLimiter->consume('tg:' . $currentUser->telegramId, 'save_meal', 30, 3600)) {
                return ResponseResponder::json($response, ['error' => 'Too Many Requests'], 429);
            }

            $productsNeedingAi = array_filter(
                $products,
                fn(mixed $product): bool => is_array($product) && empty($product['kbju']['calories'])
            );

            foreach ($productsNeedingAi as $_) {
                if (!$this->rateLimiter->consume('tg:' . $currentUser->telegramId, 'ai_daily', 20, 86400)) {
                    return ResponseResponder::json($response, ['error' => 'Daily AI quota exceeded'], 429);
                }
            }

            $result = $this->mealAnalysisService->saveManualMeal($tgId, $mealName, $products, $draftImagePath);
            
            return ResponseResponder::json($response, $result);

        } catch (ValidationException $e) {
            return ResponseResponder::json($response, $e->toArray(), 400);
        } catch (AppException $e) {
            return ResponseResponder::json($response, $e->toArray(), $e->getCode() ?: 500);
        } catch (\Exception $e) {
            error_log('Save meal error: ' . $e->getMessage());
            return ResponseResponder::json($response, ['error' => 'Internal server error'], 500);
        }
    }

    #[RouteAttribute('/api/meals/{id}/image', 'GET')]
    public function mealImage(Request $request, Response $response, array $args): Response
    {
        try {
            $currentUser = $this->currentUser($request);
            $mealId = isset($args['id']) && is_numeric($args['id']) ? (int)$args['id'] : 0;

            if ($mealId < 1) {
                throw new ValidationException('Invalid meal id');
            }

            $image = $this->mealAnalysisService->getMealImage($mealId, $currentUser->telegramId);
            $response->getBody()->write((string)file_get_contents($image['path']));

            return $response
                ->withHeader('Content-Type', $image['mime_type'])
                ->withHeader('Cache-Control', 'private, max-age=3600');
        } catch (ValidationException $e) {
            return ResponseResponder::json($response, $e->toArray(), 400);
        } catch (\Exception $e) {
            return ResponseResponder::json($response, ['error' => 'Not Found'], 404);
        }
    }

    #[RouteAttribute('/api/meals/{id}', 'GET')]
    public function mealDetails(Request $request, Response $response, array $args): Response
    {
        try {
            $currentUser = $this->currentUser($request);
            $mealId = isset($args['id']) && is_numeric($args['id']) ? (int)$args['id'] : 0;

            if ($mealId < 1) {
                throw new ValidationException('Invalid meal id');
            }

            return ResponseResponder::json($response, [
                'status' => 'success',
                'data' => $this->mealAnalysisService->getMealDetails($mealId, $currentUser->telegramId),
            ]);
        } catch (ValidationException $e) {
            return ResponseResponder::json($response, $e->toArray(), 400);
        } catch (AppException $e) {
            return ResponseResponder::json($response, $e->toArray(), $e->getCode() ?: 500);
        } catch (\Exception $e) {
            error_log('Meal details error: ' . $e->getMessage());
            return ResponseResponder::json($response, ['error' => 'Not Found'], 404);
        }
    }

    private function currentUser(Request $request): CurrentUser
    {
        $currentUser = $request->getAttribute(TelegramAuthMiddleware::CURRENT_USER_ATTRIBUTE);

        if (!$currentUser instanceof CurrentUser) {
            throw new \RuntimeException('Authenticated Telegram user is missing');
        }

        return $currentUser;
    }
}
