<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('follow_ups')) {
            return;
        }

        Schema::table('follow_ups', function (Blueprint $table) {
            if (! Schema::hasColumn('follow_ups', 'reminder_notified_at')) {
                $table->timestamp('reminder_notified_at')->nullable()->after('next_follow_up_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('follow_ups')) {
            return;
        }

        Schema::table('follow_ups', function (Blueprint $table) {
            if (Schema::hasColumn('follow_ups', 'reminder_notified_at')) {
                $table->dropColumn('reminder_notified_at');
            }
        });
    }
};
