<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('macy_metrics') || Schema::hasColumn('macy_metrics', 'bullet_points')) {
            return;
        }

        Schema::table('macy_metrics', function (Blueprint $table) {
            $table->longText('bullet_points')->nullable()->after('sku');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('macy_metrics') || ! Schema::hasColumn('macy_metrics', 'bullet_points')) {
            return;
        }

        Schema::table('macy_metrics', function (Blueprint $table) {
            $table->dropColumn('bullet_points');
        });
    }
};
