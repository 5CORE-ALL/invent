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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class LmpOverallController extends Controller
{
    public function index(): View
    {
        return view('market-places.lmp_overall');
    }

    public function data(): JsonResponse
    {
        $products = ProductMaster::query()
            ->whereNull('deleted_at')
            ->whereRaw('UPPER(TRIM(sku)) NOT LIKE ?', ['PARENT%'])
            ->orderBy('parent')
            ->orderBy('sku')
            ->get(['id', 'parent', 'sku', 'main_image']);

        $skus = $products->pluck('sku')->filter()->unique()->values()->all();
        $shopifyBySku = ShopifySku::mapByProductSkus($skus);

        $groups = app(LmpSkuGroupService::class);
        try {
            $groups->prepareForSkus($skus);
        } catch (\Throwable $e) {
            Log::warning('LMP Overall: SKU link groups failed', ['error' => $e->getMessage()]);
        }

        $amzLowest = $this->lowestLookup(fn () => AmazonSkuCompetitor::buildGroupedLookup('amazon'));
        $ebayLowest = $this->lowestLookup(fn () => EbaySkuCompetitor::buildGroupedLookup('ebay'));
        $googleLowest = $this->lowestLookup(fn () => GoogleSkuCompetitor::buildGroupedLookup('google'));
        $temuBySku = $this->temuLowestBySku();
        $manual = $this->amazonManualPrices();
        $stdBySku = $manual['std'];
        $myLmpBySku = $manual['my_lmp'];
        $cvrBySku = $this->pricingCvrAvgBySku();

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

            $cvr = $cvrBySku[$this->skuKey($sku)] ?? null;

            $rows[] = [
                'image' => $product->main_image ?: null,
                'parent' => preg_replace('/\s+/', ' ', trim((string) ($product->parent ?? ''))),
                'sku' => $sku,
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
                    $amzLowest,
                    fn (string $member) => AmazonSkuCompetitor::normalizeSkuKey($member),
                    fn ($row) => AmazonSkuCompetitor::landedPrice($row)
                ),
                'lmp_ebay' => $this->minAcrossGroup(
                    $members,
                    $ebayLowest,
                    fn (string $member) => EbaySkuCompetitor::normalizeSkuKey($member),
                    function ($row) {
                        $price = $row->total_price ?? null;

                        return is_numeric($price) && (float) $price > 0 ? (float) $price : null;
                    }
                ),
                'lmp_temu' => $this->minTemuAcrossGroup($members, $temuBySku),
                'lmp_google' => $this->minAcrossGroup(
                    $members,
                    $googleLowest,
                    fn (string $member) => GoogleSkuCompetitor::normalizeSkuKey($member),
                    function ($row) {
                        $price = $row->price ?? null;

                        return is_numeric($price) && (float) $price > 0 ? (float) $price : null;
                    }
                ),
            ];
        }

        return response()->json([
            'data' => $rows,
            'meta' => [
                'sku_count' => count($rows),
                'refreshed_at' => now()->timezone('Asia/Kolkata')->format('Y-m-d H:i'),
            ],
        ]);
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
     * Latest /pricing-master-cvr SKU snapshot: Avg Price, Avg GROI%, Avg GPFT%, Avg NROI%, Avg NPFT%.
     *
     * @return array<string, array{avg_price: ?float, avg_roi: ?float, avg_gpft: ?float, avg_nroi: ?float, avg_pft: ?float}>
     */
    private function pricingCvrAvgBySku(): array
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
     * @return \Illuminate\Support\Collection<string, mixed>
     */
    private function lowestLookup(callable $build)
    {
        try {
            $lookup = $build();

            return $lookup['lowest'] ?? collect();
        } catch (\Throwable $e) {
            Log::warning('LMP Overall: competitor lookup failed', ['error' => $e->getMessage()]);

            return collect();
        }
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
     * Lowest Temu LMP (price + delivery) keyed by normalized SKU.
     *
     * @return array<string, float>
     */
    private function temuLowestBySku(): array
    {
        if (! Schema::hasTable('temu_lmp')) {
            return [];
        }

        $out = [];
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
                        $price = $this->temuRowPrice($row);
                        if ($price === null) {
                            continue;
                        }
                        if (! isset($out[$key]) || $price < $out[$key]) {
                            $out[$key] = $price;
                        }
                    }
                });
        } catch (\Throwable $e) {
            Log::warning('LMP Overall: Temu LMP lookup failed', ['error' => $e->getMessage()]);
        }

        return $out;
    }

    private function temuRowPrice(TemuLmp $row): ?float
    {
        $prices = [];
        $entries = $row->lmp_entries;
        if (is_array($entries)) {
            foreach ($entries as $entry) {
                if (! is_array($entry) || ! empty($entry['ignored'])) {
                    continue;
                }
                $price = isset($entry['price']) && is_numeric($entry['price']) ? (float) $entry['price'] : 0.0;
                if ($price <= 0) {
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

        if ($prices === []) {
            if ($row->lmp !== null && is_numeric($row->lmp) && (float) $row->lmp > 0) {
                $prices[] = (float) $row->lmp;
            }
            if ($row->lmp_2 !== null && is_numeric($row->lmp_2) && (float) $row->lmp_2 > 0) {
                $prices[] = (float) $row->lmp_2;
            }
        }

        return $prices !== [] ? round(min($prices), 2) : null;
    }
}
