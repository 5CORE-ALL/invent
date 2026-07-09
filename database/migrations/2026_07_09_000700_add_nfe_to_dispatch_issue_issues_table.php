<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('dispatch_issue_issues')) {
            return;
        }

        if (Schema::hasColumn('dispatch_issue_issues', 'nfe')) {
            return;
        }

        Schema::table('dispatch_issue_issues', function (Blueprint $table) {
            // 'yes' = No Fault of Executive confirmed, 'no' = fault of executive, null = not set.
            $table->string('nfe', 3)->nullable()->after('close_note');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('dispatch_issue_issues', 'nfe')) {
            Schema::table('dispatch_issue_issues', function (Blueprint $table) {
                $table->dropColumn('nfe');
            });
        }
    }
};
