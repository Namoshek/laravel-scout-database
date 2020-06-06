<?php

declare(strict_types=1);

namespace Namoshek\Scout\Database\Tests;

use Namoshek\Scout\Database\DatabaseIndexer;

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
}
