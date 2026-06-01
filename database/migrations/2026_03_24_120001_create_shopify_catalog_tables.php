<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shopify_catalog_products')) {
            Schema::create('shopify_catalog_products', function (Blueprint $table) {
                $table->id();
                $table->string('store', 32)->index()->comment('main or pls');
                $table->unsignedBigInteger('shopify_id')->comment('Shopify product id');
                $table->string('title')->nullable();
                $table->string('handle')->nullable();
                $table->string('status', 32)->nullable();
                $table->text('body_html')->nullable();
                $table->string('vendor')->nullable();
                $table->string('product_type')->nullable();
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();

                $table->unique(['store', 'shopify_id']);
            });
        }

        if (! Schema::hasTable('shopify_catalog_variants')) {
            Schema::create('shopify_catalog_variants', function (Blueprint $table) {
                $table->id();
                $table->string('store', 32)->index();
                $table->foreignId('shopify_catalog_product_id')
                    ->constrained('shopify_catalog_products')
                    ->cascadeOnDelete();
                $table->unsignedBigInteger('shopify_variant_id')->comment('Shopify variant id');
                $table->unsignedBigInteger('shopify_product_id')->comment('Shopify product id (denormalized)');
                $table->string('sku')->nullable()->index();
                $table->string('variant_title')->nullable();
                $table->decimal('price', 12, 2)->nullable();
                $table->integer('position')->nullable();
                $table->integer('inventory_quantity')->nullable();
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();

                $table->unique(['store', 'shopify_variant_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_catalog_variants');
        Schema::dropIfExists('shopify_catalog_products');
    }
};
