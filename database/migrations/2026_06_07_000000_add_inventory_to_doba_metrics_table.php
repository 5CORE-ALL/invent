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
        if (! Schema::hasTable('doba_metrics')) {
            return;
        }

        if (! Schema::hasColumn('doba_metrics', 'inventory')) {
            Schema::table('doba_metrics', function (Blueprint $table) {
                $table->integer('inventory')->default(0)->after('item_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('doba_metrics')) {
            return;
        }

        if (Schema::hasColumn('doba_metrics', 'inventory')) {
            Schema::table('doba_metrics', function (Blueprint $table) {
                $table->dropColumn('inventory');
            });
        }
    }
};
