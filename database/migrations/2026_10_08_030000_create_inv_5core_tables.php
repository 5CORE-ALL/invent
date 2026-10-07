<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inv_5core_balances')) {
            Schema::create('inv_5core_balances', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_master_id')->nullable()->index();
                $table->string('sku', 191)->unique();
                $table->string('sku_compact', 191)->nullable()->index();
                $table->decimal('opening_qty', 12, 2)->nullable();
                $table->timestamp('opening_seeded_at')->nullable();
                $table->unsignedBigInteger('sales_after_order_id')->nullable();
                $table->unsignedBigInteger('sales_after_manual_id')->nullable();
                $table->decimal('qty_on_hand', 12, 2)->nullable();
                $table->decimal('l30_sold', 12, 2)->default(0);
                $table->boolean('shopify_locked')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('inv_5core_transactions')) {
            Schema::create('inv_5core_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('balance_id')->nullable()->index();
                $table->unsignedBigInteger('product_master_id')->nullable()->index();
                $table->string('sku', 191)->index();
                $table->string('txn_type', 32)->index();
                $table->decimal('qty_delta', 12, 2);
                $table->decimal('qty_before', 12, 2);
                $table->decimal('qty_after', 12, 2);
                $table->string('source', 64);
                $table->unsignedBigInteger('source_id')->nullable();
                $table->string('reference', 128)->nullable();
                $table->string('channel', 64)->nullable();
                $table->text('detail')->nullable();
                $table->timestamp('occurred_at')->nullable()->index();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(['source', 'source_id'], 'inv_5core_txn_source_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inv_5core_transactions');
        Schema::dropIfExists('inv_5core_balances');
    }
};
