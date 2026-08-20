<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('temu2_metrics')) {
            return;
        }

        Schema::table('temu2_metrics', function (Blueprint $table) {
            if (! Schema::hasColumn('temu2_metrics', 'listing_status')) {
                $table->string('listing_status', 32)->nullable()->index()->after('quantity');
            }
            if (! Schema::hasColumn('temu2_metrics', 'inactive_reason')) {
                $table->string('inactive_reason', 191)->nullable()->after('listing_status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('temu2_metrics')) {
            return;
        }

        Schema::table('temu2_metrics', function (Blueprint $table) {
            foreach (['inactive_reason', 'listing_status'] as $column) {
                if (Schema::hasColumn('temu2_metrics', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
