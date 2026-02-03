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
        Schema::create('deleted_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_task_id')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('group')->nullable();
            $table->string('priority')->nullable();
            $table->string('status')->nullable();
            $table->string('assignor')->nullable();
            $table->string('assign_to')->nullable();
            $table->string('assignor_name')->nullable();
            $table->string('assignee_name')->nullable();
            $table->integer('eta_time')->nullable();
            $table->integer('etc_done')->nullable();
            $table->dateTime('start_date')->nullable();
            $table->dateTime('completion_date')->nullable();
            $table->integer('completion_day')->nullable();
            $table->tinyInteger('split_tasks')->default(0);
            $table->tinyInteger('is_missed')->default(0);
            $table->tinyInteger('is_missed_track')->default(0);
            $table->string('link1')->nullable();
            $table->string('link2')->nullable();
            $table->string('link3')->nullable();
            $table->string('link4')->nullable();
            $table->string('link5')->nullable();
            $table->string('link6')->nullable();
            $table->string('link7')->nullable();
            $table->string('link8')->nullable();
            $table->string('link9')->nullable();
            $table->string('image')->nullable();
            $table->string('task_type')->nullable();
            $table->text('rework_reason')->nullable();
            
            // Deletion tracking fields
            $table->string('deleted_by_email')->nullable();
            $table->string('deleted_by_name')->nullable();
            $table->dateTime('deleted_at')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deleted_tasks');
    }
};
