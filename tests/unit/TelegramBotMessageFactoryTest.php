<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Telegram\TelegramBotMessageFactory;
use PHPUnit\Framework\TestCase;

class TelegramBotMessageFactoryTest extends TestCase
{
    public function testFormatsSummaryMessage(): void
    {
        $factory = new TelegramBotMessageFactory();

        $message = $factory->summary([
            'today_sum' => 1450,
            'daily_goal' => 2200,
            'remaining_calories' => 750,
            'today_macros' => [
                'proteins' => 82.0,
                'fats' => 78.0,
                'carbs' => 160.0,
            ],
            'macro_goals' => [
                'proteins_goal' => 130,
                'fats_goal' => 65,
                'carbs_goal' => 250,
            ],
        ]);

        $this->assertSame(
            "Сегодня ты съел 1450 / 2200 ккал.\n\n"
            . "БЖУ:\n"
            . "Белки: 82 / 130 г\n"
            . "Жиры: 78 / 65 г\n"
            . "Углеводы: 160 / 250 г\n\n"
            . 'Осталось 750 ккал.',
            $message
        );
    }

    public function testFormatsMealReminderMessage(): void
    {
        $factory = new TelegramBotMessageFactory();

        $this->assertStringContainsString('завтрак', $factory->mealReminder('breakfast'));
        $this->assertStringContainsString('обед', $factory->mealReminder('lunch'));
        $this->assertStringContainsString('ужин', $factory->mealReminder('dinner'));
    }
}
