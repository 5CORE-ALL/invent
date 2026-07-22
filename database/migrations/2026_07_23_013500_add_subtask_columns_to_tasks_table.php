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
        if (! Schema::hasColumn('tasks', 'parent_task_id')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->unsignedBigInteger('parent_task_id')->nullable()->after('id')->index();
                $table->unsignedSmallInteger('subtask_order')->default(0)->after('parent_task_id');
            });
        }

        if (! Schema::hasColumn('deleted_tasks', 'parent_task_id')) {
            Schema::table('deleted_tasks', function (Blueprint $table) {
                $table->unsignedBigInteger('parent_task_id')->nullable()->after('original_task_id')->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('tasks', 'parent_task_id')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropColumn(['parent_task_id', 'subtask_order']);
            });
        }

        if (Schema::hasColumn('deleted_tasks', 'parent_task_id')) {
            Schema::table('deleted_tasks', function (Blueprint $table) {
                $table->dropColumn('parent_task_id');
            });
        }
    }
};
