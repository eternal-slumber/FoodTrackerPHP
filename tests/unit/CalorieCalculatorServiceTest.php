<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\CalorieCalculatorService;
use App\Services\NutritionCalculatorService;
use App\ValueObjects\BodyMetrics;
use App\Enums\ActivityLevel;
use App\Enums\Goal;
use App\Enums\Gender;

class CalorieCalculatorServiceTest extends TestCase
{
    private CalorieCalculatorService $service;

    protected function setUp(): void
    {
        $this->service = new CalorieCalculatorService();
    }

    public function testCalculateBMRForMale(): void
    {
        $metrics = new BodyMetrics(
            age: 30,
            height: 175,
            weight: 70,
            gender: Gender::MALE
        );
        
        $result = $this->service->calculateBMR($metrics);
        $this->assertEquals(1648, $result);
    }

    public function testCalculateBMRForFemale(): void
    {
        $metrics = new BodyMetrics(
            age: 30,
            height: 175,
            weight: 70,
            gender: Gender::FEMALE
        );
        
        $result = $this->service->calculateBMR($metrics);
        $this->assertEquals(1482, $result);
    }

    public function testCalculateTDEEMinimalActivity(): void
    {
        $metrics = new BodyMetrics(
            age: 30,
            height: 175,
            weight: 70,
            gender: Gender::MALE
        );
        
        $bmr = $this->service->calculateBMR($metrics);
        $tdee = $this->service->calculateTDEE($bmr, ActivityLevel::MINIMAL);
        $this->assertEquals(1977, $tdee);
    }

    public function testCalculateTDEELowActivity(): void
    {
        $metrics = new BodyMetrics(
            age: 30,
            height: 175,
            weight: 70,
            gender: Gender::MALE
        );
        
        $bmr = $this->service->calculateBMR($metrics);
        $tdee = $this->service->calculateTDEE($bmr, ActivityLevel::LOW);
        $this->assertEquals(2266, $tdee);
    }

    public function testCalculateTDEEMediumActivity(): void
    {
        $metrics = new BodyMetrics(
            age: 30,
            height: 175,
            weight: 70,
            gender: Gender::MALE
        );
        
        $bmr = $this->service->calculateBMR($metrics);
        $tdee = $this->service->calculateTDEE($bmr, ActivityLevel::MEDIUM);
        $this->assertEquals(2554, $tdee);
    }

    public function testCalculateTDEEHighActivity(): void
    {
        $metrics = new BodyMetrics(
            age: 30,
            height: 175,
            weight: 70,
            gender: Gender::MALE
        );
        
        $bmr = $this->service->calculateBMR($metrics);
        $tdee = $this->service->calculateTDEE($bmr, ActivityLevel::HIGH);
        $this->assertEquals(2842, $tdee);
    }

    public function testCalculateTDEEExtraActivity(): void
    {
        $metrics = new BodyMetrics(
            age: 30,
            height: 175,
            weight: 70,
            gender: Gender::MALE
        );
        
        $bmr = $this->service->calculateBMR($metrics);
        $tdee = $this->service->calculateTDEE($bmr, ActivityLevel::EXTRA);
        $this->assertEquals(3131, $tdee);
    }

    public function testCalculateDailyCaloriesDeficit(): void
    {
        $tdee = 2000;
        $result = $this->service->calculateDailyCalories($tdee, Goal::DEFICIT);
        $this->assertEquals(1600, $result);
    }

    public function testCalculateDailyCaloriesMaintenance(): void
    {
        $tdee = 2000;
        $result = $this->service->calculateDailyCalories($tdee, Goal::MAINTENANCE);
        $this->assertEquals(2000, $result);
    }

    public function testCalculateDailyCaloriesSurplus(): void
    {
        $tdee = 2000;
        $result = $this->service->calculateDailyCalories($tdee, Goal::SURPLUS);
        $this->assertEquals(2300, $result);
    }

    public function testFullCalculationMale(): void
    {
        $metrics = new BodyMetrics(
            age: 30,
            height: 175,
            weight: 70,
            gender: Gender::MALE
        );
        
        $result = $this->service->calculate($metrics, ActivityLevel::MEDIUM, Goal::MAINTENANCE);
        $this->assertEquals(2554, $result);
    }

    public function testFullCalculationFemale(): void
    {
        $metrics = new BodyMetrics(
            age: 25,
            height: 165,
            weight: 60,
            gender: Gender::FEMALE
        );
        
        $result = $this->service->calculate($metrics, ActivityLevel::LOW, Goal::DEFICIT);
        $this->assertEquals(1479, $result);
    }

    public function testActivityLevelFromValue(): void
    {
        $this->assertEquals(ActivityLevel::MINIMAL, ActivityLevel::fromValue('minimal'));
        $this->assertEquals(ActivityLevel::MINIMAL, ActivityLevel::fromValue('1.2'));
        $this->assertEquals(ActivityLevel::MEDIUM, ActivityLevel::fromValue('medium'));
        $this->assertEquals(ActivityLevel::MEDIUM, ActivityLevel::fromValue('1.55'));
    }

    public function testGoalFromValue(): void
    {
        $this->assertEquals(Goal::DEFICIT, Goal::fromValue('deficit'));
        $this->assertEquals(Goal::DEFICIT, Goal::fromValue('0.8'));
        $this->assertEquals(Goal::MAINTENANCE, Goal::fromValue('maintenance'));
        $this->assertEquals(Goal::MAINTENANCE, Goal::fromValue('1.0'));
    }

    public function testApplyProcessingCoefficientExtendedOptions(): void
    {
        $service = new NutritionCalculatorService();

        $this->assertEqualsWithDelta(100.0, $service->applyProcessingCoefficient(100, ''), 0.001);
        $this->assertEqualsWithDelta(140.0, $service->applyProcessingCoefficient(100, 'fry'), 0.001);
        $this->assertEqualsWithDelta(130.0, $service->applyProcessingCoefficient(100, 'bake'), 0.001);
        $this->assertEqualsWithDelta(120.0, $service->applyProcessingCoefficient(100, 'boil'), 0.001);
        $this->assertEqualsWithDelta(110.0, $service->applyProcessingCoefficient(100, 'stew'), 0.001);
        $this->assertEqualsWithDelta(125.0, $service->applyProcessingCoefficient(100, 'grill'), 0.001);
        $this->assertEqualsWithDelta(105.0, $service->applyProcessingCoefficient(100, 'steam'), 0.001);
        $this->assertEqualsWithDelta(160.0, $service->applyProcessingCoefficient(100, 'deep_fry'), 0.001);
        $this->assertEqualsWithDelta(115.0, $service->applyProcessingCoefficient(100, 'no_oil_fry'), 0.001);
    }

    public function testProcessingOptionsExposeFrontendShape(): void
    {
        $service = new NutritionCalculatorService();

        $this->assertSame(
            ['value' => '', 'label' => 'Не указано - КБЖУ готового продукта', 'coefficient' => 1.0],
            $service->getProcessingOptions()[0]
        );
    }
}
