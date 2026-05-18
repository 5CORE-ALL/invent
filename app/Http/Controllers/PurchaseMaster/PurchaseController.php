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

        // Else → CREATE new.
        // If the form's vo_number already exists (incl. soft-deleted rows the DB unique
        // index still sees), regenerate a fresh one. Retry once defensively in case two
        // requests race past the exists() check at the same time.
        $requested = trim((string) $request->vo_number);
        $voNumber  = $requested;
        if ($voNumber === '' || $this->voNumberExists($voNumber)) {
            $voNumber = $this->generateVoucherNumber();
        }

        try {
            Purchase::create([
                'vo_number'     => $voNumber,
                'purchase_date' => now()->toDateString(),
                'supplier_id'   => $request->supplier,
                'warehouse_id'  => $request->warehouse,
                'items'         => json_encode($items),
                'description'   => $request->description,
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Race: another request grabbed the same number between our check and INSERT.
            $voNumber = $this->generateVoucherNumber();
            Purchase::create([
                'vo_number'     => $voNumber,
                'purchase_date' => now()->toDateString(),
                'supplier_id'   => $request->supplier,
                'warehouse_id'  => $request->warehouse,
                'items'         => json_encode($items),
                'description'   => $request->description,
            ]);
        }

        $flash = $voNumber === $requested
            ? 'Purchase saved successfully ✅'
            : "Purchase saved as {$voNumber} (the requested VO number was already taken) ✅";

        return redirect()->back()->with('flash_message', $flash);
    }

    /** Includes soft-deleted rows — the DB unique index blocks those too. */
    private function voNumberExists(string $voNumber): bool
    {
        return Purchase::withTrashed()->where('vo_number', $voNumber)->exists();
    }

    function generateVoucherNumber()
    {
        $prefix = 'PURCHASE';

        // Numeric MAX on the suffix — alphabetical sort breaks once we cross 9→10, 99→100, etc.
        // withTrashed() so soft-deleted rows still count (they collide with the unique index).
        $maxSerial = (int) Purchase::withTrashed()
            ->where('vo_number', 'like', "$prefix-%")
            ->selectRaw("MAX(CAST(SUBSTRING_INDEX(vo_number, '-', -1) AS UNSIGNED)) AS max_serial")
            ->value('max_serial');

        $newSerial = str_pad($maxSerial + 1, 3, '0', STR_PAD_LEFT);

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
