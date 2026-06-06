<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\AmazonChannelSummary;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\TopDawgDataView;
use App\Models\TopDawgProduct;
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
            if ($dvRecord) {
                $dvValue = is_array($dvRecord->value) ? $dvRecord->value : (json_decode($dvRecord->value, true) ?? []);
                if (is_array($dvValue)) {
                    $row['B Link'] = $dvValue['buyer_link'] ?? '';
                    $row['S Link'] = $dvValue['seller_link'] ?? '';
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
}
