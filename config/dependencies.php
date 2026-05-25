<?php

declare(strict_types=1);

use App\Config\AIProviderConfig;
use App\Config\TelegramBotConfig;
use App\Config\TelegramAuthConfig;
use App\Auth\TelegramAuthService;
use App\Core\Database;
use App\Http\Middleware\TelegramAuthMiddleware;
use App\Interfaces\AIServiceInterface;
use App\Repositories\MealRepository;
use App\Repositories\MealProductRepository;
use App\Repositories\UserRepository;
use App\Services\CalorieCalculatorService;
use App\Services\DailyNutritionSummaryService;
use App\Services\MacroGoalCalculationService;
use App\Services\MealDraftService;
use App\Services\MealAnalysisService;
use App\Services\MealNutritionService;
use App\Services\MealRecommendationService;
use App\Services\MealService;
use App\Services\NutritionCalculatorService;
use App\Services\OpenAICompatibleAIService;
use App\Services\RateLimiterService;
use App\Services\SummaryService;
use App\Services\UploadedFileStorage;
use App\Telegram\TelegramBotApiClient;
use App\Telegram\TelegramBotClientInterface;
use App\Telegram\TelegramBotMessageFactory;
use App\Telegram\TelegramBotService;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Slim\Psr7\Factory\ResponseFactory;

use function DI\autowire;

$builder = new ContainerBuilder();

$builder->addDefinitions([
    PDO::class => static fn(): PDO => Database::getConnection(),

    AIProviderConfig::class => static fn(): AIProviderConfig => AIProviderConfig::fromEnv($_ENV),

    AIServiceInterface::class => static fn(ContainerInterface $container): AIServiceInterface => new OpenAICompatibleAIService(
        $container->get(AIProviderConfig::class)
    ),

    TelegramAuthConfig::class => static fn(): TelegramAuthConfig => TelegramAuthConfig::fromEnv($_ENV),
    TelegramBotConfig::class => static fn(): TelegramBotConfig => TelegramBotConfig::fromEnv($_ENV),
    ResponseFactory::class => autowire(),

    TelegramAuthService::class => static fn(ContainerInterface $container): TelegramAuthService => new TelegramAuthService(
        $container->get(TelegramAuthConfig::class)->botToken,
        $container->get(TelegramAuthConfig::class)->maxAgeSeconds
    ),

    TelegramAuthMiddleware::class => static function (ContainerInterface $container): TelegramAuthMiddleware {
        $config = $container->get(TelegramAuthConfig::class);

        return new TelegramAuthMiddleware(
            $container->get(TelegramAuthService::class),
            $container->get(ResponseFactory::class),
            $config->appEnv,
            $config->devAuthEnabled,
            $config->devUserId,
            $config->devUsername
        );
    },

    CalorieCalculatorService::class => autowire(),
    MacroGoalCalculationService::class => autowire(),
    DailyNutritionSummaryService::class => autowire(),
    UserRepository::class => autowire(),
    MealRepository::class => autowire(),
    MealProductRepository::class => autowire(),
    RateLimiterService::class => autowire(),
    NutritionCalculatorService::class => autowire(),
    MealNutritionService::class => autowire(),
    MealRecommendationService::class => autowire(),
    MealDraftService::class => autowire(),
    MealService::class => autowire(),
    SummaryService::class => autowire(),

    UploadedFileStorage::class => static fn(): UploadedFileStorage => new UploadedFileStorage(
        dirname(__DIR__) . '/storage/uploads/'
    ),
    MealAnalysisService::class => autowire(),
    TelegramBotClientInterface::class => autowire(TelegramBotApiClient::class),
    TelegramBotMessageFactory::class => autowire(),
    TelegramBotService::class => autowire(),

    App\Controllers\HomeController::class => autowire(),
    App\Controllers\UserController::class => autowire(),
    App\Controllers\AnalyzeController::class => autowire(),
    App\Controllers\TelegramBotController::class => autowire(),
]);

return $builder->build();
