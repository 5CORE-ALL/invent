<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_incentives')) {
            return;
        }

        Schema::table('user_incentives', function (Blueprint $table) {
            if (! Schema::hasColumn('user_incentives', 'achieved_at')) {
                $table->timestamp('achieved_at')->nullable();
            }
            if (! Schema::hasColumn('user_incentives', 'achieved_notified_at')) {
                $table->timestamp('achieved_notified_at')->nullable();
            }
            if (! Schema::hasColumn('user_incentives', 'closed_notified_at')) {
                $table->timestamp('closed_notified_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_incentives')) {
            return;
        }

        Schema::table('user_incentives', function (Blueprint $table) {
            foreach (['achieved_at', 'achieved_notified_at', 'closed_notified_at'] as $column) {
                if (Schema::hasColumn('user_incentives', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
