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
        return redirect()->route('spare.parts.index');
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
            return redirect()->back()->with('error', 'Requisition is not approved for issue.');
        }

        $remaining = $item->quantityRemainingToIssue();
        if ($remaining <= 0) {
            return redirect()->back()->with('error', 'Nothing left to issue for this line.');
        }

        $qty = min((int) $validated['quantity'], $remaining);

        $check = $this->inventoryService->assertSufficientStock($item->part, $qty);
        if (!$check[0]) {
            return redirect()->back()->with('error', $check[1]);
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

        return redirect()->back()->with('success', 'Issued '.$qty.' unit(s).');
    }
}
