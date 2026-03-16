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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('group')->nullable();
            $table->enum('priority', ['low', 'normal', 'high'])->default('normal');
            $table->foreignId('assignor_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('assignee_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->boolean('split_tasks')->default(false);
            $table->boolean('flag_raise')->default(false);
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->integer('etc_minutes')->nullable();
            $table->dateTime('tid')->nullable();
            $table->string('l1')->nullable();
            $table->string('l2')->nullable();
            $table->text('training_link')->nullable();
            $table->text('video_link')->nullable();
            $table->text('form_link')->nullable();
            $table->text('form_report_link')->nullable();
            $table->text('checklist_link')->nullable();
            $table->text('pl')->nullable();
            $table->text('process')->nullable();
            $table->string('image')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
