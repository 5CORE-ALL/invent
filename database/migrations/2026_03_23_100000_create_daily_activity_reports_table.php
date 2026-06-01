<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('daily_activity_reports')) {
            return;
        }

        Schema::create('daily_activity_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('channel_id');
            $table->date('report_date');
            $table->json('responsibilities');
            $table->text('comments')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->unique(['user_id', 'channel_id', 'report_date']);
            $table->index(['report_date', 'channel_id']);
        });

        if (Schema::hasTable('channel_master')) {
            Schema::table('daily_activity_reports', function (Blueprint $table) {
                $table->foreign('channel_id')->references('id')->on('channel_master')->cascadeOnDelete();
            });
        }

        $this->seedDummyIfPossible();
    }

    private function seedDummyIfPossible(): void
    {
        $userId = DB::table('users')->orderBy('id')->value('id');
        $channelIds = DB::table('channel_master')
            ->whereRaw('LOWER(TRIM(status)) = ?', ['active'])
            ->orderBy('id')
            ->limit(3)
            ->pluck('id');

        if (!$userId || $channelIds->isEmpty()) {
            return;
        }

        $sampleResp = json_encode([
            'messaging' => ['responded_to_customer_queries' => true, 'followed_up_pending_tickets' => true, 'cleared_inbox' => false],
            'returns_refunds' => ['processed_return_requests' => false, 'initiated_refunds' => false, 'verified_return_cases' => false],
            'escalations' => ['handled_escalations' => false, 'reported_critical_issues' => false],
            'general' => ['updated_crm' => true, 'internal_team_coordination' => true, 'other' => false, 'other_text' => ''],
        ]);

        foreach ($channelIds as $idx => $chId) {
            for ($d = 1; $d <= 10; $d++) {
                $date = now()->subDays($d + $idx)->toDateString();
                if (DB::table('daily_activity_reports')->where('user_id', $userId)->where('channel_id', $chId)->where('report_date', $date)->exists()) {
                    continue;
                }
                $sub = now()->subDays($d)->setTime(16, 15 + ($d % 30), 0);
                DB::table('daily_activity_reports')->insert([
                    'user_id' => $userId,
                    'channel_id' => $chId,
                    'report_date' => $date,
                    'responsibilities' => $sampleResp,
                    'comments' => 'Sample DAR entry',
                    'submitted_at' => $sub,
                    'created_at' => $sub,
                    'updated_at' => $sub,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_activity_reports');
    }
};
