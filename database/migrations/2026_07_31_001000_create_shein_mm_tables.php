<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shein_metric')) {
            Schema::create('shein_metric', function (Blueprint $table) {
                $table->id();
                $table->string('product_id', 64)->nullable()->index();
                $table->string('sku', 128)->index();
                $table->string('product_name', 512)->nullable();
                $table->decimal('price', 12, 2)->nullable();
                $table->unsignedInteger('l30')->default(0);
                $table->unsignedInteger('l60')->default(0);
                $table->json('order_dates')->nullable();
                $table->timestamp('last_order_date')->nullable();
                $table->text('bullet_points')->nullable();
                $table->timestamps();
                $table->unique('sku', 'shein_metric_sku_unique');
            });
        }

        if (! Schema::hasTable('shein_order_metrics')) {
            Schema::create('shein_order_metrics', function (Blueprint $table) {
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
                $table->unique(['order_id', 'sku'], 'shein_order_metrics_order_sku_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shein_order_metrics');
        Schema::dropIfExists('shein_metric');
    }
};
