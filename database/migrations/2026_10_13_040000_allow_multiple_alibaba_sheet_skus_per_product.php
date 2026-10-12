<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('alibaba_sheet_prices')) {
            return;
        }

        if (Schema::hasIndex('alibaba_sheet_prices', 'alibaba_sheet_prices_product_id_unique')) {
            Schema::table('alibaba_sheet_prices', function (Blueprint $table) {
                $table->dropUnique('alibaba_sheet_prices_product_id_unique');
            });
        }

        if (! Schema::hasIndex('alibaba_sheet_prices', 'alibaba_sheet_prices_product_sku_unique')) {
            Schema::table('alibaba_sheet_prices', function (Blueprint $table) {
                $table->unique(['product_id', 'sku'], 'alibaba_sheet_prices_product_sku_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('alibaba_sheet_prices')) {
            return;
        }

        if (Schema::hasIndex('alibaba_sheet_prices', 'alibaba_sheet_prices_product_sku_unique')) {
            Schema::table('alibaba_sheet_prices', function (Blueprint $table) {
                $table->dropUnique('alibaba_sheet_prices_product_sku_unique');
            });
        }

        if (! Schema::hasIndex('alibaba_sheet_prices', 'alibaba_sheet_prices_product_id_unique')) {
            Schema::table('alibaba_sheet_prices', function (Blueprint $table) {
                $table->unique('product_id');
            });
        }
    }
};
