<?php

use App\Models\Department;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Validates the two empirical claims import_concurrency_strategy.md rests
 * on, against a REAL MySQL server — two genuine, independently-committed
 * connections/transactions, not the SQLite in-memory connection the rest
 * of the suite runs on (which has no InnoDB snapshot/row-locking behavior
 * to reproduce this with). PHP itself is single-threaded, so "concurrent"
 * here means two real MySQL sessions with statements interleaved by hand
 * in a fixed order — deterministic, not timing-dependent, while still
 * exercising real InnoDB REPEATABLE READ and locking.
 *
 * Needs a MySQL server reachable at the host/port/credentials from
 * config('database.connections.mysql'); skips itself otherwise so the
 * default `php artisan test` run (SQLite) and any CI without MySQL stay
 * green. Runs against a dedicated database on that server — never the
 * app's real database — recreated fresh on every run.
 */
const IMPORT_CONCURRENCY_TEST_DATABASE = 'import_concurrency_test';

function importConcurrencyConnectionConfig(): array
{
    return array_merge(config('database.connections.mysql'), [
        'database' => IMPORT_CONCURRENCY_TEST_DATABASE,
    ]);
}

beforeEach(function () {
    try {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s', config('database.connections.mysql.host'), config('database.connections.mysql.port')),
            config('database.connections.mysql.username'),
            config('database.connections.mysql.password'),
        );
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `'.IMPORT_CONCURRENCY_TEST_DATABASE.'`');
    } catch (PDOException $e) {
        $this->markTestSkipped('Real MySQL not reachable, skipping: '.$e->getMessage());
    }

    config([
        'database.connections.concurrency_a' => importConcurrencyConnectionConfig(),
        'database.connections.concurrency_b' => importConcurrencyConnectionConfig(),
    ]);
    DB::purge('concurrency_a');
    DB::purge('concurrency_b');

    Artisan::call('migrate:fresh', ['--database' => 'concurrency_a', '--force' => true]);
});

afterEach(function () {
    DB::disconnect('concurrency_a');
    DB::disconnect('concurrency_b');
});

test('concurrentUpsertForBrandNewSlug_neverDuplicatesRows_lastCommitWins', function () {
    $connA = DB::connection('concurrency_a');
    $connB = DB::connection('concurrency_b');

    $connA->beginTransaction();
    $connB->beginTransaction();

    // Both transactions read before either commits — the real-MySQL version
    // of the stale-snapshot race in import_concurrency_strategy.md: both
    // legitimately see the slug as absent under REPEATABLE READ, exactly
    // like findExistingByKeys() would for two concurrent imports.
    expect(Department::on('concurrency_a')->where('slug', 'race-dept')->exists())->toBeFalse()
        ->and(Department::on('concurrency_b')->where('slug', 'race-dept')->exists())->toBeFalse();

    Department::on('concurrency_a')->upsert(
        [['slug' => 'race-dept', 'name' => 'From A', 'description' => null, 'status' => 'active', 'deleted_at' => null]],
        ['slug'],
        ['name', 'description', 'status', 'deleted_at']
    );
    $connA->commit();

    // B's upsert runs after A's commit. Writes always use a current read for
    // conflict detection (never the transaction's old snapshot), so despite
    // B's own SELECT having seen "no row", this correctly resolves as an
    // UPDATE rather than a second, constraint-violating INSERT.
    Department::on('concurrency_b')->upsert(
        [['slug' => 'race-dept', 'name' => 'From B', 'description' => null, 'status' => 'active', 'deleted_at' => null]],
        ['slug'],
        ['name', 'description', 'status', 'deleted_at']
    );
    $connB->commit();

    expect(Department::on('concurrency_a')->where('slug', 'race-dept')->count())->toBe(1)
        ->and(Department::on('concurrency_a')->where('slug', 'race-dept')->value('name'))->toBe('From B');
});

test('lockWaitTimeoutDuringUpsert_rollsBackCleanly_andRetrySucceedsWithCorrectData', function () {
    Department::on('concurrency_a')->create([
        'slug' => 'locked-dept', 'name' => 'Original', 'status' => 'active',
    ]);

    $connA = DB::connection('concurrency_a');
    $connB = DB::connection('concurrency_b');

    // Bound B's wait so the test fails fast instead of hanging for MySQL's
    // default 50s lock_wait_timeout.
    $connB->statement('SET SESSION innodb_lock_wait_timeout = 1');

    $connA->beginTransaction();
    $connB->beginTransaction();

    // No lockForUpdate() anywhere here — production code never calls it.
    // A's own upsert() (the exact call bulkUpsert() makes) is itself a
    // locking write: InnoDB holds an exclusive lock on any row it touches
    // until the transaction commits or rolls back. A hasn't committed yet
    // here, standing in for a chunk transaction still mid-flight (it still
    // has ImportError::insert()/incrementEach() ahead of it before it can
    // commit) when another writer reaches the same row.
    Department::on('concurrency_a')->upsert(
        [['slug' => 'locked-dept', 'name' => 'From A', 'description' => null, 'status' => 'active', 'deleted_at' => null]],
        ['slug'],
        ['name', 'description', 'status', 'deleted_at']
    );

    // B's upsert needs that same row and can't get it in time: a genuine
    // MySQL error (1205, "Lock wait timeout exceeded"), the exact class of
    // failure ImportChunkJob's $tries/$backoff exist to recover from.
    try {
        Department::on('concurrency_b')->upsert(
            [['slug' => 'locked-dept', 'name' => 'From B (attempt 1)', 'description' => null, 'status' => 'active', 'deleted_at' => null]],
            ['slug'],
            ['name', 'description', 'status', 'deleted_at']
        );
        $this->fail('Expected a lock wait timeout QueryException.');
    } catch (QueryException $e) {
        expect($e->getMessage())->toContain('Lock wait timeout');
    }

    // Mirrors what DB::transaction() does automatically in processChunk(): a
    // failed statement rolls the whole attempt back — nothing partial left
    // on B's side. A was never affected by B's failure and commits normally.
    $connB->rollBack();
    $connA->commit();

    expect(Department::on('concurrency_a')->where('slug', 'locked-dept')->value('name'))->toBe('From A');

    // Retry — A's lock is gone, exactly like the queue redelivering the job
    // after backoff. Must now succeed cleanly with no duplicate row.
    Department::on('concurrency_b')->upsert(
        [['slug' => 'locked-dept', 'name' => 'From B (attempt 2)', 'description' => null, 'status' => 'active', 'deleted_at' => null]],
        ['slug'],
        ['name', 'description', 'status', 'deleted_at']
    );

    expect(Department::on('concurrency_a')->where('slug', 'locked-dept')->count())->toBe(1)
        ->and(Department::on('concurrency_a')->where('slug', 'locked-dept')->value('name'))->toBe('From B (attempt 2)');
});
