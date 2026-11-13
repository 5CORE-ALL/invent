<?php

namespace App\Services\MarketplaceManager\StaleListing;

use App\Models\AmazonDatasheet;
use App\Models\AmazonListingStatus;
use App\Models\ShopifySku;
use App\Services\AmazonSpApiService;
use App\Services\MarketplaceManager\AmazonListingStatusHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Amazon "Listed" (AmazonListingCounts::listingsByNormalizedSku) means a SKU has an ASIN in any of:
 *   - amazon_listings_raw (asin1, the merchant listings report snapshot),
 *   - a linked amazon_listing_statuses row (ASIN in value.asin / product_id / buyer link ...),
 *   - amazon_datsheets.asin.
 * Those sources only ever gain ASINs, so a listing deleted on Amazon stays Listed here.
 *
 * Every SKU group that holds an ASIN is asked about on Amazon (Listings Items API). Only an HTTP 404 for
 * every spelling of the SKU counts as gone; then the ASIN is taken out of all three sources, so the SKU
 * shows on Missing L. A listing that exists but is inactive/closed is kept (it still holds the SKU on
 * Seller Central and has its own Inactive Listing view). Throttling, auth errors, timeouts and unreadable
 * answers are unknown: kept and counted as errors.
 */
class AmazonStaleListingVerifier implements StaleListingVerifier
{
    private const CURSOR_KEY = 'mm_amazon_stale_verify_cursor';

    private const MAX_SECONDS = 2400;

    private const MAX_CANDIDATES = 12;

    private const MAX_CONSECUTIVE_ERRORS = 5;

    private const MIN_FOR_GUARDS = 20;

    private const MAX_GONE_SHARE = 0.8;

    public function __construct(
        protected AmazonSpApiService $amazon
    ) {}

    public function verify(): array
    {
        $out = ['ok' => false, 'message' => '', 'checked' => 0, 'cleared' => 0, 'kept' => 0, 'errors' => 0];

        if (! $this->amazon->isConfigured()) {
            $out['message'] = 'Amazon SP-API is not connected (client id, secret, refresh token, marketplace id and seller id are needed); nothing was changed.';

            return $out;
        }

        $groups = $this->collectGroups();
        if ($groups === []) {
            $out['ok'] = true;
            $out['message'] = 'No Amazon SKU holds an ASIN; nothing to check.';

            return $out;
        }

        $keys = array_keys($groups);
        sort($keys, SORT_STRING);
        $total = count($keys);
        $offset = (int) Cache::get(self::CURSOR_KEY, 0);
        if ($offset < 0 || $offset >= $total) {
            $offset = 0;
        }
        $keys = array_merge(array_slice($keys, $offset), array_slice($keys, 0, $offset));

        $started = microtime(true);
        $gone = [];
        $processed = 0;
        $consecutive = 0;
        $aborted = false;
        $timedOut = false;

        foreach (array_chunk($keys, 5) as $chunk) {
            if (microtime(true) - $started > self::MAX_SECONDS) {
                $timedOut = true;
                break;
            }
            try {
                $states = $this->resolveChunk($chunk, $groups);
            } catch (\Throwable $e) {
                Log::warning('AmazonStaleListingVerifier: chunk failed', ['error' => $e->getMessage()]);
                $states = [];
            }

            foreach ($chunk as $key) {
                $state = $states[$key] ?? 'unknown';
                $processed++;
                $out['checked']++;

                if ($state === 'unknown') {
                    $out['errors']++;
                    $consecutive++;
                    if ($consecutive >= self::MAX_CONSECUTIVE_ERRORS) {
                        $aborted = true;
                        break;
                    }

                    continue;
                }
                $consecutive = 0;

                if ($state === 'missing') {
                    $gone[] = $key;
                } else {
                    $out['kept']++;
                }
            }
            if ($aborted) {
                break;
            }
        }

        Cache::put(self::CURSOR_KEY, $processed >= $total ? 0 : ($offset + $processed) % $total, now()->addDays(7));

        $refused = '';
        if ($gone !== [] && $out['checked'] >= self::MIN_FOR_GUARDS) {
            if ($out['kept'] === 0) {
                $refused = 'Amazon answered "not found" for every SKU checked, which looks like a wrong seller id or marketplace; nothing was changed.';
            } elseif (count($gone) / $out['checked'] > self::MAX_GONE_SHARE) {
                $refused = 'Amazon answered "not found" for over '.(int) (self::MAX_GONE_SHARE * 100).'% of the SKUs checked; treated as unreliable and nothing was changed.';
            }
        }

        if ($refused === '') {
            foreach ($gone as $key) {
                try {
                    $this->unlist($groups[$key]);
                    $out['cleared']++;
                    Log::info('AmazonStaleListingVerifier: SKU no longer on Amazon, ASIN cleared', ['skus' => $groups[$key]['skus']]);
                } catch (\Throwable $e) {
                    $out['errors']++;
                    Log::warning('AmazonStaleListingVerifier: update failed', ['skus' => $groups[$key]['skus'], 'error' => $e->getMessage()]);
                }
            }
            if ($out['cleared'] > 0) {
                $this->forgetCachedStates($gone, $groups);
            }
        }

        $out['ok'] = ! $aborted && $refused === '';
        $out['message'] = "Checked {$out['checked']} Amazon SKU(s): {$out['cleared']} no longer on Amazon (now Missing L), "
            ."{$out['kept']} still on Amazon, {$out['errors']} could not be verified.";
        if ($refused !== '') {
            $out['message'] = $refused;
        }
        if ($aborted) {
            $out['message'] .= ' Stopped after '.self::MAX_CONSECUTIVE_ERRORS.' failures in a row (rate limit or credentials); run again later.';
        }
        if ($timedOut) {
            $out['message'] .= ' Time limit reached; the rest is checked on the next run ('.($total - $processed).' SKU(s) left).';
        }

        return $out;
    }

    /**
     * SKU groups that currently hold an ASIN. One group = one listing (SKU spelling, FBA/FBM suffix and
     * punctuation folded), with the ids of every row that carries its ASIN.
     *
     * @return array<string, array{skus: list<string>, candidates: list<string>, raw: list<int>, status: list<int>, sheet: list<int>}>
     */
    protected function collectGroups(): array
    {
        $groups = [];
        $add = function (string $source, int $id, string $sku) use (&$groups): void {
            $sku = trim(str_replace("\xC2\xA0", ' ', $sku));
            if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                return;
            }
            $base = trim((string) preg_replace('/\s+(FBA|FBM)$/i', '', $sku));
            $key = ShopifySku::compactSkuForLookup($base !== '' ? $base : $sku);
            if ($key === '') {
                return;
            }
            $groups[$key] ??= ['skus' => [], 'candidates' => [], 'raw' => [], 'status' => [], 'sheet' => []];
            if (! in_array($sku, $groups[$key]['skus'], true)) {
                $groups[$key]['skus'][] = $sku;
            }
            $groups[$key][$source][] = $id;
        };

        if (Schema::hasTable('amazon_listings_raw')) {
            DB::table('amazon_listings_raw')
                ->whereNotNull('seller_sku')->where('seller_sku', '!=', '')
                ->whereNotNull('asin1')->where('asin1', '!=', '')
                ->select(['id', 'seller_sku', 'asin1'])
                ->orderBy('id')
                ->chunkById(1000, function ($rows) use ($add) {
                    foreach ($rows as $row) {
                        if (trim((string) $row->asin1) !== '') {
                            $add('raw', (int) $row->id, (string) $row->seller_sku);
                        }
                    }
                });
        }

        if (Schema::hasTable('amazon_listing_statuses')) {
            AmazonListingStatus::query()
                ->whereNotNull('sku')->where('sku', '!=', '')
                ->select(['id', 'sku', 'value'])
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($add) {
                    foreach ($rows as $row) {
                        if (AmazonListingStatusHelper::isLinked($row) && AmazonListingStatusHelper::resolveAsin($row) !== '') {
                            $add('status', (int) $row->id, (string) $row->sku);
                        }
                    }
                });
        }

        if (Schema::hasTable('amazon_datsheets')) {
            AmazonDatasheet::query()
                ->whereNotNull('asin')->where('asin', '!=', '')
                ->whereNotNull('sku')->where('sku', '!=', '')
                ->select(['id', 'sku', 'asin'])
                ->orderBy('id')
                ->chunkById(1000, function ($rows) use ($add) {
                    foreach ($rows as $row) {
                        if (preg_match('/^[A-Z0-9]{10}$/', strtoupper(trim((string) $row->asin)))) {
                            $add('sheet', (int) $row->id, (string) $row->sku);
                        }
                    }
                });
        }

        foreach ($groups as $key => $group) {
            $groups[$key]['candidates'] = $this->candidates($group['skus']);
        }

        return $groups;
    }

    /**
     * Every stored spelling first, then the spellings the Listings API code elsewhere in the app also tries
     * (collapsed spaces, no spaces, hyphen as space, no separators, upper case).
     *
     * @param  list<string>  $skus
     * @return list<string>
     */
    protected function candidates(array $skus): array
    {
        $out = [];
        foreach ($skus as $sku) {
            $out[$sku] = true;
        }
        foreach ($skus as $sku) {
            $collapsed = (string) preg_replace('/\s+/u', ' ', $sku);
            $noSpaces = str_replace(' ', '', $sku);
            $hyphenAsSpace = (string) preg_replace('/\s+/u', ' ', str_replace(['-', '_'], ' ', $sku));
            $noSep = str_replace([' ', '-', '_'], '', $sku);
            foreach ([$collapsed, $noSpaces, $hyphenAsSpace, str_replace(' ', '', $hyphenAsSpace), $noSep] as $variant) {
                foreach ([$variant, strtoupper($variant)] as $one) {
                    if ($one !== '' && count($out) < self::MAX_CANDIDATES) {
                        $out[$one] = true;
                    }
                }
            }
        }

        return array_map('strval', array_keys($out));
    }

    /**
     * Ask Amazon about up to 5 groups. A group is gone only when every spelling is a 404.
     *
     * @param  list<string>  $chunk
     * @param  array<string, array<string, mixed>>  $groups
     * @return array<string, 'live'|'inactive'|'missing'|'unknown'>
     */
    protected function resolveChunk(array $chunk, array $groups): array
    {
        $result = [];
        $pending = array_fill_keys($chunk, 0);

        while ($pending !== []) {
            $ask = [];
            foreach ($pending as $key => $index) {
                $candidate = $groups[$key]['candidates'][$index] ?? null;
                if ($candidate === null) {
                    $result[$key] = 'missing';
                    unset($pending[$key]);

                    continue;
                }
                $ask[$key] = $candidate;
            }
            if ($ask === []) {
                break;
            }

            $states = $this->amazon->listingsItemStatesLive(array_values($ask));
            foreach ($ask as $key => $sku) {
                $state = $states[$sku] ?? 'unknown';
                if ($state === 'missing') {
                    $pending[$key]++;

                    continue;
                }
                $result[$key] = $state;
                unset($pending[$key]);
            }
        }

        return $result;
    }

    /**
     * Take the ASIN out of everything the Listed rule reads. Rows stay (they also hold NR flags, sales and
     * traffic data); only the report snapshot rows of the gone listing are removed, as the next listings
     * report import would do.
     *
     * @param  array<string, mixed>  $group
     */
    protected function unlist(array $group): void
    {
        DB::transaction(function () use ($group) {
            if ($group['raw'] !== []) {
                DB::table('amazon_listings_raw')->whereIn('id', $group['raw'])->delete();
            }

            foreach ($group['status'] as $id) {
                $row = AmazonListingStatus::query()->find($id);
                if (! $row) {
                    continue;
                }
                $value = $this->valueWithoutAsin($row);
                $check = new AmazonListingStatus(['sku' => $row->sku, 'value' => $value]);
                if (AmazonListingStatusHelper::resolveAsin($check) !== '') {
                    throw new \RuntimeException('ASIN could not be removed from amazon_listing_statuses row '.$id);
                }
                $row->value = $value;
                $row->save();
            }

            if ($group['sheet'] !== []) {
                AmazonDatasheet::query()->whereIn('id', $group['sheet'])->update(['asin' => '', 'listing_status' => null]);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function valueWithoutAsin(AmazonListingStatus $row): array
    {
        $value = AmazonListingStatusHelper::valueArray($row);
        foreach (['asin', 'ASIN', 'asin1'] as $key) {
            unset($value[$key]);
        }
        if (preg_match('/^[A-Z0-9]{10}$/', strtoupper(trim((string) ($value['product_id'] ?? ''))))) {
            $value['product_id'] = 'AMZ:'.trim((string) $row->sku);
        }
        foreach (['buyer_link', 'seller_link', 'listing_url', 'url', 'link'] as $key) {
            $url = trim((string) ($value[$key] ?? ''));
            if ($url !== '' && (preg_match('#/(?:dp|gp/product|ASIN)/([A-Z0-9]{10})#i', $url) || preg_match('#[?&]asin=([A-Z0-9]{10})#i', $url))) {
                unset($value[$key]);
            }
        }
        $value['listed'] = 'Not Listed';
        $value['listing_status'] = 'missing';
        $value['unlisted_at'] = now()->toDateTimeString();

        return $value;
    }

    /**
     * @param  list<string>  $keys
     * @param  array<string, array<string, mixed>>  $groups
     */
    protected function forgetCachedStates(array $keys, array $groups): void
    {
        try {
            $skus = [];
            foreach ($keys as $key) {
                foreach ($groups[$key]['skus'] ?? [] as $sku) {
                    $skus[] = $sku;
                }
            }
            $this->amazon->forgetSellerCentralListingStates($skus);
        } catch (\Throwable $e) {
            // The cache entry only lives 12 hours.
        }
    }
}
