<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Runs code on a second PostgreSQL connection to the same test database,
 * so interleavings between two buyers can be simulated in one PHP process:
 * sequential calls on two connections, or one connection holding an open
 * transaction while the other one tries to act.
 *
 * Requires DatabaseMigrations (committed data); RefreshDatabase wraps each
 * test in a transaction a second connection cannot see.
 */
trait InteractsWithTwoPostgresConnections
{
    private const SECONDARY_CONNECTION = 'pgsql_secondary';

    private string $originalDefaultConnection = 'pgsql';

    private bool $secondaryHeld = false;

    /** Skip the test when the suite is not running on PostgreSQL. */
    protected function skipUnlessPostgres(): void
    {
        if (env('DB_CONNECTION') !== 'pgsql') {
            $this->markTestSkipped('This test needs a PostgreSQL database.');
        }
    }

    /**
     * Register the safety rollback BEFORE parent::setUp(): callbacks run in
     * registration order, and DatabaseMigrations registers migrate:rollback
     * during parent::setUp — a held secondary transaction must be rolled back
     * first or DROP TABLE would hang forever when a test fails early.
     */
    protected function registerSecondaryCleanup(): void
    {
        $this->beforeApplicationDestroyed(function (): void {
            $this->rollbackSecondary();

            if ($this->app) {
                DB::setDefaultConnection($this->originalDefaultConnection);
                DB::purge(self::SECONDARY_CONNECTION);
            }
        });
    }

    /** Register the second connection config. Call after parent::setUp(). */
    protected function twoConnectionsSetUp(): void
    {
        $this->originalDefaultConnection = (string) config('database.default');

        config([
            'database.connections.'.self::SECONDARY_CONNECTION => config('database.connections.'.$this->originalDefaultConnection),
        ]);
    }

    /**
     * Run $callback with the default connection switched to the second
     * connection (Eloquent and DB::transaction included), then switch back.
     */
    protected function onSecondary(callable $callback): mixed
    {
        $original = (string) config('database.default');
        DB::setDefaultConnection(self::SECONDARY_CONNECTION);

        try {
            return $callback();
        } finally {
            DB::setDefaultConnection($original);
        }
    }

    /**
     * Begin a real transaction on the second connection and run $callback
     * inside it without committing: the service's own DB::transaction calls
     * become savepoints, so locks and uncommitted rows stay held afterwards.
     * The default connection is switched back; use commitSecondary() or
     * rollbackSecondary() to finish.
     */
    protected function holdSecondary(callable $callback): void
    {
        $original = (string) config('database.default');
        DB::setDefaultConnection(self::SECONDARY_CONNECTION);

        try {
            DB::connection()->beginTransaction();
            $this->secondaryHeld = true;

            $callback();
        } catch (Throwable $e) {
            $this->rollbackSecondary();

            throw $e;
        } finally {
            DB::setDefaultConnection($original);
        }
    }

    protected function commitSecondary(): void
    {
        if (! $this->secondaryHeld) {
            $this->fail('No held secondary transaction to commit.');
        }

        DB::connection(self::SECONDARY_CONNECTION)->commit();
        $this->secondaryHeld = false;
    }

    protected function rollbackSecondary(): void
    {
        if (! $this->secondaryHeld) {
            return;
        }

        $connection = DB::connection(self::SECONDARY_CONNECTION);

        while ($connection->transactionLevel() > 0) {
            $connection->rollBack();
        }

        $this->secondaryHeld = false;
    }

    /**
     * Bound how long a connection waits for a row lock, so a test can prove
     * a competing sale is blocked instead of hanging forever. Null disables
     * the timeout again (PostgreSQL default: wait forever).
     */
    protected function setLockTimeout(?int $milliseconds, ?string $connection = null): void
    {
        if ($milliseconds !== null && $milliseconds < 1) {
            throw new \InvalidArgumentException('Lock timeout must be at least 1ms, or null to disable.');
        }

        $statement = $milliseconds === null
            ? 'SET lock_timeout = 0'
            : "SET lock_timeout = '{$milliseconds}ms'";

        DB::connection($connection)->statement($statement);
    }
}
