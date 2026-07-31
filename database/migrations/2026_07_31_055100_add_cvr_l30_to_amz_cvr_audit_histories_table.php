<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('amz_cvr_audit_histories')) {
            return;
        }
        if (Schema::hasColumn('amz_cvr_audit_histories', 'cvr_l30')) {
            return;
        }

        Schema::table('amz_cvr_audit_histories', function (Blueprint $table) {
            $table->decimal('cvr_l30', 8, 2)->nullable()->after('task_count');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('amz_cvr_audit_histories')) {
            return;
        }
        if (! Schema::hasColumn('amz_cvr_audit_histories', 'cvr_l30')) {
            return;
        }

        Schema::table('amz_cvr_audit_histories', function (Blueprint $table) {
            $table->dropColumn('cvr_l30');
        });
    }
};
