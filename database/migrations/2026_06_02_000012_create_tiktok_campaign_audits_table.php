<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit log for TikTok campaigns viewed on /tiktok-video-ads. Each row is
 * one snapshot of "did this campaign pass each checklist item at this
 * point in time", plus the auditor, timestamp and any free-form comment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiktok_campaign_audits', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_id', 64)->index();
            $table->string('campaign_name')->nullable();
            $table->json('checks');
            $table->unsignedTinyInteger('score_pct')->default(0);
            $table->text('comments')->nullable();
            $table->unsignedBigInteger('audited_by')->nullable()->index();
            $table->string('audited_by_name')->nullable();
            $table->timestamp('audited_at')->useCurrent();
            $table->timestamps();

            $table->index(['campaign_id', 'audited_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiktok_campaign_audits');
    }
};
