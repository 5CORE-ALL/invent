<?php

namespace App\Http\Controllers\InventoryManagement;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
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

    /**
     * Spare Parts Purchase Sheet — add-on to /forecast.analysis.
     */
    public function purchaseSheet(Request $request)
    {
        $payload = [
            'rows' => $this->buildPurchaseSheetRows(),
            'suppliers' => Supplier::query()
                ->select('id', 'name', 'company')
                ->orderBy('name')
                ->limit(300)
                ->get()
                ->map(fn (Supplier $supplier) => [
                    'id' => (int) $supplier->id,
                    'label' => trim((string) ($supplier->name ?: $supplier->company)),
                ])
                ->values(),
        ];

        if ($request->ajax() || $request->expectsJson() || $request->boolean('ajax')) {
            return response()->json($payload);
        }

        return view('purchase-master.spare-parts-forecast', $payload);
    }

    /**
     * One row per spare part with stock, commitments, open POs and a suggested order qty.
     *
     *   net        = available - reserved (approved requisitions not yet issued) + on order (open POs)
     *   target     = max_stock_level, else 2 × reorder_level
     *   to_order   = target - net, only when net <= reorder_level (otherwise 0)
     */
    private function buildPurchaseSheetRows(): array
    {
        $parts = ProductMaster::query()
            ->spareParts()
            ->with(['parentPart:id,sku', 'sparePartDetail', 'sparePartDetail.supplier:id,name,company'])
            ->orderBy('sku')
            ->get();

        $onOrderByPart = DB::table('spare_part_purchase_order_items as i')
            ->join('spare_part_purchase_orders as p', 'p.id', '=', 'i.po_id')
            ->whereIn('p.status', ['draft', 'sent', 'partially_received'])
            ->groupBy('i.part_id')
            ->selectRaw('i.part_id, SUM(GREATEST(i.qty_ordered - i.qty_received, 0)) as remaining')
            ->pluck('remaining', 'part_id');

        $reservedByPart = DB::table('requisition_items as ri')
            ->join('requisitions as r', 'r.id', '=', 'ri.requisition_id')
            ->where('r.status', 'approved')
            ->groupBy('ri.part_id')
            ->selectRaw('ri.part_id, SUM(GREATEST(COALESCE(ri.quantity_approved, ri.quantity_requested) - ri.quantity_issued, 0)) as remaining')
            ->pluck('remaining', 'part_id');

        $lastCostByPart = [];
        $costRows = DB::table('spare_part_purchase_order_items')
            ->whereNotNull('unit_cost')
            ->where('unit_cost', '>', 0)
            ->orderByDesc('id')
            ->get(['part_id', 'unit_cost']);
        foreach ($costRows as $costRow) {
            $lastCostByPart[$costRow->part_id] ??= (float) $costRow->unit_cost;
        }

        return $parts->map(function (ProductMaster $part) use ($onOrderByPart, $reservedByPart, $lastCostByPart) {
            $available = $part->sku ? $this->inventoryService->totalAvailableForSku((string) $part->sku) : 0;
            $reserved = (int) ($reservedByPart[$part->id] ?? 0);
            $onOrder = (int) ($onOrderByPart[$part->id] ?? 0);
            $reorderLevel = (int) ($part->reorder_level ?? 0);
            $maxLevel = (int) ($part->max_stock_level ?? 0);

            $net = $available - $reserved + $onOrder;
            $target = $maxLevel > 0 ? $maxLevel : $reorderLevel * 2;
            $toOrder = ($target > 0 && $net <= $reorderLevel) ? max(0, $target - $net) : 0;

            $supplier = $part->sparePartDetail?->supplier;
            $unitCost = $lastCostByPart[$part->id] ?? null;

            return [
                'id' => $part->id,
                'sku' => (string) ($part->sku ?? ''),
                'part_name' => (string) ($part->sparePartDetail->part_name ?? $part->sku ?? ''),
                'parent_sku' => (string) ($part->parentPart->sku ?? ''),
                'supplier_id' => $supplier?->id,
                'supplier' => $supplier ? trim((string) ($supplier->name ?: $supplier->company)) : '',
                'available' => $available,
                'reserved' => $reserved,
                'on_order' => $onOrder,
                'net' => $net,
                'min_stock_level' => (int) ($part->min_stock_level ?? 0),
                'reorder_level' => $reorderLevel,
                'max_stock_level' => $maxLevel,
                'lead_time_days' => $part->lead_time_days !== null ? (int) $part->lead_time_days : null,
                'to_order' => $toOrder,
                'last_unit_cost' => $unitCost,
                'est_amount' => $unitCost !== null ? round($unitCost * $toOrder, 2) : null,
            ];
        })->values()->all();
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

        return response()->json(['message' => 'Purchase order created.'], 201);
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

        return Supplier::query()
            ->whereRaw('LOWER(name) = ?', [strtolower($supplierInput)])
            ->orWhereRaw('LOWER(company) = ?', [strtolower($supplierInput)])
            ->first();
    }
}
