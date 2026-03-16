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
        Schema::table('shein_sheet_data', function (Blueprint $table) {
            $table->integer('shopify_sheinl30')->nullable();
            $table->integer('shopify_sheinl60')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shein_sheet_data', function (Blueprint $table) {
            $table->dropColumn(['shopify_sheinl30', 'shopify_sheinl60']);
        });
    }
};
