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
        Schema::create('ebay_general_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_range'); // L30 / L60
            $table->string('campaign_id');
            $table->string('listing_id');
            $table->bigInteger('impressions')->nullable();
            $table->bigInteger('clicks')->nullable();
            $table->string('ad_fees', 191)->nullable();
            $table->bigInteger('sales')->nullable();
            $table->string('sale_amount', 191)->nullable();
            $table->bigInteger('ctr')->nullable();
            $table->string('avg_cost_per_sale', 191)->nullable();
            $table->string('channels', 191)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ebay_general_reports');
    }
};
