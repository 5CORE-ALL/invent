<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds soft-archive support to forecast_analysis. Rows with archived_at IS NOT NULL
     * are hidden from /forecast.analysis and surfaced on the Restore page for the
     * president user only.
     */
    public function up(): void
    {
        Schema::table('forecast_analysis', function (Blueprint $table) {
            if (!Schema::hasColumn('forecast_analysis', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('updated_at');
            }
            if (!Schema::hasColumn('forecast_analysis', 'archived_by')) {
                $table->string('archived_by', 191)->nullable()->after('archived_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('forecast_analysis', function (Blueprint $table) {
            if (Schema::hasColumn('forecast_analysis', 'archived_by')) {
                $table->dropColumn('archived_by');
            }
            if (Schema::hasColumn('forecast_analysis', 'archived_at')) {
                $table->dropColumn('archived_at');
            }
        });
    }
};
