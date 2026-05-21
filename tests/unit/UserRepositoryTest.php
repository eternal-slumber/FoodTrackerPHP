<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\CalorieCalculatorService;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\TestCase;

class UserRepositoryTest extends TestCase
{
    public function testSaveUpdatesGenderForExistingUser(): void
    {
        $repository = new UserRepository(
            $this->createDatabase(),
            new CalorieCalculatorService()
        );

        $this->assertTrue($repository->save(new User(
            tgId: 100001,
            weight: 80,
            height: 180,
            age: 30,
            gender: 'male',
            activityLevel: 'medium',
            goal: 'maintenance'
        )));

        $this->assertTrue($repository->save(new User(
            tgId: 100001,
            weight: 70,
            height: 170,
            age: 28,
            gender: 'female',
            activityLevel: 'low',
            goal: 'deficit'
        )));

        $updatedUser = $repository->findByTelegramId(100001);

        $this->assertNotNull($updatedUser);
        $this->assertSame('female', $updatedUser->gender);
        $this->assertSame(70.0, $updatedUser->weight);
        $this->assertSame(170, $updatedUser->height);
        $this->assertSame(28, $updatedUser->age);
        $this->assertSame('low', $updatedUser->activityLevel);
        $this->assertSame('deficit', $updatedUser->goal);
    }

    public function testGetTodayNutritionUsesClientTimezoneOffset(): void
    {
        $db = $this->createDatabase();
        $repository = new UserRepository($db, new CalorieCalculatorService());

        $db->exec(
            "INSERT INTO meals (user_id, calories, proteins, fats, carbs, created_at) VALUES
             (1, 100, 10, 5, 20, '2026-05-20 20:30:00'),
             (1, 200, 20, 10, 30, '2026-05-20 21:15:00'),
             (1, 300, 30, 15, 40, '2026-05-21 10:00:00'),
             (1, 400, 40, 20, 50, '2026-05-21 21:30:00'),
             (2, 900, 90, 45, 100, '2026-05-21 10:00:00')"
        );

        $nutrition = $repository->getTodayNutrition(
            1,
            -180,
            new DateTimeImmutable('2026-05-20 21:30:00', new DateTimeZone('UTC'))
        );

        $this->assertSame(500, $nutrition['calories']);
        $this->assertSame(50.0, $nutrition['proteins']);
        $this->assertSame(25.0, $nutrition['fats']);
        $this->assertSame(70.0, $nutrition['carbs']);
    }

    private function createDatabase(): PDO
    {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->exec(
            'CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tg_id INTEGER NOT NULL UNIQUE,
                weight REAL NOT NULL,
                height INTEGER NOT NULL,
                age INTEGER NOT NULL,
                gender TEXT NOT NULL,
                activity_level TEXT NOT NULL,
                goal TEXT NOT NULL,
                daily_goal INTEGER NOT NULL
            )'
        );
        $db->exec(
            'CREATE TABLE meals (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                calories INTEGER NOT NULL,
                proteins REAL NOT NULL,
                fats REAL NOT NULL,
                carbs REAL NOT NULL,
                created_at TEXT NOT NULL
            )'
        );

        return $db;
    }
}
