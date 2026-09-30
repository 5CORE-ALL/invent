<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_fulfillment_manual_orders')) {
            return;
        }

        Schema::create('order_fulfillment_manual_orders', function (Blueprint $table) {
            $table->id();
            $table->string('marketplace', 128);
            $table->string('order_id', 128);
            $table->dateTime('order_date');
            $table->string('sku', 191);
            $table->unsignedInteger('qty')->default(1);
            $table->boolean('paid')->default(true);
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('reference', 128)->nullable();
            $table->string('customer_name', 191)->nullable();
            $table->string('customer_email', 191)->nullable();
            $table->string('customer_phone', 64)->nullable();
            $table->string('address1', 191)->nullable();
            $table->string('address2', 191)->nullable();
            $table->string('city', 128)->nullable();
            $table->string('state', 128)->nullable();
            $table->string('zip', 32)->nullable();
            $table->string('country', 64)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 32)->default('Order Created');
            $table->timestamp('fulfilled_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('fulfilled_by')->nullable();
            $table->timestamps();

            $table->index(['marketplace', 'order_id'], 'of_manual_mp_order_idx');
            $table->index('order_date', 'of_manual_order_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_fulfillment_manual_orders');
    }
};
