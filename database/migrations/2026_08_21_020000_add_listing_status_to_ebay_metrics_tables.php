<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['ebay_metrics', 'ebay_2_metrics', 'ebay_3_metrics'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (! Schema::hasColumn($table, 'listing_status')) {
                    $blueprint->string('listing_status', 32)->nullable()->index();
                }
                if (! Schema::hasColumn($table, 'inactive_reason')) {
                    $blueprint->string('inactive_reason', 191)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['ebay_2_metrics', 'ebay_3_metrics'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                foreach (['inactive_reason', 'listing_status'] as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        $blueprint->dropColumn($column);
                    }
                }
            });
        }
        if (Schema::hasTable('ebay_metrics') && Schema::hasColumn('ebay_metrics', 'inactive_reason')) {
            Schema::table('ebay_metrics', function (Blueprint $blueprint) {
                $blueprint->dropColumn('inactive_reason');
            });
        }
    }
};
