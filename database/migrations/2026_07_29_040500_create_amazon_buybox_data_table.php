<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('amazon_buybox_data')) {
            return;
        }

        Schema::create('amazon_buybox_data', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 191)->unique();
            $table->string('asin', 32)->nullable()->index();
            $table->string('item_condition', 32)->nullable();
            $table->string('status', 64)->nullable();

            // Summary
            $table->unsignedInteger('total_offer_count')->nullable();
            $table->unsignedInteger('offer_count_amazon')->nullable();
            $table->unsignedInteger('offer_count_merchant')->nullable();
            $table->decimal('list_price', 12, 2)->nullable();
            $table->decimal('competitive_price_threshold', 12, 2)->nullable();
            $table->decimal('suggested_lower_price_plus_shipping', 12, 2)->nullable();

            // Buy Box prices (Summary.BuyBoxPrices)
            $table->decimal('buybox_listing_price', 12, 2)->nullable();
            $table->decimal('buybox_landed_price', 12, 2)->nullable();
            $table->decimal('buybox_shipping', 12, 2)->nullable();
            $table->string('buybox_currency', 8)->nullable();

            // Lowest prices (Summary.LowestPrices — prefer Amazon channel, else first)
            $table->decimal('lowest_listing_price', 12, 2)->nullable();
            $table->decimal('lowest_landed_price', 12, 2)->nullable();
            $table->decimal('lowest_shipping', 12, 2)->nullable();
            $table->string('lowest_fulfillment_channel', 32)->nullable();

            // Our offer (Offers where MyOffer = true)
            $table->boolean('is_buy_box_winner')->nullable();
            $table->boolean('my_offer')->nullable();
            $table->boolean('is_fulfilled_by_amazon')->nullable();
            $table->boolean('is_featured_merchant')->nullable();
            $table->boolean('is_prime')->nullable();
            $table->boolean('is_national_prime')->nullable();
            $table->decimal('our_listing_price', 12, 2)->nullable();
            $table->decimal('our_shipping', 12, 2)->nullable();
            $table->decimal('our_landed_price', 12, 2)->nullable();
            $table->string('our_subcondition', 32)->nullable();
            $table->decimal('our_feedback_rating', 8, 2)->nullable();
            $table->unsignedInteger('our_feedback_count')->nullable();
            $table->unsignedInteger('our_ship_min_hours')->nullable();
            $table->unsignedInteger('our_ship_max_hours')->nullable();
            $table->string('our_ships_from_country', 8)->nullable();

            // Buy box winner offer (first IsBuyBoxWinner)
            $table->string('bb_seller_id', 64)->nullable();
            $table->boolean('bb_is_fulfilled_by_amazon')->nullable();
            $table->boolean('bb_is_featured_merchant')->nullable();
            $table->boolean('bb_is_prime')->nullable();
            $table->decimal('bb_listing_price', 12, 2)->nullable();
            $table->decimal('bb_shipping', 12, 2)->nullable();
            $table->decimal('bb_landed_price', 12, 2)->nullable();
            $table->decimal('bb_feedback_rating', 8, 2)->nullable();
            $table->unsignedInteger('bb_feedback_count')->nullable();
            $table->string('bb_subcondition', 32)->nullable();
            $table->string('bb_ships_from_country', 8)->nullable();

            // Sales rank
            $table->unsignedInteger('sales_rank')->nullable();
            $table->string('sales_rank_category', 191)->nullable();

            $table->json('raw_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('fetched_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amazon_buybox_data');
    }
};
