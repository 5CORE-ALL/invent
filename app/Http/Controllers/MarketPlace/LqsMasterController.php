<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\JungleScoutProductData;
use App\Models\LqsHistory;
use App\Models\AmazonDatasheet;
use App\Models\LqsAmzHistory;
use App\Models\LqsAmzAction;
use Illuminate\Http\Request;

class LqsMasterController extends Controller
{
    /**
     * Trigger a background Jungle Scout data refresh.
     *
     * Launches the import command as a detached OS process so the HTTP response
     * returns immediately. We can't rely on dispatchAfterResponse() here because
     * under Apache/mod_php there is no fastcgi_finish_request(), so the job would
     * run synchronously and block the request until PHP's max_execution_time is
     * hit (producing a 500 "Failed to trigger refresh"). A detached process also
     * avoids requiring a running queue worker.
     */
    public function refreshJungleScoutData()
    {
        try {
            $php     = PHP_BINARY;
            $artisan = base_path('artisan');
            $cmd     = escapeshellarg($php) . ' ' . escapeshellarg($artisan) . ' app:process-jungle-scout-sheet-data';

            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                pclose(popen('start /B ' . $cmd, 'r'));
            } else {
                exec($cmd . ' > /dev/null 2>&1 &');
            }

            \Log::info('JungleScout refresh: dispatched background import process');

            return response()->json([
                'success' => true,
                'message' => 'Jungle Scout refresh started in the background. LQS data will update shortly.',
            ]);
        } catch (\Exception $e) {
            \Log::error('JungleScout refresh dispatch error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

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
                        'date'  => $row->date->format('d M'),
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

    // ─────────────────────────────────────────────────────────────────────────
    //  LQS AMZ
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Display the LQS Amz page
     */
    public function lqsAmzView()
    {
        return view('market-places.lqs_amz_view');
    }

    /**
     * Get Amazon-specific LQS data for the Tabulator table.
     *
     * Data sources:
     *   - ProductMaster        → parent / SKU grouping
     *   - AmazonDatasheet      → ASIN, price, units_ordered_l30, sessions_l30
     *   - ShopifySku           → inventory (inv), image_src
     *   - JungleScoutProductData → LQS, rating, reviews (matched by ASIN first, then SKU)
     */
    public function getLqsAmzData(Request $request)
    {
        try {
            $normalizeSku = static function ($value) {
                return strtoupper(str_replace("\u{00a0}", ' ', trim((string) $value)));
            };

            // Load all data sets
            $productMastersBySku = ProductMaster::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->whereRaw('UPPER(sku) NOT LIKE ?', ['%PARENT%'])
                ->get()
                ->keyBy(fn ($row) => $normalizeSku($row->sku));

            $shopifyBySku = ShopifySku::all()
                ->keyBy(fn ($row) => $normalizeSku($row->sku));

            $amazonBySku = AmazonDatasheet::all()
                ->keyBy(fn ($row) => $normalizeSku($row->sku));

            // Load latest action per SKU (one query, keyed by uppercase SKU)
            $latestActionsBySku = LqsAmzAction::with('user')
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy(fn ($r) => strtoupper(trim($r->sku)))
                ->map(fn ($group) => $group->first());

            // JungleScout keyed by normalized ASIN and by normalized SKU
            $jsAll = JungleScoutProductData::all();
            $jsByAsin = [];
            $jsBySku  = [];
            foreach ($jsAll as $jsRow) {
                $normalizedJs = $normalizeSku($jsRow->sku);
                $jsBySku[$normalizedJs][] = $jsRow;
                if (is_array($jsRow->data)) {
                    $asin = strtoupper(trim((string) ($jsRow->data['asin'] ?? '')));
                    if ($asin) {
                        $jsByAsin[$asin][] = $jsRow;
                    }
                }
            }

            $rows = [];
            foreach ($productMastersBySku->keys() as $normalizedSku) {
                $productMaster = $productMastersBySku->get($normalizedSku);
                $shopifyRow    = $shopifyBySku->get($normalizedSku);
                $amazonRow     = $amazonBySku->get($normalizedSku);

                $inv      = $shopifyRow ? (int)   ($shopifyRow->inv          ?? 0) : 0;
                $imageSrc = $shopifyRow ?          ($shopifyRow->image_src    ?? null) : null;

                $asin     = $amazonRow  ? strtoupper(trim((string) ($amazonRow->asin                 ?? ''))) : '';
                $price    = $amazonRow  ? (float)  ($amazonRow->price             ?? 0) : 0;
                $l30      = $amazonRow  ? (int)    ($amazonRow->units_ordered_l30 ?? 0) : 0;
                $sessions = $amazonRow  ? (int)    ($amazonRow->sessions_l30      ?? 0) : 0;

                // Try to get Amazon image from ASIN if shopify has no image
                if (!$imageSrc && $asin) {
                    $imageSrc = "https://images-na.ssl-images-amazon.com/images/P/{$asin}.01.THUMBZZZ.jpg";
                }

                // Resolve LQS / rating / reviews from JungleScout
                $lqs     = null;
                $rating  = null;
                $reviews = null;

                $jsEntries = [];
                if ($asin && !empty($jsByAsin[$asin])) {
                    foreach ($jsByAsin[$asin] as $jsRow) {
                        if (is_array($jsRow->data)) {
                            $jsEntries[] = $jsRow->data;
                        }
                    }
                }
                if (!empty($jsBySku[$normalizedSku])) {
                    foreach ($jsBySku[$normalizedSku] as $jsRow) {
                        if (is_array($jsRow->data)) {
                            $jsEntries[] = $jsRow->data;
                        }
                    }
                }

                foreach ($jsEntries as $entry) {
                    if (!empty($entry['rating']) && $entry['rating'] > 0) {
                        $rating  = (float) $entry['rating'];
                        $reviews = isset($entry['reviews']) ? (int) $entry['reviews'] : null;
                        if (isset($entry['listing_quality_score']) && is_numeric($entry['listing_quality_score'])) {
                            $lqs = (float) $entry['listing_quality_score'];
                        }
                        break;
                    }
                }
                if ($lqs === null) {
                    foreach ($jsEntries as $entry) {
                        if (isset($entry['listing_quality_score']) && is_numeric($entry['listing_quality_score']) && $entry['listing_quality_score'] !== '') {
                            $lqs = (float) $entry['listing_quality_score'];
                            break;
                        }
                    }
                }

                $displaySku = $productMaster->sku ?? $normalizedSku;
                $parent     = $productMaster ? (trim((string) ($productMaster->parent ?? '')) ?: null) : null;
                $dilPercent = $inv > 0 ? round(($l30 / $inv) * 100, 2) : 0;
                $cvr        = $sessions > 0 ? round(($l30 / $sessions) * 100, 2) : null;

                // Latest action for this SKU
                $latestAction = $latestActionsBySku->get(strtoupper(trim((string) $displaySku)));
                $hasAction    = $latestAction !== null;

                $rows[] = [
                    'sku'                => trim((string) $displaySku),
                    'parent'             => $parent,
                    'is_parent'          => false,
                    'asin'               => $asin ?: null,
                    'image'              => $imageSrc,
                    'inv'                => $inv,
                    'l30'                => $l30,
                    'sessions'           => $sessions,
                    'dil_percent'        => $dilPercent,
                    'price'              => $price,
                    'rating'             => $rating,
                    'reviews'            => $reviews,
                    'cvr'                => $cvr,
                    'lqs'                => $lqs,
                    'has_action'         => $hasAction,
                    'latest_action_text' => $hasAction ? $latestAction->action : null,
                    'latest_action_user' => $hasAction ? ($latestAction->user->name ?? 'Unknown') : null,
                    'latest_action_date' => $hasAction ? $latestAction->created_at->format('d M, h:i A') : null,
                ];
            }

            // Group by parent and prepend parent summary rows
            $parentGroups = [];
            foreach ($rows as $row) {
                $parentKey = $row['parent'] ?: $row['sku'];
                $parentGroups[$parentKey][] = $row;
            }

            $formattedData = [];
            foreach ($parentGroups as $parentKey => $childRows) {
                $totalInv  = array_sum(array_column($childRows, 'inv'));
                $totalL30  = array_sum(array_column($childRows, 'l30'));
                $totalSess = array_sum(array_column($childRows, 'sessions'));

                $formattedData[] = [
                    'sku'                => $parentKey,
                    'parent'             => '',
                    'asin'               => null,
                    'image'              => '',
                    'inv'                => $totalInv,
                    'l30'                => $totalL30,
                    'sessions'           => $totalSess,
                    'dil_percent'        => $totalInv > 0 ? round(($totalL30 / $totalInv) * 100, 2) : 0,
                    'price'              => null,
                    'rating'             => null,
                    'reviews'            => null,
                    'cvr'                => $totalSess > 0 ? round(($totalL30 / $totalSess) * 100, 2) : null,
                    'lqs'                => null,
                    'has_action'         => false,
                    'latest_action_text' => null,
                    'latest_action_user' => null,
                    'latest_action_date' => null,
                    'is_parent'          => true,
                ];

                foreach ($childRows as $child) {
                    $formattedData[] = $child;
                }
            }

            $this->saveLqsAmzSnapshot($formattedData);

            return response()->json($formattedData);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Save daily LQS Amz snapshot for badge trend tracking.
     */
    private function saveLqsAmzSnapshot(array $rows): void
    {
        try {
            $today      = now()->toDateString();
            $childRows  = collect($rows)->filter(fn ($r) => !($r['is_parent'] ?? false));

            $totalInv    = 0; $totalL30  = 0; $totalSess = 0;
            $dilSum      = 0; $dilCount  = 0;
            $lqsSum      = 0; $lqsCount  = 0;
            $ratingSum   = 0; $ratingCount = 0;
            $lqsBelow9   = 0;

            foreach ($childRows as $r) {
                $inv    = (float) ($r['inv']      ?? 0);
                $l30    = (float) ($r['l30']      ?? 0);
                $sess   = (float) ($r['sessions'] ?? 0);
                $lqs    = (float) ($r['lqs']      ?? 0);
                $rating = (float) ($r['rating']   ?? 0);

                $totalInv  += $inv;
                $totalL30  += $l30;
                $totalSess += $sess;

                if ($inv > 0)    { $dilSum    += ($l30 / $inv) * 100; $dilCount++; }
                if ($lqs > 0)    { $lqsSum    += $lqs;    $lqsCount++; }
                if ($rating > 0) { $ratingSum += $rating; $ratingCount++; }
                if ($lqs > 0 && $lqs < 9) { $lqsBelow9++; }
            }

            $avgDil    = $dilCount    > 0 ? $dilSum    / $dilCount    : 0;
            $avgLqs    = $lqsCount    > 0 ? $lqsSum    / $lqsCount    : 0;
            $avgRating = $ratingCount > 0 ? $ratingSum / $ratingCount : 0;

            LqsAmzHistory::updateOrCreate(
                ['date' => $today],
                [
                    'total_inv'         => round($totalInv, 2),
                    'total_l30'         => round($totalL30, 2),
                    'total_sessions'    => round($totalSess, 2),
                    'avg_dil'           => round($avgDil, 2),
                    'avg_lqs'           => round($avgLqs, 2),
                    'avg_rating'        => round($avgRating, 2),
                    'lqs_below_9_count' => $lqsBelow9,
                ]
            );

        } catch (\Exception $e) {
            \Log::error('LQS Amz snapshot failed: ' . $e->getMessage());
        }
    }

    /**
     * Save an action taken for a SKU.
     */
    public function saveLqsAmzAction(Request $request)
    {
        try {
            $request->validate([
                'sku'    => 'required|string',
                'action' => 'required|string|max:100',
            ]);

            $entry = LqsAmzAction::create([
                'sku'     => trim($request->sku),
                'action'  => trim($request->action),
                'user_id' => auth()->id(),
            ]);

            $entry->load('user');

            return response()->json([
                'success'     => true,
                'id'          => $entry->id,
                'action'      => $entry->action,
                'user_name'   => $entry->user->name ?? 'Unknown',
                    'created_at'  => $entry->created_at->format('d M, h:i A'),
            ]);
        } catch (\Exception $e) {
            \Log::error('LQS Amz action save error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to save action'], 500);
        }
    }

    /**
     * Get full action history for a SKU.
     */
    public function getLqsAmzActionHistory(string $sku)
    {
        try {
            $entries = LqsAmzAction::where('sku', trim($sku))
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(fn ($e) => [
                    'id'         => $e->id,
                    'action'     => $e->action,
                    'user_name'  => $e->user->name ?? 'Unknown',
                    'created_at' => $e->created_at->format('d M, h:i A'),
                ]);

            return response()->json(['success' => true, 'data' => $entries]);
        } catch (\Exception $e) {
            \Log::error('LQS Amz action history error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch history'], 500);
        }
    }

    /**
     * CVR history for the CVR badge chart (total_l30 / total_sessions × 100 per day).
     */
    public function getLqsAmzCvrHistory(Request $request)
    {
        try {
            $days      = (int) $request->input('days', 32);
            $startDate = $days > 0 ? now()->subDays($days)->toDateString() : '2000-01-01';
            $today     = now()->toDateString();

            $rows = LqsAmzHistory::where('date', '>=', $startDate)
                ->where('date', '<=', $today)
                ->orderBy('date', 'asc')
                ->get()
                ->map(function ($row) {
                    $cvr = ($row->total_sessions > 0)
                        ? round(($row->total_l30 / $row->total_sessions) * 100, 2)
                        : 0;
                    return [
                        'date'  => \Carbon\Carbon::parse($row->date)->format('d M'),
                        'value' => $cvr,
                    ];
                })
                ->values();

            return response()->json(['success' => true, 'data' => $rows]);
        } catch (\Exception $e) {
            \Log::error('LQS Amz CVR history error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Badge trend chart data for LQS Amz.
     */
    public function lqsAmzBadgeChartData(Request $request)
    {
        try {
            $metric = $request->input('metric');
            $days   = (int) $request->input('days', 30);

            if (!$metric) {
                return response()->json(['success' => false, 'message' => 'Metric required'], 400);
            }

            $startDate = now()->subDays($days)->toDateString();
            $today     = now()->toDateString();

            $data = LqsAmzHistory::where('date', '>=', $startDate)
                ->where('date', '<=', $today)
                ->orderBy('date', 'asc')
                ->get()
                ->map(fn ($row) => [
                    'date'  => $row->date->format('d M'),
                    'value' => (float) $row->{$metric}
                ]);

            if ($data->isEmpty()) {
                $data = collect([['date' => $today, 'value' => 0]]);
            }

            return response()->json(['success' => true, 'data' => $data->values()->toArray()]);

        } catch (\Exception $e) {
            \Log::error('LQS Amz badge chart error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
