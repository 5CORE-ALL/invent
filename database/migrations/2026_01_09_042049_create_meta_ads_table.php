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
        Schema::create('meta_ads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('ad_account_id')->nullable()->index();
            $table->unsignedBigInteger('campaign_id')->nullable()->index();
            $table->unsignedBigInteger('adset_id')->nullable()->index();
            $table->string('meta_id', 191);
            $table->string('name')->nullable();
            $table->string('status', 50)->nullable();
            $table->string('effective_status', 50)->nullable();
            $table->string('creative_id', 191)->nullable();
            $table->string('preview_shareable_link')->nullable();
            $table->timestamp('meta_updated_time')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->json('raw_json')->nullable();
            $table->timestamps();
            
            $table->unique(['user_id', 'meta_id']);
            $table->index(['adset_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meta_ads');
    }
};
