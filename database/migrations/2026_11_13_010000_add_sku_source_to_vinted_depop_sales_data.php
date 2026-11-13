<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tells where a sheet row's SKU came from, so a guessed SKU is visible on the sheet and a SKU a person
 * typed in is never overwritten by the Shopify sync.
 *
 *   NULL    = taken from the Shopify order / variant (exact)
 *   'guess' = closest product title, not confirmed
 *   'manual'= typed in on /vinted/sheet (or /depop/sheet); the sync leaves it alone
 */
return new class extends Migration
{
    private const TABLES = ['vinted_sales_data', 'depop_sales_data'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            if (Schema::hasTable($name) && ! Schema::hasColumn($name, 'sku_source')) {
                Schema::table($name, function (Blueprint $table) {
                    $table->string('sku_source', 12)->nullable()->after('sku_code');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            if (Schema::hasTable($name) && Schema::hasColumn($name, 'sku_source')) {
                Schema::table($name, function (Blueprint $table) {
                    $table->dropColumn('sku_source');
                });
            }
        }
    }
};
