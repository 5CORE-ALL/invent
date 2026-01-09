<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('meta_campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('ad_account_id')->nullable()->index();
            $table->string('meta_id', 191);
            $table->string('name')->nullable();
            $table->string('status', 50)->nullable();
            $table->string('effective_status', 50)->nullable();
            $table->string('objective', 100)->nullable();
            $table->decimal('daily_budget', 15, 2)->nullable();
            $table->decimal('lifetime_budget', 15, 2)->nullable();
            $table->decimal('budget_remaining', 15, 2)->nullable();
            $table->timestamp('start_time')->nullable();
            $table->timestamp('stop_time')->nullable();
            $table->string('buying_type', 50)->nullable();
            $table->string('bid_strategy', 50)->nullable();
            $table->json('special_ad_categories')->nullable();
            $table->timestamp('meta_updated_time')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->json('raw_json')->nullable();
            $table->timestamps();
            
            $table->unique(['user_id', 'meta_id']);
            $table->index(['ad_account_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meta_campaigns');
    }
};
