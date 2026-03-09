<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Allow multiple rows per ASIN (e.g. GSP 12150 8 and GSP 1030 8 same ASIN) by keying on SKU.
     */
    public function up(): void
    {
        Schema::table('amazon_datsheets', function (Blueprint $table) {
            $table->dropUnique('amazon_datsheets_asin_unique');
        });

        // Ensure sku column exists (in case add_columns migration was not run)
        if (!Schema::hasColumn('amazon_datsheets', 'sku')) {
            Schema::table('amazon_datsheets', function (Blueprint $table) {
                $table->string('sku')->after('asin')->nullable();
            });
        }

        Schema::table('amazon_datsheets', function (Blueprint $table) {
            $table->unique('sku', 'amazon_datsheets_sku_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('amazon_datsheets', function (Blueprint $table) {
            $table->dropUnique('amazon_datsheets_sku_unique');
        });
        Schema::table('amazon_datsheets', function (Blueprint $table) {
            $table->unique('asin', 'amazon_datsheets_asin_unique');
        });
    }
};
