<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_rr', function (Blueprint $table) {
            // Change role column from VARCHAR(255) to LONGTEXT to accommodate HTML content with images
            $table->longText('role')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_rr', function (Blueprint $table) {
            // Revert back to VARCHAR(255) if needed
            $table->string('role', 255)->nullable()->change();
        });
    }
};
