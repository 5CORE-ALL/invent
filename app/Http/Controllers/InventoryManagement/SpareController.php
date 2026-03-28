<?php

namespace App\Http\Controllers\InventoryManagement;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\SparePartDetail;
use App\Models\SparePartPurchaseOrder;
use App\Models\SparePartPurchaseOrderItem;
use App\Models\Supplier;
use App\Services\SparePartInventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SpareController extends Controller
{
    public function __construct(
        protected SparePartInventoryService $inventoryService
    ) {}

    public function index(Request $request)
    {
        $payload = $this->buildDashboardPayload();

        if ($request->ajax() || $request->expectsJson() || $request->boolean('ajax')) {
            return response()->json($payload);
        }

        return view('spares.dashboard', $payload);
    }

    public function storeSpare(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:255',
            'part_name' => 'required|string|max:255',
            'parent_sku' => 'nullable|string|max:255',
            'supplier' => 'nullable|string|max:255',
            'qty' => 'required|integer|min:0',
            'reorder_level' => 'nullable|integer|min:0',
        ]);

        $existing = ProductMaster::query()
            ->whereRaw('LOWER(sku) = ?', [strtolower(trim($validated['sku']))])
            ->first();
        if ($existing) {
            throw ValidationException::withMessages(['sku' => 'This SKU already exists.']);
        }

        DB::transaction(function () use ($validated) {
            $parent = null;
            if (!empty($validated['parent_sku'])) {
                $parent = ProductMaster::query()
                    ->whereRaw('LOWER(sku) = ?', [strtolower(trim((string) $validated['parent_sku']))])
                    ->first();
            }

            $part = ProductMaster::query()->create([
                'sku' => trim((string) $validated['sku']),
                'is_spare_part' => true,
                'reorder_level' => $validated['reorder_level'] ?? 0,
                'parent_id' => $parent?->id,
            ]);

            $supplier = $this->resolveSupplier($validated['supplier'] ?? null);

            SparePartDetail::query()->create([
                'product_master_id' => $part->id,
                'part_name' => trim((string) $validated['part_name']),
                'quantity' => (int) $validated['qty'],
                'supplier_id' => $supplier?->id,
            ]);

            if ((int) $validated['qty'] > 0) {
                $this->inventoryService->applyManualAdjustment(
                    $part,
                    (int) $validated['qty'],
                    'spare_create',
                    $part->id,
                    Auth::id(),
                    'Opening stock for spare part'
                );
            }
        });

        return response()->json([
            'message' => 'Spare part added successfully.',
            'data' => $this->buildDashboardPayload(),
        ], 201);
    }

    public function createRequisition(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:255',
            'qty' => 'required|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        $part = ProductMaster::query()
            ->whereRaw('LOWER(sku) = ?', [strtolower(trim($validated['sku']))])
            ->where('is_spare_part', true)
            ->first();

        if (!$part) {
            throw ValidationException::withMessages(['sku' => 'Spare SKU not found.']);
        }

        DB::transaction(function () use ($validated, $part) {
            $requisition = Requisition::query()->create([
                'requested_by' => Auth::id(),
                'department' => 'Spares',
                'status' => 'draft',
                'priority' => 'medium',
                'notes' => $validated['notes'] ?? null,
            ]);

            RequisitionItem::query()->create([
                'requisition_id' => $requisition->id,
                'part_id' => $part->id,
                'quantity_requested' => (int) $validated['qty'],
                'quantity_approved' => null,
                'quantity_issued' => 0,
            ]);
        });

        return response()->json([
            'message' => 'Requisition created.',
            'data' => $this->buildDashboardPayload(),
        ], 201);
    }

    public function updateRequisitionStatus(Request $request, Requisition $requisition): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:submitted,approved,issued,closed',
        ]);

        $targetStatus = (string) $validated['status'];
        $currentStatus = (string) $requisition->status;

        $allowedTransitions = [
            'draft' => ['submitted', 'approved'],
            'submitted' => ['approved', 'closed'],
            'approved' => ['issued', 'closed'],
            'issued' => ['closed'],
            'closed' => [],
        ];

        if (!in_array($targetStatus, $allowedTransitions[$currentStatus] ?? [], true)) {
            return response()->json(['message' => 'Invalid status transition.'], 422);
        }

        DB::transaction(function () use ($requisition, $targetStatus) {
            if ($targetStatus === 'approved') {
                foreach ($requisition->items as $item) {
                    if ($item->quantity_approved === null) {
                        $item->quantity_approved = (int) $item->quantity_requested;
                        $item->save();
                    }
                }
                $requisition->approved_by = Auth::id();
                $requisition->approved_at = now();
            }

            if ($targetStatus === 'issued') {
                $items = $requisition->items()->get();
                $allIssued = $items->every(function (RequisitionItem $item) {
                    $required = $item->quantity_approved ?? $item->quantity_requested;

                    return (int) $item->quantity_issued >= (int) $required;
                });

                if (!$allIssued) {
                    throw ValidationException::withMessages([
                        'status' => 'All approved quantities must be issued before marking as issued.',
                    ]);
                }
            }

            $requisition->status = $targetStatus;
            $requisition->save();
        });

        return response()->json([
            'message' => 'Requisition status updated.',
            'data' => $this->buildDashboardPayload(),
        ]);
    }

    public function issueSpare(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'requisition_item_id' => 'required|exists:requisition_items,id',
            'issue_qty' => 'required|integer|min:1',
        ]);

        $item = RequisitionItem::query()
            ->with(['requisition', 'part'])
            ->findOrFail($validated['requisition_item_id']);

        if ((string) $item->requisition->status !== 'approved' && (string) $item->requisition->status !== 'issued') {
            return response()->json(['message' => 'Only approved requisitions can be issued.'], 422);
        }

        $requiredQty = $item->quantity_approved ?? $item->quantity_requested;
        $remaining = max(0, (int) $requiredQty - (int) $item->quantity_issued);
        if ($remaining <= 0) {
            return response()->json(['message' => 'This requisition item is already fully issued.'], 422);
        }

        $issueQty = min((int) $validated['issue_qty'], $remaining);
        $check = $this->inventoryService->assertSufficientStock($item->part, $issueQty);
        if (!$check[0]) {
            return response()->json(['message' => $check[1] ?? 'Insufficient stock.'], 422);
        }

        DB::transaction(function () use ($item, $issueQty) {
            $this->inventoryService->applyIssue(
                $item->part,
                $issueQty,
                'requisition',
                $item->requisition_id,
                Auth::id(),
                'Issued from spare dashboard'
            );

            $item->quantity_issued = (int) $item->quantity_issued + $issueQty;
            $item->save();

            $requisition = $item->requisition()->first();
            if (!$requisition) {
                return;
            }

            $allIssued = $requisition->items()->get()->every(function (RequisitionItem $requisitionItem) {
                $required = $requisitionItem->quantity_approved ?? $requisitionItem->quantity_requested;

                return (int) $requisitionItem->quantity_issued >= (int) $required;
            });

            if ($allIssued) {
                $requisition->status = 'issued';
                $requisition->save();
            }
        });

        return response()->json([
            'message' => 'Stock issued successfully.',
            'data' => $this->buildDashboardPayload(),
        ]);
    }

    public function createPO(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier' => 'required|string|max:255',
            'sku' => 'required|string|max:255',
            'qty' => 'required|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        $part = ProductMaster::query()
            ->whereRaw('LOWER(sku) = ?', [strtolower(trim($validated['sku']))])
            ->where('is_spare_part', true)
            ->first();
        if (!$part) {
            throw ValidationException::withMessages(['sku' => 'Spare SKU not found.']);
        }

        $supplier = $this->resolveSupplier($validated['supplier']);
        if (!$supplier) {
            throw ValidationException::withMessages(['supplier' => 'Supplier could not be resolved.']);
        }

        DB::transaction(function () use ($validated, $supplier, $part) {
            $serial = SparePartPurchaseOrder::query()->count() + 1;
            $po = SparePartPurchaseOrder::query()->create([
                'po_number' => 'SP-'.now()->format('Ymd').'-'.str_pad((string) $serial, 4, '0', STR_PAD_LEFT),
                'supplier_id' => (int) $supplier->id,
                'status' => 'draft',
                'expected_at' => null,
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            SparePartPurchaseOrderItem::query()->create([
                'po_id' => $po->id,
                'part_id' => $part->id,
                'qty_ordered' => (int) $validated['qty'],
                'qty_received' => 0,
                'unit_cost' => null,
            ]);
        });

        return response()->json([
            'message' => 'Purchase order created.',
            'data' => $this->buildDashboardPayload(),
        ], 201);
    }

    public function receivePO(Request $request, SparePartPurchaseOrder $purchaseOrder): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:sent,receive',
            'po_item_id' => 'nullable|exists:spare_part_purchase_order_items,id',
            'qty' => 'nullable|integer|min:1',
        ]);

        if ((string) $validated['action'] === 'sent') {
            if ((string) $purchaseOrder->status !== 'draft') {
                return response()->json(['message' => 'Only draft purchase orders can be marked as sent.'], 422);
            }

            $purchaseOrder->status = 'sent';
            $purchaseOrder->save();

            return response()->json([
                'message' => 'Purchase order marked as sent.',
                'data' => $this->buildDashboardPayload(),
            ]);
        }

        if (!in_array((string) $purchaseOrder->status, ['sent', 'partially_received'], true)) {
            return response()->json(['message' => 'PO must be sent before receiving stock.'], 422);
        }

        $poItemId = (int) ($validated['po_item_id'] ?? 0);
        $receiveQty = (int) ($validated['qty'] ?? 0);
        if ($poItemId <= 0 || $receiveQty <= 0) {
            throw ValidationException::withMessages([
                'po_item_id' => 'PO line and quantity are required for receiving.',
            ]);
        }

        $line = SparePartPurchaseOrderItem::query()
            ->where('po_id', $purchaseOrder->id)
            ->with('part')
            ->findOrFail($poItemId);

        $remaining = max(0, (int) $line->qty_ordered - (int) $line->qty_received);
        if ($remaining <= 0) {
            return response()->json(['message' => 'This PO line is already fully received.'], 422);
        }

        $receiveQty = min($receiveQty, $remaining);

        DB::transaction(function () use ($purchaseOrder, $line, $receiveQty) {
            $this->inventoryService->applyPurchaseReceipt(
                $line->part,
                $receiveQty,
                'purchase_order',
                $purchaseOrder->id,
                Auth::id(),
                'Received via spare dashboard'
            );

            $line->qty_received = (int) $line->qty_received + $receiveQty;
            $line->save();

            $items = $purchaseOrder->items()->get();
            $allReceived = $items->every(fn (SparePartPurchaseOrderItem $item) => (int) $item->qty_received >= (int) $item->qty_ordered);
            $anyReceived = $items->contains(fn (SparePartPurchaseOrderItem $item) => (int) $item->qty_received > 0);

            if ($allReceived) {
                $purchaseOrder->status = 'received';
            } elseif ($anyReceived) {
                $purchaseOrder->status = 'partially_received';
            }
            $purchaseOrder->save();
        });

        return response()->json([
            'message' => 'Stock received successfully.',
            'data' => $this->buildDashboardPayload(),
        ]);
    }

    private function buildDashboardPayload(): array
    {
        $spareParts = ProductMaster::query()
            ->spareParts()
            ->with([
                'parentPart:id,sku',
                'sparePartDetail',
                'sparePartDetail.supplier:id,name,company',
            ])
            ->orderBy('sku')
            ->get();

        $spares = $spareParts->map(function (ProductMaster $part) {
            $availableQty = $part->sku ? $this->inventoryService->totalAvailableForSku((string) $part->sku) : 0;
            $reorderLevel = (int) ($part->reorder_level ?? 0);
            $isLow = $availableQty <= $reorderLevel;
            $supplierLabel = '';
            if ($part->sparePartDetail?->supplier) {
                $supplierLabel = trim((string) ($part->sparePartDetail->supplier->name ?: $part->sparePartDetail->supplier->company));
            }

            return [
                'id' => $part->id,
                'sku' => (string) ($part->sku ?? ''),
                'part_name' => (string) ($part->sparePartDetail->part_name ?? $part->sku ?? ''),
                'parent_sku' => (string) ($part->parentPart->sku ?? ''),
                'supplier' => $supplierLabel,
                'available_qty' => $availableQty,
                'reorder_level' => $reorderLevel,
                'status' => $isLow ? 'low_stock' : 'healthy',
            ];
        })->values();

        $requisitionRows = Requisition::query()
            ->with(['items.part:id,sku'])
            ->latest()
            ->limit(200)
            ->get()
            ->flatMap(function (Requisition $requisition) {
                return $requisition->items->map(function (RequisitionItem $item) use ($requisition) {
                    return [
                        'requisition_id' => $requisition->id,
                        'item_id' => $item->id,
                        'sku' => (string) ($item->part->sku ?? ''),
                        'qty' => (int) $item->quantity_requested,
                        'approved_qty' => (int) ($item->quantity_approved ?? 0),
                        'issued_qty' => (int) ($item->quantity_issued ?? 0),
                        'status' => (string) $requisition->status,
                    ];
                });
            })->values();

        $issueItems = RequisitionItem::query()
            ->with(['part:id,sku', 'requisition:id,status'])
            ->whereHas('requisition', fn ($q) => $q->where('status', 'approved'))
            ->latest()
            ->get()
            ->map(function (RequisitionItem $item) {
                $required = $item->quantity_approved ?? $item->quantity_requested;
                $issued = (int) $item->quantity_issued;
                $remaining = max(0, (int) $required - $issued);

                return [
                    'requisition_id' => $item->requisition_id,
                    'item_id' => $item->id,
                    'sku' => (string) ($item->part->sku ?? ''),
                    'requested_qty' => (int) $required,
                    'issued_qty' => $issued,
                    'remaining_qty' => $remaining,
                ];
            })
            ->filter(fn (array $row) => $row['remaining_qty'] > 0)
            ->values();

        $purchaseOrders = SparePartPurchaseOrder::query()
            ->with(['supplier:id,name,company', 'items.part:id,sku'])
            ->latest()
            ->limit(200)
            ->get()
            ->flatMap(function (SparePartPurchaseOrder $po) {
                return $po->items->map(function (SparePartPurchaseOrderItem $item) use ($po) {
                    return [
                        'po_id' => $po->id,
                        'po_number' => (string) $po->po_number,
                        'item_id' => $item->id,
                        'supplier' => (string) trim((string) ($po->supplier->name ?? $po->supplier->company ?? '')),
                        'sku' => (string) ($item->part->sku ?? ''),
                        'qty_ordered' => (int) $item->qty_ordered,
                        'qty_received' => (int) $item->qty_received,
                        'remaining_qty' => max(0, (int) $item->qty_ordered - (int) $item->qty_received),
                        'status' => (string) $po->status,
                    ];
                });
            })->values();

        $hierarchy = ProductMaster::query()
            ->whereHas('childParts', fn ($q) => $q->where('is_spare_part', true))
            ->with(['childParts' => fn ($q) => $q->where('is_spare_part', true)->select('id', 'sku', 'parent_id')])
            ->select('id', 'sku')
            ->orderBy('sku')
            ->get()
            ->map(fn (ProductMaster $parent) => [
                'parent_sku' => (string) ($parent->sku ?? ''),
                'spares' => $parent->childParts
                    ->map(fn (ProductMaster $child) => (string) ($child->sku ?? ''))
                    ->values(),
            ])
            ->values();

        $openPurchaseOrderStatuses = ['draft', 'sent', 'partially_received'];
        $pendingReqStatuses = ['draft', 'submitted', 'approved'];

        return [
            'summary' => [
                'total_spares' => $spares->count(),
                'low_stock_items' => $spares->where('status', 'low_stock')->count(),
                'pending_requisitions' => $requisitionRows
                    ->whereIn('status', $pendingReqStatuses)
                    ->pluck('requisition_id')
                    ->unique()
                    ->count(),
                'open_purchase_orders' => $purchaseOrders
                    ->whereIn('status', $openPurchaseOrderStatuses)
                    ->pluck('po_id')
                    ->unique()
                    ->count(),
            ],
            'spares' => $spares,
            'requisitions' => $requisitionRows,
            'issue_items' => $issueItems,
            'purchase_orders' => $purchaseOrders,
            'hierarchy' => $hierarchy,
            'suppliers' => Supplier::query()
                ->select('id', 'name', 'company')
                ->orderBy('name')
                ->limit(300)
                ->get()
                ->map(fn (Supplier $supplier) => [
                    'id' => (int) $supplier->id,
                    'label' => trim((string) ($supplier->name ?: $supplier->company)),
                ]),
            'requisition_statuses' => ['draft', 'submitted', 'approved', 'issued', 'closed'],
            'po_statuses' => ['draft', 'sent', 'partially_received', 'received'],
        ];
    }

    private function resolveSupplier(?string $supplierInput): ?Supplier
    {
        $supplierInput = trim((string) $supplierInput);
        if ($supplierInput === '') {
            return null;
        }

        if (is_numeric($supplierInput)) {
            return Supplier::query()->find((int) $supplierInput);
        }

        return Supplier::query()->firstOrCreate(
            ['name' => $supplierInput],
            ['company' => $supplierInput]
        );
    }
}
