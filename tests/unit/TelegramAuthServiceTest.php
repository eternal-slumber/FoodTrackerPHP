<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Auth\TelegramAuthService;
use App\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

class TelegramAuthServiceTest extends TestCase
{
    public function testValidInitDataReturnsCurrentUser(): void
    {
        $service = new TelegramAuthService('test-bot-token', 86400);
        $initData = $this->buildInitData('test-bot-token', [
            'auth_date' => (string)time(),
            'query_id' => 'abc',
            'user' => json_encode([
                'id' => 123456789,
                'first_name' => 'Artem',
                'username' => 'artem',
            ]),
        ]);

        $currentUser = $service->validateInitData($initData);

        $this->assertSame(123456789, $currentUser->telegramId);
        $this->assertSame('artem', $currentUser->username);
        $this->assertSame('Artem', $currentUser->firstName);
    }

    public function testInvalidHashIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        $service = new TelegramAuthService('test-bot-token', 86400);
        $initData = $this->buildInitData('other-token', [
            'auth_date' => (string)time(),
            'user' => json_encode(['id' => 123456789]),
        ]);

        $service->validateInitData($initData);
    }

    public function testExpiredInitDataIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        $service = new TelegramAuthService('test-bot-token', 60);
        $initData = $this->buildInitData('test-bot-token', [
            'auth_date' => (string)(time() - 3600),
            'user' => json_encode(['id' => 123456789]),
        ]);

        $service->validateInitData($initData);
    }

    private function buildInitData(string $botToken, array $data): string
    {
        ksort($data, SORT_STRING);

        $checkString = implode(
            "\n",
            array_map(
                fn(string $key, string $value): string => $key . '=' . $value,
                array_keys($data),
                array_values($data)
            )
        );

        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $data['hash'] = hash_hmac('sha256', $checkString, $secretKey);

        return http_build_query($data);
    }
}
