<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Config\TelegramBotConfig;
use App\Services\DailyNutritionSummaryService;
use App\Services\MealRecommendationService;
use App\Services\RateLimiterService;
use App\Telegram\TelegramBotClientInterface;
use App\Telegram\TelegramBotMessageFactory;
use App\Telegram\TelegramBotService;
use PHPUnit\Framework\TestCase;

class TelegramBotServiceTest extends TestCase
{
    public function testStartSendsWelcomeWithKeyboard(): void
    {
        $client = new FakeTelegramBotClient();
        $service = $this->createService($client, null);

        $service->handleUpdate([
            'message' => [
                'chat' => ['id' => 55],
                'from' => ['id' => 100001, 'first_name' => 'Артем'],
                'text' => '/start',
            ],
        ]);

        $this->assertStringContainsString('Привет', $client->messages[0]['text']);
        $this->assertSame('Сводка за сегодня', $client->messages[0]['reply_markup']['inline_keyboard'][0][0]['text']);
        $this->assertSame('Что съесть', $client->messages[0]['reply_markup']['inline_keyboard'][1][0]['text']);
        $this->assertSame('Настроить напоминания', $client->messages[0]['reply_markup']['inline_keyboard'][2][0]['text']);
        $this->assertSame('Открыть приложение', $client->messages[0]['reply_markup']['inline_keyboard'][3][0]['text']);
        $this->assertSame('https://example.com', $client->messages[0]['reply_markup']['inline_keyboard'][3][0]['web_app']['url']);
    }

    public function testDuplicateStartCommandIsIgnoredByDebounce(): void
    {
        $client = new FakeTelegramBotClient();
        $service = $this->createService($client, null);

        $service->handleUpdate([
            'message' => [
                'message_id' => 10,
                'chat' => ['id' => 55],
                'from' => ['id' => 100001, 'first_name' => 'Артем'],
                'text' => '/start',
            ],
        ]);
        $service->handleUpdate([
            'message' => [
                'message_id' => 11,
                'chat' => ['id' => 55],
                'from' => ['id' => 100001, 'first_name' => 'Артем'],
                'text' => '/start',
            ],
        ]);

        $this->assertCount(1, $client->messages);
        $this->assertStringContainsString('Привет', $client->messages[0]['text']);
    }

    public function testStartDebounceDoesNotBlockSummaryCommand(): void
    {
        $client = new FakeTelegramBotClient();
        $service = $this->createService($client, [
            'today_sum' => 1450,
            'daily_goal' => 2200,
            'remaining_calories' => 750,
            'today_macros' => ['proteins' => 82.0, 'fats' => 78.0, 'carbs' => 160.0],
            'macro_goals' => ['proteins_goal' => 130, 'fats_goal' => 65, 'carbs_goal' => 250],
        ]);

        $service->handleUpdate([
            'message' => [
                'chat' => ['id' => 55],
                'from' => ['id' => 100001, 'first_name' => 'Артем'],
                'text' => '/start',
            ],
        ]);
        $service->handleUpdate([
            'message' => [
                'chat' => ['id' => 55],
                'from' => ['id' => 100001],
                'text' => '/summary',
            ],
        ]);

        $this->assertCount(2, $client->messages);
        $this->assertStringContainsString('Привет', $client->messages[0]['text']);
        $this->assertSame("Сегодня ты съел 1450 / 2200 ккал.\n\nБЖУ:\nБелки: 82 / 130 г\nЖиры: 78 / 65 г\nУглеводы: 160 / 250 г\n\nОсталось 750 ккал.", $client->messages[1]['text']);
    }

    public function testSummaryCommandSendsDailySummary(): void
    {
        $client = new FakeTelegramBotClient();
        $service = $this->createService($client, [
            'today_sum' => 1450,
            'daily_goal' => 2200,
            'remaining_calories' => 750,
            'today_macros' => ['proteins' => 82.0, 'fats' => 78.0, 'carbs' => 160.0],
            'macro_goals' => ['proteins_goal' => 130, 'fats_goal' => 65, 'carbs_goal' => 250],
        ]);

        $service->handleUpdate([
            'message' => [
                'chat' => ['id' => 55],
                'from' => ['id' => 100001],
                'text' => '/summary',
            ],
        ]);

        $this->assertSame("Сегодня ты съел 1450 / 2200 ккал.\n\nБЖУ:\nБелки: 82 / 130 г\nЖиры: 78 / 65 г\nУглеводы: 160 / 250 г\n\nОсталось 750 ккал.", $client->messages[0]['text']);
        $this->assertSame('Назад', $client->messages[0]['reply_markup']['inline_keyboard'][0][0]['text']);
        $this->assertSame('Что съесть', $client->messages[0]['reply_markup']['inline_keyboard'][0][1]['text']);
        $this->assertSame('Добавить еду', $client->messages[0]['reply_markup']['inline_keyboard'][1][0]['text']);
    }

    public function testSummaryCallbackSendsDailySummary(): void
    {
        $client = new FakeTelegramBotClient();
        $service = $this->createService($client, [
            'today_sum' => 1450,
            'daily_goal' => 2200,
            'remaining_calories' => 750,
            'today_macros' => ['proteins' => 82.0, 'fats' => 78.0, 'carbs' => 160.0],
            'macro_goals' => ['proteins_goal' => 130, 'fats_goal' => 65, 'carbs_goal' => 250],
        ]);

        $service->handleUpdate([
            'callback_query' => [
                'id' => 'callback-1',
                'from' => ['id' => 100001],
                'message' => ['message_id' => 77, 'chat' => ['id' => 55]],
                'data' => 'summary_today',
            ],
        ]);

        $this->assertSame(['callback-1'], $client->answeredCallbacks);
        $this->assertSame([], $client->messages);
        $this->assertSame(77, $client->editedMessages[0]['message_id']);
        $this->assertSame("Сегодня ты съел 1450 / 2200 ккал.\n\nБЖУ:\nБелки: 82 / 130 г\nЖиры: 78 / 65 г\nУглеводы: 160 / 250 г\n\nОсталось 750 ккал.", $client->editedMessages[0]['text']);
    }

    public function testMainMenuCallbackEditsCurrentMessage(): void
    {
        $client = new FakeTelegramBotClient();
        $service = $this->createService($client, null);

        $service->handleUpdate([
            'callback_query' => [
                'id' => 'callback-main',
                'from' => ['id' => 100001, 'first_name' => 'Артем'],
                'message' => ['message_id' => 88, 'chat' => ['id' => 55]],
                'data' => 'main_menu',
            ],
        ]);

        $this->assertSame(['callback-main'], $client->answeredCallbacks);
        $this->assertSame([], $client->messages);
        $this->assertSame(88, $client->editedMessages[0]['message_id']);
        $this->assertStringContainsString('Привет, Артем', $client->editedMessages[0]['text']);
        $this->assertSame('Сводка за сегодня', $client->editedMessages[0]['reply_markup']['inline_keyboard'][0][0]['text']);
    }

    public function testRecommendationCallbackSendsAiRecommendation(): void
    {
        $client = new FakeTelegramBotClient();
        $service = $this->createService($client, null, 'Съешь омлет с овощами.');

        $service->handleUpdate([
            'callback_query' => [
                'id' => 'callback-2',
                'from' => ['id' => 100001],
                'message' => ['message_id' => 99, 'chat' => ['id' => 55]],
                'data' => 'meal_recommendation',
            ],
        ]);

        $this->assertSame(['callback-2'], $client->answeredCallbacks);
        $this->assertSame([], $client->messages);
        $this->assertSame(99, $client->editedMessages[0]['message_id']);
        $this->assertSame('Думаю...', $client->editedMessages[0]['text']);
        $this->assertSame(99, $client->editedMessages[1]['message_id']);
        $this->assertSame('Съешь омлет с овощами.', $client->editedMessages[1]['text']);
        $this->assertSame('Назад', $client->editedMessages[1]['reply_markup']['inline_keyboard'][0][0]['text']);
        $this->assertSame('Добавить еду', $client->editedMessages[1]['reply_markup']['inline_keyboard'][1][0]['text']);
    }

    public function testEatCommandStillSendsThinkingThenEditsIt(): void
    {
        $client = new FakeTelegramBotClient();
        $service = $this->createService($client, null, 'Съешь омлет с овощами.');

        $service->handleUpdate([
            'message' => [
                'chat' => ['id' => 55],
                'from' => ['id' => 100001],
                'text' => '/eat',
            ],
        ]);

        $this->assertSame('Думаю...', $client->messages[0]['text']);
        $this->assertSame(1, $client->editedMessages[0]['message_id']);
        $this->assertSame('Съешь омлет с овощами.', $client->editedMessages[0]['text']);
    }

    private function createService(
        FakeTelegramBotClient $client,
        ?array $summary,
        ?string $recommendation = null
    ): TelegramBotService
    {
        return new TelegramBotService(
            new TelegramBotConfig('token', 'secret', 'https://example.com', -180),
            $client,
            new TelegramBotMessageFactory(),
            new FakeTelegramDailySummaryService($summary),
            new FakeTelegramMealRecommendationService($recommendation),
            new FakeTelegramRateLimiterService()
        );
    }
}

class FakeTelegramBotClient implements TelegramBotClientInterface
{
    public array $messages = [];
    public array $editedMessages = [];
    public array $answeredCallbacks = [];

    public function sendMessage(int|string $chatId, string $text, ?array $replyMarkup = null): ?array
    {
        $messageId = count($this->messages) + 1;
        $this->messages[] = [
            'message_id' => $messageId,
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => $replyMarkup,
        ];

        return ['message_id' => $messageId];
    }

    public function editMessageText(int|string $chatId, int $messageId, string $text, ?array $replyMarkup = null): void
    {
        $this->editedMessages[] = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'reply_markup' => $replyMarkup,
        ];
    }

    public function answerCallbackQuery(string $callbackQueryId): void
    {
        $this->answeredCallbacks[] = $callbackQueryId;
    }
}

class FakeTelegramDailySummaryService extends DailyNutritionSummaryService
{
    public function __construct(private readonly ?array $summary) {}

    public function getForTelegramUser(int $telegramId, int $timezoneOffsetMinutes = 0): ?array
    {
        return $this->summary;
    }
}

class FakeTelegramMealRecommendationService extends MealRecommendationService
{
    public function __construct(private readonly ?string $recommendation) {}

    public function recommendForTelegramUser(
        int $telegramId,
        int $timezoneOffsetMinutes = 0,
        ?\DateTimeImmutable $nowUtc = null
    ): ?string {
        return $this->recommendation;
    }
}

class FakeTelegramRateLimiterService extends RateLimiterService
{
    private array $attempts = [];

    public function __construct() {}

    public function consume(string $scope, string $action, int $limit, int $windowSeconds): bool
    {
        $key = $scope . ':' . $action;
        $this->attempts[$key] = $this->attempts[$key] ?? 0;

        if ($this->attempts[$key] >= $limit) {
            return false;
        }

        $this->attempts[$key]++;

        return true;
    }
}
