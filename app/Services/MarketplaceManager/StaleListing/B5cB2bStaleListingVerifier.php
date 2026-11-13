<?php

namespace App\Services\MarketplaceManager\StaleListing;

use App\Models\B5cB2bProduct;
use App\Services\MarketplaceManager\B5cB2bLiveListingsService;
use App\Services\MarketplaceManager\MarketplaceLiveInventoryRules;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Business 5 Core B2B is Listed when the SKU has a row on b5c_b2b_products (ChannelListingRegistry::listedB5cB2b;
 * the listing id is the row's listing_id, else the SKU). B5cB2bLiveListingsService::refresh() only upserts, so a
 * listing deleted on the store kept its row and stayed Listed.
 *
 * The full store listing list is read (complete read only), then every stored row that is neither in that list
 * (by SKU or listing id) nor returned by a single-SKU lookup on /api/listings and /api/inventory is removed.
 * A failed, short or suspicious list removes nothing.
 */
class B5cB2bStaleListingVerifier implements StaleListingVerifier
{
    private const MIN_LIVE_LISTINGS = 20;

    private const MAX_CLEAR_SHARE = 0.8;

    private const MAX_CONSECUTIVE_ERRORS = 5;

    /**
     * @return array{ok: bool, message: string, checked: int, cleared: int, kept: int, errors: int}
     */
    public function verify(): array
    {
        $out = ['ok' => false, 'message' => '', 'checked' => 0, 'cleared' => 0, 'kept' => 0, 'errors' => 0];

        if (! Schema::hasTable('b5c_b2b_products')) {
            $out['message'] = 'Business 5Core B2B: b5c_b2b_products table not found.';

            return $out;
        }

        $service = app(B5cB2bLiveListingsService::class);
        $startedAt = Carbon::now()->subSeconds(5);

        $rows = B5cB2bProduct::query()
            ->whereNotNull('sku')->where('sku', '!=', '')
            ->orderBy('id')
            ->get(['id', 'sku', 'listing_id', 'updated_at', 'created_at'])
            ->filter(static fn ($row) => ! MarketplaceLiveInventoryRules::isParentPlaceholderSku((string) $row->sku))
            ->values();
        $out['checked'] = $rows->count();
        if ($rows->isEmpty()) {
            $out['ok'] = true;
            $out['message'] = 'Business 5Core B2B: no listed SKUs to check.';

            return $out;
        }

        $index = $service->liveListingIndex();
        if (! $index['complete']) {
            $out['errors'] = 1;
            $out['kept'] = $out['checked'];
            $out['message'] = 'Business 5Core B2B: could not read the store listings ('.$index['message'].'). Nothing changed.';

            return $out;
        }
        if ($index['count'] < self::MIN_LIVE_LISTINGS) {
            $out['errors'] = 1;
            $out['kept'] = $out['checked'];
            $out['message'] = 'Business 5Core B2B: the store returned only '.$index['count'].' listing(s); list looks incomplete, nothing changed.';

            return $out;
        }

        // Rows the list does not hold.
        $candidates = [];
        foreach ($rows as $row) {
            $sku = trim((string) $row->sku);
            $id = $row->listing_id !== null ? trim((string) $row->listing_id) : '';
            if (isset($index['skus'][B5cB2bLiveListingsService::skuKey($sku)]) || ($id !== '' && isset($index['ids'][$id]))) {
                continue;
            }
            $candidates[] = $row;
        }
        if (count($candidates) > self::MAX_CLEAR_SHARE * $rows->count()) {
            $out['errors'] = 1;
            $out['kept'] = $out['checked'];
            $out['message'] = 'Business 5Core B2B: '.count($candidates).' of '.$rows->count().' stored listings are not in the store list; too many to be right, nothing changed.';

            return $out;
        }

        $consecutiveErrors = 0;
        $aborted = false;
        foreach ($candidates as $row) {
            $sku = trim((string) $row->sku);
            $id = $row->listing_id !== null ? trim((string) $row->listing_id) : '';
            try {
                // Saved or published while the list was being read: not in the list by timing, not gone.
                $touched = $row->updated_at ?: $row->created_at;
                if ($touched !== null && Carbon::parse($touched)->greaterThanOrEqualTo($startedAt)) {
                    continue;
                }

                $probe = $service->probeSku($sku, $id);
                if ($probe['state'] === 'error') {
                    $out['errors']++;
                    $consecutiveErrors++;
                    Log::warning('B5cB2bStaleListingVerifier: could not verify SKU', ['sku' => $sku, 'message' => $probe['message']]);
                    if ($consecutiveErrors >= self::MAX_CONSECUTIVE_ERRORS) {
                        $aborted = true;

                        break;
                    }

                    continue;
                }
                $consecutiveErrors = 0;

                if ($probe['state'] === 'live') {
                    continue;
                }

                // Definitely gone: the store list and both single-SKU lookups no longer hold it.
                B5cB2bProduct::query()->whereKey($row->getKey())->delete();
                $out['cleared']++;
                Log::info('B5cB2bStaleListingVerifier: listing no longer on the store, row removed', ['sku' => $sku, 'listing_id' => $id]);
            } catch (\Throwable $e) {
                $out['errors']++;
                Log::warning('B5cB2bStaleListingVerifier: row check failed', ['sku' => $sku, 'error' => $e->getMessage()]);
            }
        }

        if ($out['cleared'] > 0) {
            $service->clearCache();
        }

        $out['ok'] = ! $aborted;
        $out['kept'] = max(0, $out['checked'] - $out['cleared']);
        $out['message'] = "Business 5Core B2B: checked {$out['checked']} listed SKU(s): {$out['cleared']} no longer on the store (now Missing L), "
            ."{$out['errors']} could not be verified"
            .($aborted ? '. Stopped after 5 failures in a row; run again later.' : '.');

        return $out;
    }
}
