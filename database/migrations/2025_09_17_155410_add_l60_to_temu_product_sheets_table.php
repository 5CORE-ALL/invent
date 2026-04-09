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
        if (! Schema::hasTable('temu_product_sheets') || Schema::hasColumn('temu_product_sheets', 'l60')) {
            return;
        }

        Schema::table('temu_product_sheets', function (Blueprint $table) {
            $table->integer('l60')->nullable()->after('l30');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('temu_product_sheets') || ! Schema::hasColumn('temu_product_sheets', 'l60')) {
            return;
        }

        Schema::table('temu_product_sheets', function (Blueprint $table) {
            $table->dropColumn('l60');
        });
    }
};
