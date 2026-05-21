<?php

declare(strict_types=1);

namespace App\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class RouteAttribute
{
    public function __construct(
        public readonly string $path,
        public readonly string $method = 'GET',
        public readonly ?string $name = null
    ) {}
}
