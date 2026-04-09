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
        if (! Schema::hasTable('ready_to_ship')) {
            return;
        }
        if (Schema::hasColumn('ready_to_ship', 'supplier_sku')) {
            return;
        }

        Schema::table('ready_to_ship', function (Blueprint $table) {
            $table->string('supplier_sku')->nullable()->after('supplier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('ready_to_ship')) {
            return;
        }
        if (! Schema::hasColumn('ready_to_ship', 'supplier_sku')) {
            return;
        }

        Schema::table('ready_to_ship', function (Blueprint $table) {
            $table->dropColumn('supplier_sku');
        });
    }
};
