<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\User;
use App\Services\CalorieCalculatorService;
use App\ValueObjects\BodyMetrics;
use App\Enums\ActivityLevel;
use App\Enums\Goal;
use App\Enums\Gender;

class UserModelTest extends TestCase
{
    public function testCreateUser(): void
    {
        $user = new User(
            tgId: 123456789,
            weight: 70.0,
            height: 175,
            age: 30,
            gender: 'male'
        );

        $this->assertEquals(123456789, $user->tgId);
        $this->assertEquals(70.0, $user->weight);
        $this->assertEquals(175, $user->height);
        $this->assertEquals(30, $user->age);
        $this->assertEquals('male', $user->gender);
    }

    public function testCreateUserWithAllFields(): void
    {
        $user = new User(
            tgId: 123456789,
            weight: 70.0,
            height: 175,
            age: 30,
            gender: 'male',
            activityLevel: 'medium',
            goal: 'maintenance',
            dailyGoal: 2000
        );

        $this->assertEquals('medium', $user->activityLevel);
        $this->assertEquals('maintenance', $user->goal);
        $this->assertEquals(2000, $user->dailyGoal);
    }

    public function testDefaultActivityAndGoal(): void
    {
        $user = new User(
            tgId: 123456789,
            weight: 70.0,
            height: 175,
            age: 30,
            gender: 'male'
        );

        $this->assertEquals('medium', $user->activityLevel);
        $this->assertEquals('maintenance', $user->goal);
    }

    public function testCalculateDailyGoalWithCalculator(): void
    {
        $metrics = new BodyMetrics(
            age: 30,
            height: 175,
            weight: 70,
            gender: Gender::MALE
        );
        
        $calculator = new CalorieCalculatorService();
        $dailyGoal = $calculator->calculate(
            $metrics,
            ActivityLevel::MEDIUM,
            Goal::MAINTENANCE
        );

        $this->assertEquals(2554, $dailyGoal);
    }

    public function testCalculateDailyGoalWithDeficit(): void
    {
        $metrics = new BodyMetrics(
            age: 30,
            height: 175,
            weight: 70,
            gender: Gender::MALE
        );
        
        $calculator = new CalorieCalculatorService();
        $dailyGoal = $calculator->calculate(
            $metrics,
            ActivityLevel::MEDIUM,
            Goal::DEFICIT
        );

        $this->assertEquals(2043, $dailyGoal);
    }

    public function testCalculateDailyGoalWithSurplus(): void
    {
        $metrics = new BodyMetrics(
            age: 30,
            height: 175,
            weight: 70,
            gender: Gender::MALE
        );
        
        $calculator = new CalorieCalculatorService();
        $dailyGoal = $calculator->calculate(
            $metrics,
            ActivityLevel::MEDIUM,
            Goal::SURPLUS
        );

        $this->assertEquals(2937, $dailyGoal);
    }

    public function testGenderMale(): void
    {
        $user = new User(
            tgId: 123456789,
            weight: 70.0,
            height: 175,
            age: 30,
            gender: 'male'
        );

        $this->assertEquals('male', $user->gender);
    }

    public function testGenderFemale(): void
    {
        $user = new User(
            tgId: 987654321,
            weight: 70.0,
            height: 175,
            age: 30,
            gender: 'female'
        );

        $this->assertEquals('female', $user->gender);
    }

    public function testActivityLevelFromValue(): void
    {
        $this->assertEquals('medium', ActivityLevel::fromValue('medium')->value);
        $this->assertEquals('medium', ActivityLevel::fromValue('1.55')->value);
    }

    public function testGoalFromValue(): void
    {
        $this->assertEquals('maintenance', Goal::fromValue('maintenance')->value);
        $this->assertEquals('maintenance', Goal::fromValue('1.0')->value);
    }
}