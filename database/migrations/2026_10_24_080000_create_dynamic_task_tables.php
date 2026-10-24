<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dynamic_task_rules')) {
            Schema::create('dynamic_task_rules', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150);
                $table->string('event_key', 80);
                // Optional condition: <metric> <operator> <threshold>. NULL metric = always fire.
                $table->string('metric', 80)->nullable();
                $table->string('operator', 4)->nullable();
                $table->decimal('threshold', 14, 2)->nullable();
                // Pre-saved task template (supports {placeholders}).
                $table->string('title', 190);
                $table->text('description')->nullable();
                $table->string('task_group', 120)->nullable();
                $table->string('priority', 10)->default('normal'); // low | normal | high
                $table->unsignedInteger('eta_minutes')->default(10);
                $table->unsignedSmallInteger('tid_in_days')->default(0); // TID = today + N days
                $table->string('link', 2048)->nullable();
                $table->json('recipient_user_ids');
                $table->string('frequency', 10)->default('daily'); // once | daily | weekly
                $table->boolean('skip_if_open')->default(true);
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamp('last_run_at')->nullable();
                $table->timestamps();

                $table->index(['is_active', 'event_key']);
            });
        }

        if (! Schema::hasTable('dynamic_task_creations')) {
            Schema::create('dynamic_task_creations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('rule_id')->nullable();
                $table->unsignedBigInteger('assignee_user_id');
                $table->unsignedBigInteger('task_id')->nullable();
                $table->string('title', 190)->nullable();
                $table->string('dedupe_key', 120);
                $table->json('context')->nullable();
                $table->timestamps();

                $table->unique(['rule_id', 'assignee_user_id', 'dedupe_key'], 'dyn_task_unique_creation');
                $table->index('task_id');
                $table->foreign('rule_id')->references('id')->on('dynamic_task_rules')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dynamic_task_creations');
        Schema::dropIfExists('dynamic_task_rules');
    }
};
