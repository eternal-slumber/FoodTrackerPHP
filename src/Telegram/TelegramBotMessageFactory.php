<?php

declare(strict_types=1);

namespace App\Telegram;

class TelegramBotMessageFactory
{
    public const SUMMARY_BUTTON = 'Сводка за сегодня';
    public const RECOMMENDATION_BUTTON = 'Что съесть';
    public const REMINDERS_BUTTON = 'Настроить напоминания';
    public const MAIN_MENU_CALLBACK = 'main_menu';
    public const SUMMARY_CALLBACK = 'summary_today';
    public const RECOMMENDATION_CALLBACK = 'meal_recommendation';
    public const REMINDERS_CALLBACK = 'reminders';

    public function welcome(?string $firstName = null): string
    {
        $name = $firstName !== null && trim($firstName) !== '' ? ', ' . trim($firstName) : '';

        return "Привет{$name}! Я быстрый доступ к FoodTracker.\n\n"
            . "Mini App остается основным интерфейсом, а здесь можно быстро посмотреть сводку за сегодня.";
    }

    public function help(): string
    {
        return "Команды:\n"
            . "/start - приветствие и быстрые кнопки\n"
            . "/summary - сводка за сегодня\n"
            . "/eat - рекомендация, что съесть сейчас\n"
            . "/help - список команд";
    }

    public function registrationRequired(string $miniAppUrl): string
    {
        $message = 'Профиль еще не создан. Откройте Mini App и пройдите регистрацию.';

        if ($miniAppUrl !== '') {
            $message .= "\n\nПосле регистрации команда /summary начнет показывать сводку.";
        }

        return $message;
    }

    public function reminders(string $miniAppUrl): string
    {
        if ($miniAppUrl === '') {
            return 'Настройка напоминаний';
        }

        return 'Открой Mini App, чтобы настроить напоминания.';
    }

    public function mealReminder(string $mealType): string
    {
        return match ($mealType) {
            'breakfast' => "Пора добавить завтрак 🍳\n\nЗаполни дневник, чтобы FoodTracker посчитал КБЖУ за день.",
            'lunch' => "Пора добавить обед 🥗\n\nДобавь приём пищи вручную или по фото.",
            'dinner' => "Пора добавить ужин 🍽️\n\nЗаверши дневник дня и посмотри итог по КБЖУ.",
            default => throw new \InvalidArgumentException('Unsupported reminder meal type'),
        };
    }

    public function summary(array $summary): string
    {
        $todayCalories = (int)$summary['today_sum'];
        $dailyGoal = (int)$summary['daily_goal'];
        $remainingCalories = (int)$summary['remaining_calories'];
        $todayMacros = $summary['today_macros'];
        $macroGoals = $summary['macro_goals'];

        $remainingLine = $remainingCalories >= 0
            ? 'Осталось ' . $remainingCalories . ' ккал.'
            : 'Превышение ' . abs($remainingCalories) . ' ккал.';

        return 'Сегодня ты съел ' . $todayCalories . ' / ' . $dailyGoal . " ккал.\n\n"
            . "БЖУ:\n"
            . 'Белки: ' . $this->formatNumber($todayMacros['proteins']) . ' / ' . $macroGoals['proteins_goal'] . " г\n"
            . 'Жиры: ' . $this->formatNumber($todayMacros['fats']) . ' / ' . $macroGoals['fats_goal'] . " г\n"
            . 'Углеводы: ' . $this->formatNumber($todayMacros['carbs']) . ' / ' . $macroGoals['carbs_goal'] . " г\n\n"
            . $remainingLine;
    }

    public function mainKeyboard(): array
    {
        return [
            'keyboard' => [
                [
                    ['text' => self::SUMMARY_BUTTON],
                    ['text' => self::REMINDERS_BUTTON],
                ],
            ],
            'resize_keyboard' => true,
            'is_persistent' => true,
        ];
    }

    public function removeReplyKeyboard(): array
    {
        return ['remove_keyboard' => true];
    }

    public function mainInlineKeyboard(string $miniAppUrl): array
    {
        $keyboard = [
            [
                ['text' => self::SUMMARY_BUTTON, 'callback_data' => self::SUMMARY_CALLBACK],
            ],
            [
                ['text' => self::RECOMMENDATION_BUTTON, 'callback_data' => self::RECOMMENDATION_CALLBACK],
            ],
            [
                ['text' => self::REMINDERS_BUTTON, 'callback_data' => self::REMINDERS_CALLBACK],
            ],
        ];

        if ($miniAppUrl === '') {
            return ['inline_keyboard' => $keyboard];
        }

        $keyboard[] = [[
            'text' => 'Открыть приложение',
            'web_app' => ['url' => $miniAppUrl],
        ]];

        return ['inline_keyboard' => $keyboard];
    }

    public function recommendationInlineKeyboard(string $miniAppUrl): array
    {
        $keyboard = [
            [
                ['text' => 'Назад', 'callback_data' => self::MAIN_MENU_CALLBACK],
            ],
        ];

        if ($miniAppUrl !== '') {
            $keyboard[] = [[
                'text' => 'Добавить еду',
                'web_app' => ['url' => $miniAppUrl],
            ]];
        }

        return ['inline_keyboard' => $keyboard];
    }

    public function summaryInlineKeyboard(string $miniAppUrl): array
    {
        $keyboard = [
            [
                ['text' => 'Назад', 'callback_data' => self::MAIN_MENU_CALLBACK],
                ['text' => self::RECOMMENDATION_BUTTON, 'callback_data' => self::RECOMMENDATION_CALLBACK],
            ],
        ];

        if ($miniAppUrl !== '') {
            $keyboard[] = [[
                'text' => 'Добавить еду',
                'web_app' => ['url' => $miniAppUrl],
            ]];
        }

        return ['inline_keyboard' => $keyboard];
    }

    public function miniAppInlineKeyboard(string $miniAppUrl): ?array
    {
        if ($miniAppUrl === '') {
            return null;
        }

        return [
            'inline_keyboard' => [[
                ['text' => 'Открыть Mini App', 'web_app' => ['url' => $miniAppUrl]],
            ]],
        ];
    }

    private function formatNumber(float|int $value): string
    {
        $rounded = round((float)$value, 1);

        if (abs($rounded - round($rounded)) < 0.01) {
            return (string)(int)round($rounded);
        }

        return number_format($rounded, 1, '.', '');
    }
}
