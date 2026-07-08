<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('kpi_shipping_links')) {
            return;
        }

        if (Schema::hasColumn('kpi_shipping_links', 'updated_by')) {
            return;
        }

        Schema::table('kpi_shipping_links', function (Blueprint $table) {
            $table->string('updated_by', 191)->nullable()->after('on_time_pct');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('kpi_shipping_links', 'updated_by')) {
            Schema::table('kpi_shipping_links', function (Blueprint $table) {
                $table->dropColumn('updated_by');
            });
        }
    }
};
