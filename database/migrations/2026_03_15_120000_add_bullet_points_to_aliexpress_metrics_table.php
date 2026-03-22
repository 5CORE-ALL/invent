<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds bullet_points to aliexpress_metrics (plural) for databases that use this table name.
     */
    public function up(): void
    {
        if (Schema::hasTable('aliexpress_metrics') && ! Schema::hasColumn('aliexpress_metrics', 'bullet_points')) {
            Schema::table('aliexpress_metrics', function (Blueprint $table) {
                $table->text('bullet_points')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('aliexpress_metrics') && Schema::hasColumn('aliexpress_metrics', 'bullet_points')) {
            Schema::table('aliexpress_metrics', function (Blueprint $table) {
                $table->dropColumn('bullet_points');
            });
        }
    }
};
