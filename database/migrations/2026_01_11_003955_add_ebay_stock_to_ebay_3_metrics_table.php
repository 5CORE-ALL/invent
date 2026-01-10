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
        Schema::table('ebay_3_metrics', function (Blueprint $table) {
            $table->integer('ebay_stock')->nullable()->after('ebay_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ebay_3_metrics', function (Blueprint $table) {
            $table->dropColumn('ebay_stock');
        });
    }
};
