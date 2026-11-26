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

        // Reverb is now pushed straight from the page's tracking number (no Shopify tracking needed):
        // rows that never reached Reverb get a fresh run.
        DB::table('order_fulfillment_trackings')
            ->where('mm_slug', 'reverb')
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
        // Data requeue only; nothing to undo.
    }
};
