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
        if (Schema::hasTable('claim_reimbursements') && !Schema::hasColumn('claim_reimbursements', 'created_by')) {
            Schema::table('claim_reimbursements', function (Blueprint $table) {
                $table->string('created_by')->nullable()->after('total_amount');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('claim_reimbursements') && Schema::hasColumn('claim_reimbursements', 'created_by')) {
            Schema::table('claim_reimbursements', function (Blueprint $table) {
                $table->dropColumn('created_by');
            });
        }
    }
};
