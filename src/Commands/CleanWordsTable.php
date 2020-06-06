<?php

declare(strict_types=1);

namespace Namoshek\Scout\Database\Commands;

use Illuminate\Console\Command;
use Namoshek\Scout\Database\DatabaseIndexer;

/**
 * Removes entries from the Laravel Scout words table without associated documents.
 *
 * @package Namoshek\Scout\Database\Commands
 */
class CleanWordsTable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scout:clean-words-table';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Removes entries from the Scout words table without associated documents';

    /**
     * Execute the console command.
     *
     * @param DatabaseIndexer $databaseIndexer
     * @return void
     */
    public function handle(DatabaseIndexer $databaseIndexer): void
    {
        $databaseIndexer->deleteWordsWithoutAssociatedDocuments();
    }
}
