<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vinted and Depop sales also arrive through Shopify (the Fluf app creates the orders).
 * These columns mark which sheet rows came from a Shopify order so the sync can update
 * them in place, and a CSV upload can leave them alone.
 */
return new class extends Migration
{
    private const TABLES = ['vinted_sales_data', 'depop_sales_data'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            if (! Schema::hasTable($name)) {
                continue;
            }
            Schema::table($name, function (Blueprint $table) use ($name) {
                if (! Schema::hasColumn($name, 'source')) {
                    $table->string('source', 20)->default('upload')->after('sku_code');
                }
                if (! Schema::hasColumn($name, 'shopify_order_id')) {
                    $table->unsignedBigInteger('shopify_order_id')->nullable()->after('source');
                }
                if (! Schema::hasColumn($name, 'shopify_line_item_id')) {
                    $table->unsignedBigInteger('shopify_line_item_id')->nullable()->after('shopify_order_id');
                }
                if (! Schema::hasColumn($name, 'shopify_order_number')) {
                    $table->string('shopify_order_number', 60)->nullable()->after('shopify_line_item_id');
                }
            });
            Schema::table($name, function (Blueprint $table) use ($name) {
                $index = $name.'_shopify_line_unique';
                $exists = collect(\Illuminate\Support\Facades\DB::select('SHOW INDEX FROM `'.$name.'` WHERE Key_name = ?', [$index]))->isNotEmpty();
                if (! $exists) {
                    $table->unique(['shopify_order_id', 'shopify_line_item_id'], $index);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            if (! Schema::hasTable($name)) {
                continue;
            }
            Schema::table($name, function (Blueprint $table) use ($name) {
                $index = $name.'_shopify_line_unique';
                $exists = collect(\Illuminate\Support\Facades\DB::select('SHOW INDEX FROM `'.$name.'` WHERE Key_name = ?', [$index]))->isNotEmpty();
                if ($exists) {
                    $table->dropUnique($index);
                }
                foreach (['shopify_order_number', 'shopify_line_item_id', 'shopify_order_id', 'source'] as $col) {
                    if (Schema::hasColumn($name, $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
