<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the `words` table.
 */
class CreateScoutDatabaseWordsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::connection(config('scout-database.connection'))->create('scout_words', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('document_type');
            $table->string('term', '128');
            $table->unsignedInteger('num_hits');
            $table->unsignedInteger('num_documents');
            $table->unsignedInteger('length');

            $table->unique(['document_type', 'term']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::connection(config('scout-database.connection'))->dropIfExists('scout_words');
    }
}
