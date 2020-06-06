<?php

declare(strict_types=1);

namespace Namoshek\Scout\Database\Tests;

use Namoshek\Scout\Database\DatabaseIndexer;
use Namoshek\Scout\Database\Tests\Stubs\User;

class DatabaseIndexerTest extends TestCase
{
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

        $this->assertDatabaseCount('scout_words', 4);

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
        /** @var \Illuminate\Database\ConnectionInterface $connection */
        $connection = $this->app->make('db');

        $connection->table('scout_words')->insert([
            ['document_type' => 'user', 'term' => 'abc', 'num_hits' => 0, 'num_documents' => 1, 'length' => 3],
            ['document_type' => 'user', 'term' => 'def', 'num_hits' => 0, 'num_documents' => 2, 'length' => 3],
            ['document_type' => 'post', 'term' => 'ghi', 'num_hits' => 0, 'num_documents' => 0, 'length' => 3],
            ['document_type' => 'comment', 'term' => 'jkl', 'num_hits' => 1, 'num_documents' => 0, 'length' => 3],
        ]);

        $this->assertDatabaseCount('scout_words', 4);

        $connection->table('scout_documents')->insert([
            ['document_type' => 'user', 'word_id' => 1, 'document_id' => 1, 'num_hits' => 1],
            ['document_type' => 'user', 'word_id' => 1, 'document_id' => 2, 'num_hits' => 4],
            ['document_type' => 'user', 'word_id' => 2, 'document_id' => 1, 'num_hits' => 2],
            ['document_type' => 'post', 'word_id' => 3, 'document_id' => 1, 'num_hits' => 1],
            ['document_type' => 'comment', 'word_id' => 4, 'document_id' => 1, 'num_hits' => 4],
            ['document_type' => 'comment', 'word_id' => 4, 'document_id' => 2, 'num_hits' => 3],
        ]);

        $this->assertDatabaseCount('scout_documents', 6);

        $indexer = $this->app->make(DatabaseIndexer::class);
        $indexer->deleteEntireModelFromIndex(new User());

        $this->assertDatabaseCount('scout_words', 2);
        $this->assertDatabaseHas('scout_words', [
            'document_type' => 'post',
            'term' => 'ghi',
            'num_hits' => 0,
            'num_documents' => 0,
            'length' => 3,
        ]);
        $this->assertDatabaseHas('scout_words', [
            'document_type' => 'comment',
            'term' => 'jkl',
            'num_hits' => 1,
            'num_documents' => 0,
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
}
