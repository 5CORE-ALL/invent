<?php

use App\Services\OrderFulfillment\OrderFulfillmentShopifyPushService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_fulfillment_trackings')) {
            return;
        }
        OrderFulfillmentShopifyPushService::ensureColumns();

        // Newegg / AliExpress are now pushed straight from the page's tracking number (no Shopify
        // tracking needed). Rows that never reached the marketplace — including ones that used up
        // their attempts waiting for Shopify — get a fresh run.
        DB::table('order_fulfillment_trackings')
            ->whereIn('mm_slug', OrderFulfillmentShopifyPushService::DIRECT_CHANNEL_SLUGS)
            ->where('created_at', '>=', now()->subDays(OrderFulfillmentShopifyPushService::MAX_ROW_AGE_DAYS))
            ->whereNotNull('tracking_number')
            ->where('tracking_number', '!=', '')
            ->whereNull('channel_pushed_at')
            ->update([
                'channel_push_attempts' => 0,
                'channel_next_try_at' => null,
            ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('order_fulfillment_trackings')
            && Schema::hasColumn('order_fulfillment_trackings', 'channel_next_try_at')) {
            Schema::table('order_fulfillment_trackings', function ($table) {
                $table->dropColumn('channel_next_try_at');
            });
        }
    }
};
