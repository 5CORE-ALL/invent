<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per marketplace order ref that is being (or was) created on Shopify.
     * The unique key lets only one process create a given marketplace order; the
     * Shopify id is kept so a later import links instead of trusting Shopify search,
     * which does not show a just-created order for a while.
     */
    public function up(): void
    {
        if (Schema::hasTable('marketplace_shopify_order_claims')) {
            return;
        }

        Schema::create('marketplace_shopify_order_claims', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 64);
            $table->string('ref', 191);
            $table->string('shopify_order_id', 64)->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            $table->unique(['channel', 'ref'], 'mm_shopify_claim_unique');
            $table->index('shopify_order_id', 'mm_shopify_claim_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_shopify_order_claims');
    }
};
