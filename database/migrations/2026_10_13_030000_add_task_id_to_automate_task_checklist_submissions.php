<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tie a checklist submission to the fired task that was marked Done,
 * so the assignor can reopen that task and still see the ticked answers.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('automate_task_checklist_submissions')) {
            return;
        }

        if (! Schema::hasColumn('automate_task_checklist_submissions', 'task_id')) {
            Schema::table('automate_task_checklist_submissions', function (Blueprint $table) {
                $table->unsignedBigInteger('task_id')->nullable()->index()->after('automate_task_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('automate_task_checklist_submissions')) {
            return;
        }

        if (Schema::hasColumn('automate_task_checklist_submissions', 'task_id')) {
            Schema::table('automate_task_checklist_submissions', function (Blueprint $table) {
                $table->dropIndex(['task_id']);
                $table->dropColumn('task_id');
            });
        }
    }
};
