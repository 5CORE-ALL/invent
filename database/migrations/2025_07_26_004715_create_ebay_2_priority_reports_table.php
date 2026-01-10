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
        Schema::create('ebay_2_priority_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_range', 10); // L30 / L60
            $table->string('campaign_id');
            $table->bigInteger('cpc_impressions')->nullable();
            $table->bigInteger('cpc_clicks')->nullable();
            $table->bigInteger('cpc_attributed_sales')->nullable();
            $table->bigInteger('cpc_ctr')->nullable();
            $table->string('cpc_ad_fees_listingsite_currency', 191)->nullable();
            $table->string('cpc_sale_amount_listingsite_currency', 191)->nullable();
            $table->string('cpc_avg_cost_per_sale', 191)->nullable();
            $table->decimal('cpc_return_on_ad_spend', 10, 2)->nullable();            
            $table->decimal('cpc_conversion_rate', 10, 2)->nullable();
            $table->string('cpc_sale_amount_payout_currency', 191)->nullable();
            $table->string('cost_per_click', 191)->nullable();
            $table->string('cpc_ad_fees_payout_currency', 191)->nullable();
            $table->string('channels', 191)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ebay_2_priority_reports');
    }
};
