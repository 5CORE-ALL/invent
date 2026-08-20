<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('automate_task_checklist_forms')
            && ! Schema::hasColumn('automate_task_checklist_forms', 'cl_id')) {
            Schema::table('automate_task_checklist_forms', function (Blueprint $table) {
                $table->string('cl_id', 32)->nullable()->unique()->after('id');
            });
        }

        if (Schema::hasTable('automate_task_checklist_forms')
            && Schema::hasColumn('automate_task_checklist_forms', 'cl_id')) {
            $forms = DB::table('automate_task_checklist_forms')
                ->where(function ($q) {
                    $q->whereNull('cl_id')->orWhere('cl_id', '');
                })
                ->get(['id']);
            foreach ($forms as $form) {
                DB::table('automate_task_checklist_forms')
                    ->where('id', $form->id)
                    ->update(['cl_id' => 'CL-'.$form->id]);
            }
        }

        if (Schema::hasTable('tasks') && ! Schema::hasColumn('tasks', 'cl_id')) {
            Schema::table('tasks', function (Blueprint $table) {
                if (Schema::hasColumn('tasks', 'automate_task_id')) {
                    $table->string('cl_id', 32)->nullable()->index()->after('automate_task_id');
                } else {
                    $table->string('cl_id', 32)->nullable()->index();
                }
            });
        }

        if (Schema::hasTable('tasks')
            && Schema::hasColumn('tasks', 'cl_id')
            && Schema::hasTable('automate_task_checklist_forms')
            && Schema::hasColumn('automate_task_checklist_forms', 'cl_id')) {
            $forms = DB::table('automate_task_checklist_forms')
                ->whereNotNull('cl_id')
                ->where('cl_id', '!=', '')
                ->get(['automate_task_id', 'cl_id']);

            foreach ($forms as $form) {
                DB::table('tasks')
                    ->where('automate_task_id', $form->automate_task_id)
                    ->where(function ($q) {
                        $q->whereNull('cl_id')->orWhere('cl_id', '');
                    })
                    ->update(['cl_id' => $form->cl_id]);

                DB::table('tasks')
                    ->where('automate_task_id', $form->automate_task_id)
                    ->where(function ($q) {
                        $q->whereNull('link7')->orWhere('link7', '');
                    })
                    ->update(['link7' => $form->cl_id]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tasks') && Schema::hasColumn('tasks', 'cl_id')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropColumn('cl_id');
            });
        }

        if (Schema::hasTable('automate_task_checklist_forms')
            && Schema::hasColumn('automate_task_checklist_forms', 'cl_id')) {
            Schema::table('automate_task_checklist_forms', function (Blueprint $table) {
                $table->dropUnique(['cl_id']);
                $table->dropColumn('cl_id');
            });
        }
    }
};
