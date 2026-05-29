<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dispatch_issue_issues') || Schema::hasColumn('dispatch_issue_issues', 'cc_action_history')) {
            return;
        }
        Schema::table('dispatch_issue_issues', function (Blueprint $table) {
            $table->text('cc_action_history')->nullable()->after('action_1');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dispatch_issue_issues') || ! Schema::hasColumn('dispatch_issue_issues', 'cc_action_history')) {
            return;
        }
        Schema::table('dispatch_issue_issues', function (Blueprint $table) {
            $table->dropColumn('cc_action_history');
        });
    }
};
