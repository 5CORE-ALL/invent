<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('channel_master_calculated_data')) {
            return;
        }

        if (! Schema::hasColumn('channel_master_calculated_data', 'today_sales')) {
            Schema::table('channel_master_calculated_data', function (Blueprint $table) {
                $table->decimal('today_sales', 15, 2)->default(0)->after('yesterday_sales');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('channel_master_calculated_data')
            && Schema::hasColumn('channel_master_calculated_data', 'today_sales')) {
            Schema::table('channel_master_calculated_data', function (Blueprint $table) {
                $table->dropColumn('today_sales');
            });
        }
    }
};
