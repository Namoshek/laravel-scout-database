<?php

namespace Namoshek\Scout\Database;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\ServiceProvider;
use Laravel\Scout\EngineManager;
use Namoshek\Scout\Database\Contracts\Stemmer;
use Namoshek\Scout\Database\Contracts\Tokenizer;
use Namoshek\Scout\Database\Support\DatabaseHelper;

/**
 * Registers and boots services of the Laravel Scout Database package.
 *
 * @package Namoshek\Scout\Database
 */
class ScoutDatabaseServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/scout-database.php', 'scout-database');

        $this->app->bind(Tokenizer::class, function (Application $app) {
            /** @var ConfigRepository $config */
            $config = $app->make('config');

            $tokenizer = $config->get('scout-database.tokenizer');

            return new $tokenizer();
        });

        $this->app->bind(Stemmer::class, function (Application $app) {
            /** @var ConfigRepository $config */
            $config = $app->make('config');

            $stemmer = $config->get('scout-database.stemmer');

            return new $stemmer();
        });

        $this->app->bind(DatabaseHelper::class, function (Application $app) {
            /** @var ConfigRepository $config */
            $config = $app->make('config');

            return new DatabaseHelper($config->get('scout-database.table_prefix'));
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            if (!function_exists('config_path') || !function_exists('database_path')) {
                throw new \Exception('config_path() and/or database_path() function not found. Is the Laravel framework installed?');
            }

            $this->publishes([
                __DIR__.'/../config/scout-database.php' => config_path('scout-database.php'),
            ], 'config');

            $this->publishes([
                __DIR__.'/../migrations/create_scout_database_words_table.php' =>
                    database_path('migrations/'.date('Y_m_d_His', time()).'_create_scout_database_words_table.php'),
                __DIR__.'/../migrations/create_scout_database_documents_table.php' =>
                    database_path('migrations/'.date('Y_m_d_His', time()).'_create_scout_database_documents_table.php'),
            ], 'migrations');
        }

        $this->app[EngineManager::class]->extend('database', function (Application $app) {
            /** @var ConfigRepository $config */
            $config = $app->make('config');

            $connection = $app->make(DatabaseManager::class)->connection($config->get('scout-database.connection'));

            $tokenizer      = $app->make(Tokenizer::class);
            $stemmer        = $app->make(Stemmer::class);
            $databaseHelper = $app->make(DatabaseHelper::class);

            $searchConfiguration = new SearchConfiguration(
                $config->get('scout-database.search.inverse_document_frequency_weight', 1),
                $config->get('scout-database.search.term_frequency_weight', 1),
                $config->get('scout-database.search.term_deviation_weight', 1),
                $config->get('scout-database.search.wildcard_last_token', true)
            );

            $indexer = new DatabaseIndexer($connection, $tokenizer, $stemmer, $databaseHelper);
            $seeker = new DatabaseSeeker($connection, $tokenizer, $stemmer, $databaseHelper, $searchConfiguration);

            return new DatabaseEngine($indexer, $seeker);
        });
    }
}
