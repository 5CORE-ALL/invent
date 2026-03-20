<?php

namespace App\Http\Controllers\InventoryManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductMaster;
use App\Models\Warehouse;
use App\Models\Inventory;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class StockTransferController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $warehouses = Warehouse::select('id', 'name')->get();
        $skus = ProductMaster::select('id','parent','sku')->get();

        return view('inventory-management.stock-transfer-view', compact('warehouses', 'skus'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'sku' => 'required|string',
            'parent' => 'required|string',
            'qty' => 'required|integer|min:1',
            'warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse' => 'required|exists:warehouses,id|different:warehouse_id',
            'reason' => 'required|string',
            'date' => 'required|date',
        ]);

        try {
            Inventory::create([
                'sku' => trim($request->sku),
                'verified_stock' => (int) $request->qty,
                'to_adjust' => (int) $request->qty,
                'reason' => $request->reason,
                'warehouse_id' => $request->warehouse_id,
                'to_warehouse' => $request->to_warehouse,
                'type' => 'transfer',
                'is_approved' => true,
                'approved_by' => Auth::user()->name ?? 'N/A',
                'approved_at' => Carbon::now('America/New_York'),
            ]);

            return response()->json(['message' => 'Stock transfer recorded successfully.']);
        } catch (\Exception $e) {
            Log::error("Stock Transfer Error: " . $e->getMessage());
            return response()->json(['error' => 'Stock transfer failed.'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function list()
    {
        $data = Inventory::with('warehouse', 'warehouseTo')
            ->where('type', 'transfer') // Only stock transfer records
            ->latest()
            ->get()
            ->map(function ($item) {

                return [
                    'sku' => $item->sku,
                    'qty' => $item->verified_stock,
                    'from_warehouse' => $item->warehouse->name ?? '-',
                    'to_warehouse' => $item->warehouseTo->name ?? '-',
                    'reason' => $item->reason,
                    'approved_by' => $item->approved_by,
                    'approved_at' =>  $item->approved_at
                        ? Carbon::parse($item->approved_at)->timezone('America/New_York')->format('m-d-Y')
                        : '',
                ];      
            });

        return response()->json(['data' => $data]);
    }
}
