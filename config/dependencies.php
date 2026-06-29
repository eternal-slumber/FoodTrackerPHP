<?php

declare(strict_types=1);

use App\Config\AIProviderConfig;
use App\Config\TelegramBotConfig;
use App\Config\TelegramAuthConfig;
use App\Auth\TelegramAuthService;
use App\AI\AIChatClientInterface;
use App\AI\AIJsonResponseParser;
use App\AI\DailyNutritionInsightAIService;
use App\AI\MealPhotoAnalysisAIService;
use App\AI\MealRecommendationAIService;
use App\AI\OpenAICompatibleChatClient;
use App\AI\ProductNutritionAIService;
use App\Core\Database;
use App\Core\DatabaseConnection;
use App\Http\Middleware\TelegramAuthMiddleware;
use App\Repositories\MealRepository;
use App\Repositories\MealProductRepository;
use App\Repositories\DailyNutritionInsightRepository;
use App\Repositories\UserRepository;
use App\Services\CalorieCalculatorService;
use App\Services\AiQuotaService;
use App\Services\DailyNutritionSummaryService;
use App\Services\DailyNutritionInsightService;
use App\Services\MacroGoalCalculationService;
use App\Services\MealDraftService;
use App\Services\MealAnalysisService;
use App\Services\MealNutritionService;
use App\Services\MealRecommendationService;
use App\Services\MealService;
use App\Services\NutritionCalculatorService;
use App\Services\NutritionStreakService;
use App\Services\RateLimiterService;
use App\Services\SummaryService;
use App\Services\TelemetryService;
use App\Services\UploadedFileStorage;
use App\Telegram\TelegramBotApiClient;
use App\Telegram\TelegramBotClientInterface;
use App\Telegram\TelegramBotMessageFactory;
use App\Telegram\TelegramBotService;
use App\View\ViewRenderer;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Slim\Psr7\Factory\ResponseFactory;

use function DI\autowire;

$builder = new ContainerBuilder();

$definitions = [
    DatabaseConnection::class => autowire(),
    PDO::class => fn(): PDO => Database::getConnection(),
    AIProviderConfig::class => fn(): AIProviderConfig => AIProviderConfig::fromEnv($_ENV),

    AIChatClientInterface::class => fn(ContainerInterface $container): AIChatClientInterface => new OpenAICompatibleChatClient(
        $container->get(AIProviderConfig::class),
        $container->get(TelemetryService::class)
    ),

    TelegramAuthConfig::class => fn(): TelegramAuthConfig => TelegramAuthConfig::fromEnv($_ENV),
    TelegramBotConfig::class => fn(): TelegramBotConfig => TelegramBotConfig::fromEnv($_ENV),
    ResponseFactory::class => autowire(),

    TelegramAuthService::class => fn(ContainerInterface $container): TelegramAuthService => new TelegramAuthService(
        $container->get(TelegramAuthConfig::class)->botToken,
        $container->get(TelegramAuthConfig::class)->maxAgeSeconds
    ),

    TelegramAuthMiddleware::class => function (ContainerInterface $container): TelegramAuthMiddleware {
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
    AiQuotaService::class => autowire(),
    MacroGoalCalculationService::class => autowire(),
    DailyNutritionSummaryService::class => autowire(),
    DailyNutritionInsightAIService::class => autowire(),
    DailyNutritionInsightService::class => autowire(),
    AIJsonResponseParser::class => autowire(),
    MealPhotoAnalysisAIService::class => autowire(),
    MealRecommendationAIService::class => autowire(),
    ProductNutritionAIService::class => autowire(),
    UserRepository::class => autowire(),
    MealRepository::class => autowire(),
    MealProductRepository::class => autowire(),
    DailyNutritionInsightRepository::class => autowire(),
    RateLimiterService::class => autowire(),
    NutritionCalculatorService::class => autowire(),
    NutritionStreakService::class => autowire(),
    MealNutritionService::class => autowire(),
    MealRecommendationService::class => autowire(),
    MealDraftService::class => autowire(),
    MealService::class => autowire(),
    SummaryService::class => autowire(),
    TelemetryService::class => autowire(),

    ViewRenderer::class => fn(): ViewRenderer => new ViewRenderer(
        dirname(__DIR__) . '/resources/views'
    ),

    UploadedFileStorage::class => fn(): UploadedFileStorage => new UploadedFileStorage(
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
];

if (filter_var($_ENV['ADMIN_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
    $definitions = array_merge($definitions, require __DIR__ . '/admin-dependencies.php');
}

$builder->addDefinitions($definitions);

return $builder->build();
