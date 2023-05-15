<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a `tenant_id` (UUID) to the `users` table.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not required because tests reset the database and dropping columns causes issues with SQLite.
        //Schema::table('users', function (Blueprint $table) {
        //    $table->dropColumn('tenant_id');
        //});
    }
};
