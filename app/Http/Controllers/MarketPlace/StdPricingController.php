<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\AmazonDataView;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\StdPricingSprcDil;
use App\Services\LmpSkuGroupService;
use App\Support\AmazonDilGroiRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class StdPricingController extends Controller
{
    /** 20% marketplace margin → price × 0.80. */
    private const MARGIN = 0.80;

    /** 10% ads on Std Price. */
    private const ADS = 0.10;

    public function index(): View
    {
        return view('market-places.std_pricing');
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
        $shopifyBySku = ShopifySku::mapByProductSkus($skus);
        $stdBySku = $this->standardPrices();

        $groups = app(LmpSkuGroupService::class);
        try {
            $groups->prepareForSkus($skus);
        } catch (\Throwable $e) {
            Log::warning('Std pricing: SKU link groups failed', ['error' => $e->getMessage()]);
        }

        $rows = [];
        foreach ($products as $product) {
            $sku = trim((string) ($product->sku ?? ''));
            if ($sku === '') {
                continue;
            }

            $shopify = $shopifyBySku[$sku] ?? null;
            $inv = (float) ($shopify->inv ?? 0);
            $ovl30 = (float) ($shopify->quantity ?? 0);
            $members = $groups->groupContaining($sku);
            if ($members === []) {
                $members = [$sku];
            }

            [$lp, $ship] = $this->landedAndShip($product);
            $std = $this->priceForGroup($members, $stdBySku);

            $rows[] = array_merge([
                'image' => $product->main_image ?: null,
                'parent' => preg_replace('/\s+/', ' ', trim((string) ($product->parent ?? ''))),
                'sku' => $sku,
                'inv' => $inv,
                'ovl30' => $ovl30,
                'dil' => $inv > 0 ? round(($ovl30 / $inv) * 100, 2) : 0.0,
                'std_price' => $std,
                'lp' => $lp,
                'ship' => $ship,
                'linked_skus' => array_values($members),
            ], $this->stdMetrics($std, $lp, $ship), $this->projectedMetrics($std, $lp, $ship, $inv));
        }

        return response()->json([
            'data' => $rows,
            'meta' => [
                'sku_count' => count($rows),
                'refreshed_at' => now()->timezone('Asia/Kolkata')->format('Y-m-d H:i'),
            ],
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        $sku = trim((string) $request->input('sku', ''));
        $stdRaw = $request->input('std_price');

        if ($sku === '') {
            return response()->json(['error' => 'SKU is required.'], 400);
        }
        if (! is_numeric($stdRaw) || (float) $stdRaw <= 0) {
            return response()->json(['error' => 'Std Price must be greater than 0.'], 400);
        }

        $std = round((float) $stdRaw, 2);
        $groups = app(LmpSkuGroupService::class);
        try {
            $groups->prepareForSkus([$sku]);
        } catch (\Throwable $e) {
            Log::warning('Std pricing: save group lookup failed', ['error' => $e->getMessage()]);
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
            $row->value = $existing;
            $row->save();
            $applied[] = (string) $row->sku;
        }

        return response()->json([
            'std_price' => $std,
            'applied_skus' => $applied,
        ]);
    }

    /**
     * Sprc Dil slabs for this page. Stored in std_pricing_sprc_dil, not amazon_dil_vs_groi.
     */
    public function sprcDilRules(): JsonResponse
    {
        $row = StdPricingSprcDil::query()->orderBy('id')->first();
        $savedRules = is_array($row?->rules) ? $row->rules : [];
        $rules = $savedRules === []
            ? AmazonDilGroiRule::amazonDefaults()
            : AmazonDilGroiRule::ensureZeroToZero($savedRules);

        return response()->json([
            'success' => true,
            'is_default' => $savedRules === [],
            'rules' => $rules,
            'cvr_adj' => AmazonDilGroiRule::normalizeCvrAdj(is_array($row?->cvr_adj) ? $row->cvr_adj : null),
            'clearance_nroi' => $row?->clearance_nroi,
        ]);
    }

    public function saveSprcDilRules(Request $request): JsonResponse
    {
        $incoming = $request->input('rules');
        if (! is_array($incoming) && is_string($request->input('rules'))) {
            $decoded = json_decode((string) $request->input('rules'), true);
            $incoming = is_array($decoded) ? $decoded : null;
        }
        if (! is_array($incoming)) {
            return response()->json(['success' => false, 'message' => 'rules array required'], 422);
        }

        $rules = AmazonDilGroiRule::ensureZeroToZero(AmazonDilGroiRule::normalizeList($incoming));
        if ($rules === []) {
            return response()->json(['success' => false, 'message' => 'At least one Dil slab is required'], 422);
        }

        $cvrIncoming = $request->input('cvr_adj');
        if (is_string($cvrIncoming)) {
            $decodedCvr = json_decode($cvrIncoming, true);
            $cvrIncoming = is_array($decodedCvr) ? $decodedCvr : null;
        }
        $cvrAdj = AmazonDilGroiRule::normalizeCvrAdj(is_array($cvrIncoming) ? $cvrIncoming : null);

        $clearance = null;
        if ($request->exists('clearance_nroi') && is_numeric($request->input('clearance_nroi'))) {
            $clearance = round(max(0, (float) $request->input('clearance_nroi')), 2);
        }

        $row = StdPricingSprcDil::query()->orderBy('id')->first() ?: new StdPricingSprcDil;
        $row->rules = $rules;
        $row->cvr_adj = $cvrAdj;
        $row->clearance_nroi = $clearance;
        $row->save();

        return response()->json([
            'success' => true,
            'rules' => $rules,
            'cvr_adj' => $cvrAdj,
            'clearance_nroi' => $clearance,
        ]);
    }

    /**
     * STD GROI / GPFT ignore ads. STD GNROI / GNPFT subtract 10% ads.
     *
     * @return array{std_groi: ?float, std_gpft: ?float, std_gnroi: ?float, std_gnpft: ?float}
     */
    private function stdMetrics(?float $std, float $lp, float $ship): array
    {
        $empty = [
            'std_groi' => null,
            'std_gpft' => null,
            'std_gnroi' => null,
            'std_gnpft' => null,
        ];
        if ($std === null || $std <= 0) {
            return $empty;
        }

        $gross = ($std * self::MARGIN) - $ship - $lp;
        $net = $gross - ($std * self::ADS);

        return [
            'std_groi' => $lp > 0 ? round(($gross / $lp) * 100, 2) : null,
            'std_gpft' => round(($gross / $std) * 100, 2),
            'std_gnroi' => $lp > 0 ? round(($net / $lp) * 100, 2) : null,
            'std_gnpft' => round(($net / $std) * 100, 2),
        ];
    }

    /**
     * P sales = inv × Std Price. P PFT = inv × unit gross profit.
     * The four projected margins use those totals (per row, and Σ across rows).
     *
     * @return array{p_sales: ?float, p_pft: ?float, p_groi: ?float, p_gpft: ?float, p_gnroi: ?float, p_gnpft: ?float}
     */
    private function projectedMetrics(?float $std, float $lp, float $ship, float $inv): array
    {
        $empty = [
            'p_sales' => null,
            'p_pft' => null,
            'p_groi' => null,
            'p_gpft' => null,
            'p_gnroi' => null,
            'p_gnpft' => null,
        ];
        if ($std === null || $std <= 0) {
            return $empty;
        }

        $pSales = round($inv * $std, 2);
        $pPft = round($inv * (($std * self::MARGIN) - $ship - $lp), 2);
        $pCogs = $inv * $lp;
        $pNet = $pPft - ($pSales * self::ADS);

        return [
            'p_sales' => $pSales,
            'p_pft' => $pPft,
            'p_groi' => abs($pCogs) > 0.00001 ? round(($pPft / $pCogs) * 100, 2) : null,
            'p_gpft' => abs($pSales) > 0.00001 ? round(($pPft / $pSales) * 100, 2) : null,
            'p_gnroi' => abs($pCogs) > 0.00001 ? round(($pNet / $pCogs) * 100, 2) : null,
            'p_gnpft' => abs($pSales) > 0.00001 ? round(($pNet / $pSales) * 100, 2) : null,
        ];
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function landedAndShip(ProductMaster $product): array
    {
        $values = is_array($product->Values) ? $product->Values : [];
        $lp = 0.0;
        foreach ($values as $key => $value) {
            if (strtolower((string) $key) === 'lp' && is_numeric($value)) {
                $lp = (float) $value;
                break;
            }
        }
        if ($lp <= 0 && is_numeric($product->lp ?? null)) {
            $lp = (float) $product->lp;
        }

        $ship = 0.0;
        if (isset($values['ship']) && is_numeric($values['ship'])) {
            $ship = (float) $values['ship'];
        } elseif (is_numeric($product->ship ?? null)) {
            $ship = (float) $product->ship;
        }

        return [$lp, $ship];
    }

    /**
     * @return array<string, float>
     */
    private function standardPrices(): array
    {
        $out = [];
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
                            $out[$key] = round((float) $std, 2);
                        }
                    }
                });
        } catch (\Throwable $e) {
            Log::warning('Std pricing: Amazon price lookup failed', ['error' => $e->getMessage()]);
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

    private function skuKey(string $sku): string
    {
        return strtoupper(preg_replace('/\s+/', ' ', trim($sku)) ?? '');
    }
}
