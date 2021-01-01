<?php

/** @noinspection PhpUndefinedMethodInspection */

declare(strict_types=1);

namespace Namoshek\Scout\Database\Tests;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Namoshek\Scout\Database\DatabaseIndexer;
use Namoshek\Scout\Database\Stemmer\NullStemmer;
use Namoshek\Scout\Database\Tests\Stubs\Animal;
use Namoshek\Scout\Database\Tests\Stubs\User;

/**
 * Tests for the {@see DatabaseIndexer} class.
 *
 * @package Namoshek\Scout\Database\Tests
 */
class DatabaseIndexerTest extends TestCase
{
    use DatabaseMigrations;

    protected function insertCommonTestDataInDatabase(): void
    {
        /** @var \Illuminate\Database\ConnectionInterface $connection */
        $connection = $this->app->make('db');

        $connection->table('scout_index')->insert([
            ['document_type' => 'user', 'document_id' => 1, 'term' => 'abc', 'length' => 3, 'num_hits' => 1],
            ['document_type' => 'user', 'document_id' => 2, 'term' => 'abc', 'length' => 3, 'num_hits' => 4],
            ['document_type' => 'user', 'document_id' => 1, 'term' => 'def', 'length' => 3, 'num_hits' => 2],
            ['document_type' => 'post', 'document_id' => 1, 'term' => 'ghi', 'length' => 3, 'num_hits' => 1],
            ['document_type' => 'comment', 'document_id' => 1, 'term' => 'jkl', 'length' => 3, 'num_hits' => 4],
            ['document_type' => 'comment', 'document_id' => 2, 'term' => 'jkl', 'length' => 3, 'num_hits' => 3],
        ]);

        $connection->table('users')->insert([
            ['name' => 'Max Mustermann', 'email' => 'max.mustermann@example.com', 'password' => '123456', 'remember_token' => now()],
            ['name' => 'Mia Musterfrau', 'email' => 'mia.musterfrau@example.com', 'password' => '123456', 'remember_token' => now()],
        ]);
    }

    public function test_removing_all_entities_of_model_from_search_does_not_affect_other_models(): void
    {
        $this->insertCommonTestDataInDatabase();

        User::removeAllFromSearch();

        $this->assertDatabaseCount('scout_index', 3);
        $this->assertDatabaseHas('scout_index', [
            'document_type' => 'post',
            'document_id' => 1,
            'term' => 'ghi',
            'length' => 3,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'document_type' => 'comment',
            'document_id' => 1,
            'term' => 'jkl',
            'length' => 3,
            'num_hits' => 4,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'document_type' => 'comment',
            'document_id' => 2,
            'term' => 'jkl',
            'length' => 3,
            'num_hits' => 3,
        ]);
    }

    public function test_making_model_unsearchable_performs_correct_database_updates(): void
    {
        $this->insertCommonTestDataInDatabase();

        $user = new User(['id' => 1]);
        $user->unsearchable();

        $this->assertDatabaseCount('scout_index', 4);
        $this->assertDatabaseHas('scout_index', [
            'document_type' => 'user',
            'document_id' => 2,
            'term' => 'abc',
            'length' => 3,
            'num_hits' => 4,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'document_type' => 'post',
            'document_id' => 1,
            'term' => 'ghi',
            'length' => 3,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'document_type' => 'comment',
            'document_id' => 1,
            'term' => 'jkl',
            'length' => 3,
            'num_hits' => 4,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'document_type' => 'comment',
            'document_id' => 2,
            'term' => 'jkl',
            'length' => 3,
            'num_hits' => 3,
        ]);
    }

    public function test_making_new_model_searchable_adds_correct_data_to_index(): void
    {
        config(['scout-database.stemmer' => NullStemmer::class]);

        $this->assertDatabaseCount('scout_index', 0);

        $user = new User(['id' => 1, 'first_name' => 'Foo', 'last_name' => 'Bar', 'email' => 'Baz']);
        $user->searchable();

        $this->assertDatabaseCount('scout_index', 4);
        $this->assertDatabaseHas('scout_index', [
            'document_type' => 'user',
            'document_id' => 1,
            'term' => '1',
            'length' => 1,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'document_type' => 'user',
            'document_id' => 1,
            'term' => 'foo',
            'length' => 3,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'document_type' => 'user',
            'document_id' => 1,
            'term' => 'bar',
            'length' => 3,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'document_type' => 'user',
            'document_id' => 1,
            'term' => 'baz',
            'length' => 3,
            'num_hits' => 1,
        ]);
    }

    public function test_making_existing_model_searchable_updates_data_in_index_correctly(): void
    {
        config(['scout-database.stemmer' => NullStemmer::class]);

        $this->insertCommonTestDataInDatabase();

        $this->assertDatabaseCount('scout_index', 6);

        $user = new User(['id' => 1, 'first_name' => 'Foo', 'last_name' => 'Bar', 'email' => 'Baz']);
        $user->searchable();

        $this->assertDatabaseCount('scout_index', 8);
        $this->assertDatabaseHas('scout_index', [
            'document_type' => 'user',
            'document_id' => 1,
            'term' => '1',
            'length' => 1,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'document_type' => 'user',
            'document_id' => 1,
            'term' => 'foo',
            'length' => 3,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'document_type' => 'user',
            'document_id' => 1,
            'term' => 'bar',
            'length' => 3,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'document_type' => 'user',
            'document_id' => 1,
            'term' => 'baz',
            'length' => 3,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'document_type' => 'user',
            'document_id' => 2,
            'term' => 'abc',
            'length' => 3,
            'num_hits' => 4,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'document_type' => 'post',
            'document_id' => 1,
            'term' => 'ghi',
            'length' => 3,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'document_type' => 'comment',
            'document_id' => 1,
            'term' => 'jkl',
            'length' => 3,
            'num_hits' => 4,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'document_type' => 'comment',
            'document_id' => 2,
            'term' => 'jkl',
            'length' => 3,
            'num_hits' => 3,
        ]);
    }

    public function test_making_model_searchable_adds_terms_with_correct_hit_count_to_database(): void
    {
        config(['scout-database.stemmer' => NullStemmer::class]);

        $this->assertDatabaseCount('scout_index', 0);

        $user = new User(['id' => 1, 'first_name' => 'Foo Bar', 'last_name' => 'Foo bar', 'email' => 'Foo baz']);
        $user->searchable();

        $this->assertDatabaseCount('scout_index', 4);
        $this->assertDatabaseHas('scout_index', [
            'document_type' => 'user',
            'document_id' => 1,
            'term' => '1',
            'length' => 1,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'document_type' => 'user',
            'document_id' => 1,
            'term' => 'foo',
            'length' => 3,
            'num_hits' => 3,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'document_type' => 'user',
            'document_id' => 1,
            'term' => 'bar',
            'length' => 3,
            'num_hits' => 2,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'document_type' => 'user',
            'document_id' => 1,
            'term' => 'baz',
            'length' => 3,
            'num_hits' => 1,
        ]);
    }

    public function test_making_different_models_searchable_performs_correct_database_updates(): void
    {
        config(['scout-database.stemmer' => NullStemmer::class]);

        $this->assertDatabaseCount('scout_index', 0);

        $user   = new User(['id' => 1, 'first_name' => 'Foo']);
        $animal = new Animal(['id' => 1, 'name' => 'Doggo']);

        $models = new Collection([$user, $animal]);
        $models->searchable();

        $this->assertDatabaseCount('scout_index', 4);
        $this->assertDatabaseHas('scout_index', [
            'document_type' => 'user',
            'document_id' => 1,
            'term' => '1',
            'length' => 1,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'document_type' => 'user',
            'document_id' => 1,
            'term' => 'foo',
            'length' => 3,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'document_type' => 'animal',
            'document_id' => 1,
            'term' => '1',
            'length' => 1,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_index', [
            'document_type' => 'animal',
            'document_id' => 1,
            'term' => 'doggo',
            'length' => 5,
            'num_hits' => 1,
        ]);
    }
}
