<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates daily_activity_reports when missing (e.g. migrate never reached this migration).
 */
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
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_activity_reports');
    }
};
