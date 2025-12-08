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
        Schema::table('ebay_2_metrics', function (Blueprint $table) {
            $table->integer('ebay_l7')->nullable()->after('ebay_l60');
            $table->integer('l7_views')->nullable()->after('views');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ebay_2_metrics', function (Blueprint $table) {
            $table->dropColumn(['ebay_l7', 'l7_views']);
        });
    }
};
