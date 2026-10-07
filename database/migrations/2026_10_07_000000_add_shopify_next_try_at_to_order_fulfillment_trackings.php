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

        // Rows that hit the old attempt cap or were marked "stopped" never retried.
        // Retries now wait on shopify_next_try_at instead, so give every one a fresh run.
        DB::table('order_fulfillment_trackings')
            ->where('created_at', '>=', now()->subDays(OrderFulfillmentShopifyPushService::MAX_ROW_AGE_DAYS))
            ->whereNotIn('mm_slug', OrderFulfillmentShopifyPushService::EXCLUDED_SLUGS)
            ->whereNotNull('tracking_number')
            ->where('tracking_number', '!=', '')
            ->whereNull('shopify_fulfilled_at')
            ->update([
                'shopify_push_attempts' => 0,
                'shopify_push_checked_at' => null,
                'shopify_next_try_at' => null,
            ]);

        // Misses re-checked under the new age-based schedule straight away.
        DB::table('order_fulfillment_trackings')
            ->where('created_at', '>=', now()->subDays(OrderFulfillmentShopifyPushService::MAX_ROW_AGE_DAYS))
            ->where(function ($q) {
                $q->whereNull('tracking_number')->orWhere('tracking_number', '');
            })
            ->where(function ($q) {
                $q->whereNull('source')->orWhere('source', '!=', 'manual');
            })
            ->update(['checked_at' => null]);
    }

    public function down(): void
    {
        if (Schema::hasTable('order_fulfillment_trackings')
            && Schema::hasColumn('order_fulfillment_trackings', 'shopify_next_try_at')) {
            Schema::table('order_fulfillment_trackings', function ($table) {
                $table->dropIndex('of_tracking_next_try_idx');
                $table->dropColumn('shopify_next_try_at');
            });
        }
    }
};
