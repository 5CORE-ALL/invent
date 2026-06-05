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
        if (Schema::hasTable('claim_reimbursements') && !Schema::hasColumn('claim_reimbursements', 'details_note')) {
            Schema::table('claim_reimbursements', function (Blueprint $table) {
                $table->text('details_note')->nullable()->after('received_amount');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('claim_reimbursements') && Schema::hasColumn('claim_reimbursements', 'details_note')) {
            Schema::table('claim_reimbursements', function (Blueprint $table) {
                $table->dropColumn('details_note');
            });
        }
    }
};
