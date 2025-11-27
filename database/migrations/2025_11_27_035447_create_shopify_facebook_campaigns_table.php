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
        Schema::create('shopify_facebook_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_id', 100)->nullable();
            $table->string('campaign_name')->nullable();
            $table->enum('date_range', ['7_days', '30_days', '60_days']);
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('sales', 15, 2)->default(0);
            $table->integer('orders')->default(0);
            $table->integer('sessions')->default(0);
            $table->decimal('conversion_rate', 8, 4)->default(0);
            $table->decimal('ad_spend', 15, 2)->default(0);
            $table->decimal('roas', 8, 2)->default(0);
            $table->string('referring_channel')->default('facebook');
            $table->string('traffic_type')->default('paid');
            $table->string('country')->default('IN');
            $table->timestamps();
            
            $table->index(['campaign_id', 'date_range', 'start_date'], 'shopify_fb_camp_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopify_facebook_campaigns');
    }
};
