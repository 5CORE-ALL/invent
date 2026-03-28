<?php

namespace App\Http\Controllers\InventoryManagement;

use App\Http\Controllers\Controller;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RequisitionController extends Controller
{
    public function index()
    {
        return redirect()->route('spare.parts.index');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'department' => 'nullable|string|max:255',
            'priority' => 'required|in:low,medium,high',
            'notes' => 'nullable|string',
            'part_id' => 'required|array|min:1',
            'part_id.*' => 'nullable|exists:product_master,id',
            'qty' => 'required|array|min:1',
            'qty.*' => 'nullable|integer|min:1',
        ]);

        $req = DB::transaction(function () use ($validated) {
            $r = Requisition::query()->create([
                'requested_by' => Auth::id(),
                'department' => $validated['department'] ?? null,
                'status' => 'draft',
                'priority' => $validated['priority'],
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['part_id'] as $i => $partId) {
                $qty = isset($validated['qty'][$i]) ? (int) $validated['qty'][$i] : 0;
                if (!$partId || $qty <= 0) {
                    continue;
                }
                RequisitionItem::query()->create([
                    'requisition_id' => $r->id,
                    'part_id' => $partId,
                    'quantity_requested' => $qty,
                    'quantity_issued' => 0,
                ]);
            }

            return $r->load('items.part');
        });

        return redirect()->back()->with('success', 'Requisition Created');
    }

    public function submit(Requisition $requisition)
    {
        if ($requisition->status !== 'draft') {
            return redirect()->back()->with('error', 'Only draft requisitions can be submitted.');
        }
        $requisition->update(['status' => 'submitted']);

        return redirect()->back()->with('success', 'Requisition submitted.');
    }

    public function approve(Request $request, Requisition $requisition)
    {
        if (!in_array($requisition->status, ['submitted', 'draft'], true)) {
            return redirect()->back()->with('error', 'Requisition cannot be approved in its current state.');
        }

        $validated = $request->validate([
            'lines' => 'nullable|array',
            'lines.*.id' => 'required|exists:requisition_items,id',
            'lines.*.quantity_approved' => 'required|integer|min:0',
        ]);

        DB::transaction(function () use ($requisition, $validated) {
            if (!empty($validated['lines'])) {
                foreach ($validated['lines'] as $line) {
                    $item = RequisitionItem::query()
                        ->where('requisition_id', $requisition->id)
                        ->whereKey($line['id'])
                        ->firstOrFail();
                    $cap = $item->quantity_requested;
                    $item->quantity_approved = min((int) $line['quantity_approved'], $cap);
                    $item->save();
                }
            } else {
                foreach ($requisition->items as $item) {
                    if ($item->quantity_approved === null) {
                        $item->quantity_approved = $item->quantity_requested;
                        $item->save();
                    }
                }
            }

            $requisition->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);
        });

        return redirect()->back()->with('success', 'Requisition approved.');
    }

    public function close(Requisition $requisition)
    {
        if ($requisition->status === 'closed') {
            return redirect()->back()->with('error', 'Already closed.');
        }
        $requisition->update(['status' => 'closed']);

        return redirect()->back()->with('success', 'Requisition closed.');
    }
}
