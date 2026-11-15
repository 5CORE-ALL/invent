<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dispatch_issue_issues') || Schema::hasColumn('dispatch_issue_issues', 'resolved')) {
            return;
        }

        Schema::table('dispatch_issue_issues', function (Blueprint $table) {
            // 'yes' = resolved, 'no' (or null) = not resolved yet.
            $table->string('resolved', 3)->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('dispatch_issue_issues', 'resolved')) {
            Schema::table('dispatch_issue_issues', function (Blueprint $table) {
                $table->dropColumn('resolved');
            });
        }
    }
};