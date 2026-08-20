<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_live_sessions')) {
            return;
        }

        Schema::create('attendance_live_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('viewer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('attendance_session_id')->nullable()->constrained('attendance_sessions')->nullOnDelete();
            $table->string('status', 20)->default('requested'); // requested, streaming, ended
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('last_viewer_ping_at')->nullable();
            $table->timestamp('last_frame_at')->nullable();
            $table->unsignedInteger('frame_count')->default(0);
            $table->string('recording_path')->nullable();
            $table->string('recording_mime', 80)->nullable();
            $table->unsignedInteger('recording_size')->default(0);
            $table->unsignedInteger('recording_seconds')->default(0);
            $table->string('ended_reason', 40)->nullable();
            $table->string('window_title', 500)->nullable();
            $table->string('app_name', 200)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['viewer_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_live_sessions');
    }
};
