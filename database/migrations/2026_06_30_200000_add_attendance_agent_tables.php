<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendance_devices')) {
            Schema::create('attendance_devices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('machine_id', 120);
                $table->string('device_name')->nullable();
                $table->string('os_name', 50)->nullable();
                $table->string('os_version', 100)->nullable();
                $table->string('agent_version', 30)->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['user_id', 'machine_id']);
            });
        }

        if (! Schema::hasTable('attendance_screenshots')) {
            Schema::create('attendance_screenshots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('attendance_session_id')->constrained('attendance_sessions')->cascadeOnDelete();
                $table->foreignId('attendance_device_id')->nullable()->constrained('attendance_devices')->nullOnDelete();
                $table->timestamp('captured_at');
                $table->string('storage_path');
                $table->string('thumbnail_path')->nullable();
                $table->string('window_title', 500)->nullable();
                $table->string('app_name', 200)->nullable();
                $table->unsignedInteger('file_size')->default(0);
                $table->unsignedSmallInteger('idle_seconds')->default(0);
                $table->timestamps();

                $table->index(['user_id', 'captured_at']);
                $table->index(['attendance_session_id', 'captured_at']);
            });
        }

        if (Schema::hasTable('attendance_sessions') && ! Schema::hasColumn('attendance_sessions', 'attendance_device_id')) {
            Schema::table('attendance_sessions', function (Blueprint $table) {
                $table->foreignId('attendance_device_id')->nullable()->after('user_id')->constrained('attendance_devices')->nullOnDelete();
                $table->string('clock_source', 20)->default('web')->after('work_location');
            });
        }

        if (Schema::hasTable('attendance_activity_logs')) {
            Schema::table('attendance_activity_logs', function (Blueprint $table) {
                if (! Schema::hasColumn('attendance_activity_logs', 'source')) {
                    $table->string('source', 20)->default('web')->after('page_url');
                }
                if (! Schema::hasColumn('attendance_activity_logs', 'app_name')) {
                    $table->string('app_name', 200)->nullable()->after('source');
                }
                if (! Schema::hasColumn('attendance_activity_logs', 'process_name')) {
                    $table->string('process_name', 200)->nullable()->after('app_name');
                }
                if (! Schema::hasColumn('attendance_activity_logs', 'attendance_device_id')) {
                    $table->foreignId('attendance_device_id')->nullable()->after('process_name')->constrained('attendance_devices')->nullOnDelete();
                }
                if (! Schema::hasColumn('attendance_activity_logs', 'keystroke_count')) {
                    $table->unsignedSmallInteger('keystroke_count')->default(0)->after('attendance_device_id');
                }
                if (! Schema::hasColumn('attendance_activity_logs', 'mouse_click_count')) {
                    $table->unsignedSmallInteger('mouse_click_count')->default(0)->after('keystroke_count');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('attendance_activity_logs')) {
            Schema::table('attendance_activity_logs', function (Blueprint $table) {
                foreach (['mouse_click_count', 'keystroke_count', 'attendance_device_id', 'process_name', 'app_name', 'source'] as $col) {
                    if (Schema::hasColumn('attendance_activity_logs', $col)) {
                        if ($col === 'attendance_device_id') {
                            $table->dropConstrainedForeignId('attendance_device_id');
                        } else {
                            $table->dropColumn($col);
                        }
                    }
                }
            });
        }

        if (Schema::hasTable('attendance_sessions') && Schema::hasColumn('attendance_sessions', 'attendance_device_id')) {
            Schema::table('attendance_sessions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('attendance_device_id');
                $table->dropColumn('clock_source');
            });
        }

        Schema::dropIfExists('attendance_screenshots');
        Schema::dropIfExists('attendance_devices');
    }
};
