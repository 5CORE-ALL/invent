<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Veeqo / Amazon Buy Shipping label adjustments from Amazon Finances
 * (LabmanLabelChargeBack, LabmanLabelReturn, ...). Veeqo's API has no chargeback endpoint.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('veeqo_chargebacks')) {
            return;
        }

        Schema::create('veeqo_chargebacks', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id', 128)->unique();
            $table->string('transaction_type', 64)->nullable();
            $table->string('description', 100)->nullable()->index();
            $table->string('transaction_status', 32)->nullable();
            $table->timestamp('posted_at')->nullable()->index();
            $table->string('amazon_order_id', 64)->nullable()->index();
            $table->string('settlement_id', 64)->nullable();
            $table->string('financial_event_group_id', 128)->nullable();
            $table->string('marketplace_name', 100)->nullable();
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->string('currency', 10)->nullable();
            $table->string('direction', 10)->nullable()->index();
            $table->string('item_types', 500)->nullable();
            $table->decimal('customer_charge', 12, 2)->nullable();
            $table->decimal('carrier_charge', 12, 2)->nullable();
            $table->json('components')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('veeqo_chargebacks');
    }
};
