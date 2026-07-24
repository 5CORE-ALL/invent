<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temu_ads_api_reports', function (Blueprint $table) {
            $table->id();
            $table->string('goods_id')->index();
            $table->string('sku')->nullable()->index();
            $table->string('period', 10)->index(); // L7 | L30 | L60
            $table->unsignedBigInteger('start_ts')->nullable();
            $table->unsignedBigInteger('end_ts')->nullable();

            // Extracted from reportInfo.reportsSummary
            $table->unsignedBigInteger('impressions')->nullable();      // imprCntAll
            $table->unsignedBigInteger('clicks')->nullable();           // clkCntAll
            $table->decimal('ctr', 12, 4)->nullable();                  // ctrAll
            $table->unsignedBigInteger('cart_cnt')->nullable();         // cartCntAll
            $table->unsignedBigInteger('order_pay_cnt')->nullable();    // orderPayCntAll
            $table->decimal('order_pay_amt', 14, 4)->nullable();        // orderPayAmtAll
            $table->decimal('ad_spend', 14, 4)->nullable();             // adSpendAll
            $table->decimal('roas', 12, 4)->nullable();                 // roasAll
            $table->decimal('acos', 12, 4)->nullable();                 // acosAll

            // Full API result JSON (goodsInfo + reportInfo + reportsItemList)
            $table->longText('raw_response')->nullable();
            $table->boolean('success')->default(true);
            $table->string('error_msg', 500)->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->unique(['goods_id', 'period'], 'temu_ads_api_reports_goods_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temu_ads_api_reports');
    }
};
