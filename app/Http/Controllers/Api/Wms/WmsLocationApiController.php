<?php

namespace App\Http\Controllers\Api\Wms;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Warehouse;
use App\Models\Wms\Bin;
use App\Models\Wms\StockMovement;
use App\Repositories\Wms\BinRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WmsLocationApiController extends Controller
{
    public function __construct(
        private readonly BinRepository $binRepository
    ) {}

    public function tree(Request $request): JsonResponse
    {
        $warehouseId = $request->integer('warehouse_id');

        $q = Warehouse::query()->with([
            'zones' => function ($z) {
                $z->orderBy('name');
            },
            'zones.racks' => function ($r) {
                $r->orderBy('pick_priority')->orderBy('name');
            },
            'zones.racks.shelves' => function ($s) {
                $s->orderBy('name');
            },
            'zones.racks.shelves.bins' => function ($b) {
                $b->orderBy('name');
            },
        ])->orderBy('name');

        if ($warehouseId) {
            $q->where('id', $warehouseId);
        }

        $data = $q->get()->map(function (Warehouse $w) {
            return [
                'id' => $w->id,
                'name' => $w->name,
                'code' => $w->code,
                'zones' => $w->zones->map(function ($zone) {
                    return [
                        'id' => $zone->id,
                        'name' => $zone->name,
                        'code' => $zone->code,
                        'racks' => $zone->racks->map(function ($rack) {
                            return [
                                'id' => $rack->id,
                                'name' => $rack->name,
                                'code' => $rack->code,
                                'pick_priority' => $rack->pick_priority,
                                'shelves' => $rack->shelves->map(function ($shelf) {
                                    return [
                                        'id' => $shelf->id,
                                        'name' => $shelf->name,
                                        'code' => $shelf->code,
                                        'bins' => $shelf->bins->map(function ($bin) {
                                            return [
                                                'id' => $bin->id,
                                                'name' => $bin->name,
                                                'code' => $bin->code,
                                                'capacity' => $bin->capacity,
                                                'full_location_code' => $bin->full_location_code,
                                            ];
                                        }),
                                    ];
                                }),
                            ];
                        }),
                    ];
                }),
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function binProducts(int $id): JsonResponse
    {
        $bin = Bin::query()->find($id);
        if (! $bin) {
            return response()->json(['message' => 'Bin not found'], 404);
        }

        $rows = Inventory::query()
            ->where('bin_id', $id)
            ->orderBy('sku')
            ->get();

        $skus = $rows->pluck('sku')->unique()->filter()->values();
        $titles = \App\Models\ProductMaster::query()
            ->whereIn('sku', $skus)
            ->pluck('title150', 'sku');

        $payload = $rows->map(function (Inventory $inv) use ($titles) {
            return [
                'inventory_id' => $inv->id,
                'sku' => $inv->sku,
                'title' => $titles[$inv->sku] ?? $inv->sku,
                'on_hand' => (int) $inv->on_hand,
                'pick_locked_qty' => (int) $inv->pick_locked_qty,
                'available' => max(0, (int) $inv->on_hand - (int) $inv->pick_locked_qty),
            ];
        });

        $capacity = $bin->capacity;
        $used = (int) $rows->sum('on_hand');
        $status = $capacity === null ? 'unknown' : ($used >= $capacity ? 'full' : ($used === 0 ? 'empty' : 'ok'));

        return response()->json([
            'bin' => [
                'id' => $bin->id,
                'full_location_code' => $bin->full_location_code,
                'capacity' => $capacity,
                'capacity_status' => $status,
            ],
            'products' => $payload,
        ]);
    }

    public function fastMoving(Request $request): JsonResponse
    {
        $days = min($request->integer('days', 30), 90);

        $rows = StockMovement::query()
            ->selectRaw('product_id, SUM(qty) as total_qty, COUNT(*) as moves')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('product_id')
            ->orderByDesc('moves')
            ->limit(50)
            ->get();

        $productIds = $rows->pluck('product_id');
        $products = \App\Models\ProductMaster::query()->whereIn('id', $productIds)->get()->keyBy('id');

        $data = $rows->map(function ($r) use ($products) {
            $p = $products->get($r->product_id);

            return [
                'product_id' => $r->product_id,
                'sku' => $p?->sku,
                'title' => $p?->title150 ?? $p?->sku,
                'movement_count' => (int) $r->moves,
                'quantity_moved' => (int) $r->total_qty,
            ];
        });

        return response()->json(['data' => $data, 'days' => $days]);
    }

    public function suggestNearestBin(Request $request): JsonResponse
    {
        $request->validate([
            'sku' => ['required', 'string'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
        ]);

        $bin = $this->binRepository->suggestPickBinForSku(
            $request->string('sku')->toString(),
            $request->filled('warehouse_id') ? $request->integer('warehouse_id') : null
        );

        if (! $bin) {
            return response()->json(['bin' => null, 'message' => 'No stocked bin found for SKU']);
        }

        $bin->loadMissing('shelf.rack.zone.warehouse');

        return response()->json([
            'bin' => [
                'id' => $bin->id,
                'full_location_code' => $bin->full_location_code,
                'pick_priority' => $bin->shelf?->rack?->pick_priority,
            ],
        ]);
    }
}
