<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Auth\CurrentUser;
use App\Config\TelegramAuthConfig;
use App\Controllers\UserController;
use App\Http\Middleware\TelegramAuthMiddleware;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Repositories\MealRepository;
use App\Services\DailyNutritionSummaryService;
use App\Services\DailyNutritionInsightService;
use App\Services\AiQuotaService;
use App\Services\MacroGoalCalculationService;
use App\Services\NutritionStreakService;
use App\Services\RateLimiterService;
use App\Services\SummaryService;
use App\Services\TelemetryService;
use App\Services\UploadedFileStorage;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

class UserEventsControllerTest extends TestCase
{
    public function testAppOpenedUsesAuthenticatedUserAndIgnoresBodyTelegramId(): void
    {
        $telemetry = new FakeUserEventsTelemetryService();
        $controller = new UserController(
            new FakeUserEventsUserRepository(),
            new FakeUserEventsRateLimiterService(),
            new AiQuotaService(new FakeUserEventsRateLimiterService()),
            new FakeUserEventsSummaryService(),
            new FakeUserEventsDailyNutritionSummaryService(),
            new MacroGoalCalculationService(),
            new FakeUserEventsDailyNutritionInsightService(),
            new NutritionStreakService(new FakeUserEventsMealRepository()),
            new UploadedFileStorage('/tmp/'),
            $telemetry,
            new TelegramAuthConfig(
                botToken: 'test-token',
                maxAgeSeconds: 86400,
                appEnv: 'local',
                devAuthEnabled: true,
                devUserId: 100001,
                devUsername: 'real_user'
            )
        );
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/events/app-opened')
            ->withParsedBody(['tg_id' => 999999])
            ->withAttribute(
                TelegramAuthMiddleware::CURRENT_USER_ATTRIBUTE,
                new CurrentUser(telegramId: 100001, username: 'real_user')
            );

        $response = $controller->appOpened($request, (new ResponseFactory())->createResponse());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(7, $telemetry->userId);
        $this->assertSame('app_opened', $telemetry->eventName);
        $this->assertNull($telemetry->eventData);
    }

    public function testAppOpenedStoresDevMarkerForUnregisteredLocalDevUser(): void
    {
        $telemetry = new FakeUserEventsTelemetryService();
        $controller = new UserController(
            new FakeUserEventsUserRepository(),
            new FakeUserEventsRateLimiterService(),
            new AiQuotaService(new FakeUserEventsRateLimiterService()),
            new FakeUserEventsSummaryService(),
            new FakeUserEventsDailyNutritionSummaryService(),
            new MacroGoalCalculationService(),
            new FakeUserEventsDailyNutritionInsightService(),
            new NutritionStreakService(new FakeUserEventsMealRepository()),
            new UploadedFileStorage('/tmp/'),
            $telemetry,
            new TelegramAuthConfig(
                botToken: 'test-token',
                maxAgeSeconds: 86400,
                appEnv: 'local',
                devAuthEnabled: true,
                devUserId: 200002,
                devUsername: 'dev_user'
            )
        );
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/events/app-opened')
            ->withAttribute(
                TelegramAuthMiddleware::CURRENT_USER_ATTRIBUTE,
                new CurrentUser(telegramId: 200002, username: 'dev_user')
            );

        $response = $controller->appOpened($request, (new ResponseFactory())->createResponse());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNull($telemetry->userId);
        $this->assertSame('app_opened', $telemetry->eventName);
        $this->assertSame(['dev' => true, 'dev_telegram_id' => 200002], $telemetry->eventData);
    }

    public function testRegisterRecordsUserRegisteredEventForNewUser(): void
    {
        $telemetry = new FakeUserEventsTelemetryService();
        $controller = new UserController(
            new FakeUserEventsUserRepository(),
            new FakeUserEventsRateLimiterService(),
            new AiQuotaService(new FakeUserEventsRateLimiterService()),
            new FakeUserEventsSummaryService(),
            new FakeUserEventsDailyNutritionSummaryService(),
            new MacroGoalCalculationService(),
            new FakeUserEventsDailyNutritionInsightService(),
            new NutritionStreakService(new FakeUserEventsMealRepository()),
            new UploadedFileStorage('/tmp/'),
            $telemetry,
            new TelegramAuthConfig(
                botToken: 'test-token',
                maxAgeSeconds: 86400,
                appEnv: 'production',
                devAuthEnabled: false,
                devUserId: 0,
                devUsername: ''
            )
        );
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/register')
            ->withParsedBody([
                'weight' => 80,
                'height' => 180,
                'age' => 30,
                'gender' => 'male',
                'activity_level' => 'medium',
                'goal' => 'maintenance',
            ])
            ->withAttribute(
                TelegramAuthMiddleware::CURRENT_USER_ATTRIBUTE,
                new CurrentUser(telegramId: 300003, username: 'new_user')
            );

        $response = $controller->register($request, (new ResponseFactory())->createResponse());

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(11, $telemetry->userId);
        $this->assertSame('user_registered', $telemetry->eventName);
        $this->assertSame(['source' => 'mini_app'], $telemetry->eventData);
        $this->assertSame(2400, $payload['daily_goal']);
        $this->assertSame(128, $payload['macro_goals']['proteins_goal']);
        $this->assertSame(67, $payload['macro_goals']['fats_goal']);
        $this->assertSame(321, $payload['macro_goals']['carbs_goal']);
    }
}

class FakeUserEventsUserRepository extends UserRepository
{
    private ?User $savedUser = null;

    public function __construct() {}

    public function findByTelegramId(int $telegramId): ?User
    {
        if ($this->savedUser !== null && $this->savedUser->tgId === $telegramId) {
            return $this->savedUser;
        }

        return $telegramId === 100001
            ? new User(
                tgId: $telegramId,
                weight: 70,
                height: 175,
                age: 30,
                gender: 'male',
                id: 7
            )
            : null;
    }

    public function save(User $user): bool
    {
        $user->id = 11;
        $user->dailyGoal = 2400;
        $this->savedUser = $user;

        return true;
    }
}

class FakeUserEventsTelemetryService extends TelemetryService
{
    public ?int $userId = null;
    public ?string $eventName = null;
    public ?array $eventData = null;

    public function __construct() {}

    public function recordUserEvent(
        ?int $userId,
        string $eventName,
        ?array $eventData = null,
        ?Request $request = null
    ): void {
        $this->userId = $userId;
        $this->eventName = $eventName;
        $this->eventData = $eventData;
    }
}

class FakeUserEventsRateLimiterService extends RateLimiterService
{
    public function __construct() {}

    public function consume(string $scope, string $action, int $limit, int $windowSeconds): bool
    {
        return true;
    }
}

class FakeUserEventsSummaryService extends SummaryService
{
    public function __construct() {}
}

class FakeUserEventsDailyNutritionSummaryService extends DailyNutritionSummaryService
{
    public function __construct() {}
}

class FakeUserEventsDailyNutritionInsightService extends DailyNutritionInsightService
{
    public function __construct() {}
}

class FakeUserEventsMealRepository extends MealRepository
{
    public function __construct() {}
}
