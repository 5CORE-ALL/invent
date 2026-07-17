<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mm_inventory_ledgers')) {
            return;
        }

        Schema::create('mm_inventory_ledgers', function (Blueprint $table) {
            $table->id();
            $table->string('store', 32)->default('main')->index();
            $table->string('sku')->index();
            $table->string('shopify_variant_id', 64)->nullable();
            $table->string('shopify_inventory_item_id', 64)->nullable();
            $table->string('location_id', 64)->nullable();
            $table->integer('on_hand')->default(0);
            $table->integer('available')->default(0);
            $table->unsignedBigInteger('version')->default(0);
            $table->string('source', 32)->default('bootstrap');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['store', 'sku']);
            // Non-unique index: item id may be null until resolve/bootstrap fills it.
            // Application enforces one row per non-null (store, inventory_item_id).
            $table->index(
                ['store', 'shopify_inventory_item_id'],
                'mm_inv_ledger_store_item_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_inventory_ledgers');
    }
};
