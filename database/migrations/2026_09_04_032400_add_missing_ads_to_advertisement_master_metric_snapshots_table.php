<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('advertisement_master_metric_snapshots')
            && ! Schema::hasColumn('advertisement_master_metric_snapshots', 'missing_ads')) {
            Schema::table('advertisement_master_metric_snapshots', function (Blueprint $table) {
                $table->integer('missing_ads')->default(0)->after('active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('advertisement_master_metric_snapshots')
            && Schema::hasColumn('advertisement_master_metric_snapshots', 'missing_ads')) {
            Schema::table('advertisement_master_metric_snapshots', function (Blueprint $table) {
                $table->dropColumn('missing_ads');
            });
        }
    }
};
