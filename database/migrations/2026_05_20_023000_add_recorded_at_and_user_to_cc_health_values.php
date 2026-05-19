<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cc_health_values')) {
            return;
        }

        Schema::table('cc_health_values', function (Blueprint $table) {
            if (! Schema::hasColumn('cc_health_values', 'recorded_at')) {
                $table->dateTime('recorded_at')->nullable()->after('recorded_on');
            }
            if (! Schema::hasColumn('cc_health_values', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('recorded_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cc_health_values')) {
            return;
        }

        Schema::table('cc_health_values', function (Blueprint $table) {
            if (Schema::hasColumn('cc_health_values', 'user_id')) {
                $table->dropColumn('user_id');
            }
            if (Schema::hasColumn('cc_health_values', 'recorded_at')) {
                $table->dropColumn('recorded_at');
            }
        });
    }
};
