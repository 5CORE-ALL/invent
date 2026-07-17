<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unique checklist questionnaire per automated task template,
 * plus submission history for the Report column.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('automate_task_checklist_forms')) {
            Schema::create('automate_task_checklist_forms', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('automate_task_id')->unique();
                $table->string('title', 255)->nullable();
                $table->json('questions')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->index('created_by');
            });
        }

        if (! Schema::hasTable('automate_task_checklist_submissions')) {
            Schema::create('automate_task_checklist_submissions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('form_id');
                $table->unsignedBigInteger('automate_task_id')->index();
                $table->unsignedBigInteger('submitted_by')->nullable()->index();
                $table->json('answers')->nullable();
                $table->timestamp('submitted_at')->useCurrent();
                $table->timestamps();

                $table->foreign('form_id')
                    ->references('id')
                    ->on('automate_task_checklist_forms')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('automate_task_checklist_submissions');
        Schema::dropIfExists('automate_task_checklist_forms');
    }
};
