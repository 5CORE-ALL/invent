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
        if (Schema::hasColumn('inventory_warehouse', 'pushed')) {
            return;
        }

        Schema::table('inventory_warehouse', function (Blueprint $table) {
            $table->boolean('pushed')->default(false)->after('our_sku');
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
        if (! Schema::hasColumn('inventory_warehouse', 'pushed')) {
            return;
        }

        Schema::table('inventory_warehouse', function (Blueprint $table) {
            $table->dropColumn('pushed');
        });
    }
};
