<?php

declare(strict_types=1);

namespace Namoshek\Scout\Database\Tests;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    /**
     * Returns a list of service providers required for the tests.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return string[]
     */
    protected function getPackageProviders($app): array
    {
        return [
            \Namoshek\Scout\Database\ScoutDatabaseServiceProvider::class,
        ];
    }

    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../migrations');
    }
}
