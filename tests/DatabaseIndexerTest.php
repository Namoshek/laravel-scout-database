<?php

declare(strict_types=1);

namespace Namoshek\Scout\Database\Tests;

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
    protected function insertCommonTestDataInDatabase(): void
    {
        /** @var \Illuminate\Database\ConnectionInterface $connection */
        $connection = $this->app->make('db');

        $connection->table('scout_words')->insert([
            ['document_type' => 'user', 'term' => 'abc', 'num_hits' => 5, 'num_documents' => 2, 'length' => 3],
            ['document_type' => 'user', 'term' => 'def', 'num_hits' => 2, 'num_documents' => 1, 'length' => 3],
            ['document_type' => 'post', 'term' => 'ghi', 'num_hits' => 1, 'num_documents' => 1, 'length' => 3],
            ['document_type' => 'comment', 'term' => 'jkl', 'num_hits' => 7, 'num_documents' => 2, 'length' => 3],
        ]);

        $connection->table('scout_documents')->insert([
            ['document_type' => 'user', 'word_id' => 1, 'document_id' => 1, 'num_hits' => 1],
            ['document_type' => 'user', 'word_id' => 1, 'document_id' => 2, 'num_hits' => 4],
            ['document_type' => 'user', 'word_id' => 2, 'document_id' => 1, 'num_hits' => 2],
            ['document_type' => 'post', 'word_id' => 3, 'document_id' => 1, 'num_hits' => 1],
            ['document_type' => 'comment', 'word_id' => 4, 'document_id' => 1, 'num_hits' => 4],
            ['document_type' => 'comment', 'word_id' => 4, 'document_id' => 2, 'num_hits' => 3],
        ]);
    }

    public function test_delete_words_without_associated_documents_does_not_delete_words_with_associated_documents(): void
    {
        /** @var \Illuminate\Database\ConnectionInterface $connection */
        $connection = $this->app->make('db');

        $connection->table('scout_words')->insert([
            ['document_type' => 'user', 'term' => 'abc', 'num_hits' => 0, 'num_documents' => 1, 'length' => 3],
            ['document_type' => 'user', 'term' => 'def', 'num_hits' => 0, 'num_documents' => 2, 'length' => 3],
            ['document_type' => 'user', 'term' => 'ghi', 'num_hits' => 0, 'num_documents' => 0, 'length' => 3],
            ['document_type' => 'user', 'term' => 'jkl', 'num_hits' => 1, 'num_documents' => 0, 'length' => 3],
        ]);

        $indexer = $this->app->make(DatabaseIndexer::class);
        $indexer->deleteWordsWithoutAssociatedDocuments();

        $this->assertDatabaseCount('scout_words', 2);
        $this->assertDatabaseHas('scout_words', [
            'document_type' => 'user',
            'term' => 'abc',
            'num_hits' => 0,
            'num_documents' => 1,
            'length' => 3,
        ]);
        $this->assertDatabaseHas('scout_words', [
            'document_type' => 'user',
            'term' => 'def',
            'num_hits' => 0,
            'num_documents' => 2,
            'length' => 3,
        ]);
    }

    public function test_delete_entire_model_from_index_does_not_delete_entries_of_other_models(): void
    {
        $this->insertCommonTestDataInDatabase();

        $indexer = $this->app->make(DatabaseIndexer::class);
        $indexer->deleteEntireModelFromIndex(new User());

        $this->assertDatabaseCount('scout_words', 2);
        $this->assertDatabaseHas('scout_words', [
            'document_type' => 'post',
            'term' => 'ghi',
            'num_hits' => 1,
            'num_documents' => 1,
            'length' => 3,
        ]);
        $this->assertDatabaseHas('scout_words', [
            'document_type' => 'comment',
            'term' => 'jkl',
            'num_hits' => 7,
            'num_documents' => 2,
            'length' => 3,
        ]);

        $this->assertDatabaseCount('scout_documents', 3);
        $this->assertDatabaseHas('scout_documents', [
            'document_type' => 'post',
            'word_id' => 3,
            'document_id' => 1,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_documents', [
            'document_type' => 'comment',
            'word_id' => 4,
            'document_id' => 1,
            'num_hits' => 4,
        ]);
        $this->assertDatabaseHas('scout_documents', [
            'document_type' => 'comment',
            'word_id' => 4,
            'document_id' => 2,
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

        $this->assertDatabaseCount('scout_words', 3);
        $this->assertDatabaseMissing('scout_words', [
            'document_type' => 'user',
            'term' => 'def',
            'length' => 3,
        ]);
        $this->assertDatabaseHas('scout_words', [
            'document_type' => 'user',
            'term' => 'abc',
            'num_hits' => 4,
            'num_documents' => 1,
            'length' => 3,
        ]);
        $this->assertDatabaseHas('scout_words', [
            'document_type' => 'post',
            'term' => 'ghi',
            'num_hits' => 1,
            'num_documents' => 1,
            'length' => 3,
        ]);
        $this->assertDatabaseHas('scout_words', [
            'document_type' => 'comment',
            'term' => 'jkl',
            'num_hits' => 7,
            'num_documents' => 2,
            'length' => 3,
        ]);

        $this->assertDatabaseCount('scout_documents', 4);
        $this->assertDatabaseMissing('scout_documents', [
            'document_type' => 'user',
            'word_id' => 1,
            'document_id' => 1,
        ]);
        $this->assertDatabaseMissing('scout_documents', [
            'document_type' => 'user',
            'word_id' => 2,
            'document_id' => 1,
        ]);
        $this->assertDatabaseHas('scout_documents', [
            'document_type' => 'user',
            'word_id' => 1,
            'document_id' => 2,
            'num_hits' => 4,
        ]);
        $this->assertDatabaseHas('scout_documents', [
            'document_type' => 'post',
            'word_id' => 3,
            'document_id' => 1,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_documents', [
            'document_type' => 'comment',
            'word_id' => 4,
            'document_id' => 1,
            'num_hits' => 4,
        ]);
        $this->assertDatabaseHas('scout_documents', [
            'document_type' => 'comment',
            'word_id' => 4,
            'document_id' => 2,
            'num_hits' => 3,
        ]);
    }

    public function test_delete_from_index_does_not_clean_words_table_if_disabled_in_configuration(): void
    {
        config(['scout-database.clean_words_table_on_every_update' => false]);

        $this->insertCommonTestDataInDatabase();

        $user  = new User(['id' => 1]);
        $users = collect([$user]);

        $indexer = $this->app->make(DatabaseIndexer::class);
        $indexer->deleteFromIndex($users);

        $this->assertDatabaseCount('scout_words', 4);
        $this->assertDatabaseHas('scout_words', [
            'document_type' => 'user',
            'term' => 'def',
            'num_hits' => 0,
            'num_documents' => 0,
            'length' => 3,
        ]);

        $this->assertDatabaseCount('scout_documents', 4);
    }

    public function test_index_adds_new_data_to_index(): void
    {
        config(['scout-database.clean_words_table_on_every_update' => false]);
        config(['scout-database.stemmer' => NullStemmer::class]);

        $this->assertDatabaseCount('scout_words', 0);
        $this->assertDatabaseCount('scout_documents', 0);

        $user  = new User(['id' => 1, 'first_name' => 'Foo', 'last_name' => 'Bar', 'email' => 'Baz']);
        $users = collect([$user]);

        $indexer = $this->app->make(DatabaseIndexer::class);
        $indexer->index($users);

        $this->assertDatabaseCount('scout_words', 4);
        $this->assertDatabaseHas('scout_words', [
            'document_type' => 'user',
            'term' => '1',
            'num_hits' => 1,
            'num_documents' => 1,
            'length' => 1,
        ]);
        $this->assertDatabaseHas('scout_words', [
            'document_type' => 'user',
            'term' => 'foo',
            'num_hits' => 1,
            'num_documents' => 1,
            'length' => 3,
        ]);
        $this->assertDatabaseHas('scout_words', [
            'document_type' => 'user',
            'term' => 'bar',
            'num_hits' => 1,
            'num_documents' => 1,
            'length' => 3,
        ]);
        $this->assertDatabaseHas('scout_words', [
            'document_type' => 'user',
            'term' => 'baz',
            'num_hits' => 1,
            'num_documents' => 1,
            'length' => 3,
        ]);

        $this->assertDatabaseCount('scout_documents', 4);
        $this->assertDatabaseHas('scout_documents', [
            'document_type' => 'user',
            'word_id' => 1,
            'document_id' => 1,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_documents', [
            'document_type' => 'user',
            'word_id' => 2,
            'document_id' => 1,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_documents', [
            'document_type' => 'user',
            'word_id' => 3,
            'document_id' => 1,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_documents', [
            'document_type' => 'user',
            'word_id' => 4,
            'document_id' => 1,
            'num_hits' => 1,
        ]);
    }

    public function test_index_updates_index_based_on_document_data_correctly(): void
    {
        config(['scout-database.clean_words_table_on_every_update' => false]);
        config(['scout-database.stemmer' => NullStemmer::class]);

        $this->insertCommonTestDataInDatabase();

        $this->assertDatabaseCount('scout_documents', 6);

        $user  = new User(['id' => 1, 'first_name' => 'Foo', 'last_name' => 'Bar', 'email' => 'Baz']);
        $users = collect([$user]);

        $indexer = $this->app->make(DatabaseIndexer::class);
        $indexer->index($users);

        $this->assertDatabaseCount('scout_words', 8);
        $this->assertDatabaseHas('scout_words', [
            'document_type' => 'user',
            'term' => '1',
            'num_hits' => 1,
            'num_documents' => 1,
            'length' => 1,
        ]);
        $this->assertDatabaseHas('scout_words', [
            'document_type' => 'user',
            'term' => 'foo',
            'num_hits' => 1,
            'num_documents' => 1,
            'length' => 3,
        ]);
        $this->assertDatabaseHas('scout_words', [
            'document_type' => 'user',
            'term' => 'bar',
            'num_hits' => 1,
            'num_documents' => 1,
            'length' => 3,
        ]);
        $this->assertDatabaseHas('scout_words', [
            'document_type' => 'user',
            'term' => 'baz',
            'num_hits' => 1,
            'num_documents' => 1,
            'length' => 3,
        ]);

        $this->assertDatabaseCount('scout_documents', 8);
        $this->assertDatabaseMissing('scout_documents', [
            'document_type' => 'user',
            'word_id' => 1,
            'document_id' => 1,
        ]);
        $this->assertDatabaseMissing('scout_documents', [
            'document_type' => 'user',
            'word_id' => 2,
            'document_id' => 1,
        ]);
        $this->assertDatabaseHas('scout_documents', [
            'document_type' => 'user',
            'word_id' => 5,
            'document_id' => 1,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_documents', [
            'document_type' => 'user',
            'word_id' => 6,
            'document_id' => 1,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_documents', [
            'document_type' => 'user',
            'word_id' => 7,
            'document_id' => 1,
            'num_hits' => 1,
        ]);
        $this->assertDatabaseHas('scout_documents', [
            'document_type' => 'user',
            'word_id' => 8,
            'document_id' => 1,
            'num_hits' => 1,
        ]);
    }
}
