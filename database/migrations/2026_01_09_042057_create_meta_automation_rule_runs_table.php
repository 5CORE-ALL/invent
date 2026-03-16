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
        Schema::create('meta_automation_rule_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rule_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('status', 50)->index(); // running, completed, failed
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->integer('entities_evaluated')->default(0);
            $table->integer('conditions_matched')->default(0);
            $table->integer('actions_executed')->default(0);
            $table->boolean('dry_run')->default(false);
            $table->text('error_message')->nullable();
            $table->json('execution_log')->nullable(); // Detailed log of what was evaluated
            $table->timestamps();
            
            $table->index(['rule_id', 'started_at']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meta_automation_rule_runs');
    }
};
