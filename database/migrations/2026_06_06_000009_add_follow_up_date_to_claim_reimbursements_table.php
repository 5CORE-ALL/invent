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
        if (Schema::hasTable('claim_reimbursements') && !Schema::hasColumn('claim_reimbursements', 'follow_up_date')) {
            Schema::table('claim_reimbursements', function (Blueprint $table) {
                $table->date('follow_up_date')->nullable()->after('details_note');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('claim_reimbursements') && Schema::hasColumn('claim_reimbursements', 'follow_up_date')) {
            Schema::table('claim_reimbursements', function (Blueprint $table) {
                $table->dropColumn('follow_up_date');
            });
        }
    }
};
