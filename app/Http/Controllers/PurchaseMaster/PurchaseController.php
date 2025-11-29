<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\Purchase;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PurchaseController extends Controller
{
    public function index(){
        $voNumber = $this->generateVoucherNumber();
        $suppliers = Supplier::where('type', 'Supplier')->get();
        $warehouses = Warehouse::select('id','name')->get();
        return view('purchase-master.purchase.index', compact('suppliers', 'voNumber', 'warehouses'));
    }

    public function store(Request $request)
    {
        // Build items array
        $items = [];
        $count = count($request->sku ?? []);

        for ($i = 0; $i < $count; $i++) {
            $items[] = [
                'sku'    => $request->sku[$i],
                'qty'    => $request->qty[$i],
                'price'  => $request->rate[$i],
                'amount' => $request->qty[$i] * $request->rate[$i],
            ];
        }

        // If id exists → UPDATE
        if ($request->purchase_id) {

            $purchase = Purchase::findOrFail($request->purchase_id);

            $purchase->update([
                'vo_number'     => $request->vo_number,
                'purchase_date' => $request->purchase_date ?? now()->toDateString(),
                'supplier_id'   => $request->supplier,
                'warehouse_id'  => $request->warehouse,
                'items'         => json_encode($items),
                'description'   => $request->description,
            ]);

            return redirect()->back()->with('flash_message', 'Purchase updated successfully ✅');
        }

        // Else → CREATE new
        Purchase::create([
            'vo_number'     => $request->vo_number,
            'purchase_date' => now()->toDateString(),
            'supplier_id'   => $request->supplier,
            'warehouse_id'  => $request->warehouse,
            'items'         => json_encode($items),
            'description'   => $request->description,
        ]);

        return redirect()->back()->with('flash_message', 'Purchase saved successfully ✅');
    }

    function generateVoucherNumber()
    {
        $prefix = 'PURCHASE';

        $latestOrder = Purchase::select('vo_number')
            ->where('vo_number', 'like', "$prefix-%")
            ->orderBy('vo_number', 'desc')
            ->first();

        if ($latestOrder) {
            $parts = explode('-', $latestOrder->vo_number); 
            $lastSerial = intval(end($parts));
            $newSerial = str_pad($lastSerial + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $newSerial = '001';
        }

        return "$prefix-$newSerial";
    }

    public function getPurchaseSummary()
    {
        $purchases = Purchase::with(['supplier', 'warehouse'])->get();

        $data = $purchases->map(function ($purchase) {
            return [
                'id'             => $purchase->id,
                'vo_number'      => $purchase->vo_number,
                'purchase_date'  => $purchase->purchase_date,
                'supplier_name'  => $purchase->supplier->name ?? '',
                'supplier_id'    => $purchase->supplier_id,
                'warehouse_id'   => $purchase->warehouse_id,
                'warehouse_name' => $purchase->warehouse->name ?? '',
                'items'          => $purchase->items,
                'description'    => $purchase->description,
            ];
        });

        return response()->json($data);
    }


    public function getItemsBySupplier($supplierId)
    {
        $latestOrder = PurchaseOrder::where('supplier_id', $supplierId)->latest()->first();

        if (!$latestOrder) {
            return response()->json([]);
        }

        $items = json_decode($latestOrder->items, true);

        foreach ($items as &$item) {
            $product = ProductMaster::where('sku', $item['sku'])->first();
            $item['parent'] = $product?->parent ?? '';
        }

        return response()->json($items);
    }

    public function getParentBySku($sku)
    {
        $product = ProductMaster::where('sku', $sku)->first();

        if ($product) {
            return response()->json(['parent' => $product->parent]);
        }

        return response()->json(['parent' => null], 404);
    }
    
    public function deletePurchase(Request $request)
    {
        $ids = $request->ids;

        $userName = Auth::user()->name;

        Purchase::whereIn('id', $ids)
            ->update(['deleted_by' => $userName]);

        Purchase::whereIn('id', $ids)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Deleted successfully.'
        ]);
    }

    public function searchSku(Request $request)
    {
        $search = $request->get('q', '');
        $page = $request->get('page', 1);
        $perPage = 30;

        $query = ProductMaster::select('sku', 'parent');

        if (!empty($search)) {
            $query->where('sku', 'like', "%{$search}%")
                  ->orWhere('parent', 'like', "%{$search}%");
        }

        $total = $query->count();
        $products = $query->skip(($page - 1) * $perPage)
                          ->take($perPage)
                          ->get();

        $items = $products->map(function ($product) {
            return [
                'id' => $product->sku,
                'text' => $product->sku . ($product->parent ? ' - ' . $product->parent : ''),
                'parent' => $product->parent
            ];
        });

        return response()->json([
            'items' => $items,
            'has_more' => ($page * $perPage) < $total
        ]);
    }


}
