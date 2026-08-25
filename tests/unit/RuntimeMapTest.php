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
            serviceName: 'foodtracker',
        );

        RuntimeMap::enterAutoSpan('controller', 'App\Controllers\TestController', 'index');
        RuntimeMap::enterAutoSpan('application', 'App\Services\TestService', 'run');

        $property = new ReflectionProperty(RuntimeMap::class, 'autoFrames');
        $frames = $property->getValue();

        self::assertSame($frames[0]['span_id'], $frames[1]['parent_id']);

        RuntimeMap::leaveAutoSpan();
        RuntimeMap::leaveAutoSpan();

        $events = (new ReflectionProperty(RuntimeMap::class, 'events'))->getValue();
        self::assertCount(2, $events);

        foreach ($events as $event) {
            self::assertSame(1, $event['protocol_version']);
            self::assertSame('foodtracker', $event['service_name']);
        }

        (new ReflectionMethod(RuntimeMap::class, 'reset'))->invoke(null);
    }

    public function testExceptionStateDoesNotLeakIntoNextRequest(): void
    {
        RuntimeMap::startRequest('GET', '/broken', 'slim', 'http://127.0.0.1:1');
        $firstTraceId = RuntimeMap::traceId();
        $exception = new RuntimeException('broken');

        RuntimeMap::enterAutoSpan('application', 'App\Services\BrokenService', 'run');
        RuntimeMap::leaveAutoSpan($exception);
        RuntimeMap::recordException($exception);
        RuntimeMap::finishRequest(500);

        RuntimeMap::startRequest('GET', '/healthy', 'slim', 'http://127.0.0.1:1');

        self::assertNotSame($firstTraceId, RuntimeMap::traceId());
        self::assertCount(1, (new ReflectionProperty(RuntimeMap::class, 'stack'))->getValue());
        self::assertSame([], (new ReflectionProperty(RuntimeMap::class, 'autoFrames'))->getValue());
        self::assertSame([], (new ReflectionProperty(RuntimeMap::class, 'events'))->getValue());
        self::assertNull((new ReflectionProperty(RuntimeMap::class, 'requestException'))->getValue());

        (new ReflectionMethod(RuntimeMap::class, 'reset'))->invoke(null);
    }
}
