<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use App\Models\ShopifySkuInventoryHistory;
use Carbon\Carbon;

class InventoryHistoryController extends Controller
{
    public function index()
    {
        return view('inventory-history.index');
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
