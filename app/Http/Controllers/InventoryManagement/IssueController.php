<?php

namespace App\Http\Controllers\InventoryManagement;

use App\Http\Controllers\Controller;
use App\Models\RequisitionItem;
use App\Services\SparePartInventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class IssueController extends Controller
{
    public function __construct(
        protected SparePartInventoryService $inventoryService
    ) {}

    public function pending()
    {
        $items = RequisitionItem::query()
            ->with([
                'requisition',
                'part' => static function ($q) {
                    $q->select('id', 'sku', 'category_id')
                        ->with(['productCategory' => static fn ($c) => $c->select('id', 'category_name')]);
                },
            ])
            ->whereHas('requisition', function ($q) {
                $q->whereIn('status', ['approved', 'issued']);
            })
            ->get()
            ->filter(fn (RequisitionItem $i) => $i->quantityRemainingToIssue() > 0)
            ->values();

        $data = $items->map(function (RequisitionItem $i) {
            return [
                'item_id' => $i->id,
                'requisition_id' => $i->requisition_id,
                'status' => $i->requisition->status,
                'part_id' => $i->part_id,
                'sku' => $i->part->sku,
                'quantity_approved' => $i->quantity_approved ?? 0,
                'quantity_issued' => $i->quantity_issued,
                'remaining' => $i->quantityRemainingToIssue(),
                'stock_available' => $i->part->sku
                    ? $this->inventoryService->totalAvailableForSku($i->part->sku)
                    : 0,
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'requisition_item_id' => 'required|exists:requisition_items,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $item = RequisitionItem::query()->with(['requisition', 'part'])->findOrFail($validated['requisition_item_id']);
        $requisition = $item->requisition;

        if (!in_array($requisition->status, ['approved', 'issued'], true)) {
            return response()->json(['message' => 'Requisition is not approved for issue.'], 422);
        }

        $remaining = $item->quantityRemainingToIssue();
        if ($remaining <= 0) {
            return response()->json(['message' => 'Nothing left to issue for this line.'], 422);
        }

        $qty = min((int) $validated['quantity'], $remaining);

        $check = $this->inventoryService->assertSufficientStock($item->part, $qty);
        if (!$check[0]) {
            return response()->json(['message' => $check[1]], 422);
        }

        DB::transaction(function () use ($item, $requisition, $qty) {
            $this->inventoryService->applyIssue(
                $item->part,
                $qty,
                'requisition',
                $requisition->id,
                Auth::id(),
                'Issue against requisition #'.$requisition->id
            );

            $item->quantity_issued = (int) $item->quantity_issued + $qty;
            $item->save();

            if ($requisition->status === 'approved') {
                $requisition->update(['status' => 'issued']);
            }
        });

        return response()->json([
            'message' => 'Issued '.$qty.' unit(s).',
            'item' => $item->fresh(),
        ]);
    }
}
