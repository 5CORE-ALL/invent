<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consolidated, idempotent migration for deploying all the new
 * Claim & Reimbursement columns to the server in one step.
 *
 * Safe to run even if some columns were already created by the
 * individual feature migrations (each column is guarded by hasColumn).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('claim_reimbursements')) {
            return;
        }

        Schema::table('claim_reimbursements', function (Blueprint $table) {
            if (!Schema::hasColumn('claim_reimbursements', 'created_by')) {
                $table->string('created_by')->nullable()->after('total_amount');
            }
            if (!Schema::hasColumn('claim_reimbursements', 'action_history')) {
                $table->json('action_history')->nullable()->after('created_by');
            }
            if (!Schema::hasColumn('claim_reimbursements', 'received_amount')) {
                $table->text('received_amount')->nullable()->after('action_history');
            }
            if (!Schema::hasColumn('claim_reimbursements', 'details_note')) {
                $table->text('details_note')->nullable()->after('received_amount');
            }
            if (!Schema::hasColumn('claim_reimbursements', 'follow_up_date')) {
                $table->date('follow_up_date')->nullable()->after('details_note');
            }
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

    public function down(): void
    {
        if (!Schema::hasTable('claim_reimbursements')) {
            return;
        }

        Schema::table('claim_reimbursements', function (Blueprint $table) {
            foreach ([
                'created_by',
                'action_history',
                'received_amount',
                'details_note',
                'follow_up_date',
                'is_archived',
                'archived_by',
                'archived_at',
            ] as $col) {
                if (Schema::hasColumn('claim_reimbursements', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
