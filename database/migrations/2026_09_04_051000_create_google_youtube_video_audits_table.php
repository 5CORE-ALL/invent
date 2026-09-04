<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('google_youtube_video_audits')) {
            return;
        }

        Schema::create('google_youtube_video_audits', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_id', 64)->index();
            $table->string('campaign_name')->nullable();
            $table->json('checks');
            $table->unsignedTinyInteger('fail_count')->default(0);
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
        Schema::dropIfExists('google_youtube_video_audits');
    }
};
