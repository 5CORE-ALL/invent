<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('account_health_metric_field_definitions')) {
            return;
        }

        Schema::table('account_health_metric_field_definitions', function (Blueprint $table) {
            if (! Schema::hasColumn('account_health_metric_field_definitions', 'h_link')) {
                $table->string('h_link', 2048)->nullable()->after('m_link');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('account_health_metric_field_definitions')) {
            return;
        }

        Schema::table('account_health_metric_field_definitions', function (Blueprint $table) {
            if (Schema::hasColumn('account_health_metric_field_definitions', 'h_link')) {
                $table->dropColumn('h_link');
            }
        });
    }
};
