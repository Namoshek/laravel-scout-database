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
        Schema::create('__TABLE__PREFIX__documents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('word_id');
            $table->string('document_type');
            $table->unsignedBigInteger('document_id');
            $table->unsignedInteger('num_hits');

            $table->foreign('word_id')->references('id')->on('__TABLE__PREFIX__words');

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
        Schema::dropIfExists('__TABLE__PREFIX__documents');
    }
}
