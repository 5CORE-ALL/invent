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
        Schema::table('tiktok_sheet_data', function (Blueprint $table) {
            $table->decimal('price', 10, 4)->nullable()->change();
            $table->decimal('shopify_tiktok_price', 10, 4)->nullable()->change();
            $table->decimal('views', 10, 4)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tiktok_sheet_data', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable()->change();
            $table->decimal('shopify_tiktok_price', 10, 2)->nullable()->change();
            $table->decimal('views', 10, 2)->nullable()->change();
        });
    }
};