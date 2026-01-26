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
        Schema::table('amazon_daily_syncs', function (Blueprint $table) {
            $table->integer('items_fetched')->default(0)->after('pages_fetched')->comment('Number of order items (line items) fetched');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('amazon_daily_syncs', function (Blueprint $table) {
            $table->dropColumn('items_fetched');
        });
    }
};
