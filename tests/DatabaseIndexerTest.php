<?php

declare(strict_types=1);

namespace Namoshek\Scout\Database\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Namoshek\Scout\Database\DatabaseIndexer;
use Namoshek\Scout\Database\Stemmer\NullStemmer;
use Namoshek\Scout\Database\Tests\Stubs\User;

/**
 * Tests for the {@see DatabaseIndexer} class.
 *
 * @package Namoshek\Scout\Database\Tests
 */
class DatabaseIndexerTest extends TestCase
{
    use RefreshDatabase;

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
    }

    public function test_delete_entire_model_from_index_does_not_delete_entries_of_other_models(): void
    {
        $this->insertCommonTestDataInDatabase();

        $indexer = $this->app->make(DatabaseIndexer::class);
        $indexer->deleteEntireModelFromIndex(new User());

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

    public function test_delete_from_index_updates_database_entries_correctly(): void
    {
        $this->insertCommonTestDataInDatabase();

        $user  = new User(['id' => 1]);
        $users = collect([$user]);

        $indexer = $this->app->make(DatabaseIndexer::class);
        $indexer->deleteFromIndex($users);

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

    public function test_index_adds_new_data_to_index(): void
    {
        config(['scout-database.stemmer' => NullStemmer::class]);

        $this->assertDatabaseCount('scout_index', 0);

        $user  = new User(['id' => 1, 'first_name' => 'Foo', 'last_name' => 'Bar', 'email' => 'Baz']);
        $users = collect([$user]);

        $indexer = $this->app->make(DatabaseIndexer::class);
        $indexer->index($users);

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

    public function test_index_updates_index_based_on_document_data_correctly(): void
    {
        config(['scout-database.stemmer' => NullStemmer::class]);

        $this->insertCommonTestDataInDatabase();

        $this->assertDatabaseCount('scout_index', 6);

        $user  = new User(['id' => 1, 'first_name' => 'Foo', 'last_name' => 'Bar', 'email' => 'Baz']);
        $users = collect([$user]);

        $indexer = $this->app->make(DatabaseIndexer::class);
        $indexer->index($users);

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
}
