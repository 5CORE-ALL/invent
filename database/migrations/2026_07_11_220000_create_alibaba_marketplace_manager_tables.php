<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('alibaba_order_metrics')) {
            Schema::create('alibaba_order_metrics', function (Blueprint $table) {
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

                $table->unique(['order_id', 'sku'], 'alibaba_order_metrics_order_sku_unique');
            });
        }

        if (! Schema::hasTable('alibaba_pricing_prices')) {
            Schema::create('alibaba_pricing_prices', function (Blueprint $table) {
                $table->id();
                $table->string('sku', 128)->unique();
                $table->decimal('price', 12, 2)->nullable();
                $table->integer('ab_stock')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('alibaba_metrics')) {
            if (! Schema::hasColumn('alibaba_metrics', 'product_name')) {
                Schema::table('alibaba_metrics', function (Blueprint $table) {
                    $table->string('product_name')->nullable()->after('title');
                });
            }
            if (! Schema::hasColumn('alibaba_metrics', 'price')) {
                Schema::table('alibaba_metrics', function (Blueprint $table) {
                    $table->decimal('price', 12, 2)->nullable()->after('product_name');
                });
            }
            if (! Schema::hasColumn('alibaba_metrics', 'l30')) {
                Schema::table('alibaba_metrics', function (Blueprint $table) {
                    $table->unsignedInteger('l30')->nullable();
                });
            }
            if (! Schema::hasColumn('alibaba_metrics', 'l60')) {
                Schema::table('alibaba_metrics', function (Blueprint $table) {
                    $table->unsignedInteger('l60')->nullable();
                });
            }
            if (! Schema::hasColumn('alibaba_metrics', 'order_dates')) {
                Schema::table('alibaba_metrics', function (Blueprint $table) {
                    $table->json('order_dates')->nullable();
                });
            }
            if (! Schema::hasColumn('alibaba_metrics', 'last_order_date')) {
                Schema::table('alibaba_metrics', function (Blueprint $table) {
                    $table->dateTime('last_order_date')->nullable();
                });
            }
        }

        if (Schema::hasTable('product_stock_mappings') && ! Schema::hasColumn('product_stock_mappings', 'inventory_alibaba')) {
            Schema::table('product_stock_mappings', function (Blueprint $table) {
                $table->string('inventory_alibaba')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('alibaba_order_metrics');
        Schema::dropIfExists('alibaba_pricing_prices');

        if (Schema::hasTable('alibaba_metrics')) {
            foreach (['last_order_date', 'order_dates', 'l60', 'l30', 'price', 'product_name'] as $col) {
                if (Schema::hasColumn('alibaba_metrics', $col)) {
                    Schema::table('alibaba_metrics', function (Blueprint $table) use ($col) {
                        $table->dropColumn($col);
                    });
                }
            }
        }

        if (Schema::hasTable('product_stock_mappings') && Schema::hasColumn('product_stock_mappings', 'inventory_alibaba')) {
            Schema::table('product_stock_mappings', function (Blueprint $table) {
                $table->dropColumn('inventory_alibaba');
            });
        }
    }
};
