<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\AmazonChannelSummary;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\TopDawgDataView;
use App\Models\TopDawgProduct;
use App\Services\TopDawgApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class TopDawgPricingController extends Controller
{
    private const MAP_TOLERANCE = 3;

    public function pricingView(Request $request): View
    {
        return view('market-places.topdawg_tabulator_view', [
            'mode' => $request->query('mode'),
            'demo' => $request->query('demo'),
            'topdawgPercentage' => $this->marketplacePercentage(),
        ]);
    }

    public function dataJson(Request $request): JsonResponse
    {
        try {
            $response = $this->getViewTopDawgTabularData($request);
            $payload = json_decode($response->getContent(), true);
            $rows = $payload['data'] ?? [];
            $this->saveDailySummaryIfNeeded($rows);

            return response()->json($rows);
        } catch (\Throwable $e) {
            Log::error('TopDawg pricing dataJson: ' . $e->getMessage());

            return response()->json(['error' => 'Failed to fetch TopDawg pricing data'], 500);
        }
    }

    /** Save Buyer (B) / Seller (S) links for a SKU into topdawg_data_views.value JSON. */
    public function saveLinks(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku'         => 'required|string',
            'buyer_link'  => 'nullable|string|max:1000',
            'seller_link' => 'nullable|string|max:1000',
        ]);

        $sku = trim($validated['sku']);

        $buyerLink  = isset($validated['buyer_link']) ? trim((string) $validated['buyer_link']) : '';
        $sellerLink = isset($validated['seller_link']) ? trim((string) $validated['seller_link']) : '';

        foreach (['buyer_link' => $buyerLink, 'seller_link' => $sellerLink] as $label => $link) {
            if ($link !== '' && !filter_var($link, FILTER_VALIDATE_URL)) {
                return response()->json([
                    'success' => false,
                    'message' => ucfirst(str_replace('_', ' ', $label)) . ' must be a valid URL.',
                ], 422);
            }
        }

        $dv       = TopDawgDataView::firstOrNew(['sku' => $sku]);
        $existing = is_array($dv->value) ? $dv->value : (json_decode($dv->value, true) ?? []);
        $existing['buyer_link']  = $buyerLink;
        $existing['seller_link'] = $sellerLink;
        $dv->value = $existing;
        $dv->save();

        return response()->json([
            'success'     => true,
            'message'     => 'Links saved.',
            'buyer_link'  => $buyerLink,
            'seller_link' => $sellerLink,
        ]);
    }

    public function getViewTopDawgTabularData(Request $request): JsonResponse
    {
        $percentageValue = $this->marketplacePercentage() / 100;

        $productMasterRows = ProductMaster::all()
            ->filter(fn ($item) => stripos($item->sku, 'PARENT') === false)
            ->keyBy('sku');

        $skus = $productMasterRows->pluck('sku')->toArray();
        $shopifyData = ShopifySku::mapByProductSkus($skus);

        $topdawgData = Schema::hasTable('topdawg_products')
            ? TopDawgProduct::buildLookupByNormalizedSku($skus)
            : [];

        $dataViews = Schema::hasTable('topdawg_data_views')
            ? TopDawgDataView::whereIn('sku', $skus)->get()->keyBy('sku')
            : collect();

        $processedData = [];

        foreach ($productMasterRows as $productMaster) {
            $sku = $productMaster->sku;
            $skuNorm = $this->normalizeSkuKey($sku);

            $values = is_array($productMaster->Values)
                ? $productMaster->Values
                : (json_decode($productMaster->Values ?? '', true) ?: []);
            $lp = (float) ($values['lp'] ?? $productMaster->lp ?? 0);
            $ship = (float) ($values['ship'] ?? $productMaster->ship ?? 0);

            $shopifyItem = $shopifyData[$sku] ?? null;
            $inv = (float) ($shopifyItem->inv ?? 0);
            $ovL30 = (float) ($shopifyItem->quantity ?? 0);

            $td = $topdawgData[$skuNorm] ?? null;
            $tdPrice = $td ? (float) ($td->price ?? 0) : 0;
            $tdStock = $td ? (int) ($td->remaining_inventory ?? 0) : 0;
            $tdL30 = $td ? (int) ($td->r_l30 ?? 0) : 0;
            $tdL60 = $td ? (int) ($td->r_l60 ?? 0) : 0;

            $row = [
                'Parent' => $productMaster->parent ?? null,
                '(Child) sku' => $sku,
                'INV' => $inv,
                'L30' => $ovL30,
                'TD Price' => $tdPrice,
                'TD Stock' => $tdStock,
                'TD L30' => $tdL30,
                'TD L60' => $tdL60,
                'TDID' => $td ? ($td->tdid ?? $td->topdawg_listing_id ?? null) : null,
                'listing_state' => $td ? ($td->listing_state ?? null) : null,
                'LP_productmaster' => $lp,
                'Ship_productmaster' => $ship,
                'nr_req' => 'REQ',
                'B Link' => '',
                'S Link' => '',
                'percentage' => $percentageValue,
                'image_path' => ($td ? ($td->image_src ?? null) : null) ?: ($shopifyItem->image_src ?? ($values['image_path'] ?? ($productMaster->image_path ?? null))),
            ];

            $dvRecord = $dataViews->get($sku);
            $row['SPRICE'] = null;
            if ($dvRecord) {
                $dvValue = is_array($dvRecord->value) ? $dvRecord->value : (json_decode($dvRecord->value, true) ?? []);
                if (is_array($dvValue)) {
                    $row['B Link'] = $dvValue['buyer_link'] ?? '';
                    $row['S Link'] = $dvValue['seller_link'] ?? '';
                    if (array_key_exists('sprice', $dvValue) && $dvValue['sprice'] !== null && $dvValue['sprice'] !== '') {
                        $row['SPRICE'] = round((float) $dvValue['sprice'], 2);
                    }
                }
            }

            $nrReq = $row['nr_req'];
            $isMissing = ($nrReq === 'REQ' && $inv > 0 && $tdPrice <= 0);
            $row['Missing'] = $isMissing ? 'M' : '';

            if ($nrReq === 'REQ' && $inv > 0 && ! $isMissing) {
                $diff = abs($inv - $tdStock);
                $row['MAP'] = $diff <= self::MAP_TOLERANCE ? 'Map' : 'N Map|' . $diff;
            } else {
                $row['MAP'] = '';
            }

            $row['Dil'] = $inv > 0 ? round(($ovL30 / $inv) * 100, 0) : 0;

            $price = $tdPrice;
            if ($price > 0) {
                $row['GPFT%'] = round((($price * $percentageValue - $lp - $ship) / $price) * 100, 0);
                $row['ROI%'] = $lp > 0 ? round((($price * $percentageValue - $lp - $ship) / $lp) * 100, 0) : 0;
            } else {
                $row['GPFT%'] = 0;
                $row['ROI%'] = 0;
            }

            $row['PFT %'] = $row['GPFT%'];
            $row['Profit'] = ($price * $percentageValue) - $lp - $ship;

            // SPRICE-based S* metrics (recomputed live in JS too — kept in sync here for fresh page loads).
            $sprice = (float) ($row['SPRICE'] ?? 0);
            if ($sprice > 0) {
                $row['SGPFT'] = round((($sprice * $percentageValue - $lp - $ship) / $sprice) * 100, 0);
                $row['SROI']  = $lp > 0 ? round((($sprice * $percentageValue - $lp - $ship) / $lp) * 100, 0) : 0;
            } else {
                $row['SGPFT'] = null;
                $row['SROI']  = null;
            }

            $processedData[] = $row;
        }

        return response()->json([
            'message' => 'TopDawg pricing data fetched successfully',
            'data' => $processedData,
            'status' => 200,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function countTopDawgPricingBadgeTotals(array $rows): array
    {
        $map = 0;
        $miss = 0;
        $nmap = 0;

        foreach ($rows as $row) {
            if (is_object($row)) {
                $row = (array) $row;
            }
            if (! is_array($row)) {
                continue;
            }

            $parent = trim((string) ($row['Parent'] ?? ''));
            if ($parent !== '' && str_starts_with(strtoupper($parent), 'PARENT')) {
                continue;
            }

            $inv = (float) ($row['INV'] ?? 0);
            $nrReq = strtoupper(trim((string) ($row['nr_req'] ?? 'REQ')));
            $isReq = ($nrReq === 'REQ');
            $isMissing = (($row['Missing'] ?? '') === 'M');

            if ($isMissing && $isReq && $inv > 0) {
                $miss++;
            }

            if ($isReq && $inv > 0 && ! $isMissing) {
                $mapValue = (string) ($row['MAP'] ?? '');
                if ($mapValue === 'Map') {
                    $map++;
                } elseif (str_contains($mapValue, 'N Map|')) {
                    $nmap++;
                }
            }
        }

        return [
            'map' => $map,
            'miss' => $miss,
            'nmap' => $nmap,
            'total_views' => 0,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     */
    private function saveDailySummaryIfNeeded(array $products): void
    {
        try {
            $today = now()->toDateString();

            $filtered = collect($products)->filter(function ($p) {
                $invCheck = floatval($p['INV'] ?? 0) > 0;
                $reqCheck = ($p['nr_req'] ?? '') === 'REQ';
                $notParent = ! (isset($p['Parent']) && str_starts_with(strtoupper((string) $p['Parent']), 'PARENT'));

                return $invCheck && $reqCheck && $notParent;
            });

            if ($filtered->isEmpty()) {
                return;
            }

            $counts = self::countTopDawgPricingBadgeTotals($filtered->values()->all());

            $totalPft = 0;
            $totalSales = 0;
            $totalGpft = 0;
            $totalInv = 0;
            $totalTdL30 = 0;
            $zeroSold = 0;
            $moreSold = 0;

            foreach ($filtered as $row) {
                $totalPft += floatval($row['Profit'] ?? 0);
                $totalSales += floatval($row['TD Price'] ?? 0) * floatval($row['TD L30'] ?? 0);
                $totalGpft += floatval($row['GPFT%'] ?? 0);
                $totalInv += floatval($row['INV'] ?? 0);
                $tdL30 = floatval($row['TD L30'] ?? 0);
                $totalTdL30 += $tdL30;
                if ($tdL30 == 0) {
                    $zeroSold++;
                } else {
                    $moreSold++;
                }
            }

            $totalSkuCount = $filtered->count();

            AmazonChannelSummary::updateOrCreate(
                [
                    'channel' => 'topdawg',
                    'snapshot_date' => $today,
                ],
                [
                    'summary_data' => [
                        'total_sku_count' => $totalSkuCount,
                        'missing_count' => $counts['miss'],
                        'map_count' => $counts['map'],
                        'nmap_count' => $counts['nmap'],
                        'inv_td_stock_count' => $counts['nmap'],
                        'zero_sold_count' => $zeroSold,
                        'sold_count' => $moreSold,
                        'total_pft' => round($totalPft, 2),
                        'total_sales' => round($totalSales, 2),
                        'total_inv' => round($totalInv, 2),
                        'total_l30' => round($totalTdL30, 2),
                        'avg_gpft' => $totalSkuCount > 0 ? round($totalGpft / $totalSkuCount, 2) : 0,
                        'total_views' => $counts['total_views'],
                        'calculated_at' => now()->toDateTimeString(),
                    ],
                    'notes' => 'Auto-saved from topdawg-pricing (INV > 0, REQ only)',
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('TopDawg saveDailySummaryIfNeeded: ' . $e->getMessage());
        }
    }

    private function marketplacePercentage(): float
    {
        $fromTable = MarketplacePercentage::where('marketplace', 'TopDawg')->value('percentage');

        return $fromTable !== null ? (float) $fromTable : 95.0;
    }

    private function normalizeSkuKey(?string $sku): string
    {
        return ShopifySku::normalizeSkuForShopifyLookup((string) $sku);
    }

    /**
     * Persist SPRICE updates from the TopDawg Analytics page pricing modes
     * (Decrease / Increase / Same Price). Accepts an array of { sku, sprice }
     * pairs and upserts each into topdawg_data_views.value JSON keyed by sku.
     * A null/blank `sprice` clears the saved value for that SKU.
     */
    public function saveSprice(Request $request): JsonResponse
    {
        $request->validate([
            'updates'           => 'required|array|min:1',
            'updates.*.sku'     => 'required|string|max:255',
            'updates.*.sprice'  => 'nullable|numeric',
        ]);

        $updates = $request->input('updates', []);
        $saved   = 0;

        try {
            foreach ($updates as $u) {
                $sku = trim((string) ($u['sku'] ?? ''));
                if ($sku === '' || stripos($sku, 'PARENT') === 0) {
                    continue;
                }

                $raw    = $u['sprice'] ?? null;
                $sprice = ($raw === null || $raw === '') ? null : round((float) $raw, 2);

                $dv       = TopDawgDataView::firstOrNew(['sku' => $sku]);
                $existing = is_array($dv->value)
                    ? $dv->value
                    : (json_decode((string) $dv->value, true) ?: []);
                if ($sprice === null) {
                    unset($existing['sprice']);
                } else {
                    $existing['sprice'] = $sprice;
                }
                $dv->value = $existing;
                $dv->save();

                $saved++;
            }

            return response()->json([
                'success' => true,
                'updated' => $saved,
                'message' => "Saved SPRICE for {$saved} SKU(s)",
            ]);
        } catch (\Throwable $e) {
            Log::error('TopDawg saveSprice failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Push a batch of SKU → price updates to TopDawg.
     *
     * Each item is POSTed individually through TopDawgApiService::pushPrice()
     * (which talks to the confirmed `/SupplierProduct/update` endpoint with
     * body `{ product_code, price }`). We collect a per-row outcome so the UI
     * can render exactly which SKUs were accepted into TopDawg's review queue
     * and which failed.
     *
     * Note: a 200 from TopDawg means "queued for review", not "live price".
     * That's by design on TD's side and is surfaced in the per-row `message`.
     *
     * Request shape:
     *   { "items": [ { "sku": "ABC", "price": 19.99 }, ... ] }
     *
     * Response shape:
     *   {
     *     "success": bool,             // true iff every item succeeded
     *     "ok_count":   int,           // # of items TD accepted
     *     "fail_count": int,           // # of items TD rejected / errored
     *     "results": [
     *       { "sku": "ABC", "price": 19.99, "ok": true,
     *         "status": 200, "message": "Product submitted successfully for review." },
     *       ...
     *     ]
     *   }
     */
    public function pushPrices(Request $request, TopDawgApiService $api): JsonResponse
    {
        $validated = $request->validate([
            'items'           => 'required|array|min:1|max:500',
            'items.*.sku'     => 'required|string|max:190',
            'items.*.price'   => 'required|numeric|min:0.01',
        ]);

        $results   = [];
        $okCount   = 0;
        $failCount = 0;

        foreach ($validated['items'] as $item) {
            $sku   = (string) $item['sku'];
            $price = (float) $item['price'];

            try {
                $r = $api->pushPrice($sku, $price);
                $okCount   += $r['ok'] ? 1 : 0;
                $failCount += $r['ok'] ? 0 : 1;
                $results[] = [
                    'sku'     => $sku,
                    'price'   => $price,
                    'ok'      => $r['ok'],
                    'status'  => $r['status'],
                    'message' => is_array($r['response'])
                        ? ($r['response']['message']
                            ?? ($r['response']['error'] ?? json_encode($r['response'])))
                        : (string) $r['response'],
                ];
            } catch (\Throwable $e) {
                $failCount++;
                Log::warning('TopDawg pushPrices: per-row push failed', [
                    'sku' => $sku, 'price' => $price, 'error' => $e->getMessage(),
                ]);
                $results[] = [
                    'sku'     => $sku,
                    'price'   => $price,
                    'ok'      => false,
                    'status'  => 0,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success'    => $failCount === 0,
            'ok_count'   => $okCount,
            'fail_count' => $failCount,
            'results'    => $results,
        ]);
    }
}
