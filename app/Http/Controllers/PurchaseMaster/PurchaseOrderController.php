<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Supplier;
use App\Models\ProductMaster;
use App\Models\PurchaseOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseOrderController extends Controller
{
    
    public function index()
    {
        $poNumber = $this->generateOrderNumber();
        $suppliers = Supplier::select('id', 'name')->get();
        $orders = PurchaseOrder::with('supplier')->latest()->get();
        return view('purchase-master.purchase-order.purchase-order',compact('suppliers','orders','poNumber'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'po_number' => 'required|string|unique:purchase_orders,po_number',
            'supplier' => 'required|exists:suppliers,id',
            'sku' => 'required|array',
            'sku.*' => 'nullable|string',
        ]);

        $poNumber = $request->po_number;
        $supplierId = $request->supplier;
        $advanceAmt = $request->advance_amount;
        $today = now()->toDateString();

        // Extract arrays from request
        $skus = $request->sku;
        $supplierSkus = $request->supplier_sku ?? [];
        $qtys = $request->qty ?? [];
        $prices = $request->price ?? [];
        $techs = $request->tech ?? [];
        $currencies = $request->currency ?? [];
        $priceTypes = $request->price_type ?? [];
        $nws = $request->nw ?? [];
        $gws = $request->gw ?? [];
        $cbms = $request->cbm ?? [];

        $photos = $request->file('photo') ?? [];
        $barcodes = $request->file('barcode') ?? [];

        $items = [];
        $totalAmount = 0;

        foreach ($skus as $index => $sku) {
            $photoPath = isset($photos[$index]) ? $photos[$index]->store('purchase_orders/photos', 'public') : null;
            $barcodePath = isset($barcodes[$index]) ? $barcodes[$index]->store('purchase_orders/barcodes', 'public') : null;

            $qty = $qtys[$index] ?? 0;
            $price = $prices[$index] ?? 0;

            $lineTotal = (float)$qty * (float)$price;
            $totalAmount += $lineTotal;

            $items[] = [
                'sku' => $sku,
                'supplier_sku' => $supplierSkus[$index] ?? null,
                'qty' => $qtys[$index] ?? null,
                'price' => $prices[$index] ?? null,
                'tech' => $techs[$index] ?? null,
                'currency' => $currencies[$index] ?? null,
                'price_type' => $priceTypes[$index] ?? null,
                'nw' => $nws[$index] ?? null,
                'gw' => $gws[$index] ?? null,
                'cbm' => $cbms[$index] ?? null,
                'photo' => $photoPath,
                'barcode' => $barcodePath,
            ];
        }

        PurchaseOrder::create([
            'po_number' => $poNumber,
            'supplier_id' => $supplierId,
            'po_date' => $today,
            'items' => json_encode($items),
            'advance_amount' => $advanceAmt,
            'total_amount' => $totalAmount,
        ]);

        return redirect()->back()->with('flash_message', 'PO with all items saved as one row successfully.');
    }

    public function showPurchaseOrders($id)
    {
        $po = PurchaseOrder::with('supplier')->findOrFail($id);

        return response()->json([
            'id' => $po->id,
            'po_number' => $po->po_number,
            'supplier_id' => $po->supplier_id,
            'advance_amount' => $po->advance_amount ?? 0,
            'po_date' => $po->po_date,
            'items' => json_decode($po->items, true) ?? [],
        ]);
    }

    public function updatePurchaseOrder(Request $request, $id)
    {
        $po = PurchaseOrder::findOrFail($id);

        $po->supplier_id = $request->supplier;
        $po->advance_amount = $request->advance_amount;
        $po->po_date = $request->po_date;

        $items = [];
        $totalAmount = 0;

        if ($request->sku) {
            for ($i = 0; $i < count($request->sku); $i++) {
                
                $photoPath = $request->hasFile("photo.$i") ? $request->file("photo.$i")->store('purchase_orders/photos', 'public') : null;
                $barcodePath = $request->hasFile("barcode.$i") ? $request->file("barcode.$i")->store('purchase_orders/barcodes', 'public') : null;

                $existingItems = json_decode($po->items, true);
                $existingPhoto = $existingItems[$i]['photo'] ?? null;
                $existingBarcode = $existingItems[$i]['barcode'] ?? null;

                $qty = $request->qty[$i] ?? 0;
                $price = $request->price[$i] ?? 0;

                $lineTotal = $qty * $price;
                $totalAmount += $lineTotal;

                $items[] = [
                    'sku' => $request->sku[$i],
                    'supplier_sku' => $request->supplier_sku[$i],
                    'tech' => $request->tech[$i] ?? '',
                    'qty' => $request->qty[$i] ?? 0,
                    'price' => $request->price[$i] ?? 0,
                    'currency' => $request->currency[$i] ?? 'USD',
                    'price_type' => $request->price_type[$i] ?? 'EXW',
                    'nw' => $request->nw[$i] ?? 0,
                    'gw' => $request->gw[$i] ?? 0,
                    'cbm' => $request->cbm[$i] ?? 0,
                    'photo' => $photoPath ?? $existingPhoto,
                    'barcode' => $barcodePath ?? $existingBarcode,
                ];
            }
        }

        $po->items = json_encode($items);
        $po->total_amount = $totalAmount;
        $po->save();

        return redirect()->back()->with('success', 'Purchase Order updated successfully!');
    }

    public function getPurchaseOrdersData()
    {
        $orders = PurchaseOrder::select('id', 'po_number', 'po_date', 'supplier_id', 'items', 'advance_amount')
        ->with('supplier:id,name')
        ->get();

        $orders = $orders->map(function ($order) {
            $items = collect(json_decode($order->items));
            $firstItem = $items->first();

            $skuList = $items->pluck('sku')->take(3)->implode(', ');
            if ($items->count() > 3) {
                $skuList .= '...';
            }

            return [
                'id' => $order->id,
                'po_number' => $order->po_number,
                'po_date' => $order->po_date,
                'supplier_name' => $order->supplier->name ?? '',
                'supplier_id' => $order->supplier_id ?? '',
                'advance_amount' => $order->advance_amount ?? '',
                'sku_list' => $skuList,
                'photo' => $firstItem->photo ?? '',
                'barcode' => $firstItem->barcode ?? '',
                'items_json' => $order->items,
            ];
        });

        return response()->json($orders);
    }

    public function generatePdf($orderId){
        $order = DB::table('purchase_orders')->where('id', $orderId)->first();
        if (!$order) abort(404, 'Purchase Order not found');

        $items = json_decode($order->items ?? '[]');

        $supplier = DB::table('suppliers')->where('id', $order->supplier_id)->first();

        return view('purchase-master.purchase-order.proforma', [
            'order'    => $order,
            'items'    => $items,
            'supplier' => $supplier,
        ]);
    }

    public function convert(Request $request)
    {
        $amount = $request->query('amount', 1);
        $from = $request->query('from', 'USD');
        $to = $request->query('to', 'CNY');

        try {
            $apiUrl = "https://api.frankfurter.app/latest?amount=$amount&from=$from&to=$to";
            $response = Http::get($apiUrl);

            if ($response->successful()) {
                return response()->json($response->json());
            } else {
                return response()->json(['error' => 'Frankfurter API error'], 500);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    function generateOrderNumber()
    {
        $datePart = Carbon::now()->format('dmy'); 
        $prefix = 'PO-' . $datePart;

        $latestOrder = PurchaseOrder::select('po_number')
            ->where('po_number', 'like', "$prefix-%")
            ->orderBy('po_number', 'desc')
            ->first();

        if ($latestOrder) {
            $parts = explode('-', $latestOrder->po_number);
            $lastSerial = intval(end($parts));
            $newSerial = str_pad($lastSerial + 1, 2, '0', STR_PAD_LEFT);
        } else {
            $newSerial = '01';
        }
        return "$prefix-$newSerial";
    }

    public function deletePurchaseOrders(Request $request)
    {
        $ids = $request->ids;
        PurchaseOrder::whereIn('id', $ids)->delete();

        return redirect()->back()->with('flash_message', 'Selected orders deleted successfully.');

    }
}
