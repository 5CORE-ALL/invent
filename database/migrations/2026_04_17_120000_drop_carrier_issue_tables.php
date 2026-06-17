<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('carrier_issue_issue_histories');
        Schema::dropIfExists('carrier_issue_issues');

        if (Schema::hasTable('customer_care_issue_dropdown_options')) {
            DB::table('customer_care_issue_dropdown_options')->where('module_key', 'carrier_issue')->delete();
        }
    }

    public function down(): void
    {
        // Tables were removed permanently; restore only by re-running original create migrations from backup.
    }
};
