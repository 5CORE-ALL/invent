<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remember which Wayfair purchase order the stored Price came from, so the automatic
 * API price sync only moves a SKU's Price when a NEWER purchase order shows up
 * (a manual sheet upload / pushed S PRC is never undone by an older PO).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wayfair_pricing_prices')) {
            return;
        }

        Schema::table('wayfair_pricing_prices', function (Blueprint $table) {
            if (! Schema::hasColumn('wayfair_pricing_prices', 'price_po_number')) {
                $table->string('price_po_number', 64)->nullable();
            }
            if (! Schema::hasColumn('wayfair_pricing_prices', 'price_po_date')) {
                $table->dateTime('price_po_date')->nullable();
            }
            if (! Schema::hasColumn('wayfair_pricing_prices', 'price_synced_at')) {
                $table->dateTime('price_synced_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('wayfair_pricing_prices')) {
            return;
        }

        Schema::table('wayfair_pricing_prices', function (Blueprint $table) {
            foreach (['price_po_number', 'price_po_date', 'price_synced_at'] as $column) {
                if (Schema::hasColumn('wayfair_pricing_prices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
