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
        $rows = Requisition::query()
            ->with([
                'requester:id,name',
                'items.part' => static function ($q) {
                    $q->select('id', 'sku', 'category_id')
                        ->with(['productCategory' => static fn ($c) => $c->select('id', 'category_name')]);
                },
            ])
            ->latest()
            ->limit(100)
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'department' => 'nullable|string|max:255',
            'priority' => 'required|in:low,medium,high',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.part_id' => 'required|exists:product_master,id',
            'items.*.quantity_requested' => 'required|integer|min:1',
        ]);

        $req = DB::transaction(function () use ($validated) {
            $r = Requisition::query()->create([
                'requested_by' => Auth::id(),
                'department' => $validated['department'] ?? null,
                'status' => 'draft',
                'priority' => $validated['priority'],
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $line) {
                RequisitionItem::query()->create([
                    'requisition_id' => $r->id,
                    'part_id' => $line['part_id'],
                    'quantity_requested' => $line['quantity_requested'],
                    'quantity_issued' => 0,
                ]);
            }

            return $r->load('items.part');
        });

        return response()->json(['message' => 'Requisition created', 'requisition' => $req], 201);
    }

    public function submit(Requisition $requisition)
    {
        if ($requisition->status !== 'draft') {
            return response()->json(['message' => 'Only draft requisitions can be submitted.'], 422);
        }
        $requisition->update(['status' => 'submitted']);

        return response()->json(['message' => 'Submitted', 'requisition' => $requisition]);
    }

    public function approve(Request $request, Requisition $requisition)
    {
        if (!in_array($requisition->status, ['submitted', 'draft'], true)) {
            return response()->json(['message' => 'Requisition cannot be approved in its current state.'], 422);
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

        return response()->json(['message' => 'Approved', 'requisition' => $requisition->fresh(['items.part'])]);
    }

    public function close(Requisition $requisition)
    {
        if ($requisition->status === 'closed') {
            return response()->json(['message' => 'Already closed.'], 422);
        }
        $requisition->update(['status' => 'closed']);

        return response()->json(['message' => 'Closed', 'requisition' => $requisition]);
    }
}
