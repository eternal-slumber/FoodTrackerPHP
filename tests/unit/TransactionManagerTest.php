<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\TransactionManager;
use PDO;
use PHPUnit\Framework\TestCase;

class TransactionManagerTest extends TestCase
{
    private PDO $db;
    private TransactionManager $transactions;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->exec('CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $this->transactions = new TransactionManager($this->db);
    }

    public function testCommitsAndReturnsOperationResult(): void
    {
        $result = $this->transactions->transactional(function (): string {
            $this->db->exec("INSERT INTO items (name) VALUES ('saved')");

            return 'result';
        });

        $this->assertSame('result', $result);
        $this->assertSame(1, $this->countItems());
        $this->assertFalse($this->db->inTransaction());
    }

    public function testRollsBackAndRethrowsException(): void
    {
        try {
            $this->transactions->transactional(function (): void {
                $this->db->exec("INSERT INTO items (name) VALUES ('rolled back')");
                if ($this->db->inTransaction()) {
                    throw new \RuntimeException('Operation failed');
                }
            });

            $this->fail('Expected operation exception was not thrown');
        } catch (\RuntimeException $error) {
            $this->assertSame('Operation failed', $error->getMessage());
        }

        $this->assertSame(0, $this->countItems());
        $this->assertFalse($this->db->inTransaction());
    }

    public function testRollsBackOnError(): void
    {
        try {
            $this->transactions->transactional(function (): void {
                $this->db->exec("INSERT INTO items (name) VALUES ('rolled back')");
                if ($this->db->inTransaction()) {
                    throw new \TypeError('Type failure');
                }
            });

            $this->fail('Expected type error was not thrown');
        } catch (\TypeError $error) {
            $this->assertSame('Type failure', $error->getMessage());
        }

        $this->assertSame(0, $this->countItems());
        $this->assertFalse($this->db->inTransaction());
    }

    private function countItems(): int
    {
        $statement = $this->db->query('SELECT COUNT(*) FROM items');
        if ($statement === false) {
            throw new \RuntimeException('Could not count test items');
        }

        return (int)$statement->fetchColumn();
    }
}
