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
        Schema::create('meta_insights_daily', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('entity_type', 50)->index(); // account, campaign, adset, ad
            $table->unsignedBigInteger('entity_id')->index();
            $table->date('date_start')->index();
            $table->string('breakdown_hash', 64)->nullable()->index(); // hash of breakdowns for uniqueness
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('reach')->default(0);
            $table->decimal('spend', 15, 2)->default(0);
            $table->decimal('ctr', 8, 4)->default(0);
            $table->decimal('cpc', 10, 4)->default(0);
            $table->decimal('cpm', 10, 4)->default(0);
            $table->decimal('cpp', 10, 4)->default(0);
            $table->decimal('frequency', 8, 4)->default(0);
            $table->unsignedBigInteger('actions_count')->default(0);
            $table->json('actions')->nullable(); // Array of action types
            $table->decimal('action_values', 15, 2)->default(0);
            $table->json('action_values_breakdown')->nullable();
            $table->unsignedBigInteger('purchases')->default(0);
            $table->decimal('purchase_roas', 10, 4)->default(0);
            $table->decimal('cpa', 10, 4)->default(0);
            $table->json('breakdowns_json')->nullable(); // Store breakdown dimensions
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            
            $table->unique(['user_id', 'entity_type', 'entity_id', 'date_start', 'breakdown_hash'], 'insights_unique');
            $table->index(['entity_type', 'entity_id', 'date_start']);
            $table->index(['date_start', 'entity_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meta_insights_daily');
    }
};
