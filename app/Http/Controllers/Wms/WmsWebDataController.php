<?php

namespace App\Http\Controllers\Wms;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\ProductMaster;
use App\Models\Warehouse;
use App\Models\Wms\Bin;
use App\Models\Wms\Rack;
use App\Models\Wms\Shelf;
use App\Models\Wms\StockMovement;
use App\Models\Wms\Zone;
use App\Services\Wms\LocationCodeService;
use App\Services\Wms\StockMovementService;
use App\Services\Wms\WmsAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WmsWebDataController extends Controller
{
    public function __construct(
        private readonly LocationCodeService $locationCodeService,
        private readonly StockMovementService $stockMovementService,
        private readonly WmsAuditService $auditService
    ) {}

    public function updateWarehouse(Request $request, int $id): JsonResponse
    {
        $warehouse = Warehouse::query()->findOrFail($id);
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:32', Rule::unique('warehouses', 'code')->ignore($warehouse->id)],
            'name' => ['sometimes', 'string', 'max:191'],
        ]);
        $warehouse->fill($data);
        $warehouse->save();
        $this->recalcBinsForWarehouse($warehouse->id);

        return response()->json(['warehouse' => $warehouse]);
    }

    public function storeZone(Request $request): JsonResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'name' => ['required', 'string', 'max:191'],
            'code' => ['required', 'string', 'max:32'],
        ]);
        $zone = Zone::query()->create($data);
        $this->recalcBinsForWarehouse((int) $zone->warehouse_id);

        return response()->json(['zone' => $zone], 201);
    }

    public function updateZone(Request $request, int $id): JsonResponse
    {
        $zone = Zone::query()->findOrFail($id);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:191'],
            'code' => ['sometimes', 'string', 'max:32'],
        ]);
        $zone->update($data);
        $this->recalcBinsForWarehouse((int) $zone->warehouse_id);

        return response()->json(['zone' => $zone]);
    }

    public function destroyZone(int $id): JsonResponse
    {
        $zone = Zone::query()->findOrFail($id);
        $wh = (int) $zone->warehouse_id;
        $zone->delete();
        $this->recalcBinsForWarehouse($wh);

        return response()->json(['ok' => true]);
    }

    public function storeRack(Request $request): JsonResponse
    {
        $data = $request->validate([
            'zone_id' => ['required', 'integer', 'exists:zones,id'],
            'name' => ['required', 'string', 'max:191'],
            'code' => ['required', 'string', 'max:32'],
            'pick_priority' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);
        $rack = Rack::query()->create($data);
        $this->recalcBinsForZoneRacks($rack->zone_id);

        return response()->json(['rack' => $rack], 201);
    }

    public function updateRack(Request $request, int $id): JsonResponse
    {
        $rack = Rack::query()->findOrFail($id);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:191'],
            'code' => ['sometimes', 'string', 'max:32'],
            'pick_priority' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);
        $rack->update($data);
        $this->recalcBinsForZoneRacks($rack->zone_id);

        return response()->json(['rack' => $rack]);
    }

    public function destroyRack(int $id): JsonResponse
    {
        $rack = Rack::query()->findOrFail($id);
        $zoneId = (int) $rack->zone_id;
        $rack->delete();
        $this->recalcBinsForZoneRacks($zoneId);

        return response()->json(['ok' => true]);
    }

    public function storeShelf(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rack_id' => ['required', 'integer', 'exists:racks,id'],
            'name' => ['required', 'string', 'max:191'],
            'code' => ['required', 'string', 'max:32'],
        ]);
        $shelf = Shelf::query()->create($data);
        $rack = Rack::query()->find($shelf->rack_id);
        if ($rack) {
            $this->recalcBinsForZoneRacks($rack->zone_id);
        }

        return response()->json(['shelf' => $shelf], 201);
    }

    public function updateShelf(Request $request, int $id): JsonResponse
    {
        $shelf = Shelf::query()->findOrFail($id);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:191'],
            'code' => ['sometimes', 'string', 'max:32'],
        ]);
        $shelf->update($data);
        $rack = Rack::query()->find($shelf->rack_id);
        if ($rack) {
            $this->recalcBinsForZoneRacks($rack->zone_id);
        }

        return response()->json(['shelf' => $shelf]);
    }

    public function destroyShelf(int $id): JsonResponse
    {
        $shelf = Shelf::query()->findOrFail($id);
        $rack = Rack::query()->find($shelf->rack_id);
        $zoneId = $rack ? (int) $rack->zone_id : null;
        $shelf->delete();
        if ($zoneId) {
            $this->recalcBinsForZoneRacks($zoneId);
        }

        return response()->json(['ok' => true]);
    }

    public function storeBin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'shelf_id' => ['required', 'integer', 'exists:shelves,id'],
            'name' => ['required', 'string', 'max:191'],
            'code' => ['required', 'string', 'max:32'],
            'capacity' => ['nullable', 'integer', 'min:0'],
        ]);
        $bin = Bin::query()->create($data);
        $this->locationCodeService->refreshBinFullCode($bin);

        return response()->json(['bin' => $bin->fresh()], 201);
    }

    public function updateBin(Request $request, int $id): JsonResponse
    {
        $bin = Bin::query()->findOrFail($id);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:191'],
            'code' => ['sometimes', 'string', 'max:32'],
            'capacity' => ['nullable', 'integer', 'min:0'],
        ]);
        $bin->update($data);
        $this->locationCodeService->refreshBinFullCode($bin->fresh());

        return response()->json(['bin' => $bin->fresh()]);
    }

    public function destroyBin(int $id): JsonResponse
    {
        Bin::query()->whereKey($id)->delete();

        return response()->json(['ok' => true]);
    }

    public function zonesByWarehouse(int $warehouseId): JsonResponse
    {
        $rows = Zone::query()->where('warehouse_id', $warehouseId)->orderBy('name')->get();

        return response()->json(['data' => $rows]);
    }

    public function racksByZone(int $zoneId): JsonResponse
    {
        $rows = Rack::query()->where('zone_id', $zoneId)->orderBy('pick_priority')->orderBy('name')->get();

        return response()->json(['data' => $rows]);
    }

    public function shelvesByRack(int $rackId): JsonResponse
    {
        $rows = Shelf::query()->where('rack_id', $rackId)->orderBy('name')->get();

        return response()->json(['data' => $rows]);
    }

    public function binsByShelf(int $shelfId): JsonResponse
    {
        $rows = Bin::query()->where('shelf_id', $shelfId)->orderBy('name')->get();

        return response()->json(['data' => $rows]);
    }

    public function inventoryRows(Request $request): JsonResponse
    {
        $request->validate([
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'bin_id' => ['nullable', 'integer', 'exists:bins,id'],
            'sku' => ['nullable', 'string'],
        ]);

        $q = Inventory::query()
            ->with(['warehouse', 'bin.shelf.rack.zone.warehouse'])
            ->orderByDesc('updated_at');

        if ($request->filled('warehouse_id')) {
            $q->where('warehouse_id', $request->integer('warehouse_id'));
        }
        if ($request->filled('bin_id')) {
            $q->where('bin_id', $request->integer('bin_id'));
        }
        if ($request->filled('sku')) {
            $q->where('sku', 'like', '%'.$request->string('sku').'%');
        }

        $rows = $q->limit(500)->get();
        $skus = $rows->pluck('sku')->unique()->filter()->values();
        $titles = ProductMaster::query()->whereIn('sku', $skus)->pluck('title150', 'sku');

        $data = $rows->map(function (Inventory $inv) use ($titles) {
            $onHand = (int) $inv->on_hand;
            $locked = (int) $inv->pick_locked_qty;
            $avail = max(0, $onHand - $locked);
            $stockFlag = $onHand <= 0 ? 'empty' : ($avail <= 0 ? 'locked' : ($onHand < 5 ? 'low' : 'ok'));

            return [
                'id' => $inv->id,
                'sku' => $inv->sku,
                'title' => $titles[$inv->sku] ?? $inv->sku,
                'warehouse' => $inv->warehouse?->name,
                'full_path' => $inv->bin?->full_location_code,
                'bin_id' => $inv->bin_id,
                'on_hand' => $onHand,
                'pick_locked_qty' => $locked,
                'available' => $avail,
                'stock_flag' => $stockFlag,
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function movements(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => ['nullable', 'integer'],
            'sku' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $q = StockMovement::query()
            ->with(['product', 'fromBin', 'toBin', 'user'])
            ->orderByDesc('id');

        if ($request->filled('product_id')) {
            $q->where('product_id', $request->integer('product_id'));
        }
        if ($request->filled('sku')) {
            $q->where('sku', 'like', '%'.$request->string('sku').'%');
        }
        if ($request->filled('type')) {
            $q->where('type', $request->string('type'));
        }
        if ($request->filled('from')) {
            $q->whereDate('created_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $q->whereDate('created_at', '<=', $request->date('to'));
        }

        $page = $q->paginate(min((int) $request->get('per_page', 40), 100));

        return response()->json($page);
    }

    public function locate(Request $request): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:1']]);
        $term = $request->string('q')->trim();

        $product = ProductMaster::query()
            ->where(function ($q) use ($term) {
                $q->where('sku', $term)
                    ->orWhere('barcode', $term)
                    ->orWhere('sku', 'like', '%'.$term.'%');
            })
            ->first();

        if (! $product) {
            return response()->json(['product' => null, 'locations' => []]);
        }

        $locations = Inventory::query()
            ->where('sku', $product->sku)
            ->whereNotNull('bin_id')
            ->with(['bin.shelf.rack.zone.warehouse', 'warehouse'])
            ->get()
            ->map(function (Inventory $inv) {
                $parts = [];
                $b = $inv->bin;
                if ($b && $b->relationLoaded('shelf') && $b->shelf?->rack?->zone?->warehouse) {
                    $w = $b->shelf->rack->zone->warehouse;
                    $z = $b->shelf->rack->zone;
                    $r = $b->shelf->rack;
                    $s = $b->shelf;
                    $parts = [
                        'warehouse' => $w->name,
                        'zone' => $z->name,
                        'rack' => $r->name,
                        'shelf' => $s->name,
                        'bin' => $b->name,
                    ];
                }

                return [
                    'inventory_id' => $inv->id,
                    'full_path' => $b?->full_location_code,
                    'hierarchy' => $parts,
                    'on_hand' => (int) $inv->on_hand,
                    'available' => max(0, (int) $inv->on_hand - (int) $inv->pick_locked_qty),
                ];
            });

        $movements30d = StockMovement::query()
            ->where('product_id', $product->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        return response()->json([
            'product' => [
                'id' => $product->id,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'title' => $product->title150 ?? $product->sku,
            ],
            'locations' => $locations,
            'is_fast_moving' => $movements30d >= 10,
        ]);
    }

    public function stockMove(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:product_master,id'],
            'type' => ['required', 'string', Rule::in(StockMovement::types())],
            'qty' => ['required', 'integer'],
            'from_bin_id' => ['nullable', 'integer', 'min:1', 'exists:bins,id'],
            'to_bin_id' => ['nullable', 'integer', 'min:1', 'exists:bins,id'],
            'note' => ['nullable', 'string', 'max:2000'],
            'force_pick_without_lock' => ['sometimes', 'boolean'],
        ]);

        try {
            $movement = $this->stockMovementService->move($validated, $request->user());
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['movement' => $movement], 201);
    }

    public function pickLock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku' => ['required', 'string'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'bin_id' => ['nullable', 'integer', 'min:1', 'exists:bins,id'],
            'qty' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $inv = $this->stockMovementService->lockForPick(
                $validated['sku'],
                (int) $validated['warehouse_id'],
                isset($validated['bin_id']) && (int) $validated['bin_id'] > 0 ? (int) $validated['bin_id'] : null,
                (int) $validated['qty'],
                $request->user()
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['inventory' => $inv]);
    }

    public function pickUnlock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku' => ['required', 'string'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'bin_id' => ['nullable', 'integer', 'min:1', 'exists:bins,id'],
            'qty' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $inv = $this->stockMovementService->releasePickLock(
                $validated['sku'],
                (int) $validated['warehouse_id'],
                isset($validated['bin_id']) && (int) $validated['bin_id'] > 0 ? (int) $validated['bin_id'] : null,
                (int) $validated['qty'],
                $request->user()
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['inventory' => $inv]);
    }

    public function scanLookup(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string']]);
        $code = $request->string('code')->trim();

        $product = ProductMaster::query()
            ->where(function ($q) use ($code) {
                $q->where('barcode', $code)->orWhere('sku', $code);
            })
            ->first();

        if (! $product) {
            return response()->json(['found' => false], 404);
        }

        $this->auditService->log($request->user(), 'wms.scan', ProductMaster::class, $product->id, ['code' => $code], $request);

        $locations = Inventory::query()
            ->where('sku', $product->sku)
            ->with(['bin', 'warehouse'])
            ->get();

        return response()->json([
            'found' => true,
            'product' => [
                'id' => $product->id,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'title' => $product->title150 ?? $product->sku,
            ],
            'lines' => $locations->map(function (Inventory $inv) {
                $bid = $inv->bin_id && (int) $inv->bin_id > 0 ? (int) $inv->bin_id : null;

                return [
                    'inventory_id' => $inv->id,
                    'bin_id' => $bid,
                    'warehouse' => $inv->warehouse?->name,
                    'full_path' => $bid ? $inv->bin?->full_location_code : null,
                    'on_hand' => (int) $inv->on_hand,
                    'available' => max(0, (int) $inv->on_hand - (int) $inv->pick_locked_qty),
                ];
            }),
        ]);
    }

    private function recalcBinsForWarehouse(int $warehouseId): void
    {
        $binIds = Bin::query()
            ->whereHas('shelf.rack.zone', fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->pluck('id');

        foreach ($binIds as $bid) {
            $b = Bin::query()->find($bid);
            if ($b) {
                $this->locationCodeService->refreshBinFullCode($b);
            }
        }
    }

    private function recalcBinsForZoneRacks(int $zoneId): void
    {
        $binIds = Bin::query()
            ->whereHas('shelf.rack', fn ($q) => $q->where('zone_id', $zoneId))
            ->pluck('id');

        foreach ($binIds as $bid) {
            $b = Bin::query()->find($bid);
            if ($b) {
                $this->locationCodeService->refreshBinFullCode($b);
            }
        }
    }
}
