<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\MealRepository;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\TestCase;

class MealRepositoryInsightTest extends TestCase
{
    public function testFindForLocalDayReturnsOnlyTodayMealsInLocalTime(): void
    {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->exec(
            'CREATE TABLE meals (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                food_description TEXT,
                calories INTEGER NOT NULL,
                proteins REAL NOT NULL,
                fats REAL NOT NULL,
                carbs REAL NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
        $db->exec(
            "INSERT INTO meals (user_id, food_description, calories, proteins, fats, carbs, created_at) VALUES
             (7, 'Старый прием', 300, 20, 10, 30, '2026-06-26 20:30:00'),
             (7, 'Завтрак: омлет', 420, 32, 25, 8, '2026-06-26 21:30:00'),
             (7, 'Обед: суп', 510, 28, 18, 55, '2026-06-27 10:15:00'),
             (8, 'Чужой прием', 900, 60, 40, 80, '2026-06-27 10:15:00')"
        );
        $repository = new MealRepository($db);

        $meals = $repository->findForLocalDay(
            7,
            -180,
            new DateTimeImmutable('2026-06-27 12:00:00', new DateTimeZone('UTC'))
        );

        $this->assertCount(2, $meals);
        $this->assertSame('Завтрак: омлет', $meals[0]['name']);
        $this->assertSame('00:30', $meals[0]['time']);
        $this->assertSame('13:15', $meals[1]['time']);
    }
}
