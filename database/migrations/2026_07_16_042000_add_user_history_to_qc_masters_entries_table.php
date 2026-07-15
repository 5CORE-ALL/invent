<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('qc_masters_entries')) {
            return;
        }

        Schema::table('qc_masters_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('qc_masters_entries', 'user_history')) {
                $table->json('user_history')->nullable()->after('video_size_kb');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('qc_masters_entries')) {
            return;
        }

        Schema::table('qc_masters_entries', function (Blueprint $table) {
            if (Schema::hasColumn('qc_masters_entries', 'user_history')) {
                $table->dropColumn('user_history');
            }
        });
    }
};
