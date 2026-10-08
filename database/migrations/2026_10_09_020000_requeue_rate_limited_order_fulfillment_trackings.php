<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rows that failed only because Shopify rate-limited the push (429, or the order read that
 * was really a 429) were given failure back-off of up to 6 hours. Make them due again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_fulfillment_trackings')
            || ! Schema::hasColumn('order_fulfillment_trackings', 'shopify_next_try_at')) {
            return;
        }

        DB::table('order_fulfillment_trackings')
            ->whereNull('shopify_fulfilled_at')
            ->where(function ($q) {
                $q->where('shopify_push_message', 'like', 'shopify_order_missing%')
                    ->orWhere('shopify_push_message', 'like', '%HTTP 429%')
                    ->orWhere('shopify_push_message', 'like', '%Exceeded 2 calls per second%');
            })
            ->update([
                'shopify_push_attempts' => 0,
                'shopify_next_try_at' => null,
                'shopify_push_checked_at' => null,
            ]);
    }

    public function down(): void
    {
    }
};
