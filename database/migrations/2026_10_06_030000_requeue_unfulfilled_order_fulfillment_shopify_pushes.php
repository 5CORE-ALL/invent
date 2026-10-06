<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_fulfillment_trackings')
            || ! Schema::hasColumn('order_fulfillment_trackings', 'shopify_push_attempts')) {
            return;
        }

        // Rows that ran out of the old 8 Shopify attempts (mostly "not linked yet" while the
        // Shopify import was delayed): give them a fresh run under the new retry rules.
        DB::table('order_fulfillment_trackings')
            ->where('created_at', '>=', now()->subDays(30))
            ->whereNotIn('mm_slug', ['manual', 'doba'])
            ->whereNotNull('tracking_number')
            ->where('tracking_number', '!=', '')
            ->whereNull('shopify_fulfilled_at')
            ->where('shopify_push_attempts', '>=', 8)
            ->update(['shopify_push_attempts' => 0, 'shopify_push_checked_at' => null]);
    }

    public function down(): void
    {
    }
};
