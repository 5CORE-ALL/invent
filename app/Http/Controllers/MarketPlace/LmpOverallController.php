<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\AmazonDataView;
use App\Models\AmazonSkuCompetitor;
use App\Models\EbaySkuCompetitor;
use App\Models\GoogleSkuCompetitor;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\TemuLmp;
use App\Services\LmpSkuGroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class LmpOverallController extends Controller
{
    public const CVR_AVG_CACHE_KEY = 'lmp_overall_cvr_avg_metrics_v1';

    public function index(): View
    {
        return view('market-places.lmp_overall', [
            'amazonAdsPercent' => $this->amazonAdsPercent(),
        ]);
    }

    /**
     * Lowest LMP across Amz, eBay, Temu, and Google, plus My LMP.
     * Both maps are keyed by normalized SKU.
     *
     * @param  list<string>  $skus
     * @return array{lmp: array<string, float>, my_lmp: array<string, float>}
     */
    public function lmpMapsForSkus(array $skus): array
    {
        $groups = app(LmpSkuGroupService::class);
        try {
            $groups->prepareForSkus($skus);
        } catch (\Throwable $e) {
            Log::warning('LMP Overall: SKU link groups failed', ['error' => $e->getMessage()]);
        }

        $amzLookup = $this->groupedLookup(fn () => AmazonSkuCompetitor::buildGroupedLookup('amazon'));
        $ebayLookup = $this->groupedLookup(fn () => EbaySkuCompetitor::buildGroupedLookup('ebay'));
        $googleLookup = $this->groupedLookup(fn () => GoogleSkuCompetitor::buildGroupedLookup('google'));
        $temuBySku = $this->temuLowestBySku()['price'];
        $myLmpBySku = $this->amazonManualPrices()['my_lmp'];

        $lmp = [];
        $mine = [];
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            if ($sku === '') {
                continue;
            }
            $members = $groups->groupContaining($sku);
            if ($members === []) {
                $members = [$sku];
            }
            $key = $this->skuKey($sku);
            if ($key === '') {
                continue;
            }
            $prices = array_filter([
                $this->minAcrossGroup(
                    $members,
                    $amzLookup['lowest'],
                    fn (string $member) => AmazonSkuCompetitor::normalizeSkuKey($member),
                    fn ($row) => AmazonSkuCompetitor::landedPrice($row)
                ),
                $this->minAcrossGroup(
                    $members,
                    $ebayLookup['lowest'],
                    fn (string $member) => EbaySkuCompetitor::normalizeSkuKey($member),
                    function ($row) {
                        $price = $row->total_price ?? null;

                        return is_numeric($price) && (float) $price > 0 ? (float) $price : null;
                    }
                ),
                $this->minTemuAcrossGroup($members, $temuBySku),
                $this->minAcrossGroup(
                    $members,
                    $googleLookup['lowest'],
                    fn (string $member) => GoogleSkuCompetitor::normalizeSkuKey($member),
                    function ($row) {
                        $price = $row->price ?? null;

                        return is_numeric($price) && (float) $price > 0 ? (float) $price : null;
                    }
                ),
            ], fn ($price) => is_numeric($price) && (float) $price > 0);
            if ($prices !== []) {
                $lmp[$key] = round(min($prices), 2);
            }
            $my = $this->priceForGroup($members, $myLmpBySku);
            if ($my !== null && $my > 0) {
                $mine[$key] = $my;
            }
        }

        return ['lmp' => $lmp, 'my_lmp' => $mine];
    }

    public function data(): JsonResponse
    {
        $products = ProductMaster::query()
            ->whereNull('deleted_at')
            ->whereRaw('UPPER(TRIM(sku)) NOT LIKE ?', ['PARENT%'])
            ->orderBy('parent')
            ->orderBy('sku')
            ->get(['id', 'parent', 'sku', 'main_image', 'Values']);

        $skus = $products->pluck('sku')->filter()->unique()->values()->all();
        $cvrBySku = $this->pricingCvrAvgBySku();
        $shopifyBySku = ShopifySku::mapByProductSkus($skus);

        $groups = app(LmpSkuGroupService::class);
        try {
            $groups->prepareForSkus($skus);
        } catch (\Throwable $e) {
            Log::warning('LMP Overall: SKU link groups failed', ['error' => $e->getMessage()]);
        }

        $amzLookup = $this->groupedLookup(fn () => AmazonSkuCompetitor::buildGroupedLookup('amazon'));
        $ebayLookup = $this->groupedLookup(fn () => EbaySkuCompetitor::buildGroupedLookup('ebay'));
        $googleLookup = $this->groupedLookup(fn () => GoogleSkuCompetitor::buildGroupedLookup('google'));
        $temuStats = $this->temuLowestBySku();
        $temuBySku = $temuStats['price'];
        $temuCountBySku = $temuStats['count'];
        $manual = $this->amazonManualPrices();
        $stdBySku = $manual['std'];
        $myLmpBySku = $manual['my_lmp'];

        $rows = [];
        foreach ($products as $product) {
            $sku = trim((string) ($product->sku ?? ''));
            if ($sku === '') {
                continue;
            }

            $shopify = $shopifyBySku[$sku] ?? null;
            $inv = (float) ($shopify->inv ?? 0);
            $ovl30 = (float) ($shopify->quantity ?? 0);
            $dil = $inv > 0 ? round(($ovl30 / $inv) * 100, 2) : 0.0;

            $members = $groups->groupContaining($sku);
            if ($members === []) {
                $members = [$sku];
            }

            $skuKey = $this->skuKey($sku);
            $cvr = $cvrBySku[$skuKey] ?? $cvrBySku[str_replace(' ', '', $skuKey)] ?? null;
            $cost = $this->productCost($product);

            $row = [
                'image' => $product->main_image ?: null,
                'parent' => preg_replace('/\s+/', ' ', trim((string) ($product->parent ?? ''))),
                'sku' => $sku,
                'lp' => $cost['lp'],
                'ship' => $cost['ship'],
                'inv' => $inv,
                'ovl30' => $ovl30,
                'dil' => $dil,
                'std_price' => $this->priceForGroup($members, $stdBySku),
                'my_lmp' => $this->priceForGroup($members, $myLmpBySku),
                'linked_lmp_skus' => array_values($members),
                'avg_price' => $cvr['avg_price'] ?? null,
                'groi' => $cvr['avg_roi'] ?? null,
                'gpft' => $cvr['avg_gpft'] ?? null,
                'nroi' => $cvr['avg_nroi'] ?? null,
                'npft' => $cvr['avg_pft'] ?? null,
                'lmp_amz' => $this->minAcrossGroup(
                    $members,
                    $amzLookup['lowest'],
                    fn (string $member) => AmazonSkuCompetitor::normalizeSkuKey($member),
                    fn ($row) => AmazonSkuCompetitor::landedPrice($row)
                ),
                'lmp_amz_count' => $this->countAcrossGroup(
                    $members,
                    $amzLookup['details'],
                    fn (string $member) => AmazonSkuCompetitor::normalizeSkuKey($member),
                    function ($row) {
                        $asin = strtoupper(trim((string) ($row->asin ?? '')));

                        return $asin !== '' ? $asin : 'id:'.($row->id ?? '');
                    }
                ),
                'lmp_ebay' => $this->minAcrossGroup(
                    $members,
                    $ebayLookup['lowest'],
                    fn (string $member) => EbaySkuCompetitor::normalizeSkuKey($member),
                    function ($row) {
                        $price = $row->total_price ?? null;

                        return is_numeric($price) && (float) $price > 0 ? (float) $price : null;
                    }
                ),
                'lmp_ebay_count' => $this->countAcrossGroup(
                    $members,
                    $ebayLookup['details'],
                    fn (string $member) => EbaySkuCompetitor::normalizeSkuKey($member),
                    function ($row) {
                        $item = trim((string) ($row->item_id ?? ''));

                        return $item !== '' ? $item : 'id:'.($row->id ?? '');
                    }
                ),
                'lmp_temu' => $this->minTemuAcrossGroup($members, $temuBySku),
                'lmp_temu_count' => $this->countTemuAcrossGroup($members, $temuCountBySku),
                'lmp_google' => $this->minAcrossGroup(
                    $members,
                    $googleLookup['lowest'],
                    fn (string $member) => GoogleSkuCompetitor::normalizeSkuKey($member),
                    function ($row) {
                        $price = $row->price ?? null;

                        return is_numeric($price) && (float) $price > 0 ? (float) $price : null;
                    }
                ),
                'lmp_google_count' => $this->countAcrossGroup(
                    $members,
                    $googleLookup['details'],
                    fn (string $member) => GoogleSkuCompetitor::normalizeSkuKey($member),
                    fn ($row) => GoogleSkuCompetitor::offerDedupeKey($row)
                ),
                'is_parent_summary' => false,
            ];
            $row = array_merge($row, $this->stdPriceMargins($row['std_price'], $cost['lp'], $cost['ship']));
            $rows[] = array_merge($row, $this->marketplaceLmpSummary($row));
        }

        $data = $this->withParentRows($rows);

        return response()->json([
            'data' => $data,
            'meta' => [
                'sku_count' => count($rows),
                'parent_count' => count(array_filter($data, fn ($row) => ! empty($row['is_parent_summary']))),
                'refreshed_at' => now()->timezone('Asia/Kolkata')->format('Y-m-d H:i'),
            ],
        ]);
    }

    /**
     * Same Avg GPFT% / GROI% / NPFT% / NROI%, OV LMP, Avg LMP, My LMP, Diff, and Avg Price
     * as one child row on /lmp-overall. Used by the Amazon Std Price modal.
     */
    public function skuMetrics(Request $request): JsonResponse
    {
        $sku = trim((string) $request->query('sku', ''));
        if ($sku === '' || str_starts_with(strtoupper($sku), 'PARENT')) {
            return response()->json(['success' => false, 'message' => 'SKU is required.'], 422);
        }

        try {
            $payload = $this->metricsForSku($sku);
        } catch (\Throwable $e) {
            Log::warning('LMP Overall: SKU metrics failed', ['sku' => $sku, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Could not load LMP metrics.'], 500);
        }

        return response()->json($payload);
    }

    /**
     * (Avg LMP − Avg Price) / Avg Price × 100. Same as the Diff column on /lmp-overall.
     */
    public function lmpPriceDiffPct(?float $avgLmp, ?float $avgPrice): ?float
    {
        if ($avgLmp === null || $avgPrice === null || $avgLmp <= 0 || $avgPrice <= 0) {
            return null;
        }

        return round((($avgLmp - $avgPrice) / $avgPrice) * 100, 2);
    }

    /**
     * @return array<string, mixed>
     */
    private function metricsForSku(string $sku): array
    {
        $groups = app(LmpSkuGroupService::class);
        try {
            $groups->prepareForSkus([$sku]);
        } catch (\Throwable $e) {
            Log::warning('LMP Overall: SKU link groups failed', ['error' => $e->getMessage()]);
        }
        $members = $groups->groupContaining($sku);
        if ($members === []) {
            $members = [$sku];
        }

        $cvrBySku = $this->pricingCvrAvgBySku();
        $skuKey = $this->skuKey($sku);
        $cvr = $cvrBySku[$skuKey] ?? $cvrBySku[str_replace(' ', '', $skuKey)] ?? null;

        $amzLookup = $this->amazonLookupForMembers($members);
        $ebayLookup = $this->ebayLookupForMembers($members);
        $googleLookup = $this->googleLookupForMembers($members);
        $temuBySku = $this->temuLowestForMembers($members)['price'];

        $row = [
            'lmp_amz' => $this->minAcrossGroup(
                $members,
                $amzLookup['lowest'],
                fn (string $member) => AmazonSkuCompetitor::normalizeSkuKey($member),
                fn ($item) => AmazonSkuCompetitor::landedPrice($item)
            ),
            'lmp_ebay' => $this->minAcrossGroup(
                $members,
                $ebayLookup['lowest'],
                fn (string $member) => EbaySkuCompetitor::normalizeSkuKey($member),
                function ($item) {
                    $price = $item->total_price ?? null;

                    return is_numeric($price) && (float) $price > 0 ? (float) $price : null;
                }
            ),
            'lmp_temu' => $this->minTemuAcrossGroup($members, $temuBySku),
            'lmp_google' => $this->minAcrossGroup(
                $members,
                $googleLookup['lowest'],
                fn (string $member) => GoogleSkuCompetitor::normalizeSkuKey($member),
                function ($item) {
                    $price = $item->price ?? null;

                    return is_numeric($price) && (float) $price > 0 ? (float) $price : null;
                }
            ),
        ];
        $summary = $this->marketplaceLmpSummary($row);
        $avgPrice = isset($cvr['avg_price']) && is_numeric($cvr['avg_price']) ? (float) $cvr['avg_price'] : null;

        return [
            'success' => true,
            'sku' => $sku,
            'gpft' => $cvr['avg_gpft'] ?? null,
            'groi' => $cvr['avg_roi'] ?? null,
            'npft' => $cvr['avg_pft'] ?? null,
            'nroi' => $cvr['avg_nroi'] ?? null,
            'ov_lmp' => $summary['ov_lmp'],
            'avg_lmp' => $summary['avg_lmp'],
            'my_lmp' => $this->myLmpForMembers($members),
            'diff' => $this->lmpPriceDiffPct($summary['avg_lmp'], $avgPrice),
            'avg_price' => $avgPrice,
        ];
    }

    /**
     * One summary row per parent, placed above that parent's SKUs.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function withParentRows(array $rows): array
    {
        $parentNames = collect($rows)->pluck('parent')->filter()->unique()->values()->all();
        $parentImages = [];
        foreach (array_chunk(array_map(fn ($name) => 'PARENT '.$name, $parentNames), 500) as $chunk) {
            if ($chunk === []) {
                continue;
            }
            ProductMaster::query()
                ->whereNull('deleted_at')
                ->whereIn('sku', $chunk)
                ->get(['sku', 'main_image'])
                ->each(function ($pm) use (&$parentImages) {
                    $parentImages[trim((string) $pm->sku)] = $pm->main_image ?: null;
                });
        }

        $data = [];
        foreach (collect($rows)->groupBy(fn ($row) => ($row['parent'] ?? '') !== '' ? $row['parent'] : '__none__') as $parentKey => $children) {
            if ($parentKey !== '__none__') {
                $data[] = $this->parentSummaryRow((string) $parentKey, $children, $parentImages);
            }
            foreach ($children as $child) {
                $data[] = $child;
            }
        }

        return $data;
    }

    /**
     * Pieces sold in the last 30 days, split by the channel on each Shopify order.
     */
    public function channelSales(Request $request): JsonResponse
    {
        $sku = trim((string) $request->input('sku', ''));
        $incoming = $request->input('skus', []);
        $skus = [];
        if (is_array($incoming)) {
            foreach ($incoming as $item) {
                $item = trim((string) $item);
                if ($item !== '' && ! str_starts_with(strtoupper($item), 'PARENT ')) {
                    $skus[] = $item;
                }
            }
        }
        if ($skus === [] && $sku !== '' && ! str_starts_with(strtoupper($sku), 'PARENT ')) {
            $skus = [$sku];
        }
        $skus = array_values(array_unique($skus));
        if (count($skus) > 400) {
            $skus = array_slice($skus, 0, 400);
        }

        [$start, $end] = ShopifySku::ovL30Window();
        $from = $start->format('Y-m-d');
        $to = $end->format('Y-m-d');
        $channels = [];
        $total = 0;

        if ($skus !== [] && Schema::hasTable('shopify_raw_orders')) {
            $compacts = [];
            foreach ($skus as $one) {
                $key = ShopifySku::compactSkuForLookup($one);
                if ($key !== '') {
                    $compacts[$key] = true;
                }
            }
            $compacts = array_keys($compacts);
            if ($compacts !== []) {
                $expr = "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(sku, ' ', ''), '-', ''), '_', ''), '.', ''), '/', ''))";
                $rows = DB::table('shopify_raw_orders')
                    ->whereBetween('order_date', [$from, $to])
                    ->where(function ($query) {
                        $query->whereNull('financial_status')
                            ->orWhereNotIn('financial_status', ['refunded', 'voided']);
                    })
                    ->whereNotNull('sku')
                    ->where('sku', '!=', '')
                    ->whereIn(DB::raw($expr), $compacts)
                    ->get(['source_name', 'tags', 'quantity']);

                foreach ($rows as $row) {
                    $pieces = (int) ($row->quantity ?? 0);
                    if ($pieces === 0) {
                        continue;
                    }
                    $label = $this->channelLabelForSale((string) ($row->source_name ?? ''), (string) ($row->tags ?? ''));
                    if (! isset($channels[$label])) {
                        $channels[$label] = 0;
                    }
                    $channels[$label] += $pieces;
                    $total += $pieces;
                }
            }
        }

        arsort($channels);
        $list = [];
        foreach ($channels as $label => $pieces) {
            $list[] = ['channel' => $label, 'pieces' => $pieces];
        }

        return response()->json([
            'success' => true,
            'sku' => $sku,
            'from' => $from,
            'to' => $to,
            'total' => $total,
            'channels' => $list,
        ]);
    }

    public function channelLabelForSale(string $source, string $tags): string
    {
        $src = strtolower(trim($source));
        $tag = strtolower($tags);
        $blob = $src.' '.$tag;

        if (str_contains($blob, 'ebay')) {
            if (str_contains($blob, 'ebay3') || str_contains($blob, 'ebay 3')) {
                return 'eBay 3';
            }
            if (str_contains($blob, 'ebay2') || str_contains($blob, 'ebay 2')) {
                return 'eBay 2';
            }
            if (str_contains($blob, 'ebay1') || str_contains($blob, 'ebay 1')) {
                return 'eBay 1';
            }

            return 'eBay';
        }

        $map = [
            'amazon' => 'Amazon',
            'temu3' => 'Temu 3',
            'temu2' => 'Temu 2',
            'temu' => 'Temu',
            'tiktok2' => 'TikTok 2',
            'tiktok' => 'TikTok',
            'shein' => 'Shein',
            'walmart' => 'Walmart',
            'wayfair' => 'Wayfair',
            'aliexpress' => 'AliExpress',
            'ali express' => 'AliExpress',
            'alibaba' => 'Alibaba',
            'newegg' => 'Newegg',
            'bestbuy' => 'Best Buy',
            'best buy' => 'Best Buy',
            'macy' => "Macy's",
            'reverb' => 'Reverb',
            'faire' => 'Faire',
            'doba' => 'Doba',
            'mercari' => 'Mercari',
            'depop' => 'Depop',
            'topdawg' => 'TopDawg',
            'purchasingpower' => 'Purchasing Power',
            'purchasing power' => 'Purchasing Power',
            'shopify_draft_order' => 'Shopify Wholesale',
            'pos' => 'Shopify POS',
            'web' => 'Shopify',
            'online_store' => 'Shopify',
            'shopify' => 'Shopify',
        ];
        foreach ($map as $needle => $label) {
            if ($needle !== '' && str_contains($src, $needle)) {
                return $label;
            }
        }
        if ($src === '') {
            return 'Shopify';
        }

        return trim($source) !== '' ? trim($source) : 'Shopify';
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $children
     * @param  array<string, ?string>  $parentImages
     * @return array<string, mixed>
     */
    private function parentSummaryRow(string $parent, $children, array $parentImages): array
    {
        $inv = (float) $children->sum('inv');
        $ovl30 = (float) $children->sum('ovl30');
        $parentSku = 'PARENT '.$parent;
        $image = $parentImages[$parentSku] ?? null;
        if (! $image) {
            $withImage = $children->first(fn ($row) => ! empty($row['image']));
            $image = $withImage['image'] ?? null;
        }

        $row = [
            'is_parent_summary' => true,
            'image' => $image,
            'parent' => $parent,
            'sku' => $parentSku,
            'inv' => $inv,
            'ovl30' => $ovl30,
            'dil' => $inv > 0 ? round(($ovl30 / $inv) * 100, 2) : 0.0,
            'lp' => $this->avgPositive($children, 'lp'),
            'ship' => $this->avgNumeric($children, 'ship'),
            'std_price' => $this->avgPositive($children, 'std_price'),
            'my_lmp' => $this->avgPositive($children, 'my_lmp'),
            'linked_lmp_skus' => [],
            'avg_price' => $this->avgPositive($children, 'avg_price'),
            'groi' => $this->avgNumeric($children, 'groi'),
            'gpft' => $this->avgNumeric($children, 'gpft'),
            'nroi' => $this->avgNumeric($children, 'nroi'),
            'npft' => $this->avgNumeric($children, 'npft'),
            'std_nroi' => $this->avgNumeric($children, 'std_nroi'),
            'std_npft' => $this->avgNumeric($children, 'std_npft'),
            'lmp_amz' => $this->avgPositive($children, 'lmp_amz'),
            'lmp_amz_count' => (int) $children->sum('lmp_amz_count'),
            'lmp_ebay' => $this->avgPositive($children, 'lmp_ebay'),
            'lmp_ebay_count' => (int) $children->sum('lmp_ebay_count'),
            'lmp_temu' => $this->avgPositive($children, 'lmp_temu'),
            'lmp_temu_count' => (int) $children->sum('lmp_temu_count'),
            'lmp_google' => $this->avgPositive($children, 'lmp_google'),
            'lmp_google_count' => (int) $children->sum('lmp_google_count'),
        ];

        return array_merge($row, $this->marketplaceLmpSummary($row));
    }

    /**
     * OV LMP is the lowest of Amz, eBay, Temu, and Google. Avg LMP is their mean.
     * Marketplaces with no price are left out of both.
     *
     * @param  array<string, mixed>  $row
     * @return array{ov_lmp: ?float, avg_lmp: ?float}
     */
    private function marketplaceLmpSummary(array $row): array
    {
        $prices = array_values(array_filter(
            [$row['lmp_amz'] ?? null, $row['lmp_ebay'] ?? null, $row['lmp_temu'] ?? null, $row['lmp_google'] ?? null],
            fn ($price) => is_numeric($price) && (float) $price > 0
        ));
        if ($prices === []) {
            return ['ov_lmp' => null, 'avg_lmp' => null];
        }

        return [
            'ov_lmp' => round((float) min($prices), 2),
            'avg_lmp' => round(array_sum($prices) / count($prices), 2),
        ];
    }

    private function avgPositive($rows, string $field): ?float
    {
        $values = collect($rows)->pluck($field)->filter(fn ($value) => is_numeric($value) && (float) $value > 0);

        return $values->isNotEmpty() ? round((float) $values->avg(), 2) : null;
    }

    private function avgNumeric($rows, string $field): ?float
    {
        $values = collect($rows)->pluck($field)->filter(fn ($value) => is_numeric($value));

        return $values->isNotEmpty() ? round((float) $values->avg(), 2) : null;
    }

    /**
     * Amazon channel Ads% from /all-marketplace-master.
     */
    private function amazonAdsPercent(): float
    {
        $amazonAdsPercent = \App\Models\ChannelMasterCalculatedData::where('channel', 'Amazon')
            ->value('ads_percentage');
        if ($amazonAdsPercent === null) {
            $amazonAdsPercent = \App\Models\ChannelMasterCalculatedData::where('channel', 'like', 'Amazon%')
                ->value('ads_percentage');
        }

        return (float) ($amazonAdsPercent ?? 0);
    }

    /**
     * NROI% and NPFT% at Std Price — same shape as Amazon NROI / PFT, using Std Price as the price.
     *
     * Std NROI% = ((Std × 0.70 − ship − LP) / LP) × 100
     * Std NPFT% = ((Std × 0.70 − ship − LP) / Std) × 100
     *
     * @return array{std_nroi: ?float, std_npft: ?float}
     */
    private function stdPriceMargins(?float $std, ?float $lp, float $ship): array
    {
        if ($std === null || $std <= 0) {
            return ['std_nroi' => null, 'std_npft' => null];
        }

        $lpVal = ($lp !== null && $lp > 0) ? $lp : 0.0;
        $gross = ($std * 0.70) - $ship - $lpVal;
        $npft = round(($gross / $std) * 100, 2);
        $nroi = $lpVal > 0
            ? round(($gross / $lpVal) * 100, 2)
            : null;

        return [
            'std_nroi' => $nroi,
            'std_npft' => $npft,
        ];
    }

    /**
     * LP and ship from product_master.Values — same inputs as Amazon GROI% / NROI%.
     *
     * @return array{lp: ?float, ship: float}
     */
    private function productCost(ProductMaster $product): array
    {
        $values = is_array($product->Values) ? $product->Values : [];
        $lp = 0.0;
        foreach ($values as $key => $value) {
            if (strtolower((string) $key) === 'lp' && is_numeric($value)) {
                $lp = (float) $value;
                break;
            }
        }
        $ship = (isset($values['ship']) && is_numeric($values['ship'])) ? (float) $values['ship'] : 0.0;

        return [
            'lp' => $lp > 0 ? round($lp, 2) : null,
            'ship' => round($ship, 2),
        ];
    }

    private function skuKey(string $sku): string
    {
        return strtoupper(preg_replace('/\s+/', ' ', trim($sku)) ?? '');
    }

    public function save(Request $request): JsonResponse
    {
        $sku = trim((string) $request->input('sku', ''));
        $stdRaw = $request->input('std_price');
        $myRaw = $request->input('my_lmp');

        if ($sku === '') {
            return response()->json(['error' => 'SKU is required.'], 400);
        }
        if (! is_numeric($stdRaw) || (float) $stdRaw <= 0) {
            return response()->json(['error' => 'Std Price must be greater than 0.'], 400);
        }

        $myLmp = null;
        if ($myRaw !== null && $myRaw !== '') {
            if (! is_numeric($myRaw) || (float) $myRaw <= 0) {
                return response()->json(['error' => 'My LMP must be greater than 0.'], 400);
            }
            $myLmp = round((float) $myRaw, 2);
        }

        $std = round((float) $stdRaw, 2);
        $groups = app(LmpSkuGroupService::class);
        try {
            $groups->prepareForSkus([$sku]);
        } catch (\Throwable $e) {
            Log::warning('LMP Overall: save group lookup failed', ['error' => $e->getMessage()]);
        }
        $members = $groups->groupContaining($sku);
        if ($members === []) {
            $members = [$sku];
        }

        $applied = [];
        foreach ($members as $member) {
            $display = trim((string) $member);
            if ($display === '') {
                continue;
            }
            $row = AmazonDataView::query()
                ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($display)])
                ->first();
            if (! $row) {
                $row = new AmazonDataView(['sku' => $display]);
            }
            $existing = is_array($row->value)
                ? $row->value
                : (json_decode($row->value ?? '{}', true) ?? []);
            $existing['STANDARD_PRICE'] = $std;
            if ($myLmp === null) {
                unset($existing['MY_LMP']);
            } else {
                $existing['MY_LMP'] = $myLmp;
            }
            $row->value = $existing;
            $row->save();
            $applied[] = (string) $row->sku;
        }

        return response()->json([
            'std_price' => $std,
            'my_lmp' => $myLmp,
            'applied_skus' => $applied,
        ]);
    }

    /**
     * Std Price and My LMP from amazon_data_view (same store as /amazon-tabulator-view).
     *
     * @return array{std: array<string, float>, my_lmp: array<string, float>}
     */
    private function amazonManualPrices(): array
    {
        $out = ['std' => [], 'my_lmp' => []];
        if (! Schema::hasTable('amazon_data_view')) {
            return $out;
        }

        try {
            AmazonDataView::query()
                ->select(['id', 'sku', 'value'])
                ->orderBy('id')
                ->chunkById(1000, function ($rows) use (&$out) {
                    foreach ($rows as $row) {
                        $key = $this->skuKey((string) $row->sku);
                        if ($key === '') {
                            continue;
                        }
                        $val = is_array($row->value) ? $row->value : [];
                        $std = $val['STANDARD_PRICE'] ?? null;
                        if (is_numeric($std) && (float) $std > 0) {
                            $out['std'][$key] = round((float) $std, 2);
                        }
                        $mine = $val['MY_LMP'] ?? null;
                        if (is_numeric($mine) && (float) $mine > 0) {
                            $out['my_lmp'][$key] = round((float) $mine, 2);
                        }
                    }
                });
        } catch (\Throwable $e) {
            Log::warning('LMP Overall: Amazon price lookup failed', ['error' => $e->getMessage()]);
        }

        return $out;
    }

    /**
     * @param  list<string>  $members
     * @param  array<string, float>  $bySku
     */
    private function priceForGroup(array $members, array $bySku): ?float
    {
        foreach ($members as $member) {
            $key = $this->skuKey((string) $member);
            if ($key !== '' && isset($bySku[$key])) {
                return $bySku[$key];
            }
        }

        return null;
    }

    /**
     * Same Avg Price / Avg GROI% / Avg GPFT% / Avg NROI% / Avg NPFT% as /pricing-master-cvr.
     * Prefer the live table payload (cached), then the daily snapshot.
     *
     * @param  list<array<string, mixed>|object>  $rows
     */
    public static function rememberCvrAvgMetrics(array $rows): void
    {
        $map = (new self)->mapCvrAvgRows($rows);
        if ($map === []) {
            return;
        }

        Cache::put(self::CVR_AVG_CACHE_KEY, $map, now()->addMinutes(30));
    }

    /**
     * @return array<string, array{avg_price: ?float, avg_roi: ?float, avg_gpft: ?float, avg_nroi: ?float, avg_pft: ?float}>
     */
    private function pricingCvrAvgBySku(): array
    {
        $cached = Cache::get(self::CVR_AVG_CACHE_KEY);
        if (is_array($cached) && $cached !== [] && $this->cvrMapHasProfitCols($cached)) {
            return $cached;
        }

        $live = $this->pricingCvrAvgFromLive();
        if ($live !== []) {
            Cache::put(self::CVR_AVG_CACHE_KEY, $live, now()->addMinutes(30));

            return $live;
        }

        return $this->pricingCvrAvgFromSnapshot();
    }

    /**
     * @param  array<string, array<string, mixed>>  $map
     */
    private function cvrMapHasProfitCols(array $map): bool
    {
        foreach ($map as $row) {
            if (array_key_exists('avg_gpft', $row) && $row['avg_gpft'] !== null) {
                return true;
            }
            if (array_key_exists('avg_roi', $row) && $row['avg_roi'] !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, array{avg_price: ?float, avg_roi: ?float, avg_gpft: ?float, avg_nroi: ?float, avg_pft: ?float}>
     */
    private function pricingCvrAvgFromLive(): array
    {
        try {
            set_time_limit(180);
            $response = app(CvrMasterController::class)->getCvrDataJson(
                Request::create('/cvr-master-data-json', 'GET')
            );
            $payload = $response->getData(true);
            if (! is_array($payload) || isset($payload['error'])) {
                return [];
            }

            return $this->mapCvrAvgRows($payload);
        } catch (\Throwable $e) {
            Log::warning('LMP Overall: live pricing-master-cvr lookup failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @param  list<array<string, mixed>|object>  $rows
     * @return array<string, array{avg_price: ?float, avg_roi: ?float, avg_gpft: ?float, avg_nroi: ?float, avg_pft: ?float}>
     */
    private function mapCvrAvgRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            if (! empty($row['is_parent_summary'])) {
                continue;
            }
            $key = $this->skuKey((string) ($row['sku'] ?? ''));
            if ($key === '') {
                continue;
            }
            $entry = [
                'avg_price' => $this->numOrNull($row['avg_price'] ?? null, true),
                'avg_roi' => $this->numOrNull($row['avg_roi'] ?? null),
                'avg_gpft' => $this->numOrNull($row['avg_gpft'] ?? null),
                'avg_nroi' => $this->numOrNull($row['avg_nroi'] ?? null),
                'avg_pft' => $this->numOrNull($row['avg_pft'] ?? null),
            ];
            $out[$key] = $entry;
            $compact = str_replace(' ', '', $key);
            if ($compact !== '' && $compact !== $key) {
                $out[$compact] = $entry;
            }
        }

        return $out;
    }

    private function numOrNull(mixed $value, bool $zeroIsEmpty = false): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }
        $number = round((float) $value, 2);
        if ($zeroIsEmpty && $number <= 0) {
            return null;
        }

        return $number;
    }

    /**
     * Latest /pricing-master-cvr SKU snapshot: Avg Price, Avg GROI%, Avg GPFT%, Avg NROI%, Avg NPFT%.
     *
     * @return array<string, array{avg_price: ?float, avg_roi: ?float, avg_gpft: ?float, avg_nroi: ?float, avg_pft: ?float}>
     */
    private function pricingCvrAvgFromSnapshot(): array
    {
        $table = 'pricing_master_daily_snapshots_sku';
        if (! Schema::hasTable($table)) {
            return [];
        }

        $optional = ['avg_roi', 'avg_gpft', 'avg_nroi', 'avg_pft'];
        $cols = ['sku', 'avg_price'];
        foreach ($optional as $col) {
            if (Schema::hasColumn($table, $col)) {
                $cols[] = $col;
            }
        }

        try {
            $date = DB::table($table)->max('snapshot_date');
            if (! $date) {
                return [];
            }

            $out = [];
            foreach (DB::table($table)->where('snapshot_date', $date)->get($cols) as $row) {
                $key = $this->skuKey((string) ($row->sku ?? ''));
                if ($key === '') {
                    continue;
                }
                $out[$key] = [
                    'avg_price' => isset($row->avg_price) && is_numeric($row->avg_price) && (float) $row->avg_price > 0
                        ? round((float) $row->avg_price, 2) : null,
                    'avg_roi' => $this->snapshotPercent($row, 'avg_roi'),
                    'avg_gpft' => $this->snapshotPercent($row, 'avg_gpft'),
                    'avg_nroi' => $this->snapshotPercent($row, 'avg_nroi'),
                    'avg_pft' => $this->snapshotPercent($row, 'avg_pft'),
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            Log::warning('LMP Overall: pricing-master-cvr snapshot lookup failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function snapshotPercent(object $row, string $column): ?float
    {
        if (! isset($row->{$column}) || ! is_numeric($row->{$column})) {
            return null;
        }

        return round((float) $row->{$column}, 2);
    }

    /**
     * @return array{details: \Illuminate\Support\Collection, lowest: \Illuminate\Support\Collection}
     */
    private function groupedLookup(callable $build): array
    {
        try {
            $lookup = $build();

            return [
                'details' => $lookup['details'] ?? collect(),
                'lowest' => $lookup['lowest'] ?? collect(),
            ];
        } catch (\Throwable $e) {
            Log::warning('LMP Overall: competitor lookup failed', ['error' => $e->getMessage()]);

            return ['details' => collect(), 'lowest' => collect()];
        }
    }

    /**
     * @param  list<string>  $members
     */
    private function countAcrossGroup(array $members, $details, callable $keyOf, callable $idOf): int
    {
        $ids = [];
        $seen = [];
        foreach ($members as $member) {
            $key = $keyOf((string) $member);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $entries = $details->get($key);
            if (! $entries instanceof \Illuminate\Support\Collection) {
                continue;
            }
            foreach ($entries as $entry) {
                $id = (string) $idOf($entry);
                if ($id !== '') {
                    $ids[$id] = true;
                }
            }
        }

        return count($ids);
    }

    /**
     * @param  list<string>  $members
     */
    private function minAcrossGroup(array $members, $lookup, callable $keyOf, callable $priceOf): ?float
    {
        $best = null;
        foreach ($members as $member) {
            $key = $keyOf((string) $member);
            if ($key === '' || ! $lookup->has($key)) {
                continue;
            }
            $row = $lookup->get($key);
            if ($row === null) {
                continue;
            }
            $price = $priceOf($row);
            if ($price === null || (float) $price <= 0) {
                continue;
            }
            $price = (float) $price;
            $best = $best === null ? $price : min($best, $price);
        }

        return $best !== null ? round($best, 2) : null;
    }

    /**
     * @param  list<string>  $members
     * @param  array<string, float>  $temuBySku
     */
    private function minTemuAcrossGroup(array $members, array $temuBySku): ?float
    {
        $best = null;
        foreach ($members as $member) {
            $key = strtoupper(preg_replace('/\s+/', ' ', trim((string) $member)) ?? '');
            if ($key === '' || ! isset($temuBySku[$key])) {
                continue;
            }
            $price = (float) $temuBySku[$key];
            if ($price <= 0) {
                continue;
            }
            $best = $best === null ? $price : min($best, $price);
        }

        return $best !== null ? round($best, 2) : null;
    }

    /**
     * @param  list<string>  $members
     * @param  array<string, int>  $countBySku
     */
    private function countTemuAcrossGroup(array $members, array $countBySku): int
    {
        $total = 0;
        $seen = [];
        foreach ($members as $member) {
            $key = $this->skuKey((string) $member);
            if ($key === '' || isset($seen[$key]) || ! isset($countBySku[$key])) {
                continue;
            }
            $seen[$key] = true;
            $total += (int) $countBySku[$key];
        }

        return $total;
    }

    /**
     * @param  list<string>  $members
     * @return array{details: \Illuminate\Support\Collection, lowest: \Illuminate\Support\Collection}
     */
    private function amazonLookupForMembers(array $members): array
    {
        $items = AmazonSkuCompetitor::getCompetitorsForSkus($members, 'amazon');
        $grouped = $items->groupBy(fn ($item) => AmazonSkuCompetitor::normalizeSkuKey($item->sku));

        return [
            'details' => $grouped,
            'lowest' => $grouped->map(fn ($group) => AmazonSkuCompetitor::lowestFromCollection($group)),
        ];
    }

    /**
     * @param  list<string>  $members
     * @return array{details: \Illuminate\Support\Collection, lowest: \Illuminate\Support\Collection}
     */
    private function ebayLookupForMembers(array $members): array
    {
        $query = EbaySkuCompetitor::query()
            ->where('marketplace', 'ebay')
            ->where('total_price', '>', 0);
        $this->constrainSkuKeys($query, $members);
        $grouped = $query->get()->groupBy(fn ($item) => EbaySkuCompetitor::normalizeSkuKey($item->sku));

        return [
            'details' => $grouped,
            'lowest' => $grouped->map(function ($items) {
                $active = EbaySkuCompetitor::withoutIgnored($items);

                return $active->sortBy(fn ($item) => (float) ($item->total_price ?? 0))->first();
            }),
        ];
    }

    /**
     * @param  list<string>  $members
     * @return array{details: \Illuminate\Support\Collection, lowest: \Illuminate\Support\Collection}
     */
    private function googleLookupForMembers(array $members): array
    {
        $query = GoogleSkuCompetitor::query()
            ->where('marketplace', 'google')
            ->wherePositivePrice();
        $this->constrainSkuKeys($query, $members);
        $grouped = $query->get()->groupBy(fn ($item) => GoogleSkuCompetitor::normalizeSkuKey($item->sku));

        return [
            'details' => $grouped,
            'lowest' => $grouped->map(fn ($items) => GoogleSkuCompetitor::lowestFromCollection($items)),
        ];
    }

    /**
     * @param  list<string>  $members
     * @return array{price: array<string, float>, count: array<string, int>}
     */
    private function temuLowestForMembers(array $members): array
    {
        $out = ['price' => [], 'count' => []];
        if ($members === [] || ! Schema::hasTable('temu_lmp')) {
            return $out;
        }

        $query = TemuLmp::query()->select(['id', 'sku', 'lmp', 'lmp_2', 'lmp_entries']);
        $this->constrainSkuKeys($query, $members);
        foreach ($query->get() as $row) {
            $key = $this->skuKey((string) $row->sku);
            if ($key === '') {
                continue;
            }
            $stats = $this->temuRowStats($row);
            if ($stats['price'] !== null && (! isset($out['price'][$key]) || $stats['price'] < $out['price'][$key])) {
                $out['price'][$key] = $stats['price'];
            }
            if ($stats['count'] > 0) {
                $out['count'][$key] = ($out['count'][$key] ?? 0) + $stats['count'];
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $members
     */
    private function myLmpForMembers(array $members): ?float
    {
        if ($members === [] || ! Schema::hasTable('amazon_data_view')) {
            return null;
        }

        $query = AmazonDataView::query()->select(['id', 'sku', 'value']);
        $this->constrainSkuKeys($query, $members);
        $bySku = [];
        foreach ($query->get() as $row) {
            $key = $this->skuKey((string) $row->sku);
            if ($key === '') {
                continue;
            }
            $val = is_array($row->value) ? $row->value : [];
            $mine = $val['MY_LMP'] ?? null;
            if (is_numeric($mine) && (float) $mine > 0) {
                $bySku[$key] = round((float) $mine, 2);
            }
        }

        return $this->priceForGroup($members, $bySku);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  list<string>  $members
     */
    private function constrainSkuKeys($query, array $members): void
    {
        $keys = [];
        foreach ($members as $member) {
            $key = $this->skuKey((string) $member);
            if ($key !== '') {
                $keys[$key] = $key;
            }
        }
        $keys = array_values($keys);
        if ($keys === []) {
            $query->whereRaw('1 = 0');

            return;
        }
        $query->where(function ($inner) use ($keys) {
            foreach ($keys as $key) {
                $inner->orWhereRaw('UPPER(TRIM(sku)) = ?', [$key]);
            }
        });
    }

    /**
     * Lowest Temu LMP (price + delivery) and competitor counts, keyed by normalized SKU.
     *
     * @return array{price: array<string, float>, count: array<string, int>}
     */
    private function temuLowestBySku(): array
    {
        $out = ['price' => [], 'count' => []];
        if (! Schema::hasTable('temu_lmp')) {
            return $out;
        }

        try {
            TemuLmp::query()
                ->select(['id', 'sku', 'lmp', 'lmp_2', 'lmp_entries'])
                ->orderBy('id')
                ->chunkById(1000, function ($rows) use (&$out) {
                    foreach ($rows as $row) {
                        $key = strtoupper(preg_replace('/\s+/', ' ', trim((string) $row->sku)) ?? '');
                        if ($key === '') {
                            continue;
                        }
                        $stats = $this->temuRowStats($row);
                        if ($stats['price'] !== null && (! isset($out['price'][$key]) || $stats['price'] < $out['price'][$key])) {
                            $out['price'][$key] = $stats['price'];
                        }
                        if ($stats['count'] > 0) {
                            $out['count'][$key] = ($out['count'][$key] ?? 0) + $stats['count'];
                        }
                    }
                });
        } catch (\Throwable $e) {
            Log::warning('LMP Overall: Temu LMP lookup failed', ['error' => $e->getMessage()]);
        }

        return $out;
    }

    /**
     * @return array{price: ?float, count: int}
     */
    private function temuRowStats(TemuLmp $row): array
    {
        $prices = [];
        $count = 0;
        $entries = $row->lmp_entries;
        if (is_array($entries)) {
            foreach ($entries as $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                $price = isset($entry['price']) && is_numeric($entry['price']) ? (float) $entry['price'] : 0.0;
                if ($price <= 0) {
                    continue;
                }
                $count++;
                if (! empty($entry['ignored'])) {
                    continue;
                }
                $delivery = isset($entry['delivery']) && is_numeric($entry['delivery'])
                    ? (float) $entry['delivery']
                    : 0.0;
                if ($delivery <= 0 && $price < 27) {
                    $delivery = 2.99;
                }
                $prices[] = $price + $delivery;
            }
        }

        if ($count === 0) {
            if ($row->lmp !== null && is_numeric($row->lmp) && (float) $row->lmp > 0) {
                $prices[] = (float) $row->lmp;
                $count++;
            }
            if ($row->lmp_2 !== null && is_numeric($row->lmp_2) && (float) $row->lmp_2 > 0) {
                $prices[] = (float) $row->lmp_2;
                $count++;
            }
        }

        return [
            'price' => $prices !== [] ? round(min($prices), 2) : null,
            'count' => $count,
        ];
    }
}
