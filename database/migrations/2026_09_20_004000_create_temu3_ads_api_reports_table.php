<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('temu3_ads_api_reports')) {
            return;
        }

        Schema::create('temu3_ads_api_reports', function (Blueprint $table) {
            $table->id();
            $table->string('goods_id')->index();
            $table->string('sku')->nullable()->index();
            $table->string('period', 10)->index();
            $table->unsignedBigInteger('start_ts')->nullable();
            $table->unsignedBigInteger('end_ts')->nullable();
            $table->unsignedBigInteger('impressions')->nullable();
            $table->unsignedBigInteger('clicks')->nullable();
            $table->decimal('ctr', 12, 4)->nullable();
            $table->unsignedBigInteger('cart_cnt')->nullable();
            $table->unsignedBigInteger('order_pay_cnt')->nullable();
            $table->decimal('order_pay_amt', 14, 4)->nullable();
            $table->decimal('ad_spend', 14, 4)->nullable();
            $table->decimal('roas', 12, 4)->nullable();
            $table->decimal('acos', 12, 4)->nullable();
            $table->string('ad_status', 32)->nullable()->index();
            $table->longText('raw_response')->nullable();
            $table->boolean('success')->default(true);
            $table->string('error_msg', 500)->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
            $table->unique(['goods_id', 'period'], 'temu3_ads_api_reports_goods_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temu3_ads_api_reports');
    }
};
