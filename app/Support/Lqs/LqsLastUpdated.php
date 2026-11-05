<?php

namespace App\Support\Lqs;

use App\Models\JungleScoutProductData;
use App\Models\LqsMarketplaceScore;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * "Last updated" time shown as a badge on every LQS page: when the LQS data behind that
 * page last changed (not when someone last opened the page).
 */
class LqsLastUpdated
{
    /** Marketplace pages (/lqs-ebay, /lqs-temu2, ...): latest saved LQS row (sheet upload, audit, import). */
    public static function marketplace(string $slug): ?Carbon
    {
        try {
            if (! Schema::hasTable('lqs_marketplace_scores')) {
                return null;
            }

            $value = LqsMarketplaceScore::query()->where('marketplace', $slug)->max('updated_at');

            return $value ? Carbon::parse($value) : null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Amazon pages (/lqs-amz, /lqs-data): latest Jungle Scout product data refresh. */
    public static function amazon(): ?Carbon
    {
        try {
            if (! Schema::hasTable('junglescout_product_data')) {
                return null;
            }

            $value = JungleScoutProductData::query()->max('updated_at');

            return $value ? Carbon::parse($value) : null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
