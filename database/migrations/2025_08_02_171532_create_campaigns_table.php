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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('state')->nullable();
            $table->string('campaigns')->nullable();
            $table->string('country')->nullable();
            $table->string('status')->nullable();
            $table->string('type')->nullable();
            $table->string('targeting')->nullable();
            $table->string('retailer')->nullable();
            $table->string('portfolio')->nullable();
            $table->string('campaign_bidding_strategy')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->double('budget_converted')->nullable();
            $table->double('budget')->nullable();
            $table->string('cost_type')->nullable();
            $table->bigInteger('impressions')->nullable();
            $table->double('top_of_search_impression_share')->nullable();
            $table->double('top_of_search_bid_adjustment')->nullable();
            $table->bigInteger('clicks')->nullable();
            $table->double('ctr')->nullable();
            $table->double('spend_converted')->nullable();
            $table->double('spend')->nullable();
            $table->double('cpc_converted')->nullable();
            $table->double('cpc')->nullable();
            $table->bigInteger('detail_page_views')->nullable();
            $table->bigInteger('orders')->nullable();
            $table->double('sales_converted')->nullable();
            $table->double('sales')->nullable();
            $table->double('acos')->nullable();
            $table->double('roas')->nullable();
            $table->bigInteger('ntb_orders')->nullable();
            $table->double('percent_orders_ntb')->nullable();
            $table->double('ntb_sales_converted')->nullable();
            $table->double('ntb_sales')->nullable();
            $table->double('percent_sales_ntb')->nullable();
            $table->double('long_term_sales_converted')->nullable();
            $table->double('long_term_sales')->nullable();
            $table->double('long_term_roas')->nullable();
            $table->bigInteger('cumulative_reach')->nullable();
            $table->bigInteger('household_reach')->nullable();
            $table->bigInteger('viewable_impressions')->nullable();
            $table->double('cpm_converted')->nullable();
            $table->double('cpm')->nullable();
            $table->double('vcpm_converted')->nullable();
            $table->double('vcpm')->nullable();
            $table->integer('video_first_quartile')->nullable();
            $table->integer('video_midpoint')->nullable();
            $table->integer('video_third_quartile')->nullable();
            $table->integer('video_complete')->nullable();
            $table->integer('video_unmute')->nullable();
            $table->double('vtr')->nullable();
            $table->double('vctr')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
