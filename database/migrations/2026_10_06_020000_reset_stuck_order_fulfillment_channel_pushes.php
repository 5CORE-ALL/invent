<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_fulfillment_trackings')
            || ! Schema::hasColumn('order_fulfillment_trackings', 'channel_push_attempts')) {
            return;
        }

        $since = now()->subDays(21);

        // Rows that gave up because the push setting was off / the order row was missing,
        // or because PLS had no push: let order-fulfillment:push-tracking retry them.
        DB::table('order_fulfillment_trackings')
            ->where('updated_at', '>=', $since)
            ->whereNotNull('shopify_fulfilled_at')
            ->whereNull('channel_pushed_at')
            ->where(function ($q) {
                $q->where('channel_push_message', 'like', 'Marketplace push disabled or order row not found for %')
                    ->orWhere('channel_push_message', 'like', 'No marketplace tracking push available for pls%');
            })
            ->update(['channel_push_attempts' => 1, 'shopify_push_checked_at' => null]);

        // PLS rows were fulfilled against the B2C store instead of the PLS store.
        DB::table('order_fulfillment_trackings')
            ->where('updated_at', '>=', $since)
            ->where('mm_slug', 'pls')
            ->whereNull('shopify_fulfilled_at')
            ->update(['shopify_push_attempts' => 0, 'shopify_push_checked_at' => null]);
    }

    public function down(): void
    {
    }
};
