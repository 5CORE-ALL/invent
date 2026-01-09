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
        Schema::create('meta_automation_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('entity_type', 50)->index(); // campaign, adset, ad
            $table->json('conditions')->nullable(); // Array of condition objects
            $table->json('actions')->nullable(); // Array of action objects
            $table->boolean('is_active')->default(true)->index();
            $table->string('schedule', 100)->nullable(); // cron expression or daily, hourly, etc.
            $table->boolean('dry_run_mode')->default(false);
            $table->timestamp('last_run_at')->nullable();
            $table->integer('total_runs')->default(0);
            $table->integer('total_actions_taken')->default(0);
            $table->timestamps();
            
            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meta_automation_rules');
    }
};
