<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the `index` table.
 */
class CreateScoutDatabaseIndexTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Create the new table for indexing.
        Schema::connection(config('scout-database.connection'))->create('scout_index', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('document_type');
            $table->unsignedBigInteger('document_id');
            $table->string('term', '128');
            $table->unsignedInteger('length');
            $table->unsignedInteger('num_hits');
        });

        // Copy all indexed data from the old tables to our new indexing table.
        $sql = <<<SQL
INSERT INTO scout_index (document_type, document_id, term, length, num_hits)
SELECT d.document_type, d.document_id, w.term, w.length, d.num_hits
FROM scout_documents d
INNER JOIN scout_words w ON w.id = d.word_id
SQL;
        DB::connection(config('scout-database.connection'))->statement($sql);

        // Add performance indexes to the new table.
        Schema::connection(config('scout-database.connection'))->table('scout_index', function (Blueprint $table) {
            $table->index(['document_type', 'term']);
            $table->index(['document_type', 'document_id']);
            $table->index(['document_id']);
        });

        // Remove the old tables which are no longer required.
        Schema::connection(config('scout-database.connection'))->dropIfExists('scout_documents');
        Schema::connection(config('scout-database.connection'))->dropIfExists('scout_words');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Recreate the old indexing tables.
        Schema::connection(config('scout-database.connection'))->create('scout_words', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('document_type');
            $table->string('term', '128');
            $table->unsignedInteger('num_hits');
            $table->unsignedInteger('num_documents');
            $table->unsignedInteger('length');

            $table->unique(['document_type', 'term']);
        });

        Schema::connection(config('scout-database.connection'))->create('scout_documents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('word_id');
            $table->string('document_type');
            $table->unsignedBigInteger('document_id');
            $table->unsignedInteger('num_hits');

            $table->foreign('word_id')->references('id')->on('scout_words');

            $table->index(['document_type', 'document_id']);
        });

        // Copy the indexed data from the new to the old tables.
        $sql = <<<SQL
INSERT INTO scout_words (document_type, term, length, num_hits, num_documents)
SELECT document_type, term, length, SUM(num_hits), COUNT(DISTINCT(document_id))
FROM scout_index
GROUP BY document_type, term, length
SQL;
        DB::connection(config('scout-database.connection'))->statement($sql);

        $sql = <<<SQL
INSERT INTO scout_documents (document_type, document_id, word_id, num_hits)
SELECT i.document_type, i.document_id, w.id, i.num_hits
FROM scout_index i
INNER JOIN scout_words w
    ON w.document_type = i.document_type
    AND w.term = i.term
SQL;
        DB::connection(config('scout-database.connection'))->statement($sql);

        // Remove the new indexing table.
        Schema::connection(config('scout-database.connection'))->dropIfExists('scout_index');
    }
}
