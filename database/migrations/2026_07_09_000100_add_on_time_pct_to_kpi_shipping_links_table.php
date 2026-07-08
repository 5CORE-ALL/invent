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

        if (Schema::hasColumn('kpi_shipping_links', 'on_time_pct')) {
            return;
        }

        Schema::table('kpi_shipping_links', function (Blueprint $table) {
            $table->decimal('on_time_pct', 8, 2)->nullable()->after('link');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('kpi_shipping_links', 'on_time_pct')) {
            Schema::table('kpi_shipping_links', function (Blueprint $table) {
                $table->dropColumn('on_time_pct');
            });
        }
    }
};
