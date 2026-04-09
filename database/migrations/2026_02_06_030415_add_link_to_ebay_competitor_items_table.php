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
        if (! Schema::hasTable('ebay_competitor_items')) {
            return;
        }
        if (Schema::hasColumn('ebay_competitor_items', 'link')) {
            return;
        }

        Schema::table('ebay_competitor_items', function (Blueprint $table) {
            $table->text('link')->nullable()->after('item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('ebay_competitor_items')) {
            return;
        }
        if (! Schema::hasColumn('ebay_competitor_items', 'link')) {
            return;
        }

        Schema::table('ebay_competitor_items', function (Blueprint $table) {
            $table->dropColumn('link');
        });
    }
};
