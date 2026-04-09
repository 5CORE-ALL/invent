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
        if (! Schema::hasTable('ebay_3_priority_reports')) {
            return;
        }

        if (! Schema::hasColumn('ebay_3_priority_reports', 'start_date')) {
            Schema::table('ebay_3_priority_reports', function (Blueprint $table) {
                $table->date('start_date')->nullable()->after('report_range');
            });
        }
        if (! Schema::hasColumn('ebay_3_priority_reports', 'end_date')) {
            Schema::table('ebay_3_priority_reports', function (Blueprint $table) {
                $table->date('end_date')->nullable()->after('start_date');
            });
        }
        if (! Schema::hasColumn('ebay_3_priority_reports', 'campaign_name')) {
            Schema::table('ebay_3_priority_reports', function (Blueprint $table) {
                $table->string('campaign_name')->nullable()->after('end_date');
            });
        }
        if (! Schema::hasColumn('ebay_3_priority_reports', 'campaignBudgetAmount')) {
            Schema::table('ebay_3_priority_reports', function (Blueprint $table) {
                $table->decimal('campaignBudgetAmount', 15, 2)->nullable()->after('campaign_name');
            });
        }
        if (! Schema::hasColumn('ebay_3_priority_reports', 'campaignStatus')) {
            Schema::table('ebay_3_priority_reports', function (Blueprint $table) {
                $table->string('campaignStatus')->nullable()->after('campaignBudgetAmount');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('ebay_3_priority_reports')) {
            return;
        }

        $columns = ['start_date', 'end_date', 'campaign_name', 'campaignBudgetAmount', 'campaignStatus'];
        $toDrop = array_values(array_filter($columns, fn (string $col) => Schema::hasColumn('ebay_3_priority_reports', $col)));
        if ($toDrop === []) {
            return;
        }

        Schema::table('ebay_3_priority_reports', function (Blueprint $table) use ($toDrop) {
            $table->dropColumn($toDrop);
        });
    }
};
