<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Track each Amazon product sync job for monitoring and debugging.
     */
    public function up(): void
    {
        if (Schema::hasTable('amazon_sync_history')) {
            return;
        }

        Schema::create('amazon_sync_history', function (Blueprint $table) {
            $table->id();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->string('status', 20)->default('running'); // running, success, failed, partial
            $table->integer('records_fetched')->default(0);
            $table->integer('records_updated')->default(0);
            $table->integer('records_created')->default(0);
            $table->integer('records_skipped')->default(0);
            $table->integer('api_calls_count')->default(0);
            $table->integer('retry_count')->default(0);
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable(); // page counts, rate limits, etc.
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amazon_sync_history');
    }
};
