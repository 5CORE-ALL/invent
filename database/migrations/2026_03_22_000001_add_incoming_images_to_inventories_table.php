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
        if (! Schema::hasTable('inventories') || Schema::hasColumn('inventories', 'incoming_images')) {
            return;
        }

        Schema::table('inventories', function (Blueprint $table) {
            if (Schema::hasColumn('inventories', 'warehouse_id')) {
                $table->json('incoming_images')->nullable()->after('warehouse_id');
            } else {
                $table->json('incoming_images')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('inventories') || ! Schema::hasColumn('inventories', 'incoming_images')) {
            return;
        }

        Schema::table('inventories', function (Blueprint $table) {
            $table->dropColumn('incoming_images');
        });
    }
};
