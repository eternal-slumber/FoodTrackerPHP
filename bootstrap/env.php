<?php

declare(strict_types=1);

use Dotenv\Dotenv;

require_once dirname(__DIR__) . '/vendor/autoload.php';

Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
