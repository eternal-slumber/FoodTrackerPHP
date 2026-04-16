<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Validators\UserValidator;
use App\Exceptions\ValidationException;
use App\Enums\ActivityLevel;
use App\Enums\Goal;
use App\Enums\Gender;
use App\ValueObjects\BodyMetrics;

class UserValidatorTest extends TestCase
{
    public function testValidateRegistrationValidData(): void
    {
        $data = [
            'tg_id' => '123456789',
            'age' => '30',
            'height' => '175',
            'weight' => '70.5',
            'gender' => 'male',
            'activity_level' => 'medium',
            'goal' => 'maintenance'
        ];

        $result = UserValidator::validateRegistration($data);

        $this->assertEquals(123456789, $result['tg_id']);
        $this->assertEquals(30, $result['age']);
        $this->assertEquals(175, $result['height']);
        $this->assertEquals(70.5, $result['weight']);
        $this->assertEquals('male', $result['gender']);
        $this->assertEquals(ActivityLevel::MEDIUM->value, $result['activity_level']);
        $this->assertEquals(Goal::MAINTENANCE->value, $result['goal']);
    }

    public function testValidateRegistrationWithFloatValues(): void
    {
        $data = [
            'tg_id' => '123456789',
            'age' => '30',
            'height' => '175',
            'weight' => '70',
            'gender' => 'male',
            'activity_level' => '1.55',
            'goal' => '1.0'
        ];

        $result = UserValidator::validateRegistration($data);

        $this->assertEquals(ActivityLevel::MEDIUM->value, $result['activity_level']);
        $this->assertEquals(Goal::MAINTENANCE->value, $result['goal']);
    }

    public function testValidateRegistrationDefaults(): void
    {
        $data = [
            'tg_id' => '123456789',
            'age' => '30',
            'height' => '175',
            'weight' => '70',
            'gender' => 'male'
        ];

        $result = UserValidator::validateRegistration($data);

        $this->assertEquals(ActivityLevel::MEDIUM->value, $result['activity_level']);
        $this->assertEquals(Goal::MAINTENANCE->value, $result['goal']);
    }

    public function testValidateRegistrationThrowsOnMissingTgId(): void
    {
        $this->expectException(ValidationException::class);

        UserValidator::validateRegistration([
            'age' => '30',
            'height' => '175',
            'weight' => '70',
            'gender' => 'male'
        ]);
    }

    public function testValidateRegistrationThrowsOnInvalidAge(): void
    {
        $this->expectException(ValidationException::class);

        UserValidator::validateRegistration([
            'tg_id' => '123456789',
            'age' => '5',
            'height' => '175',
            'weight' => '70',
            'gender' => 'male'
        ]);
    }

    public function testValidateRegistrationThrowsOnMissingAge(): void
    {
        $this->expectException(ValidationException::class);

        UserValidator::validateRegistration([
            'tg_id' => '123456789',
            'height' => '175',
            'weight' => '70',
            'gender' => 'male'
        ]);
    }

    public function testValidateRegistrationThrowsOnInvalidHeight(): void
    {
        $this->expectException(ValidationException::class);

        UserValidator::validateRegistration([
            'tg_id' => '123456789',
            'age' => '30',
            'height' => '50',
            'weight' => '70',
            'gender' => 'male'
        ]);
    }

    public function testValidateRegistrationThrowsOnInvalidWeight(): void
    {
        $this->expectException(ValidationException::class);

        UserValidator::validateRegistration([
            'tg_id' => '123456789',
            'age' => '30',
            'height' => '175',
            'weight' => '20',
            'gender' => 'male'
        ]);
    }

    public function testValidateRegistrationThrowsOnInvalidGender(): void
    {
        $this->expectException(ValidationException::class);

        UserValidator::validateRegistration([
            'tg_id' => '123456789',
            'age' => '30',
            'height' => '175',
            'weight' => '70',
            'gender' => 'other'
        ]);
    }

    public function testValidateRegistrationWithInvalidActivityLevel(): void
    {
        $data = [
            'tg_id' => '123456789',
            'age' => '30',
            'height' => '175',
            'weight' => '70',
            'gender' => 'male',
            'activity_level' => 'invalid_value'
        ];

        $result = UserValidator::validateRegistration($data);
        
        $this->assertEquals(ActivityLevel::MEDIUM->value, $result['activity_level']);
    }

    public function testValidateRegistrationWithInvalidGoal(): void
    {
        $data = [
            'tg_id' => '123456789',
            'age' => '30',
            'height' => '175',
            'weight' => '70',
            'gender' => 'male',
            'goal' => 'invalid_value'
        ];

        $result = UserValidator::validateRegistration($data);
        
        $this->assertEquals(Goal::MAINTENANCE->value, $result['goal']);
    }

    public function testGenderFromValue(): void
    {
        $this->assertEquals(Gender::MALE, Gender::fromValue('male'));
        $this->assertEquals(Gender::MALE, Gender::fromValue('m'));
        $this->assertEquals(Gender::FEMALE, Gender::fromValue('female'));
        $this->assertEquals(Gender::FEMALE, Gender::fromValue('f'));
    }

    public function testBodyMetricsValid(): void
    {
        $metrics = new BodyMetrics(
            age: 30,
            height: 175,
            weight: 70,
            gender: Gender::MALE
        );
        
        $this->assertEquals(30, $metrics->age);
        $this->assertEquals(175, $metrics->height);
        $this->assertEquals(70, $metrics->weight);
        $this->assertEquals(Gender::MALE, $metrics->gender);
    }

    public function testBodyMetricsThrowsOnInvalidAge(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        new BodyMetrics(
            age: 5,
            height: 175,
            weight: 70,
            gender: Gender::MALE
        );
    }

    public function testBodyMetricsThrowsOnInvalidHeight(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        new BodyMetrics(
            age: 30,
            height: 50,
            weight: 70,
            gender: Gender::MALE
        );
    }

    public function testBodyMetricsThrowsOnInvalidWeight(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        new BodyMetrics(
            age: 30,
            height: 175,
            weight: 20,
            gender: Gender::MALE
        );
    }
}