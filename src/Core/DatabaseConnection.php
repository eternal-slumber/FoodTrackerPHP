<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use Throwable;

class DatabaseConnection
{
    public function get(): PDO
    {
        return Database::getConnection();
    }

    public function isAvailable(): bool
    {
        try {
            $this->get()->query('SELECT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
