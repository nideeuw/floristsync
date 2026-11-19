<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Guard: refuse to run if the test DB is not a dedicated *_test database.
     * Runs before RefreshDatabase, so a wrong DB is never wiped.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        $connection = config('database.default');
        $database   = (string) config("database.connections.{$connection}.database");

        // *_test, or *_test_1, *_test_2 (suffix used by `php artisan test --parallel`)
        if (! preg_match('/_test(_\d+)?$/', $database)) {
            throw new \RuntimeException(
                "Refusing to run tests: DB_DATABASE '{$database}' must end with '_test'."
            );
        }
    }
}