<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('to_order_analysis')) {
            return;
        }

        if (Schema::hasColumn('to_order_analysis', 'issues')) {
            return;
        }

        Schema::table('to_order_analysis', function (Blueprint $table) {
            $table->text('issues')->nullable()->after('nrl');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('to_order_analysis') || ! Schema::hasColumn('to_order_analysis', 'issues')) {
            return;
        }

        Schema::table('to_order_analysis', function (Blueprint $table) {
            $table->dropColumn('issues');
        });
    }
};
