<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use Throwable;

class TransactionManager
{
    public function __construct(private readonly PDO $db) {}

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function transactional(callable $operation): mixed
    {
        $this->db->beginTransaction();

        try {
            $result = $operation();
            $this->db->commit();

            return $result;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $error;
        }
    }
}
