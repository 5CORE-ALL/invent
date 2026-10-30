<?php

namespace App\Services\Support;

use App\Models\ReverbBumpRaw;
use App\Models\ReverbProduct;
use App\Services\ReverbApiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pull GET /listings/{id}/bump and store the untouched response in reverb_bump_raw.
 * Shared by /reverb-bump (push → re-pull) and the daily `reverb:pull-bump-raw` command.
 */
class ReverbBumpRawPuller
{
    /**
     * Listings that should be pulled (ended listings can't carry a Bump bid).
     *
     * @return \Illuminate\Database\Eloquent\Builder<ReverbProduct>
     */
    public static function pullableProductsQuery()
    {
        return ReverbProduct::query()
            ->whereNotNull('reverb_listing_id')
            ->where('reverb_listing_id', '!=', '')
            ->where(function ($q) {
                $q->whereNull('listing_state')
                    ->orWhereRaw('LOWER(TRIM(listing_state)) != ?', ['ended']);
            });
    }

    /**
     * @return array{listing_id: string, success: bool, status: ?int, message: ?string, fetched_at: ?string, raw: ?array}
     */
    public static function pullOne(ReverbApiService $api, string $listingId, ?string $sku = null): array
    {
        $fetch = $api->fetchListingBumpRaw($listingId);
        $now = now();

        $sku ??= ReverbProduct::query()->where('reverb_listing_id', $listingId)->value('sku');

        ReverbBumpRaw::query()->updateOrCreate(
            ['reverb_listing_id' => $listingId],
            [
                'sku' => $sku,
                'http_status' => $fetch['status'],
                'raw_response' => $fetch['json'] !== null
                    ? json_encode($fetch['json'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    : $fetch['body'],
                'error' => $fetch['success'] ? null : $fetch['message'],
                'fetched_at' => $now,
            ]
        );

        // Keep the live Bump% column in step with what Reverb just returned.
        if ($fetch['success'] && is_array($fetch['json']) && Schema::hasColumn('reverb_products', 'bump_bid')) {
            $ads = ReverbApiService::parseListingBumpAds($fetch['json']);
            if ($ads['bump_bid'] !== null && $ads['bump_bid'] !== '') {
                DB::table('reverb_products')
                    ->where('reverb_listing_id', $listingId)
                    ->update(['bump_bid' => $ads['bump_bid'], 'updated_at' => $now]);
            }
        }

        return [
            'listing_id' => $listingId,
            'success' => $fetch['success'],
            'status' => $fetch['status'],
            'message' => $fetch['message'],
            'fetched_at' => $now->toDateTimeString(),
            'raw' => $fetch['json'],
        ];
    }
}
