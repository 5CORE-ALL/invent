<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\JungleScoutProductData;
use App\Models\LqsHistory;
use Illuminate\Http\Request;

class LqsMasterController extends Controller
{
    /**
     * Display the LQS Data page
     */
    public function lqsDataView()
    {
        return view('market-places.lqs_master_tabulator_view');
    }

    /**
     * Get LQS data for the table
     */
    public function getLqsData(Request $request)
    {
        try {
            // Normalize SKU function (same as aliexpress)
            $normalizeSku = static function ($value) {
                return strtoupper(str_replace("\u{00a0}", ' ', trim((string) $value)));
            };

            // Get all ProductMaster SKUs (excluding PARENT in SKU name)
            $productMastersBySku = ProductMaster::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->whereRaw('UPPER(sku) NOT LIKE ?', ['%PARENT%'])
                ->get()
                ->keyBy(fn($row) => $normalizeSku($row->sku));

            // Get ShopifySku data for inventory and image
            $shopifyBySku = ShopifySku::all()->keyBy(fn ($row) => $normalizeSku($row->sku));

            // Get JungleScout data for LQS (listing_quality_score)
            $jungleScoutBySku = JungleScoutProductData::all()->keyBy(fn ($row) => $normalizeSku($row->sku));

            $allNormalizedSkus = $productMastersBySku->keys();

            $rows = [];
            foreach ($allNormalizedSkus as $normalizedSku) {
                $productMaster = $productMastersBySku->get($normalizedSku);
                $shopifyRow = $shopifyBySku->get($normalizedSku);
                $jungleScoutRow = $jungleScoutBySku->get($normalizedSku);

                // Get inventory and order views from shopify_skus
                $inv        = $shopifyRow ? (int) ($shopifyRow->inv       ?? 0) : 0;
                $ovL30      = $shopifyRow ? (int) ($shopifyRow->quantity  ?? 0) : 0;
                $imageSrc   = $shopifyRow ? ($shopifyRow->image_src       ?? null) : null;

                // Get LQS from junglescout_product_data
                $lqs = null;
                if ($jungleScoutRow && is_array($jungleScoutRow->data)) {
                    $lqs = $jungleScoutRow->data['listing_quality_score'] ?? null;
                }

                $displaySku = $productMaster->sku ?? $normalizedSku;
                $parent     = $productMaster ? (trim((string) ($productMaster->parent ?? '')) ?: null) : null;

                $rows[] = [
                    'sku'         => trim((string) $displaySku),
                    'parent'      => $parent,
                    'is_parent'   => false,
                    'image'       => $imageSrc,
                    'inv'         => $inv,
                    'ov_l30'      => $ovL30,
                    'dil_percent' => $inv > 0 ? round(($ovL30 / $inv) * 100, 2) : 0,
                    'lqs'         => $lqs,
                ];
            }

            // Group by parent and add parent rows
            $parentGroups = [];
            foreach ($rows as $row) {
                $parentKey = $row['parent'] ?: $row['sku'];
                if (!isset($parentGroups[$parentKey])) {
                    $parentGroups[$parentKey] = [];
                }
                $parentGroups[$parentKey][] = $row;
            }

            $formattedData = [];
            foreach ($parentGroups as $parentKey => $childRows) {
                // Calculate parent totals
                $totalInv = 0;
                $totalOvL30 = 0;

                foreach ($childRows as $child) {
                    $totalInv += $child['inv'];
                    $totalOvL30 += $child['ov_l30'];
                }

                // Add parent row
                $formattedData[] = [
                    'sku'         => $parentKey,
                    'parent'      => '',
                    'image'       => '',
                    'inv'         => $totalInv,
                    'ov_l30'      => $totalOvL30,
                    'dil_percent' => $totalInv > 0 ? round(($totalOvL30 / $totalInv) * 100, 2) : 0,
                    'is_parent'   => true
                ];

                // Add child rows
                foreach ($childRows as $child) {
                    $formattedData[] = $child;
                }
            }

            // Save daily snapshot for trend tracking
            $this->saveDailySnapshot($formattedData);

            return response()->json($formattedData);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Save daily LQS snapshot (called automatically from getLqsData).
     * Stores to lqs_history table.
     */
    private function saveDailySnapshot(array $rows): void
    {
        try {
            $today = now()->toDateString();

            // All non-parent child rows
            $allChildRows = collect($rows)->filter(fn($r) => !($r['is_parent'] ?? false));

            // Calculate metrics
            $totalInv = 0; $totalOv = 0;
            $dilSum = 0; $dilCount = 0;
            $lqsSum = 0; $lqsCount = 0;

            foreach ($allChildRows as $r) {
                $inv = (float) ($r['inv'] ?? 0);
                $ov  = (float) ($r['ov_l30'] ?? 0);
                $lqs = (int) ($r['lqs'] ?? 0);

                $totalInv += $inv;
                $totalOv  += $ov;

                if ($inv > 0) {
                    $dil = ($ov / $inv) * 100;
                    $dilSum += $dil;
                    $dilCount++;
                }

                if ($lqs > 0) {
                    $lqsSum += $lqs;
                    $lqsCount++;
                }
            }

            $avgDil = $dilCount > 0 ? $dilSum / $dilCount : 0;
            $avgLqs = $lqsCount > 0 ? $lqsSum / $lqsCount : 0;

            // Save to lqs_history table
            LqsHistory::updateOrCreate(
                ['date' => $today],
                [
                    'total_inv' => round($totalInv, 2),
                    'total_ov'  => round($totalOv, 2),
                    'avg_dil'   => round($avgDil, 2),
                    'avg_lqs'   => round($avgLqs, 2),
                ]
            );

            \Log::info("LQS snapshot saved for {$today}: INV={$totalInv}, OV={$totalOv}, DIL={$avgDil}%, LQS={$avgLqs}");

        } catch (\Exception $e) {
            \Log::error('LQS daily snapshot failed: ' . $e->getMessage());
        }
    }

    /**
     * Get badge chart data for trends from lqs_history table
     */
    public function badgeChartData(Request $request)
    {
        try {
            $metric = $request->input('metric');
            $days   = (int) $request->input('days', 30);

            if (!$metric) {
                return response()->json(['success' => false, 'message' => 'Metric required'], 400);
            }

            $startDate = now()->subDays($days)->toDateString();
            $today = now()->toDateString();

            // Fetch historical data from lqs_history table
            $data = LqsHistory::where('date', '>=', $startDate)
                ->where('date', '<=', $today)
                ->orderBy('date', 'asc')
                ->get()
                ->map(function ($row) use ($metric) {
                    return [
                        'date'  => $row->date->format('Y-m-d'),
                        'value' => (float) $row->{$metric}
                    ];
                });

            // If no data exists, calculate and return today's data
            if ($data->isEmpty()) {
                $currentValue = $this->calculateCurrentMetric($metric);
                $data = collect([[
                    'date'  => $today,
                    'value' => $currentValue
                ]]);
            }

            // Return response with success flag and data
            return response()->json([
                'success' => true,
                'data'    => $data->values()->toArray()
            ]);

        } catch (\Exception $e) {
            \Log::error('LQS badge chart error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Calculate current metric value on the fly
     */
    private function calculateCurrentMetric(string $metric): float
    {
        try {
            // Normalize SKU function
            $normalizeSku = static function ($value) {
                return strtoupper(str_replace("\u{00a0}", ' ', trim((string) $value)));
            };

            // Get all ProductMaster SKUs
            $productMastersBySku = ProductMaster::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->whereRaw('UPPER(sku) NOT LIKE ?', ['%PARENT%'])
                ->get()
                ->keyBy(fn($row) => $normalizeSku($row->sku));

            // Get ShopifySku and JungleScout data
            $shopifyBySku = ShopifySku::all()->keyBy(fn ($row) => $normalizeSku($row->sku));
            $jungleScoutBySku = JungleScoutProductData::all()->keyBy(fn ($row) => $normalizeSku($row->sku));

            $totalInv = 0; $totalOv = 0;
            $dilSum = 0; $dilCount = 0;
            $lqsSum = 0; $lqsCount = 0;

            foreach ($productMastersBySku->keys() as $normalizedSku) {
                $shopifyRow = $shopifyBySku->get($normalizedSku);
                $jungleScoutRow = $jungleScoutBySku->get($normalizedSku);

                $inv = $shopifyRow ? (int) ($shopifyRow->inv ?? 0) : 0;
                $ov  = $shopifyRow ? (int) ($shopifyRow->quantity ?? 0) : 0;
                $lqs = null;
                if ($jungleScoutRow && is_array($jungleScoutRow->data)) {
                    $lqs = $jungleScoutRow->data['listing_quality_score'] ?? null;
                }

                $totalInv += $inv;
                $totalOv  += $ov;

                if ($inv > 0) {
                    $dil = ($ov / $inv) * 100;
                    $dilSum += $dil;
                    $dilCount++;
                }

                if ($lqs > 0) {
                    $lqsSum += $lqs;
                    $lqsCount++;
                }
            }

            switch ($metric) {
                case 'total_inv':
                    return (float) $totalInv;
                case 'total_ov':
                    return (float) $totalOv;
                case 'avg_dil':
                    return $dilCount > 0 ? round($dilSum / $dilCount, 2) : 0;
                case 'avg_lqs':
                    return $lqsCount > 0 ? round($lqsSum / $lqsCount, 2) : 0;
                default:
                    return 0;
            }
        } catch (\Exception $e) {
            \Log::error('Calculate current metric error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Export LQS data as CSV
     */
    public function exportLqsData()
    {
        // Implementation for CSV export if needed
    }
}
