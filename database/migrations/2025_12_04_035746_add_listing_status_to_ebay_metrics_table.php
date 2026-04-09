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
        if (! Schema::hasTable('ebay_metrics')) {
            return;
        }
        if (Schema::hasColumn('ebay_metrics', 'listing_status')) {
            return;
        }

        Schema::table('ebay_metrics', function (Blueprint $table) {
            $table->string('listing_status')->nullable()->after('report_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('ebay_metrics')) {
            return;
        }
        if (! Schema::hasColumn('ebay_metrics', 'listing_status')) {
            return;
        }

        Schema::table('ebay_metrics', function (Blueprint $table) {
            $table->dropColumn('listing_status');
        });
    }
};
