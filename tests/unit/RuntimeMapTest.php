<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use RuntimeMentalMap\RuntimeMap;

final class RuntimeMapTest extends TestCase
{
    public function testAutomaticSpansUseTheCurrentSpanAsParent(): void
    {
        RuntimeMap::startRequest(
            method: 'GET',
            path: '/test',
            framework: 'slim',
            collectorUrl: 'http://127.0.0.1:1',
        );

        RuntimeMap::enterAutoSpan('controller', 'App\Controllers\TestController', 'index');
        RuntimeMap::enterAutoSpan('application', 'App\Services\TestService', 'run');

        $property = new ReflectionProperty(RuntimeMap::class, 'autoFrames');
        $frames = $property->getValue();

        self::assertSame($frames[0]['span_id'], $frames[1]['parent_id']);

        (new ReflectionMethod(RuntimeMap::class, 'reset'))->invoke(null);
    }
}
