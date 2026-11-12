<?php

namespace App\Services;

use App\Http\Controllers\MarketPlace\ChannelPromoPricingController;
use App\Support\EbayRuleSprice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Headless Dil vs PRMT% for /ebay-tabulator-view, /ebay2-tabulator-view and /ebay3-tabulator-view.
 *
 * Why this exists: the page's Dil→PRMT auto-apply is switched off on the eBay pages (CH_STD_SERVER_OWNED, meant for
 * S PRC), so a Dil change never reached eBay unless someone pressed "Save & Apply". The only server job was
 * pef:dil-prmt-auto-apply: eBay 1 only, once a day, and per-SKU Dil (the page uses listing Dil).
 *
 * Every run, per channel (same rules as the page's Apply):
 *  1. Listing Dil = Σ OV L30 ÷ Σ INV by eBay item id (else Parent), the same grouping as chPromoParentDilByKey().
 *  2. PRMT% = the shared Dil vs PRMT slab for that Dil (listing INV 0 → 0).
 *  3. Saves prmt_pct on every SKU of the listing whose saved PRMT% differs.
 *  4. Queues the markdown-sale push (Push PRMT% worker) once per listing when the PRMT% eBay has
 *     (PEF_SALE_PCT) differs from the new one. eBay accepts 5–80% (0 removes the listing from a sale).
 *
 * A listing that failed on eBay keeps PEF_SALE_PCT unchanged, so it is retried; RETRY_MINUTES stops a listing that eBay
 * keeps rejecting from being re-queued every run.
 */
class EbayDilPrmtAutoApplyService
{
    private const RETRY_MINUTES = 60;

    public function __construct(
        private readonly EbaySprcDilAutoPushService $tabulator,
        private readonly PefDilPrmtAutoApplyService $rules,
        private readonly ChannelPromoPricingService $promo,
    ) {}

    /**
     * @return array{listings:int,children:int,saved:int,unchanged:int,queued:int,in_sync:int,below_min:int,throttled:int,no_listing:int,errors:list<string>}
     */
    public function run(string $channel, bool $dryRun = false, ?callable $logger = null): array
    {
        $channel = strtolower(trim($channel));
        if (! in_array($channel, EbayRuleSprice::CHANNELS, true)) {
            throw new \InvalidArgumentException('channel must be one of: '.implode(', ', EbayRuleSprice::CHANNELS));
        }
        $log = $logger ?? static function (string $msg): void {};
        $stats = [
            'listings' => 0,
            'children' => 0,
            'saved' => 0,
            'unchanged' => 0,
            'queued' => 0,
            'in_sync' => 0,
            'below_min' => 0,
            'throttled' => 0,
            'no_listing' => 0,
            'errors' => [],
        ];

        $lock = Cache::lock('ebay-dil-prmt-auto-apply-run-'.$channel, 1800);
        if (! $lock->get()) {
            $log('eBay Dil→PRMT run already in progress for '.$channel.' — skipped.');
            $stats['errors'][] = 'lock: already running';

            return $stats;
        }

        try {
            $rules = $this->rules->loadRules();
            $children = $this->childRows($this->tabulator->tabulatorRows($channel));
            $stats['children'] = count($children);
            $keyOf = $this->variationKeyFn($children);

            /** @var array<string, array{inv:float,l30:float,kids:list<array<string,mixed>>}> $groups */
            $groups = [];
            foreach ($children as $row) {
                $key = $keyOf($row);
                if ($key === '') {
                    continue;
                }
                $groups[$key] ??= ['inv' => 0.0, 'l30' => 0.0, 'kids' => []];
                $groups[$key]['inv'] += (float) ($row['INV'] ?? 0);
                $groups[$key]['l30'] += $this->ovL30($row);
                $groups[$key]['kids'][] = $row;
            }
            $stats['listings'] = count($groups);

            $tasks = [];
            $throttleKeys = [];
            foreach ($groups as $groupKey => $group) {
                $dil = $group['inv'] > 0 ? ($group['l30'] / $group['inv']) * 100 : 0.0;
                $prmt = $group['inv'] > 0 ? $this->rules->prmtForDil($dil, $rules) : 0.0;
                $prmtInt = (int) round($prmt);

                // 1. Saved PRMT% on every SKU of the listing.
                foreach ($group['kids'] as $kid) {
                    $current = (float) ($kid['prmt_pct'] ?? $kid['_prmt_pct_applied'] ?? 0);
                    if (abs($current - $prmt) < 0.005) {
                        $stats['unchanged']++;

                        continue;
                    }
                    $sku = $this->skuOf($kid);
                    if ($dryRun) {
                        $stats['saved']++;
                        $log(sprintf('  DRY %s save prmt %s → %s (listing Dil %.2f%%)', $sku, $current, $prmt, $dil));

                        continue;
                    }
                    try {
                        $this->promo->upsert($channel, $sku, ['prmt_pct' => $prmt]);
                        $stats['saved']++;
                    } catch (\Throwable $e) {
                        $stats['errors'][] = $sku.': '.$e->getMessage();
                        Log::warning('eBay Dil→PRMT save failed', ['channel' => $channel, 'sku' => $sku, 'error' => $e->getMessage()]);
                    }
                }

                // 2. eBay markdown sale push, once per listing.
                $active = array_values(array_filter($group['kids'], fn ($k) => ! $this->isEnded($k)));
                if ($active === []) {
                    continue;
                }
                if ($prmtInt > 0 && ($prmtInt < 5 || $prmtInt > 80)) {
                    $stats['below_min']++;

                    continue;
                }
                $lastSale = 0;
                foreach ($active as $kid) {
                    $lastSale = max($lastSale, (int) round(max(0.0, (float) ($kid['PEF_SALE_PCT'] ?? 0))));
                }
                $needsPush = $prmtInt === 0 ? $lastSale >= 5 : $lastSale !== $prmtInt;
                if (! $needsPush) {
                    $stats['in_sync']++;

                    continue;
                }
                // Push through a SKU that has an eBay item id. A listing with no item id in the
                // metrics table has nothing on eBay, so a push would only fail ("item_id not found").
                $seed = null;
                foreach ($active as $kid) {
                    if ($this->itemIdOf($kid) !== '') {
                        $seed = $kid;
                        break;
                    }
                }
                if ($seed === null) {
                    $stats['no_listing']++;

                    continue;
                }
                $throttleKey = 'ebay-dil-prmt-queued:'.$channel.':'.md5($groupKey).':'.$prmtInt;
                if (! $dryRun && ! Cache::add($throttleKey, 1, now()->addMinutes(self::RETRY_MINUTES))) {
                    $stats['throttled']++;

                    continue;
                }
                $throttleKeys[] = $throttleKey;
                $tasks[] = ['sku' => $this->skuOf($seed), 'prmt' => $prmtInt];
                if ($dryRun) {
                    $log(sprintf('  DRY %s push prmt %d%% (eBay has %d%%, listing Dil %.2f%%)', $this->skuOf($seed), $prmtInt, $lastSale, $dil));
                }
            }

            if ($tasks !== [] && ! $dryRun) {
                $stats['queued'] = $this->enqueuePush($channel, $tasks, $throttleKeys, $stats, $log);
            } elseif ($dryRun) {
                $stats['queued'] = count($tasks);
            }

            $log(sprintf(
                '%s: %d listings (%d SKUs): PRMT saved on %d, unchanged %d; push queued %d, already on eBay %d, below 5%% %d, no eBay item id (skipped) %d, retry-throttled %d.',
                $channel,
                $stats['listings'],
                $stats['children'],
                $stats['saved'],
                $stats['unchanged'],
                $stats['queued'],
                $stats['in_sync'],
                $stats['below_min'],
                $stats['no_listing'],
                $stats['throttled']
            ));

            return $stats;
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  list<array{sku:string,prmt:int}>  $tasks
     * @param  list<string>  $throttleKeys
     * @param  array<string, mixed>  $stats
     */
    private function enqueuePush(string $channel, array $tasks, array $throttleKeys, array &$stats, callable $log): int
    {
        try {
            $response = app(ChannelPromoPricingController::class)
                ->queuePushPrmt(Request::create('/', 'POST', ['items' => $tasks]), $channel);
            $ok = $response->getStatusCode() < 400;
            $payload = $response->getData(true);
            if ($ok) {
                $log($channel.' Push PRMT%: '.($payload['message'] ?? 'queued').(! empty($payload['worker_spawned']) ? ' — worker started' : ''));

                return count($tasks);
            }
            $stats['errors'][] = 'queue: '.($payload['message'] ?? ('HTTP '.$response->getStatusCode()));
        } catch (\Throwable $e) {
            $stats['errors'][] = 'queue: '.$e->getMessage();
            Log::warning('eBay Dil→PRMT queue failed', ['channel' => $channel, 'error' => $e->getMessage()]);
        }
        // Not queued: let the next run try these listings again.
        foreach ($throttleKeys as $k) {
            Cache::forget($k);
        }

        return 0;
    }

    /**
     * Child SKU rows only, once per SKU (same filter as the page's chPromoIsChildRow + dataset flatten).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function childRows(array $rows): array
    {
        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            $sku = $this->skuOf($row);
            if ($sku === '' || stripos($sku, 'PARENT') !== false
                || ! empty($row['is_parent_summary']) || ! empty($row['is_parent']) || ! empty($row['is_parent_row'])) {
                continue;
            }
            $key = strtoupper($sku);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $row;
        }

        return $out;
    }

    /**
     * Listing key: shared eBay item id, else Parent, else the SKU (chPromoVariationKeyFn).
     *
     * @param  list<array<string, mixed>>  $children
     */
    private function variationKeyFn(array $children): \Closure
    {
        $parentToItem = [];
        foreach ($children as $row) {
            $parent = $this->parentOf($row);
            $itemId = $this->itemIdOf($row);
            if ($parent !== '' && $itemId !== '' && $itemId !== '0' && ! isset($parentToItem[$parent])) {
                $parentToItem[$parent] = $itemId;
            }
        }

        return function (array $row) use ($parentToItem): string {
            $itemId = $this->itemIdOf($row);
            if ($itemId !== '' && $itemId !== '0') {
                return 'item:'.$itemId;
            }
            $parent = $this->parentOf($row);
            if ($parent !== '' && isset($parentToItem[$parent])) {
                return 'item:'.$parentToItem[$parent];
            }
            if ($parent !== '') {
                return 'parent:'.$parent;
            }
            $sku = strtoupper($this->skuOf($row));

            return $sku !== '' ? 'sku:'.$sku : '';
        };
    }

    /** OV L30 as the page reads it (chPromoOvl30): L30, else eBay L30, else ov_l30. */
    private function ovL30(array $row): float
    {
        $v = (float) ($row['L30'] ?? $row['eBay L30'] ?? 0);
        if ($v == 0.0) {
            $v = (float) ($row['ov_l30'] ?? 0);
        }
        if ($v == 0.0) {
            $v = (float) ($row['ovl30'] ?? 0);
        }

        return $v;
    }

    private function skuOf(array $row): string
    {
        return trim((string) ($row['(Child) sku'] ?? $row['sku'] ?? ''));
    }

    private function parentOf(array $row): string
    {
        $raw = isset($row['Parent']) && trim((string) $row['Parent']) !== '' ? $row['Parent'] : ($row['parent'] ?? '');

        return strtoupper(trim((string) preg_replace('/^PARENT\s+/i', '', trim((string) $raw))));
    }

    private function itemIdOf(array $row): string
    {
        return trim((string) ($row['eBay_item_id'] ?? $row['item_id'] ?? $row['ebay_item_id'] ?? ''));
    }

    private function isEnded(array $row): bool
    {
        if (! empty($row['listing_ended'])) {
            return true;
        }
        $status = strtoupper(trim((string) ($row['listing_status'] ?? '')));

        return in_array($status, ['ENDED', 'INACTIVE', 'UNSOLD', 'COMPLETED', 'SOLD'], true);
    }
}
