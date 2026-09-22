<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('google_shopping_live_syncs')) {
            return;
        }

        Schema::create('google_shopping_live_syncs', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 32)->default('shopping');
            $table->string('campaign_id', 32);
            $table->decimal('live_bid', 12, 4)->nullable();
            $table->boolean('bid_fetch_ok')->default(false);
            $table->string('bid_fetch_error', 180)->nullable();
            $table->timestamp('bid_fetched_at')->nullable();
            $table->boolean('bid_push_ok')->default(false);
            $table->decimal('bid_pushed_value', 12, 4)->nullable();
            $table->timestamp('bid_pushed_at')->nullable();
            $table->decimal('live_bgt', 12, 2)->nullable();
            $table->boolean('bgt_fetch_ok')->default(false);
            $table->string('bgt_fetch_error', 180)->nullable();
            $table->timestamp('bgt_fetched_at')->nullable();
            $table->boolean('bgt_push_ok')->default(false);
            $table->decimal('bgt_pushed_value', 12, 2)->nullable();
            $table->timestamp('bgt_pushed_at')->nullable();
            $table->timestamps();
            $table->unique(['channel', 'campaign_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_shopping_live_syncs');
    }
};
