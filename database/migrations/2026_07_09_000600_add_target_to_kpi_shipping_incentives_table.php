<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('kpi_shipping_incentives')) {
            return;
        }

        if (Schema::hasColumn('kpi_shipping_incentives', 'target')) {
            return;
        }

        Schema::table('kpi_shipping_incentives', function (Blueprint $table) {
            $table->string('target', 100)->nullable()->after('id');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('kpi_shipping_incentives', 'target')) {
            Schema::table('kpi_shipping_incentives', function (Blueprint $table) {
                $table->dropColumn('target');
            });
        }
    }
};
