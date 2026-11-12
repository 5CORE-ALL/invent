<?php

namespace App\Services;

use App\Models\ChannelTabulatorColumnSetting;
use App\Support\EbayDilCoupon;
use App\Support\EbayRuleSprice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Headless EL30 Dil % coupon for /ebay-tabulator-view, /ebay2-tabulator-view and /ebay3-tabulator-view.
 *
 * The page pushes this coupon from the browser (4 SKUs per request, only while the page is open). This job does the
 * same thing on the server, using the same saved On/Off switch and slabs ({channel}_zero_sold_coupon setting):
 *
 *  - EL30 Dil % = E L30 ÷ INV × 100. eBay 2 uses the parent's summed E L30 and INV, eBay 1 and 3 use the SKU's own.
 *  - The slab gives the coupon % (below 5 or above 80 = no coupon). Code = SAVE{nn}OFF.
 *  - Only a SKU whose saved coupon code differs from the wanted code is sent to eBay.
 *
 * Safety rules:
 *  - Switch Off = the job does nothing. It never removes coupons because the switch is off (the page does that).
 *  - No stacking: a SKU on a PRMT sale (live PEF_SALE_PCT or saved prmt_pct of 5+) is never GIVEN a coupon. A coupon it
 *    already has is left alone, unless $stripStacked is on (--strip-stacked), which removes it.
 *  - SKUs with no eBay item id or an ended listing are skipped (eBay can only answer "item_id not found").
 *  - At most MAX_PER_RUN SKUs per channel per run, so a first run cannot hammer eBay. The rest follow next run.
 *  - A SKU that fails is not retried for RETRY_MINUTES.
 */
class EbayDilCouponAutoApplyService
{
    private const RETRY_MINUTES = 60;

    private const CHUNK = 10;

    public const MAX_PER_RUN = 120;

    public function __construct(private readonly EbaySprcDilAutoPushService $tabulator) {}

    /**
     * @return array{skus:int,wanted_on:int,wanted_off:int,in_sync:int,sent:int,ok:int,failed:int,skipped_prmt:int,
     *     skipped_no_listing:int,kept_stacked:int,throttled:int,deferred:int,switch_off:bool,errors:list<string>}
     */
    public function run(string $channel, bool $dryRun = false, ?callable $logger = null, ?int $max = null, bool $stripStacked = false): array
    {
        $channel = strtolower(trim($channel));
        if (! in_array($channel, EbayRuleSprice::CHANNELS, true)) {
            throw new \InvalidArgumentException('channel must be one of: '.implode(', ', EbayRuleSprice::CHANNELS));
        }
        $max = $max !== null && $max > 0 ? $max : self::MAX_PER_RUN;
        $log = $logger ?? static function (string $msg): void {};
        $stats = [
            'skus' => 0,
            'wanted_on' => 0,
            'wanted_off' => 0,
            'in_sync' => 0,
            'sent' => 0,
            'ok' => 0,
            'failed' => 0,
            'skipped_prmt' => 0,
            'skipped_no_listing' => 0,
            'kept_stacked' => 0,
            'throttled' => 0,
            'deferred' => 0,
            'switch_off' => false,
            'errors' => [],
        ];

        $cfg = $this->config($channel);
        if (! $cfg['enabled']) {
            $stats['switch_off'] = true;
            $log($channel.': EL30 Dil % coupon switch is Off — nothing to do.');

            return $stats;
        }

        $lock = Cache::lock('ebay-dil-coupon-auto-apply-run-'.$channel, 1800);
        if (! $lock->get()) {
            $log('eBay Dil coupon run already in progress for '.$channel.' — skipped.');
            $stats['errors'][] = 'lock: already running';

            return $stats;
        }

        try {
            $children = $this->childRows($this->tabulator->tabulatorRows($channel));
            $stats['skus'] = count($children);
            $family = $channel === 'ebay2' ? $this->familyTotals($children) : [];
            $service = Ebay1CouponService::for($channel);

            /** @var array<string, list<array{sku:string,on:bool}>> $groups keyed by wanted coupon %, or "off" */
            $groups = [];
            $planned = 0;
            foreach ($children as $row) {
                if ($this->isEnded($row)) {
                    continue;
                }
                if ($this->itemIdOf($row) === '') {
                    $stats['skipped_no_listing']++;

                    continue;
                }
                $sku = $this->skuOf($row);
                if ($channel === 'ebay2') {
                    $fam = $family[$this->familyKey($row)] ?? null;
                    $el30 = $fam['l30'] ?? $this->el30Of($row);
                    $inv = $fam['inv'] ?? $this->invOf($row);
                } else {
                    $el30 = $this->el30Of($row);
                    $inv = $this->invOf($row);
                }
                $pct = EbayDilCoupon::percentFor($el30, $inv, $cfg['slabs']);

                // No stacking with a PRMT sale (live sale % or the saved PRMT% the PRMT job pushes).
                $prmt = max(
                    (int) round(max(0.0, (float) ($row['PEF_SALE_PCT'] ?? 0))),
                    (int) round(max(0.0, (float) ($row['prmt_pct'] ?? $row['_prmt_pct_applied'] ?? 0)))
                );
                $stored = strtoupper(trim((string) ($row['zero_sold_coupon_code'] ?? '')));
                if ($pct >= 5 && $prmt >= 5) {
                    $stats['skipped_prmt']++;
                    if ($stored !== '' && ! $stripStacked) {
                        // Already has a coupon: leave it. Only --strip-stacked removes it.
                        $stats['kept_stacked']++;

                        continue;
                    }
                    $pct = 0;
                }

                $want = $pct >= 5 ? $service->zeroSoldCouponCode($pct) : '';
                if (strtoupper($want) === $stored) {
                    $stats['in_sync']++;

                    continue;
                }
                $failKey = 'ebay-dil-cpn-failed:'.$channel.':'.md5(strtoupper($sku).'|'.$want);
                if (Cache::has($failKey)) {
                    $stats['throttled']++;

                    continue;
                }
                if ($planned >= $max) {
                    $stats['deferred']++;

                    continue;
                }
                $planned++;
                $key = $pct >= 5 ? (string) $pct : 'off';
                $groups[$key][] = ['sku' => $sku, 'on' => $pct >= 5];
                $pct >= 5 ? $stats['wanted_on']++ : $stats['wanted_off']++;
                if ($dryRun) {
                    $log(sprintf('  DRY %s coupon %s → %s', $sku, $stored !== '' ? $stored : 'none', $want !== '' ? $want : 'none'));
                }
            }

            if ($dryRun) {
                $stats['sent'] = $planned;
            } else {
                $promos = $cfg['promotions'];
                foreach ($groups as $key => $items) {
                    $pct = $key === 'off' ? 5 : (int) $key;
                    $promoId = $key === 'off' ? null : ($promos[(string) $pct] ?? null);
                    foreach (array_chunk($items, self::CHUNK) as $chunk) {
                        $stats['sent'] += count($chunk);
                        try {
                            $result = $service->syncZeroSoldCoupons($chunk, $pct, $promoId);
                        } catch (\Throwable $e) {
                            Log::warning('eBay Dil coupon sync crashed', ['channel' => $channel, 'pct' => $pct, 'error' => $e->getMessage()]);
                            $result = ['success' => false, 'message' => $e->getMessage(), 'results' => []];
                        }
                        if ($key !== 'off') {
                            $savedId = trim((string) ($result['promotion_id'] ?? ''));
                            if ($savedId !== '') {
                                $promos[(string) $pct] = $savedId;
                                $promoId = $savedId;
                            }
                        }
                        $results = is_array($result['results'] ?? null) ? $result['results'] : [];
                        if ($results === []) {
                            $stats['failed'] += count($chunk);
                            $stats['errors'][] = 'coupon '.($key === 'off' ? 'remove' : $key.'%').': '.($result['message'] ?? 'no result');
                            foreach ($chunk as $item) {
                                $this->markFailed($channel, $item['sku'], $key === 'off' ? '' : $service->zeroSoldCouponCode($pct));
                            }

                            continue;
                        }
                        foreach ($results as $r) {
                            if (! empty($r['success'])) {
                                $stats['ok']++;

                                continue;
                            }
                            $stats['failed']++;
                            $stats['errors'][] = ($r['sku'] ?? '?').': '.($r['message'] ?? 'failed');
                            $this->markFailed($channel, (string) ($r['sku'] ?? ''), $key === 'off' ? '' : $service->zeroSoldCouponCode($pct));
                        }
                    }
                }
                $this->savePromotions($channel, $promos);
            }

            $log(sprintf(
                '%s: %d SKUs; coupon on %d, off %d; sent %d (ok %d, failed %d); in sync %d, PRMT sale (no new coupon) %d (kept existing coupon %d), no eBay item id %d, retry-throttled %d, deferred to next run %d.',
                $channel,
                $stats['skus'],
                $stats['wanted_on'],
                $stats['wanted_off'],
                $stats['sent'],
                $stats['ok'],
                $stats['failed'],
                $stats['in_sync'],
                $stats['skipped_prmt'],
                $stats['kept_stacked'],
                $stats['skipped_no_listing'],
                $stats['throttled'],
                $stats['deferred']
            ));

            return $stats;
        } finally {
            $lock->release();
        }
    }

    private function markFailed(string $channel, string $sku, string $want): void
    {
        if ($sku === '') {
            return;
        }
        Cache::put(
            'ebay-dil-cpn-failed:'.$channel.':'.md5(strtoupper(trim($sku)).'|'.$want),
            1,
            now()->addMinutes(self::RETRY_MINUTES)
        );
    }

    /**
     * Saved switch, slabs and promotion ids (same row the page saves: {channel}_zero_sold_coupon).
     *
     * @return array{enabled:bool,slabs:list<array{min:float,max:float,pct:int}>,promotions:array<string,string>}
     */
    private function config(string $channel): array
    {
        $row = ChannelTabulatorColumnSetting::query()->where('channel_name', $channel.'_zero_sold_coupon')->first();
        $vis = is_array($row?->visibility) ? $row->visibility : [];
        $slabs = EbayDilCoupon::normalize($vis['slabs'] ?? null);
        if ($slabs === []) {
            $pct = isset($vis['pct']) && is_numeric($vis['pct']) ? (int) round((float) $vis['pct']) : 5;
            $slabs = EbayDilCoupon::fromLegacy($pct >= 5 && $pct <= 80 ? $pct : 5);
        }
        $promos = [];
        foreach (is_array($vis['promotions'] ?? null) ? $vis['promotions'] : [] as $pct => $id) {
            $id = trim((string) $id);
            $pctInt = (int) $pct;
            if ($id !== '' && $pctInt >= 5 && $pctInt <= 80) {
                $promos[(string) $pctInt] = $id;
            }
        }

        return [
            'enabled' => $row ? filter_var($vis['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN) : false,
            'slabs' => $slabs,
            'promotions' => $promos,
        ];
    }

    /**
     * Keep the promotion ids this run created. Re-reads the row so a switch/slab change saved from the page
     * while this run was working is not overwritten.
     *
     * @param  array<string,string>  $promos
     */
    private function savePromotions(string $channel, array $promos): void
    {
        $row = ChannelTabulatorColumnSetting::query()->where('channel_name', $channel.'_zero_sold_coupon')->first();
        if (! $row) {
            return;
        }
        $vis = is_array($row->visibility) ? $row->visibility : [];
        $current = is_array($vis['promotions'] ?? null) ? $vis['promotions'] : [];
        $merged = array_replace($current, $promos);
        if ($merged === $current) {
            return;
        }
        $vis['promotions'] = $merged;
        $row->visibility = $vis;
        $row->save();
    }

    /**
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
     * eBay 2: E L30 and INV summed per parent (the page's familyMap()).
     *
     * @param  list<array<string, mixed>>  $children
     * @return array<string, array{inv:float,l30:float}>
     */
    private function familyTotals(array $children): array
    {
        $map = [];
        foreach ($children as $row) {
            $key = $this->familyKey($row);
            $map[$key] ??= ['inv' => 0.0, 'l30' => 0.0];
            $map[$key]['inv'] += $this->invOf($row);
            $map[$key]['l30'] += $this->el30Of($row);
        }

        return $map;
    }

    private function familyKey(array $row): string
    {
        $raw = isset($row['Parent']) && trim((string) $row['Parent']) !== '' ? $row['Parent'] : ($row['parent'] ?? '');
        $p = strtoupper(trim((string) preg_replace('/^PARENT\s+/i', '', trim((string) $raw))));

        return ($p !== '' && $p !== 'PARENT') ? $p : 'SKU:'.strtoupper($this->skuOf($row));
    }

    /** E L30 = the "eBay L30" column (what the page's rowMetrics() reads). */
    private function el30Of(array $row): float
    {
        return max(0.0, (float) ($row['eBay L30'] ?? 0));
    }

    private function invOf(array $row): float
    {
        return (float) ($row['INV'] ?? 0);
    }

    private function skuOf(array $row): string
    {
        return trim((string) ($row['(Child) sku'] ?? $row['sku'] ?? ''));
    }

    private function itemIdOf(array $row): string
    {
        $id = trim((string) ($row['eBay_item_id'] ?? $row['item_id'] ?? $row['ebay_item_id'] ?? ''));

        return $id === '0' ? '' : $id;
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
