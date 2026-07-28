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

        if (! Schema::hasColumn('qc_container_audits', 'claim_links')) {
            Schema::table('qc_container_audits', function (Blueprint $table) {
                $table->json('claim_links')->nullable()->after('items');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('qc_container_audits')) {
            return;
        }

        if (Schema::hasColumn('qc_container_audits', 'claim_links')) {
            Schema::table('qc_container_audits', function (Blueprint $table) {
                $table->dropColumn('claim_links');
            });
        }
    }
};
