<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('qc_container_audits')) {
            return;
        }

        if (! Schema::hasColumn('qc_container_audits', 'action_history')) {
            Schema::table('qc_container_audits', function (Blueprint $table) {
                $table->json('action_history')->nullable()->after('claim_links');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('qc_container_audits')) {
            return;
        }

        if (Schema::hasColumn('qc_container_audits', 'action_history')) {
            Schema::table('qc_container_audits', function (Blueprint $table) {
                $table->dropColumn('action_history');
            });
        }
    }
};
