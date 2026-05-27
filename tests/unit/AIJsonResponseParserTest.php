<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\AI\AIJsonResponseParser;
use PHPUnit\Framework\TestCase;

class AIJsonResponseParserTest extends TestCase
{
    public function testParsesPlainJsonObject(): void
    {
        $parsed = (new AIJsonResponseParser())->parseObject('{"food":"Омлет","kcal":250}');

        $this->assertSame('Омлет', $parsed['food']);
        $this->assertSame(250, $parsed['kcal']);
    }

    public function testParsesJsonObjectInsideMarkdownFence(): void
    {
        $parsed = (new AIJsonResponseParser())->parseObject("```json\n{\"calories\":120}\n```");

        $this->assertSame(120, $parsed['calories']);
    }

    public function testReturnsNullForInvalidResponse(): void
    {
        $this->assertNull((new AIJsonResponseParser())->parseObject('not json'));
    }
}
