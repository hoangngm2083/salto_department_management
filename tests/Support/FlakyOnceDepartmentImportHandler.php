<?php

namespace Tests\Support;

use App\Services\Import\Handlers\DepartmentImportHandler;
use Illuminate\Database\QueryException;

/**
 * Throws on its first bulkUpsert() call — standing in for a real
 * unique-constraint violation or lock-wait timeout from a concurrent writer
 * touching the same slugs — then behaves normally after. The test suite's
 * SQLite connection can't reproduce an actual InnoDB deadlock (no real
 * row-level locking, single in-process database), so this is the closest
 * realistic substitute: it exercises the same code path (an exception
 * escaping bulkUpsert mid-transaction) that a genuine race would trigger
 * against MySQL.
 */
class FlakyOnceDepartmentImportHandler extends DepartmentImportHandler
{
    public static bool $shouldThrow = false;

    protected function bulkUpsert(array $rows): void
    {
        if (self::$shouldThrow) {
            self::$shouldThrow = false;

            throw new QueryException(
                'sqlite',
                'insert into "departments" ("slug", ...) values (...)',
                [],
                new \Exception('SQLSTATE[23000]: UNIQUE constraint failed: departments.slug')
            );
        }

        parent::bulkUpsert($rows);
    }
}
