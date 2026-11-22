<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Money TikTok actually paid the seller per order, from the Finance API
     * (statement_transactions). One row per statement transaction; an order can have
     * an ORDER row and later REFUND rows, so the page sums them per order.
     *
     * Separate table on purpose: tiktok_orders is the Orders API snapshot (customer-side prices)
     * and is overwritten on every fetch.
     */
    public function up(): void
    {
        if (Schema::hasTable('tiktok_order_settlements')) {
            return;
        }

        Schema::create('tiktok_order_settlements', function (Blueprint $table) {
            $table->id();

            $table->string('channel', 16)->default('tiktok'); // tiktok | tiktok2
            $table->string('transaction_key', 64)->unique();  // sha1(channel|statement|type|tx id)
            $table->string('order_id', 64)->index();
            $table->string('transaction_type', 64)->nullable();
            $table->string('statement_id', 64)->nullable()->index();
            $table->timestamp('statement_time')->nullable();
            $table->string('payment_status', 32)->nullable();

            // settlement_amount = net payout for the order ("What you earned" in Seller Center).
            $table->decimal('settlement_amount', 12, 2)->nullable();
            $table->decimal('revenue_amount', 12, 2)->nullable();
            $table->decimal('fee_amount', 12, 2)->nullable();
            $table->decimal('shipping_cost_amount', 12, 2)->nullable();
            $table->string('currency', 16)->nullable();

            $table->longText('raw_json')->nullable();
            $table->timestamps();

            $table->index(['channel', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiktok_order_settlements');
    }
};
