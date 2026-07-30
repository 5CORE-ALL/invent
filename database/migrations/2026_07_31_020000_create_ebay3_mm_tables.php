<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reuse existing ebay_3_metrics as MM link map (item_id = product_id).
        if (! Schema::hasTable('ebay3_order_metrics')) {
            Schema::create('ebay3_order_metrics', function (Blueprint $table) {
                $table->id();
                $table->string('order_id', 64)->index();
                $table->string('order_number', 64)->nullable()->index();
                $table->dateTime('order_date')->nullable()->index();
                $table->string('status', 64)->nullable();
                $table->string('sku', 128)->nullable()->index();
                $table->string('product_id', 64)->nullable();
                $table->string('display_title', 512)->nullable();
                $table->unsignedInteger('quantity')->default(1);
                $table->decimal('amount', 12, 2)->nullable();
                $table->string('shopify_order_id', 64)->nullable()->index();
                $table->timestamp('pushed_to_shopify_at')->nullable();
                $table->string('import_status', 32)->nullable()->index();
                $table->json('raw_payload')->nullable();
                $table->timestamps();
                $table->unique(['order_id', 'sku'], 'ebay3_order_metrics_order_sku_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ebay3_order_metrics');
    }
};
