<?php

namespace App\Http\Controllers\InventoryManagement;

use App\Http\Controllers\Controller;
use App\Models\SparePartPurchaseOrder;
use App\Models\SparePartPurchaseOrderItem;
use App\Services\SparePartInventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SparePartPurchaseOrderController extends Controller
{
    public function __construct(
        protected SparePartInventoryService $inventoryService
    ) {}

    public function index()
    {
        $rows = SparePartPurchaseOrder::query()
            ->with(['supplier:id,name,company', 'items.part:id,sku'])
            ->latest()
            ->limit(100)
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'expected_at' => 'nullable|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.part_id' => 'required|exists:product_master,id',
            'items.*.qty_ordered' => 'required|integer|min:1',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
        ]);

        $po = DB::transaction(function () use ($validated) {
            $poNumber = 'SP-'.now()->format('Ymd').'-'.str_pad(
                (string) (SparePartPurchaseOrder::query()->count() + 1),
                4,
                '0',
                STR_PAD_LEFT
            );

            $po = SparePartPurchaseOrder::query()->create([
                'po_number' => $poNumber,
                'supplier_id' => $validated['supplier_id'],
                'status' => 'draft',
                'expected_at' => $validated['expected_at'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            foreach ($validated['items'] as $line) {
                SparePartPurchaseOrderItem::query()->create([
                    'po_id' => $po->id,
                    'part_id' => $line['part_id'],
                    'qty_ordered' => $line['qty_ordered'],
                    'qty_received' => 0,
                    'unit_cost' => $line['unit_cost'] ?? null,
                ]);
            }

            return $po->load(['items.part', 'supplier']);
        });

        return response()->json(['message' => 'Purchase order created', 'purchase_order' => $po], 201);
    }

    public function send(SparePartPurchaseOrder $sparePartPurchaseOrder)
    {
        if ($sparePartPurchaseOrder->status !== 'draft') {
            return response()->json(['message' => 'Only draft orders can be sent.'], 422);
        }
        $sparePartPurchaseOrder->update(['status' => 'sent']);

        return response()->json(['message' => 'Marked as sent', 'purchase_order' => $sparePartPurchaseOrder]);
    }

    public function receive(Request $request, SparePartPurchaseOrder $sparePartPurchaseOrder)
    {
        if (!in_array($sparePartPurchaseOrder->status, ['sent', 'partially_received'], true)) {
            return response()->json(['message' => 'Order is not open for receiving.'], 422);
        }

        $validated = $request->validate([
            'item_id' => 'required|exists:spare_part_purchase_order_items,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $line = SparePartPurchaseOrderItem::query()
            ->where('po_id', $sparePartPurchaseOrder->id)
            ->with('part')
            ->findOrFail($validated['item_id']);

        $qty = min((int) $validated['quantity'], $line->quantityRemainingToReceive());
        if ($qty <= 0) {
            return response()->json(['message' => 'Nothing left to receive on this line.'], 422);
        }

        DB::transaction(function () use ($line, $sparePartPurchaseOrder, $qty) {
            $this->inventoryService->applyPurchaseReceipt(
                $line->part,
                $qty,
                'purchase_order',
                $sparePartPurchaseOrder->id,
                Auth::id(),
                'GRN spare PO '.$sparePartPurchaseOrder->po_number
            );

            $line->qty_received = (int) $line->qty_received + $qty;
            $line->save();

            $poItems = $sparePartPurchaseOrder->items()->get();

            $allReceived = $poItems->every(fn (SparePartPurchaseOrderItem $i) => $i->qty_received >= $i->qty_ordered);

            $anyReceived = $poItems->contains(fn (SparePartPurchaseOrderItem $i) => $i->qty_received > 0);

            if ($allReceived) {
                $sparePartPurchaseOrder->update(['status' => 'received']);
            } elseif ($anyReceived) {
                $sparePartPurchaseOrder->update(['status' => 'partially_received']);
            }
        });

        return response()->json([
            'message' => 'Received '.$qty.' unit(s).',
            'purchase_order' => $sparePartPurchaseOrder->fresh(['items', 'supplier']),
        ]);
    }
}
