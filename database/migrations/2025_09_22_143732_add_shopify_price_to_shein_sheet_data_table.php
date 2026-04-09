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
        if (! Schema::hasTable('shein_sheet_data') || Schema::hasColumn('shein_sheet_data', 'shopify_price')) {
            return;
        }

        Schema::table('shein_sheet_data', function (Blueprint $table) {
            $table->decimal('shopify_price', 8, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('shein_sheet_data') || ! Schema::hasColumn('shein_sheet_data', 'shopify_price')) {
            return;
        }

        Schema::table('shein_sheet_data', function (Blueprint $table) {
            $table->dropColumn('shopify_price');
        });
    }
};
