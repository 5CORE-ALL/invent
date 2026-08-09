<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Models\ShopifySkuInventoryHistory;
use Carbon\Carbon;

class InventoryHistoryController extends Controller
{
    public function index()
    {
        return view('inventory-history.index');
    }

    /**
     * Rolling INV / sold series for one SKU — dashboard-style chart payload.
     * metric=inv (default): closing_inventory. metric=sold: daily sold_quantity (L30 chart).
     * Higher is better → green when up day-over-day, red when down.
     */
    public function skuRollingHistory(Request $request): JsonResponse
    {
        $sku = trim((string) $request->query('sku', ''));
        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU is required.'], 422);
        }

        $metric = strtolower(trim((string) $request->query('metric', 'inv')));
        if (! in_array($metric, ['inv', 'sold'], true)) {
            $metric = 'inv';
        }
        $label = $metric === 'sold' ? 'L30 Sold' : 'INV';

        if (! Schema::hasTable('shopifysku_inventory_history')) {
            return response()->json([
                'success' => true,
                'sku' => $sku,
                'label' => $label,
                'metric' => $metric,
                'tone' => 'gray',
                'data' => [],
            ]);
        }

        $days = max(1, min(365, (int) $request->query('days', 30)));
        $normalized = strtoupper(preg_replace('/\s+/u', ' ', $sku));
        $live = $request->query('badge_value');
        $liveVal = ($live !== null && $live !== '' && is_numeric($live)) ? (float) $live : null;

        $tz = 'America/Los_Angeles';
        $today = now($tz)->startOfDay();
        // Inclusive window: today and the previous (days - 1) days
        $start = $today->copy()->subDays(max(0, $days - 1));

        $rows = ShopifySkuInventoryHistory::query()
            ->whereRaw('UPPER(TRIM(sku)) = ?', [$normalized])
            ->where('snapshot_date', '>=', $start->toDateString())
            ->orderBy('snapshot_date', 'asc')
            ->get(['snapshot_date', 'closing_inventory', 'opening_inventory', 'sold_quantity', 'restocked_quantity']);

        $byDate = [];
        foreach ($rows as $row) {
            $key = Carbon::parse($row->snapshot_date)->toDateString();
            $byDate[$key] = $row;
        }

        // Seed carry-forward from last snapshot before the window (INV only — sold is daily)
        $prev = null;
        if ($metric === 'inv') {
            $prevRow = ShopifySkuInventoryHistory::query()
                ->whereRaw('UPPER(TRIM(sku)) = ?', [$normalized])
                ->where('snapshot_date', '<', $start->toDateString())
                ->orderByDesc('snapshot_date')
                ->first(['closing_inventory']);
            $prev = $prevRow !== null ? (float) ($prevRow->closing_inventory ?? 0) : null;
        } else {
            $prevSoldRow = ShopifySkuInventoryHistory::query()
                ->whereRaw('UPPER(TRIM(sku)) = ?', [$normalized])
                ->where('snapshot_date', '<', $start->toDateString())
                ->orderByDesc('snapshot_date')
                ->first(['sold_quantity']);
            $prev = $prevSoldRow !== null ? (float) ($prevSoldRow->sold_quantity ?? 0) : null;
        }

        $series = [];
        for ($d = $start->copy(); $d->lte($today); $d->addDay()) {
            $dateKey = $d->toDateString();
            $hasSnap = isset($byDate[$dateKey]);
            $row = $hasSnap ? $byDate[$dateKey] : null;

            $sold = $hasSnap ? (int) ($row->sold_quantity ?? 0) : 0;
            $restocked = $hasSnap ? (int) ($row->restocked_quantity ?? 0) : 0;
            $invClose = $hasSnap
                ? (float) ($row->closing_inventory ?? 0)
                : ($prev !== null && $metric === 'inv' ? $prev : 0.0);

            if ($metric === 'sold') {
                // Daily sold — missing day = 0 (still plot a dot for every date)
                $value = (float) $sold;
            } elseif ($hasSnap) {
                $value = $invClose;
            } elseif ($prev !== null) {
                $value = $prev;
            } else {
                $value = 0.0;
            }

            // Overlay live badge value on today when provided
            if ($liveVal !== null && $dateKey === $today->toDateString()) {
                $value = $liveVal;
            }

            $tone = 'gray';
            $delta = null;
            if ($prev !== null) {
                $delta = $value - $prev;
                if ($delta > 0) {
                    $tone = 'green';
                } elseif ($delta < 0) {
                    $tone = 'red';
                }
            }

            $reason = 'No movement';
            if ($metric === 'sold') {
                if ($delta !== null && $delta > 0) {
                    $reason = 'Sales increase';
                } elseif ($delta !== null && $delta < 0) {
                    $reason = 'Sales decrease';
                } elseif ($sold > 0) {
                    $reason = 'Sales';
                } elseif ($liveVal !== null && $dateKey === $today->toDateString() && ! $hasSnap) {
                    $reason = 'Live L30';
                }
            } elseif (! $hasSnap && ! ($liveVal !== null && $dateKey === $today->toDateString())) {
                $reason = 'No movement';
            } elseif ($sold > 0 && $restocked > 0) {
                $reason = 'Sales & Restock';
            } elseif ($sold > 0 && $delta !== null && $delta < 0) {
                $reason = 'Sales';
            } elseif ($restocked > 0 && $delta !== null && $delta > 0) {
                $reason = 'Restock';
            } elseif ($sold > 0) {
                $reason = 'Sales';
            } elseif ($restocked > 0) {
                $reason = 'Restock';
            } elseif ($delta !== null && $delta > 0) {
                $reason = 'INV increase';
            } elseif ($delta !== null && $delta < 0) {
                $reason = 'INV decrease';
            } elseif ($liveVal !== null && $dateKey === $today->toDateString() && ! $hasSnap) {
                $reason = 'Live INV';
            }

            $series[] = [
                'date' => $d->format('j M'),
                'snapshot_date' => $dateKey,
                'value' => $value,
                'tone' => $tone,
                'delta' => $delta,
                'reason' => $reason,
                'sold' => $sold,
                'restocked' => $restocked,
                'filled' => ! $hasSnap,
            ];
            $prev = $value;
        }

        $currentTone = ! empty($series) ? ($series[count($series) - 1]['tone'] ?? 'gray') : 'gray';

        return response()->json([
            'success' => true,
            'sku' => $sku,
            'label' => $label,
            'metric' => $metric,
            'tone' => $currentTone,
            'lower_better' => false,
            'current' => $liveVal,
            'data' => $series,
        ]);
    }

    /**
     * Batch day-over-day tones for visible SKUs (dot colors).
     * metric=inv (default) or metric=sold.
     */
    public function skuTones(Request $request): JsonResponse
    {
        $skus = $request->input('skus', []);
        if (! is_array($skus)) {
            $skus = [];
        }
        $skus = array_values(array_unique(array_filter(array_map(static function ($s) {
            return strtoupper(preg_replace('/\s+/u', ' ', trim((string) $s)));
        }, $skus))));

        $metric = strtolower(trim((string) $request->input('metric', 'inv')));
        if (! in_array($metric, ['inv', 'sold'], true)) {
            $metric = 'inv';
        }
        $valueCol = $metric === 'sold' ? 'sold_quantity' : 'closing_inventory';

        if ($skus === [] || ! Schema::hasTable('shopifysku_inventory_history')) {
            return response()->json(['success' => true, 'tones' => (object) []]);
        }

        $out = [];
        foreach (array_slice($skus, 0, 200) as $sku) {
            $rows = ShopifySkuInventoryHistory::query()
                ->whereRaw('UPPER(TRIM(sku)) = ?', [$sku])
                ->orderByDesc('snapshot_date')
                ->limit(2)
                ->get([$valueCol, 'snapshot_date']);

            $tone = 'gray';
            if ($rows->count() >= 2) {
                $newer = (float) ($rows->get(0)->{$valueCol} ?? 0);
                $older = (float) ($rows->get(1)->{$valueCol} ?? 0);
                if ($newer > $older) {
                    $tone = 'green';
                } elseif ($newer < $older) {
                    $tone = 'red';
                }
            }
            $out[$sku] = [
                'tone' => $tone,
                'value' => $rows->count() ? (float) ($rows->get(0)->{$valueCol} ?? 0) : null,
            ];
        }

        return response()->json(['success' => true, 'metric' => $metric, 'tones' => $out]);
    }

    public function getData(Request $request)
    {
        $query = ShopifySkuInventoryHistory::query();

        $histories = $query->orderBy('snapshot_date', 'desc')
            ->orderBy('sku', 'asc')
            ->get();

        $result = [];
        foreach ($histories as $history) {
            $result[] = [
                'id' => $history->id,
                'snapshot_date' => $history->snapshot_date->format('Y-m-d'),
                'snapshot_date_formatted' => $history->snapshot_date->format('M d, Y'),
                'day_of_week' => $history->snapshot_date->format('D'),
                'sku' => $history->sku,
                'product_name' => $history->product_name ?? 'N/A',
                'opening_inventory' => (int) $history->opening_inventory,
                'closing_inventory' => (int) $history->closing_inventory,
                'sold_quantity' => (int) $history->sold_quantity,
                'restocked_quantity' => (int) $history->restocked_quantity,
                'created_at' => $history->created_at->format('M d, Y h:i A'),
                'pst_start_datetime' => $history->pst_start_datetime ? $history->pst_start_datetime->format('Y-m-d H:i:s') : null,
                'pst_end_datetime' => $history->pst_end_datetime ? $history->pst_end_datetime->format('Y-m-d H:i:s') : null,
            ];
        }

        return response()->json([
            'message' => 'Inventory history data loaded successfully',
            'data' => $result,
            'status' => 200,
        ]);
    }

    public function getStats(Request $request)
    {
        $latestDate = ShopifySkuInventoryHistory::max('snapshot_date');
        
        $stats = [
            'latest_date' => $latestDate ? Carbon::parse($latestDate)->format('M d, Y') : null,
            'total_records' => ShopifySkuInventoryHistory::count(),
            'total_skus' => ShopifySkuInventoryHistory::distinct('sku')->count('sku'),
        ];

        if ($request->filled('date')) {
            $stats['date_total_sold'] = ShopifySkuInventoryHistory::where('snapshot_date', $request->date)->sum('sold_quantity');
            $stats['date_total_restocked'] = ShopifySkuInventoryHistory::where('snapshot_date', $request->date)->sum('restocked_quantity');
            $stats['date_total_skus'] = ShopifySkuInventoryHistory::where('snapshot_date', $request->date)->count();
        }

        return response()->json($stats);
    }

    public function runSnapshot()
    {
        try {
            Artisan::call('inventory:snapshot');
            
            $output = Artisan::output();
            
            Log::info('Manual inventory snapshot triggered', [
                'triggered_by' => auth()->user()->name ?? 'Unknown',
                'output' => $output,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Inventory snapshot completed successfully!',
                'output' => $output,
            ]);

        } catch (\Exception $e) {
            Log::error('Manual inventory snapshot failed', [
                'error' => $e->getMessage(),
                'triggered_by' => auth()->user()->name ?? 'Unknown',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Inventory snapshot failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
