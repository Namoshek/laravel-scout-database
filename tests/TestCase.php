<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */
/** @noinspection PhpMissingParamTypeInspection */

declare(strict_types=1);

namespace Namoshek\Scout\Database\Tests;

/**
 * Base for all unit tests.
 *
 * @package Namoshek\Scout\Database\Tests
 */
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
            \Laravel\Scout\ScoutServiceProvider::class,
            \Namoshek\Scout\Database\ScoutDatabaseServiceProvider::class,
            \Staudenmeir\LaravelCte\DatabaseServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // The full path to the file used as sqlite database.
        $sqliteDatabaseFile = __DIR__.'/test.sqlite.db';

        // Ensure an sqlite database file exists.
        touch($sqliteDatabaseFile);

        // Setup configuration for different types of supported databases.
        $app['config']->set('database.connections.sqlite_inmemory', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
        $app['config']->set('database.connections.sqlite_file', [
            'driver' => 'sqlite',
            'database' => $sqliteDatabaseFile,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $app['config']->set('database.connections.sqlsrv', [
            'driver' => 'sqlsrv',
            'host' => env('DB_SQLSRV_HOST', '127.0.0.1'),
            'port' => env('DB_SQLSRV_PORT', '1433'),
            'database' => env('DB_SQLSRV_DATABASE'),
            'username' => env('DB_SQLSRV_USERNAME'),
            'password' => env('DB_SQLSRV_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
        ]);
        $app['config']->set('database.connections.mysql', [
            'driver' => 'mysql',
            'host' => env('DB_MYSQL_HOST', '127.0.0.1'),
            'port' => env('DB_MYSQL_PORT', '3306'),
            'database' => env('DB_MYSQL_DATABASE'),
            'username' => env('DB_MYSQL_USERNAME'),
            'password' => env('DB_MYSQL_PASSWORD'),
            'prefix' => '',
        ]);
        $app['config']->set('database.connections.pgsql', [
            'driver' => 'pgsql',
            'host' => env('DB_PGSQL_HOST', '127.0.0.1'),
            'port' => env('DB_PGSQL_PORT', '5432'),
            'database' => env('DB_PGSQL_DATABASE'),
            'username' => env('DB_PGSQL_USERNAME'),
            'password' => env('DB_PGSQL_PASSWORD'),
            'prefix' => '',
        ]);

        // We use the database scout driver as default.
        $app['config']->set('scout.driver', 'database');
    }

    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadLaravelMigrations();
        $this->loadMigrationsFrom(__DIR__ . '/../migrations');
    }
}
