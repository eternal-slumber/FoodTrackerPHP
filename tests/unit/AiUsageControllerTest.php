<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Auth\CurrentUser;
use App\Controllers\AiUsageController;
use App\Http\Middleware\TelegramAuthMiddleware;
use App\Services\AiQuotaService;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

class AiUsageControllerTest extends TestCase
{
    public function testReturnsUsageForAuthenticatedTelegramUser(): void
    {
        $quota = new FakeAiUsageQuotaService();
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/api/ai-usage')
            ->withAttribute(
                TelegramAuthMiddleware::CURRENT_USER_ATTRIBUTE,
                new CurrentUser(telegramId: 100001, username: 'user')
            );

        $response = (new AiUsageController($quota))->show(
            $request,
            (new ResponseFactory())->createResponse()
        );
        $payload = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(100001, $quota->telegramId);
        $this->assertSame(14, $payload['data']['general']['remaining']);
        $this->assertSame(9, $payload['data']['insights']['remaining']);
    }

    public function testRejectsRequestWithoutAuthenticatedUser(): void
    {
        $response = (new AiUsageController(new FakeAiUsageQuotaService()))->show(
            (new ServerRequestFactory())->createServerRequest('GET', '/api/ai-usage'),
            (new ResponseFactory())->createResponse()
        );

        $this->assertSame(401, $response->getStatusCode());
    }
}

class FakeAiUsageQuotaService extends AiQuotaService
{
    public ?int $telegramId = null;

    public function __construct() {}

    public function getUsageForTelegramUser(int $telegramId): array
    {
        $this->telegramId = $telegramId;

        return [
            'general' => $this->quota(20, 14, 3600),
            'insights' => $this->quota(12, 9, 1200),
        ];
    }

    private function quota(int $limit, int $remaining, int $resetsInSeconds): array
    {
        return [
            'used' => $limit - $remaining,
            'limit' => $limit,
            'remaining' => $remaining,
            'resets_at' => '2026-06-29T00:00:00+00:00',
            'resets_in_seconds' => $resetsInSeconds,
        ];
    }
}
