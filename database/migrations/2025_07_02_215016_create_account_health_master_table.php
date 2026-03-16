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
        if (!Schema::hasTable('account_health_master')) {
            Schema::create('account_health_master', function (Blueprint $table) {
                $table->id();

                $table->string('channel');
                $table->integer('l30_sales')->nullable();
                $table->integer('l30_orders')->nullable();
                $table->text('account_health_links')->nullable();
                $table->text('remarks')->nullable();
                $table->string('pre_fulfillment_cancel_rate')->nullable();
                $table->string('odr')->nullable();
                $table->string('fulfillment_rate')->nullable();
                $table->string('late_shipment_rate')->nullable();
                $table->string('valid_tracking_rate')->nullable();
                $table->string('on_time_delivery_rate')->nullable();
                $table->string('negative_feedback')->nullable();
                $table->string('positive_feedback')->nullable();
                $table->string('guarantee_claims')->nullable();
                $table->string('refund_rate')->nullable();
                $table->string('avg_processing_time')->nullable();
                $table->string('message_time')->nullable();
                $table->string('overall')->nullable();
                $table->date('report_date')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_health_master');
    }
};
