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
        Schema::create('scout_words', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('term', '1024');
            $table->unsignedInteger('num_hits');
            $table->unsignedInteger('num_documents');
            $table->unsignedInteger('length');

            $table->unique('term');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('scout_words');
    }
}
