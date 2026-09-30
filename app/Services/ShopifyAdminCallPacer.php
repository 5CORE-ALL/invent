<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Keeps Shopify Admin REST calls under the 2-per-second client limit across PHP workers.
 */
class ShopifyAdminCallPacer
{
    private const GAP_SECONDS = 1.1;

    public static function wait(): void
    {
        try {
            $lock = Cache::lock('shopify_admin_verification_slot', 20);
            $lock->block(20);
        } catch (\Throwable $e) {
            usleep((int) (self::GAP_SECONDS * 1_000_000));

            return;
        }

        try {
            $now = microtime(true);
            $next = (float) Cache::get('shopify_admin_verification_next_at', 0);
            if ($next > $now) {
                usleep((int) (($next - $now) * 1_000_000));
                $now = microtime(true);
            }
            Cache::put('shopify_admin_verification_next_at', $now + self::GAP_SECONDS, 60);
        } finally {
            $lock->release();
        }
    }
}
