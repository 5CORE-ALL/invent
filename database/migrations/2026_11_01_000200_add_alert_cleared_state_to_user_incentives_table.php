<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_incentives') || Schema::hasColumn('user_incentives', 'alert_cleared_state')) {
            return;
        }

        Schema::table('user_incentives', function (Blueprint $table) {
            // passed | today | tomorrow — the cutoff alert state the president last cleared.
            $table->string('alert_cleared_state', 16)->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_incentives') || ! Schema::hasColumn('user_incentives', 'alert_cleared_state')) {
            return;
        }

        Schema::table('user_incentives', function (Blueprint $table) {
            $table->dropColumn('alert_cleared_state');
        });
    }
};
