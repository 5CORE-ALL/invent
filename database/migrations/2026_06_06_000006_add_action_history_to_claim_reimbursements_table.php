<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('claim_reimbursements') && !Schema::hasColumn('claim_reimbursements', 'action_history')) {
            Schema::table('claim_reimbursements', function (Blueprint $table) {
                $table->json('action_history')->nullable()->after('created_by');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('claim_reimbursements') && Schema::hasColumn('claim_reimbursements', 'action_history')) {
            Schema::table('claim_reimbursements', function (Blueprint $table) {
                $table->dropColumn('action_history');
            });
        }
    }
};
