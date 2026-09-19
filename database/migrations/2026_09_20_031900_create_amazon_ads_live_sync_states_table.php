<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('amazon_ads_live_sync_states')) {
            return;
        }

        Schema::create('amazon_ads_live_sync_states', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 8);
            $table->string('field', 8);
            $table->string('campaign_id', 100);
            $table->string('campaign_name', 255)->nullable();
            $table->decimal('desired_value', 10, 2)->nullable();
            $table->decimal('live_value', 10, 2)->nullable();
            $table->string('status', 24)->default('pending');
            $table->text('reason')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->json('detail')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->unique(['channel', 'field', 'campaign_id'], 'amz_live_sync_unique');
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amazon_ads_live_sync_states');
    }
};
