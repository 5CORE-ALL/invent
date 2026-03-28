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
        return redirect()->route('spare.parts.index');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'expected_at' => 'nullable|date',
            'notes' => 'nullable|string',
            'part_id' => 'required|array|min:1',
            'part_id.*' => 'nullable|exists:product_master,id',
            'qty' => 'required|array|min:1',
            'qty.*' => 'nullable|integer|min:1',
            'unit_cost' => 'nullable|array',
            'unit_cost.*' => 'nullable|numeric|min:0',
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

            foreach ($validated['part_id'] as $i => $partId) {
                $qty = isset($validated['qty'][$i]) ? (int) $validated['qty'][$i] : 0;
                if (!$partId || $qty <= 0) {
                    continue;
                }
                SparePartPurchaseOrderItem::query()->create([
                    'po_id' => $po->id,
                    'part_id' => $partId,
                    'qty_ordered' => $qty,
                    'qty_received' => 0,
                    'unit_cost' => isset($validated['unit_cost'][$i]) ? $validated['unit_cost'][$i] : null,
                ]);
            }

            return $po->load(['items.part', 'supplier']);
        });

        return redirect()->back()->with('success', 'Purchase order created.');
    }

    public function send(SparePartPurchaseOrder $sparePartPurchaseOrder)
    {
        if ($sparePartPurchaseOrder->status !== 'draft') {
            return redirect()->back()->with('error', 'Only draft orders can be sent.');
        }
        $sparePartPurchaseOrder->update(['status' => 'sent']);

        return redirect()->back()->with('success', 'PO marked as sent.');
    }

    public function receive(Request $request, SparePartPurchaseOrder $sparePartPurchaseOrder)
    {
        if (!in_array($sparePartPurchaseOrder->status, ['sent', 'partially_received'], true)) {
            return redirect()->back()->with('error', 'Order is not open for receiving.');
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
            return redirect()->back()->with('error', 'Nothing left to receive on this line.');
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

        return redirect()->back()->with('success', 'Received '.$qty.' unit(s).');
    }
}
