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
        if (! Schema::hasColumn('automate_tasks', 'parent_task_id')) {
            Schema::table('automate_tasks', function (Blueprint $table) {
                $table->unsignedBigInteger('parent_task_id')->nullable()->after('id')->index();
                $table->unsignedSmallInteger('subtask_order')->default(0)->after('parent_task_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('automate_tasks', 'parent_task_id')) {
            Schema::table('automate_tasks', function (Blueprint $table) {
                $table->dropColumn(['parent_task_id', 'subtask_order']);
            });
        }
    }
};
