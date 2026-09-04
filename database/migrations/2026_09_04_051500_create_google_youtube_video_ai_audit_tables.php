<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('google_youtube_video_ai_prompts')) {
            Schema::create('google_youtube_video_ai_prompts', function (Blueprint $table) {
                $table->id();
                $table->longText('prompt');
                $table->unsignedBigInteger('saved_by')->nullable()->index();
                $table->string('saved_by_name')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('google_youtube_video_ai_audits')) {
            Schema::create('google_youtube_video_ai_audits', function (Blueprint $table) {
                $table->id();
                $table->string('campaign_id', 64)->index();
                $table->string('campaign_name')->nullable();
                $table->string('video_url', 1024)->nullable();
                $table->longText('prompt_used')->nullable();
                $table->json('result');
                $table->unsignedTinyInteger('fail_count')->default(0);
                $table->string('model', 80)->nullable();
                $table->unsignedBigInteger('audited_by')->nullable()->index();
                $table->string('audited_by_name')->nullable();
                $table->timestamp('audited_at')->useCurrent();
                $table->timestamps();
                $table->index(['campaign_id', 'audited_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('google_youtube_video_ai_audits');
        Schema::dropIfExists('google_youtube_video_ai_prompts');
    }
};
