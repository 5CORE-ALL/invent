<?php

namespace App\Services\MarketplaceManager\StaleListing;

use App\Models\FaireMetric;
use App\Services\FaireApiService;
use App\Services\MarketplaceManager\FaireLiveListingsService;
use App\Services\MarketplaceManager\MarketplaceListingQtyMatchService;
use App\Support\Marketplace\MappingChannelCounts;
use App\Support\Marketplace\MarketplaceListingPresence;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Faire "Listed" is faire_metric.product_id (non-empty and not equal to the SKU), see
 * ChannelListingRegistry::listedFaire. Every distinct product id is read once from Faire (GET /products/{id}).
 * A row loses its product_id (and gets listing_status "missing") when Faire answers HTTP 404 for the product,
 * when the product is deleted / unpublished / draft (same states MarketplaceListingPresence calls absent),
 * or when the product no longer holds the SKU as a variant.
 *
 * Errors, rate limits, auth failures and unreadable answers keep the row. The row itself stays (it also holds
 * the order metrics L30 / L60); only the id is taken away.
 */
class FaireStaleListingVerifier implements StaleListingVerifier
{
    private const MAX_CALLS = 2500;

    public function verify(): array
    {
        $out = ['ok' => false, 'message' => '', 'checked' => 0, 'cleared' => 0, 'kept' => 0, 'errors' => 0];

        if (! Schema::hasTable('faire_metric')) {
            $out['message'] = 'Faire: faire_metric table not found.';

            return $out;
        }
        $api = app(FaireApiService::class);
        if (! $api->isConfigured()) {
            $out['message'] = 'Faire: API credentials missing, nothing checked.';

            return $out;
        }

        $hasStatus = Schema::hasColumn('faire_metric', 'listing_status');
        $rows = FaireMetric::query()
            ->whereNotNull('sku')->where('sku', '!=', '')
            ->whereNotNull('product_id')->where('product_id', '!=', '')
            ->orderBy('id')
            ->get(['id', 'sku', 'product_id'])
            ->filter(function ($row) {
                $sku = trim((string) $row->sku);
                $id = trim((string) $row->product_id);

                return stripos($sku, 'PARENT') === false && strcasecmp($id, $sku) !== 0;
            })
            ->values();

        // Only real Faire product ids (p_...) can be asked about; anything else stays as it is.
        $askable = $rows->filter(fn ($row) => (bool) preg_match('/^p_[A-Za-z0-9]+$/', trim((string) $row->product_id)))->values();
        $skipped = $rows->count() - $askable->count();
        if ($askable->isEmpty()) {
            $out['ok'] = true;
            $out['kept'] = $skipped;
            $out['message'] = 'Faire: no listed SKU with a Faire product id to check.';

            return $out;
        }

        $answers = [];
        $calls = 0;
        $consecutiveErrors = 0;
        $aborted = false;
        $capped = 0;
        $plan = [];

        foreach ($askable as $row) {
            $sku = trim((string) $row->sku);
            $productId = trim((string) $row->product_id);
            $key = strtolower($productId);

            if (! isset($answers[$key])) {
                if ($aborted || $calls >= self::MAX_CALLS) {
                    $capped++;

                    continue;
                }
                $calls++;
                try {
                    $answers[$key] = $api->getProductState($productId);
                } catch (\Throwable $e) {
                    $answers[$key] = ['state' => 'error', 'status' => 0, 'message' => $e->getMessage(), 'lifecycle' => '', 'skus' => []];
                }
                if ($answers[$key]['state'] === 'error') {
                    $consecutiveErrors++;
                    Log::warning('FaireStaleListingVerifier: could not verify product', ['product_id' => $productId, 'message' => $answers[$key]['message']]);
                    if ($consecutiveErrors >= 5) {
                        $aborted = true;
                    }
                } else {
                    $consecutiveErrors = 0;
                }
                usleep(120000);
            }

            $out['checked']++;
            $answer = $answers[$key];
            if ($answer['state'] === 'error') {
                $out['errors']++;

                continue;
            }

            $reason = $this->goneReason($answer, $sku);
            if ($reason === null) {
                $out['kept']++;

                continue;
            }
            $plan[] = ['row' => $row, 'reason' => $reason];
        }

        // A wrong answer for every product (bad credentials scope, API change) must not wipe the map.
        $stored = $askable->count();
        if (count($plan) >= 20 && count($plan) > (int) floor($stored * 0.8)) {
            $out['errors'] += count($plan);
            $out['message'] = 'Faire says '.count($plan)." of {$stored} listed SKUs are gone, too many to trust - nothing removed.";

            return $out;
        }

        foreach ($plan as $item) {
            $row = $item['row'];
            $sku = trim((string) $row->sku);
            $productId = trim((string) $row->product_id);
            try {
                $payload = ['product_id' => null];
                if ($hasStatus) {
                    $payload['listing_status'] = 'missing';
                }
                FaireMetric::query()->whereKey($row->id)->where('product_id', $productId)->update($payload);
                $out['cleared']++;
                Log::info('FaireStaleListingVerifier: product id cleared', ['sku' => $sku, 'product_id' => $productId, 'reason' => $item['reason']]);
            } catch (\Throwable $e) {
                $out['errors']++;
                Log::warning('FaireStaleListingVerifier: update failed', ['sku' => $sku, 'product_id' => $productId, 'error' => $e->getMessage()]);
            }
        }

        if ($out['cleared'] > 0) {
            $this->clearListingCaches();
        }

        $out['kept'] += $skipped + $capped;
        $out['ok'] = ! $aborted;
        $out['message'] = "Checked {$out['checked']} listed SKU(s) on Faire ({$calls} product lookup(s)): {$out['cleared']} no longer on Faire"
            .($out['cleared'] > 0 ? ' (now Missing L)' : '').", {$out['kept']} kept, {$out['errors']} could not be verified"
            .($aborted ? '. Five failures in a row (rate limit or credentials); stopped, run again later' : '')
            .($capped > 0 ? ". {$capped} row(s) left for the next run (lookup limit)" : '').'.';

        return $out;
    }

    /**
     * @param  array{state: string, lifecycle: string, skus: list<string>}  $answer
     */
    private function goneReason(array $answer, string $sku): ?string
    {
        if ($answer['state'] === 'not_found') {
            return 'product not found (404)';
        }
        if ($answer['state'] !== 'found') {
            return null;
        }

        $lifecycle = strtolower(str_replace([' ', '-'], '_', trim($answer['lifecycle'])));
        if ($lifecycle !== '' && MarketplaceListingPresence::isAbsent($lifecycle)) {
            return 'product is '.$lifecycle;
        }

        // The product exists but another product (or nothing) holds this SKU now.
        if ($answer['skus'] !== []) {
            $want = self::compact($sku);
            foreach ($answer['skus'] as $candidate) {
                if (self::compact($candidate) === $want) {
                    return null;
                }
            }

            return 'SKU is not a variant of the product';
        }

        return null;
    }

    /** Upper case without spaces, hyphens or other punctuation, so "ABC 1" and "abc-1" match. */
    private static function compact(string $value): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', $value) ?? '');
    }

    private function clearListingCaches(): void
    {
        try {
            app(FaireLiveListingsService::class)->clearCache();
        } catch (\Throwable $e) {
            // ignore
        }
        try {
            Cache::forget(MarketplaceListingQtyMatchService::CACHE_PREFIX.'faire');
            MappingChannelCounts::markChannelStale('faire');
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
