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
        if (! Schema::hasTable('amazon_fbm_manual')) {
            return;
        }
        if (Schema::hasColumn('amazon_fbm_manual', 'data')) {
            return;
        }

        Schema::table('amazon_fbm_manual', function (Blueprint $table) {
            $table->json('data')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('amazon_fbm_manual')) {
            return;
        }
        if (! Schema::hasColumn('amazon_fbm_manual', 'data')) {
            return;
        }

        Schema::table('amazon_fbm_manual', function (Blueprint $table) {
            $table->dropColumn('data');
        });
    }
};
