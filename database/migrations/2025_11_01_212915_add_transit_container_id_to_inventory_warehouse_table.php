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
        if (! Schema::hasTable('inventory_warehouse')) {
            return;
        }
        if (Schema::hasColumn('inventory_warehouse', 'transit_container_id')) {
            return;
        }

        Schema::table('inventory_warehouse', function (Blueprint $table) {
            $table->unsignedBigInteger('transit_container_id')->nullable()->after('tab_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('inventory_warehouse')) {
            return;
        }
        if (! Schema::hasColumn('inventory_warehouse', 'transit_container_id')) {
            return;
        }

        Schema::table('inventory_warehouse', function (Blueprint $table) {
            $table->dropColumn('transit_container_id');
        });
    }
};
