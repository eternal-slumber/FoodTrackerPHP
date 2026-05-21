<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\Goal;
use App\Services\MacroGoalCalculationService;
use PHPUnit\Framework\TestCase;

class MacroGoalCalculationServiceTest extends TestCase
{
    public function testCalculateMaintenanceMacroGoals(): void
    {
        $service = new MacroGoalCalculationService();

        $goals = $service->calculate(2000, 70, Goal::MAINTENANCE);

        $this->assertSame(2000, $goals['calories_goal']);
        $this->assertSame(112, $goals['proteins_goal']);
        $this->assertSame(56, $goals['fats_goal']);
        $this->assertSame(262, $goals['carbs_goal']);
    }

    public function testCalculateDoesNotReturnNegativeCarbs(): void
    {
        $service = new MacroGoalCalculationService();

        $goals = $service->calculate(500, 120, Goal::DEFICIT);

        $this->assertSame(0, $goals['carbs_goal']);
    }
}
