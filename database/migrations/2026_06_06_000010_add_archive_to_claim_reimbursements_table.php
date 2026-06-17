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
        if (Schema::hasTable('claim_reimbursements')) {
            Schema::table('claim_reimbursements', function (Blueprint $table) {
                if (!Schema::hasColumn('claim_reimbursements', 'is_archived')) {
                    $table->boolean('is_archived')->default(false)->after('follow_up_date');
                }
                if (!Schema::hasColumn('claim_reimbursements', 'archived_by')) {
                    $table->string('archived_by')->nullable()->after('is_archived');
                }
                if (!Schema::hasColumn('claim_reimbursements', 'archived_at')) {
                    $table->timestamp('archived_at')->nullable()->after('archived_by');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('claim_reimbursements')) {
            Schema::table('claim_reimbursements', function (Blueprint $table) {
                foreach (['is_archived', 'archived_by', 'archived_at'] as $col) {
                    if (Schema::hasColumn('claim_reimbursements', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
