<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every overdue reading (hourly job + page visits), kept for the rolling O-Due graph on /tasks/summary.
 * task_overdue_history keeps one value per day (used by the arrow); this table keeps the points of the line.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('task_overdue_readings')) {
            return;
        }

        Schema::create('task_overdue_readings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedInteger('overdue')->default(0);
            $table->timestamp('captured_at')->useCurrent();

            $table->index(['user_id', 'captured_at'], 'task_overdue_readings_user_time_idx');
            $table->index('captured_at', 'task_overdue_readings_time_idx');
        });

        // Keep what the daily table already has as the first points of the line.
        if (Schema::hasTable('task_overdue_history')) {
            DB::table('task_overdue_history')
                ->whereNotNull('captured_at')
                ->orderBy('id')
                ->chunkById(500, function ($rows) {
                    $payload = [];
                    foreach ($rows as $r) {
                        $payload[] = [
                            'user_id' => (int) $r->user_id,
                            'overdue' => (int) $r->overdue,
                            'captured_at' => $r->captured_at,
                        ];
                    }
                    if ($payload !== []) {
                        DB::table('task_overdue_readings')->insert($payload);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('task_overdue_readings');
    }
};
