<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('amazon_ads_pause_rule_states')) {
            return;
        }

        Schema::create('amazon_ads_pause_rule_states', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 8);
            $table->string('campaign_id', 64);
            $table->string('campaign_name')->nullable();
            $table->string('paused_reason', 500)->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('reactivated_at')->nullable();
            $table->timestamps();
            $table->unique(['channel', 'campaign_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amazon_ads_pause_rule_states');
    }
};
