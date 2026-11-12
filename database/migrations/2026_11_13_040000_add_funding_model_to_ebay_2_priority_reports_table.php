<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ebay_2_priority_reports') && ! Schema::hasColumn('ebay_2_priority_reports', 'funding_model')) {
            Schema::table('ebay_2_priority_reports', function (Blueprint $table) {
                // COST_PER_CLICK = Promoted Listings Priority, COST_PER_SALE = Promoted Listings Standard
                $table->string('funding_model', 30)->nullable()->after('campaignStatus');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ebay_2_priority_reports') && Schema::hasColumn('ebay_2_priority_reports', 'funding_model')) {
            Schema::table('ebay_2_priority_reports', function (Blueprint $table) {
                $table->dropColumn('funding_model');
            });
        }
    }
};
