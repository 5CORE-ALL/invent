<?php

namespace App\Support\Marketplace;

use App\Http\Controllers\MarketPlace\AliexpressController;
use App\Http\Controllers\MarketPlace\MacyController;
use App\Http\Controllers\MarketPlace\OverallAmazonController;
use App\Models\Ebay2Metric;
use App\Models\Ebay3Metric;
use App\Models\EbayMetric;
use App\Models\EbaySkuCompetitor;
use App\Models\GoogleSkuCompetitor;
use App\Models\MacySkuCompetitor;
use App\Models\MacysPriceData;
use App\Models\ProductMaster;
use App\Models\ReverbProduct;
use App\Models\ReverbSkuCompetitor;
use App\Models\ShopifySku;
use App\Models\Temu2Pricing;
use App\Models\Temu3Pricing;
use App\Models\TemuLmp;
use App\Models\TemuMetric;
use App\Models\TikTokProduct;
use App\Models\TikTokProductTwo;
use App\Models\TiktokSkuCompetitor;
use App\Services\LmpSkuGroupService;
use App\Services\TemuShopifySalesService;
use App\Services\TikTokShopService;
use App\Support\Marketplace\EbayListingEnded;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Live Price > LMP counts that mirror each analytics page badge.
 *
 * Channels with no LMP on the grid stay 0 (same as the page badge).
 */
class PriceGtLmpPageCounts
{
    /**
     * @return array<string, int>
     */
    public static function all(): array
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(180);

        $out = [];
        foreach (array_keys(LmpMissingChannelCounts::analytics()) as $key) {
            $out[$key] = 0;
        }

        $ctx = self::productMasterContext();

        $out['amazon'] = self::safe('amazon', fn () => app(OverallAmazonController::class)->countPriceGtLmp());
        $out['aliexpress'] = self::safe('aliexpress', fn () => self::countAliexpress());
        $out['ebay'] = self::safe('ebay', fn () => self::countEbay1($ctx));
        $out['ebay2'] = self::safe('ebay2', fn () => self::countEbay2($ctx));
        $out['ebay3'] = self::safe('ebay3', fn () => self::countEbay3($ctx));
        $out['shopifyb2c'] = self::safe('shopifyb2c', fn () => self::countShopifyB2c($ctx));
        $out['shopifyb2b'] = self::safe('shopifyb2b', fn () => self::countShopifyB2b($ctx));
        $out['macys'] = self::safe('macys', fn () => self::countMacys($ctx));
        $out['reverb'] = self::safe('reverb', fn () => self::countReverb($ctx));
        $out['temu'] = self::safe('temu', fn () => self::countTemu($ctx, 'temu'));
        $out['temu2'] = self::safe('temu2', fn () => self::countTemu($ctx, 'temu2'));
        $out['temu3'] = self::safe('temu3', fn () => self::countTemu($ctx, 'temu3'));
        $out['tiktok'] = self::safe('tiktok', fn () => self::countTiktok($ctx, 'v1'));
        $out['tiktok2'] = self::safe('tiktok2', fn () => self::countTiktok($ctx, 'v2'));

        return $out;
    }

    /**
     * @param  callable(): int  $fn
     */
    private static function safe(string $key, callable $fn): int
    {
        try {
            return (int) $fn();
        } catch (\Throwable $e) {
            Log::warning('PriceGtLmpPageCounts '.$key.' failed: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * @return array{skus: list<string>, shopify: Collection, groups: LmpSkuGroupService}
     */
    private static function productMasterContext(): array
    {
        $skus = ProductMaster::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->pluck('sku')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $groups = new LmpSkuGroupService();
        try {
            $groups->prepareForSkus($skus);
        } catch (\Throwable $e) {
            Log::warning('PriceGtLmpPageCounts LmpSkuGroupService failed: '.$e->getMessage());
        }

        return [
            'skus' => $skus,
            'shopify' => ShopifySku::mapByProductSkus($skus),
            'groups' => $groups,
        ];
    }

    private static function isParentSku(string $sku): bool
    {
        return str_contains(strtoupper(trim($sku)), 'PARENT');
    }

    private static function invFor(array $ctx, string $sku): float
    {
        $shopify = $ctx['shopify'][$sku] ?? null;

        return (float) ($shopify->inv ?? 0);
    }

    /**
     * @return list<string>
     */
    private static function linkedSkus(LmpSkuGroupService $groups, string $sku): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return [];
        }
        try {
            $group = $groups->groupContaining($sku);
        } catch (\Throwable $e) {
            $group = [];
        }
        $members = $group !== [] ? $group : [$sku];
        $seen = [];
        $out = [];
        foreach ($members as $member) {
            $display = trim((string) $member);
            $norm = strtoupper($display);
            if ($norm === '' || isset($seen[$norm])) {
                continue;
            }
            $seen[$norm] = true;
            $out[] = $display;
        }

        return $out !== [] ? $out : [$sku];
    }

    private static function countAliexpress(): int
    {
        $rows = app(AliexpressController::class)->buildPricingRows(false);
        $n = 0;
        foreach ($rows as $row) {
            if (is_array($row) && PriceGtLmpChannelCounts::rowHasRedTriangle($row, 'price')) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * /ebay-tabulator-view: INV > 0 and eBay Price > landed LMP (Sku Link).
     *
     * @param  array{skus: list<string>, shopify: Collection, groups: LmpSkuGroupService}  $ctx
     */
    private static function countEbay1(array $ctx): int
    {
        $metrics = collect();
        if (class_exists(EbayMetric::class)) {
            $cols = ['sku', 'ebay_price', 'item_id'];
            $query = EbayMetric::query();
            if (method_exists(EbayListingEnded::class, 'withStatusColumn')) {
                $cols = EbayListingEnded::withStatusColumn('ebay_metrics', $cols);
            }
            $metrics = $query->select($cols)->get()->keyBy(
                fn ($row) => ShopifySku::normalizeSkuForShopifyLookup((string) $row->sku)
            );
        }
        $details = self::ebayCompetitorDetails();

        $n = 0;
        foreach ($ctx['skus'] as $sku) {
            if (self::isParentSku((string) $sku)) {
                continue;
            }
            if (! (self::invFor($ctx, (string) $sku) > 0)) {
                continue;
            }
            $metric = $metrics[ShopifySku::normalizeSkuForShopifyLookup((string) $sku)] ?? null;
            $price = (float) ($metric->ebay_price ?? 0);
            $lmp = self::ebayLinkedLmp((string) $sku, $details, $ctx['groups']);
            if ($price > 0 && $lmp > 0 && $price > $lmp) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * /ebay2-tabulator-view Price cell: eBay Price > landed LMP (no INV gate).
     * The red badge on that page is S PRC ≥ LMP; LMP Issues is Price > LMP.
     *
     * @param  array{skus: list<string>, shopify: Collection, groups: LmpSkuGroupService}  $ctx
     */
    private static function countEbay2(array $ctx): int
    {
        $metrics = collect();
        if (class_exists(Ebay2Metric::class)) {
            $cols = ['id', 'sku', 'ebay_price', 'item_id'];
            $query = Ebay2Metric::query();
            if (method_exists(EbayListingEnded::class, 'withStatusColumn')) {
                $cols = EbayListingEnded::withStatusColumn('ebay_2_metrics', $cols);
            }
            $metrics = $query->select($cols)->orderBy('id')->get()
                ->groupBy(fn ($row) => ShopifySku::normalizeSkuForShopifyLookup((string) $row->sku))
                ->map(fn ($group) => EbayListingEnded::preferLiveMetric($group))
                ->filter();
        }
        $details = self::ebayCompetitorDetails();

        $n = 0;
        foreach ($ctx['skus'] as $sku) {
            if (self::isParentSku((string) $sku)) {
                continue;
            }
            $metric = $metrics[ShopifySku::normalizeSkuForShopifyLookup((string) $sku)] ?? null;
            $price = (float) ($metric->ebay_price ?? 0);
            $baseSku = null;
            if (stripos((string) $sku, 'OPEN BOX') !== false) {
                $base = trim(str_ireplace('OPEN BOX', '', (string) $sku));
                $baseSku = $base !== '' ? $base : null;
            }
            $lmp = self::ebayLinkedLmp((string) $sku, $details, $ctx['groups'], $baseSku);
            if ($price > 0 && $lmp > 0 && $price > $lmp) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * /ebay3-tabulator-view: eBay Price > LMP, no INV gate, per-SKU LMP (no Sku Link merge).
     *
     * @param  array{skus: list<string>, shopify: Collection, groups: LmpSkuGroupService}  $ctx
     */
    private static function countEbay3(array $ctx): int
    {
        $exact = collect();
        $byNorm = [];
        if (class_exists(Ebay3Metric::class)) {
            $cols = ['sku', 'ebay_price', 'item_id'];
            $query = Ebay3Metric::query();
            if (method_exists(EbayListingEnded::class, 'withStatusColumn')) {
                $cols = EbayListingEnded::withStatusColumn('ebay_3_metrics', $cols);
            }
            $all = $query->select($cols)->get();
            $exact = $all->keyBy('sku');
            foreach ($all as $metric) {
                $nk = self::ebay3Norm((string) ($metric->sku ?? ''));
                if ($nk === '') {
                    continue;
                }
                if (! isset($byNorm[$nk]) || (empty($byNorm[$nk]->item_id) && ! empty($metric->item_id))) {
                    $byNorm[$nk] = $metric;
                }
            }
        }
        $lookups = EbaySkuCompetitor::buildGroupedLookup('ebay');

        $n = 0;
        foreach ($ctx['skus'] as $sku) {
            if (self::isParentSku((string) $sku)) {
                continue;
            }
            $metric = $exact->get($sku) ?? ($byNorm[self::ebay3Norm((string) $sku)] ?? null);
            $price = (float) ($metric->ebay_price ?? 0);
            $row = [];
            $baseSku = null;
            if (stripos((string) $sku, 'OPEN BOX') !== false) {
                $base = trim(str_ireplace('OPEN BOX', '', (string) $sku));
                $baseSku = $base !== '' ? $base : null;
            }
            EbaySkuCompetitor::applyToRow($row, (string) $sku, $lookups['lowest'], $lookups['details'], $baseSku);
            $lmp = self::lmpFromRow($row);
            if ($price > 0 && $lmp > 0 && $price > $lmp) {
                $n++;
            }
        }

        return $n;
    }

    private static function ebay3Norm(string $sku): string
    {
        $sku = strtoupper(trim($sku));
        $sku = preg_replace('/\s+/u', ' ', $sku) ?? $sku;

        return trim((string) preg_replace('/[^\S\r\n]+/u', ' ', $sku));
    }

    private static function ebayCompetitorDetails(): Collection
    {
        try {
            return EbaySkuCompetitor::where('marketplace', 'ebay')
                ->where('total_price', '>', 0)
                ->orderBy('total_price')
                ->get()
                ->groupBy(fn ($item) => strtoupper(preg_replace('/\s+/', ' ', trim((string) $item->sku))));
        } catch (\Throwable $e) {
            return collect();
        }
    }

    private static function ebayLinkedLmp(
        string $sku,
        Collection $details,
        LmpSkuGroupService $groups,
        ?string $fallbackSku = null
    ): float {
        $row = [];
        EbaySkuCompetitor::applyLinkedGroupToRow(
            $row,
            $sku,
            $details,
            self::linkedSkus($groups, $sku),
            $fallbackSku
        );

        return self::lmpFromRow($row);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function lmpFromRow(array $row): float
    {
        return self::rowLmpValue($row);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function rowLmpValue(array $row): float
    {
        $entries = $row['lmp_entries'] ?? null;
        if (is_array($entries) && $entries !== []) {
            $lowest = 0.0;
            foreach ($entries as $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                $ignored = $entry['ignored'] ?? null;
                if ($ignored === true || $ignored === 1 || $ignored === '1') {
                    continue;
                }
                $p = $entry['landed_price'] ?? ($entry['total_price'] ?? ($entry['price'] ?? 0));
                $n = is_numeric($p) ? (float) $p : 0.0;
                if ($n > 0 && ($lowest <= 0 || $n < $lowest)) {
                    $lowest = $n;
                }
            }

            return $lowest;
        }

        $fallback = $row['lmp_price'] ?? ($row['lmp'] ?? 0);

        return is_numeric($fallback) ? (float) $fallback : 0.0;
    }

    /**
     * @param  array{skus: list<string>, shopify: Collection, groups: LmpSkuGroupService}  $ctx
     */
    private static function countShopifyB2c(array $ctx): int
    {
        $details = GoogleSkuCompetitor::buildGroupedLookup('google')['details'] ?? collect();
        $n = 0;
        foreach ($ctx['skus'] as $sku) {
            if (self::isParentSku((string) $sku)) {
                continue;
            }
            $inv = self::invFor($ctx, (string) $sku);
            if (! ($inv > 0)) {
                continue;
            }
            $shopify = $ctx['shopify'][$sku] ?? null;
            $price = (float) ($shopify->price ?? 0);
            $lmp = self::googleLowestPrice((string) $sku, $details, $ctx['groups'], false);
            if ($price > 0 && $lmp > 0 && $price > $lmp) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * @param  array{skus: list<string>, shopify: Collection, groups: LmpSkuGroupService}  $ctx
     */
    private static function countShopifyB2b(array $ctx): int
    {
        $store = [];
        if (Schema::hasTable('store_listing_prices')) {
            foreach (DB::table('store_listing_prices')->whereNotNull('sku')->where('sku', '!=', '')->get(['sku', 'selling_price']) as $row) {
                $k = ShopifySku::normalizeSkuForShopifyLookup((string) $row->sku);
                if ($k !== '' && ! isset($store[$k])) {
                    $store[$k] = (float) ($row->selling_price ?? 0);
                }
            }
        }
        $details = GoogleSkuCompetitor::buildGroupedLookup('google')['details'] ?? collect();
        $n = 0;
        foreach ($ctx['skus'] as $sku) {
            if (self::isParentSku((string) $sku)) {
                continue;
            }
            if (! (self::invFor($ctx, (string) $sku) > 0)) {
                continue;
            }
            $price = (float) ($store[ShopifySku::normalizeSkuForShopifyLookup((string) $sku)] ?? 0);
            $lmp = self::googleLowestPrice((string) $sku, $details, $ctx['groups'], true);
            if ($price > 0 && $lmp > 0 && $price > $lmp) {
                $n++;
            }
        }

        return $n;
    }

    private static function googleLowestPrice(
        string $sku,
        Collection $details,
        LmpSkuGroupService $groups,
        bool $skipIgnored
    ): float {
        $lowest = 0.0;
        $seen = [];
        foreach (self::linkedSkus($groups, $sku) as $linked) {
            $group = $details->get(GoogleSkuCompetitor::normalizeSkuKey($linked));
            if (! $group instanceof Collection) {
                continue;
            }
            foreach ($group as $comp) {
                $dedupe = GoogleSkuCompetitor::offerDedupeKey($comp);
                if (isset($seen[$dedupe])) {
                    continue;
                }
                $seen[$dedupe] = true;
                if ($skipIgnored && ! empty($comp->ignored)) {
                    continue;
                }
                $p = is_numeric($comp->price ?? null) ? (float) $comp->price : 0.0;
                if ($p > 0 && ($lowest <= 0 || $p < $lowest)) {
                    $lowest = $p;
                }
            }
        }

        return $lowest;
    }

    /**
     * @param  array{skus: list<string>, shopify: Collection, groups: LmpSkuGroupService}  $ctx
     */
    private static function countMacys(array $ctx): int
    {
        $sheet = [];
        if (class_exists(MacysPriceData::class)) {
            foreach (MacysPriceData::query()->whereNotNull('sku')->where('sku', '!=', '')->get(['sku', 'price']) as $row) {
                $sheet[strtoupper((string) $row->sku)] = $row;
            }
        }
        $details = collect();
        try {
            $details = MacySkuCompetitor::buildGroupedLookup('macy')['details'] ?? collect();
        } catch (\Throwable $e) {
            // ignore
        }

        $n = 0;
        foreach ($ctx['skus'] as $sku) {
            if (self::isParentSku((string) $sku)) {
                continue;
            }
            if (! (self::invFor($ctx, (string) $sku) > 0)) {
                continue;
            }
            $resolved = MacyController::resolveListedPrice(null, $sheet[strtoupper((string) $sku)] ?? null);
            $price = (float) ($resolved['price'] ?? 0);
            $lmp = self::macyOrReverbLmp((string) $sku, $details, $ctx['groups'], MacySkuCompetitor::class);
            if ($price > 0 && $lmp > 0 && $price > $lmp) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * @param  array{skus: list<string>, shopify: Collection, groups: LmpSkuGroupService}  $ctx
     */
    private static function countReverb(array $ctx): int
    {
        $products = ReverbProduct::buildLookupByNormalizedSku($ctx['skus']);
        $details = collect();
        try {
            $details = ReverbSkuCompetitor::buildGroupedLookup('reverb')['details'] ?? collect();
        } catch (\Throwable $e) {
            // ignore
        }

        $n = 0;
        foreach ($ctx['skus'] as $sku) {
            if (self::isParentSku((string) $sku)) {
                continue;
            }
            if (! (self::invFor($ctx, (string) $sku) > 0)) {
                continue;
            }
            $product = $products[ReverbProduct::normalizeSkuForLookup((string) $sku)] ?? null;
            $price = (float) ($product->price ?? 0);
            $lmp = self::macyOrReverbLmp((string) $sku, $details, $ctx['groups'], ReverbSkuCompetitor::class);
            if ($price > 0 && $lmp > 0 && $price > $lmp) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * @param  class-string  $competitorClass
     */
    private static function macyOrReverbLmp(
        string $sku,
        Collection $details,
        LmpSkuGroupService $groups,
        string $competitorClass
    ): float {
        $all = collect();
        foreach (self::linkedSkus($groups, $sku) as $linked) {
            foreach ($competitorClass::resolveLookupKeys($linked) as $key) {
                $entries = $details->get($key);
                if ($entries instanceof Collection && $entries->isNotEmpty()) {
                    $all = $all->merge($entries);
                }
            }
        }
        if (method_exists($competitorClass, 'dedupeByItemId')) {
            $all = $competitorClass::dedupeByItemId($all);
        }
        $all = $all->filter(fn ($entry) => (float) ($entry->total_price ?? 0) > 0)
            ->sortBy(fn ($entry) => (float) ($entry->total_price ?? 0))
            ->values();
        $lowest = $all->first(fn ($c) => empty($c->ignored));

        return $lowest ? (float) $lowest->total_price : 0.0;
    }

    /**
     * Temu 1/2/3: temu_price (base + $2.99 when ≤ 26.99) vs recovery LMP. INV > 0.
     *
     * @param  array{skus: list<string>, shopify: Collection, groups: LmpSkuGroupService}  $ctx
     */
    private static function countTemu(array $ctx, string $channel): int
    {
        $normMap = [];
        foreach ($ctx['skus'] as $sku) {
            $k = self::temuNorm((string) $sku);
            if ($k !== '' && ! isset($normMap[$k])) {
                $normMap[$k] = (string) $sku;
            }
        }

        $priceByPm = [];
        if ($channel === 'temu') {
            foreach (TemuMetric::query()->get(['sku', 'base_price', 'recommended_base_price']) as $row) {
                $pm = $normMap[self::temuNorm((string) $row->sku)] ?? null;
                if ($pm === null) {
                    continue;
                }
                $base = TemuShopifySalesService::resolveListingBasePrice($row->base_price ?? 0, $row->recommended_base_price ?? null);
                $priceByPm[$pm] = PriceGtLmpChannelCounts::temuListingPrice($base);
            }
        } else {
            $model = $channel === 'temu3' ? Temu3Pricing::class : Temu2Pricing::class;
            $table = $channel === 'temu3' ? 'temu3_pricing' : 'temu2_pricing';
            if (Schema::hasTable($table)) {
                foreach ($model::query()->get(['sku', 'base_price']) as $row) {
                    $pm = $normMap[self::temuNorm((string) $row->sku)] ?? null;
                    if ($pm === null) {
                        continue;
                    }
                    $priceByPm[$pm] = PriceGtLmpChannelCounts::temuListingPrice((float) ($row->base_price ?? 0));
                }
            }
        }

        $lmpByNorm = [];
        if (Schema::hasTable('temu_lmp')) {
            foreach (TemuLmp::query()->whereNotNull('sku')->where('sku', '!=', '')->get() as $row) {
                $k = self::temuNorm((string) $row->sku);
                if ($k !== '') {
                    $lmpByNorm[$k] = $row;
                }
            }
        }

        $n = 0;
        foreach ($ctx['skus'] as $sku) {
            if (self::isParentSku((string) $sku)) {
                continue;
            }
            if (! (self::invFor($ctx, (string) $sku) > 0)) {
                continue;
            }
            $price = (float) ($priceByPm[$sku] ?? 0);
            $lmp = self::temuRecoveryLmpForSku((string) $sku, $lmpByNorm, $ctx['groups']);
            if ($price > 0 && $lmp > 0 && $price > $lmp) {
                $n++;
            }
        }

        return $n;
    }

    public static function temuNorm(string $sku): string
    {
        $sku = strtoupper(trim($sku));
        $sku = preg_replace('/(\d+)\s*(PCS?|PIECES?)$/i', '$1PC', $sku) ?? $sku;

        return (string) preg_replace('/\s+/', ' ', $sku);
    }

    /**
     * @param  array<string, TemuLmp>  $lmpByNorm
     */
    private static function temuRecoveryLmpForSku(string $sku, array $lmpByNorm, LmpSkuGroupService $groups): float
    {
        $entries = [];
        foreach (self::linkedSkus($groups, $sku) as $linked) {
            $row = $lmpByNorm[self::temuNorm($linked)] ?? null;
            if (! $row) {
                continue;
            }
            foreach (self::temuExtractEntries($row) as $entry) {
                if (is_array($entry)) {
                    $entries[] = $entry;
                }
            }
        }
        $seen = [];
        $unique = [];
        foreach ($entries as $entry) {
            $key = (string) ($entry['price'] ?? '').'|'.strtoupper(trim((string) ($entry['link'] ?? '')));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $entry;
        }

        $lowest = 0.0;
        foreach ($unique as $entry) {
            if (! empty($entry['ignored'])) {
                continue;
            }
            $eff = self::temuEntryEffective($entry);
            if ($eff > 0 && ($lowest <= 0 || $eff < $lowest)) {
                $lowest = $eff;
            }
        }
        if ($lowest <= 0) {
            $own = $lmpByNorm[self::temuNorm($sku)] ?? null;
            $fallback = $own ? (float) ($own->lmp ?? 0) : 0.0;
            $lowest = $fallback > 0 ? $fallback : 0.0;
        }

        return $lowest > 0 ? PriceGtLmpChannelCounts::temuRecoveryLmp($lowest) : 0.0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function temuExtractEntries(TemuLmp $row): array
    {
        $entries = $row->lmp_entries;
        if (is_array($entries)) {
            return array_values($entries);
        }
        $out = [];
        if ($row->lmp !== null || $row->lmp_link) {
            $out[] = ['price' => $row->lmp, 'link' => $row->lmp_link];
        }
        if ($row->lmp_2 !== null || $row->lmp_link_2) {
            $out[] = ['price' => $row->lmp_2, 'link' => $row->lmp_link_2];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private static function temuEntryEffective(array $entry): float
    {
        $p = is_numeric($entry['price'] ?? null) ? (float) $entry['price'] : 0.0;
        if (! ($p > 0)) {
            return 0.0;
        }
        $d = (is_numeric($entry['delivery'] ?? null) && (float) $entry['delivery'] > 0)
            ? (float) $entry['delivery']
            : 0.0;
        if ($d <= 0 && $p < 27) {
            $d = 2.99;
        }

        return round($p + $d, 2);
    }

    /**
     * @param  array{skus: list<string>, shopify: Collection, groups: LmpSkuGroupService}  $ctx
     */
    private static function countTiktok(array $ctx, string $variant): int
    {
        $model = $variant === 'v2' ? TikTokProductTwo::class : TikTokProduct::class;
        $tiktok = collect();
        foreach ($model::query()->get() as $item) {
            $key = strtoupper(str_replace("\u{00a0}", ' ', trim((string) $item->sku)));
            if ($key === '') {
                continue;
            }
            $existing = $tiktok->get($key);
            $itemLive = TikTokShopService::isLiveListingStatus($item->listing_status ?? '');
            $existingLive = $existing
                ? TikTokShopService::isLiveListingStatus($existing->listing_status ?? '')
                : false;
            if ($existing === null
                || ($itemLive && ! $existingLive)
                || ((float) ($existing->price ?? 0) <= 0 && (float) ($item->price ?? 0) > 0 && $itemLive === $existingLive)) {
                $tiktok->put($key, $item);
            }
        }
        $details = collect();
        try {
            $details = TiktokSkuCompetitor::buildGroupedLookup('tiktok')['details'] ?? collect();
        } catch (\Throwable $e) {
            // ignore
        }

        $n = 0;
        foreach ($ctx['skus'] as $sku) {
            if (self::isParentSku((string) $sku)) {
                continue;
            }
            if (! (self::invFor($ctx, (string) $sku) > 0)) {
                continue;
            }
            $key = strtoupper(str_replace("\u{00a0}", ' ', trim((string) $sku)));
            $item = $tiktok->get($key);
            $price = (float) ($item->price ?? 0);
            $lmp = self::tiktokLandedLmp((string) $sku, $details, $ctx['groups']);
            if ($price > 0 && $lmp > 0 && $price > $lmp) {
                $n++;
            }
        }

        return $n;
    }

    private static function tiktokLandedLmp(string $sku, Collection $details, LmpSkuGroupService $groups): float
    {
        $merged = collect();
        $seen = [];
        foreach (self::linkedSkus($groups, $sku) as $linked) {
            $group = $details->get(TiktokSkuCompetitor::normalizeSkuKey($linked));
            if (! $group instanceof Collection) {
                continue;
            }
            foreach ($group as $entry) {
                $dedupe = ((string) ($entry->id ?? '')).'|'
                    .((string) ($entry->product_id ?? '')).'|'
                    .strtoupper(trim((string) ($entry->product_link ?? '')));
                if (isset($seen[$dedupe])) {
                    continue;
                }
                $seen[$dedupe] = true;
                $merged->push($entry);
            }
        }
        $lowest = TiktokSkuCompetitor::lowestFromCollection($merged);
        if (! $lowest) {
            return 0.0;
        }
        $base = is_numeric($lowest->price ?? null) ? (float) $lowest->price : 0.0;
        $ship = is_numeric($lowest->shipping_cost ?? null) ? (float) $lowest->shipping_cost : 0.0;

        return $base > 0 ? round($base + $ship, 2) : 0.0;
    }
}
