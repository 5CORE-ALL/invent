<?php

namespace App\Services\MarketplaceManager\StaleListing;

use App\Models\SheinMetric;
use App\Models\SheinMmMetric;
use App\Services\MarketplaceManager\SheinLiveListingsService;
use App\Services\SheinApiService;
use App\Support\Marketplace\ListingCountsEngine;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Shein "Listed" is shein_metrics.shein_sku_code (non-empty, not equal to the SKU) unless shein_metrics.status
 * is inactive / disabled / delisted / deleted or one of MarketplaceListingPresence's absent states, see the
 * 'shein' entry of ChannelListingRegistry. Every distinct skuCode is read once from Shein (product/full-detail):
 *
 *  - Shein answers "invalid skuCode" (the product is gone): status becomes "deleted" and shein_sku_code is
 *    cleared, so a later sync that finds the product again (by its raw skuCode or the SKU) restores both.
 *  - Shein returns the product and every shelf entry says off shelf: status becomes "inactive".
 *
 * Rate limits, HTTP errors, unreadable answers and a skuCode that is not in the answer keep the row.
 * Rows are never deleted.
 */
class SheinStaleListingVerifier implements StaleListingVerifier
{
    private const MAX_CALLS = 3000;

    public function verify(): array
    {
        $out = ['ok' => false, 'message' => '', 'checked' => 0, 'cleared' => 0, 'kept' => 0, 'errors' => 0];

        if (! Schema::hasTable('shein_metrics') || ! Schema::hasColumn('shein_metrics', 'shein_sku_code')) {
            $out['message'] = 'Shein: shein_metrics.shein_sku_code not found.';

            return $out;
        }
        $api = app(SheinApiService::class);
        if (! $api->isConfigured()) {
            $out['message'] = 'Shein: API credentials missing, nothing checked.';

            return $out;
        }

        $hide = ['inactive', 'disabled', 'delisted', 'deleted'];
        $rows = SheinMetric::query()
            ->whereNotNull('sku')->where('sku', '!=', '')
            ->whereNotNull('shein_sku_code')->where('shein_sku_code', '!=', '')
            ->orderBy('id')
            ->get(['id', 'sku', 'shein_sku_code', 'status'])
            ->filter(function ($row) use ($hide) {
                $sku = trim((string) $row->sku);
                $code = trim((string) $row->shein_sku_code);
                if (stripos($sku, 'PARENT') !== false || strcasecmp($code, $sku) === 0) {
                    return false;
                }
                // Already Missing L by the page rule: nothing to take away.
                $status = str_replace([' ', '-'], '_', strtolower(trim((string) ($row->status ?? ''))));
                if (in_array($status, $hide, true) || ListingCountsEngine::listingStatusHidesId((string) ($row->status ?? ''), $code, $sku)) {
                    return false;
                }

                return true;
            })
            ->values();

        // Only real platform skuCodes can be asked about (not SPU names or text with spaces); the rest stays.
        $askable = $rows->filter(fn ($row) => $api->isPlatformSkuCode(trim((string) $row->shein_sku_code), trim((string) $row->sku)))->values();
        $skipped = $rows->count() - $askable->count();
        if ($askable->isEmpty()) {
            $out['ok'] = true;
            $out['kept'] = $skipped;
            $out['message'] = 'Shein: no listed SKU with a Shein skuCode to check.';

            return $out;
        }

        $answers = [];
        $calls = 0;
        $consecutiveErrors = 0;
        $aborted = false;
        $capped = 0;
        $plan = [];

        foreach ($askable as $row) {
            $code = trim((string) $row->shein_sku_code);
            $key = strtolower($code);

            if (! isset($answers[$key])) {
                if ($aborted || $calls >= self::MAX_CALLS) {
                    $capped++;

                    continue;
                }
                $calls++;
                try {
                    $answers[$key] = $api->getSkuCodeState($code);
                } catch (\Throwable $e) {
                    $answers[$key] = ['state' => 'error', 'message' => $e->getMessage(), 'rate_limited' => false];
                }
                if ($answers[$key]['state'] === 'error') {
                    $consecutiveErrors++;
                    Log::warning('SheinStaleListingVerifier: could not verify skuCode', ['sku_code' => $code, 'message' => $answers[$key]['message']]);
                    if ($consecutiveErrors >= 5) {
                        $aborted = true;
                    } elseif (! empty($answers[$key]['rate_limited'])) {
                        sleep(3);
                    }
                } else {
                    $consecutiveErrors = 0;
                }
                usleep(150000);
            }

            $out['checked']++;
            $state = $answers[$key]['state'];
            if ($state === 'error') {
                $out['errors']++;
            } elseif ($state === 'gone' || $state === 'off_shelf') {
                $plan[] = ['row' => $row, 'state' => $state];
            } else {
                $out['kept']++;
            }
        }

        // A wrong answer for every code (bad credentials scope, API change) must not wipe the map.
        $stored = $askable->count();
        $goneCount = count(array_filter($plan, static fn ($item) => $item['state'] === 'gone'));
        if ($goneCount >= 20 && $goneCount > (int) floor($stored * 0.8)) {
            $out['errors'] += count($plan);
            $out['message'] = "Shein says {$goneCount} of {$stored} listed skuCodes are invalid, too many to trust - nothing changed.";

            return $out;
        }

        $gone = 0;
        $offShelf = 0;
        foreach ($plan as $item) {
            $row = $item['row'];
            $sku = trim((string) $row->sku);
            $code = trim((string) $row->shein_sku_code);
            try {
                if ($item['state'] === 'gone') {
                    SheinMetric::query()->whereKey($row->id)->where('shein_sku_code', $row->shein_sku_code)
                        ->update(['status' => 'deleted', 'shein_sku_code' => null]);
                    $this->resetLinkMap($sku, $code);
                    $gone++;
                } else {
                    SheinMetric::query()->whereKey($row->id)->update(['status' => 'inactive']);
                    $offShelf++;
                }
                $out['cleared']++;
                Log::info('SheinStaleListingVerifier: SKU is no longer live on Shein', ['sku' => $sku, 'sku_code' => $code, 'state' => $item['state']]);
            } catch (\Throwable $e) {
                $out['errors']++;
                Log::warning('SheinStaleListingVerifier: update failed', ['sku' => $sku, 'sku_code' => $code, 'error' => $e->getMessage()]);
            }
        }

        if ($out['cleared'] > 0) {
            try {
                app(SheinLiveListingsService::class)->clearCache();
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $out['kept'] += $skipped + $capped;
        $out['ok'] = ! $aborted;
        $out['message'] = "Checked {$out['checked']} listed SKU(s) on Shein ({$calls} lookup(s)): {$gone} deleted on Shein, {$offShelf} off shelf"
            .($out['cleared'] > 0 ? ' (now Missing L)' : '').", {$out['kept']} kept, {$out['errors']} could not be verified"
            .($aborted ? '. Five failures in a row (rate limit or credentials); stopped, run again later' : '')
            .($capped > 0 ? ". {$capped} row(s) left for the next run (lookup limit)" : '').'.';

        return $out;
    }

    /**
     * The Shein link map (shein_metric.product_id) shows the SKU itself while no skuCode is known.
     */
    private function resetLinkMap(string $sku, string $code): void
    {
        try {
            if (! Schema::hasTable('shein_metric')) {
                return;
            }
            SheinMmMetric::query()->where('sku', $sku)->where('product_id', $code)->update(['product_id' => $sku]);
        } catch (\Throwable $e) {
            Log::warning('SheinStaleListingVerifier: link map reset failed', ['sku' => $sku, 'error' => $e->getMessage()]);
        }
    }
}
