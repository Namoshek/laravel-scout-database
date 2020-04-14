<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the `documents` table.
 */
class CreateScoutDatabaseDocumentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection(config('scout-database.connection'))->create('scout_documents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('word_id');
            $table->string('document_type');
            $table->unsignedBigInteger('document_id');
            $table->unsignedInteger('num_hits');

            $table->foreign('word_id')->references('id')->on('scout_words');

            $table->index(['document_type', 'document_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection(config('scout-database.connection'))->dropIfExists('scout_documents');
    }
}
