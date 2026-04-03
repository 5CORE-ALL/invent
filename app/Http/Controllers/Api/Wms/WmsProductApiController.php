<?php

namespace App\Http\Controllers\Api\Wms;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Services\Wms\BarcodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WmsProductApiController extends Controller
{
    public function __construct(
        private readonly BarcodeService $barcodeService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $q = ProductMaster::query()->orderByDesc('id');

        if ($request->filled('search')) {
            $s = '%'.$request->string('search').'%';
            $q->where(function ($qq) use ($s) {
                $qq->where('sku', 'like', $s)
                    ->orWhere('barcode', 'like', $s)
                    ->orWhere('title150', 'like', $s);
            });
        }

        $paginator = $q->paginate(min((int) $request->get('per_page', 25), 100))
            ->through(function (ProductMaster $p) {
                $barcode = $p->barcode ?: $this->barcodeService->generateForProduct($p);

                return [
                    'id' => $p->id,
                    'sku' => $p->sku,
                    'barcode' => $barcode,
                    'title' => $p->title150 ?? $p->sku,
                    'status' => $p->status ?? null,
                ];
            });

        return response()->json($paginator);
    }

    public function showByBarcode(string $barcode): JsonResponse
    {
        $product = ProductMaster::findByWmsScanCode($barcode);

        if (! $product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        if (! $product->barcode) {
            $this->barcodeService->ensureBarcode($product);
            $product->refresh();
        }

        $movements30d = $product->stockMovements()
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $locations = \App\Models\Inventory::query()
            ->where('sku', $product->sku)
            ->whereNotNull('bin_id')
            ->with(['bin.shelf.rack.zone.warehouse', 'warehouse'])
            ->get()
            ->map(function ($inv) {
                $bin = $inv->bin;
                $path = $bin?->full_location_code;

                return [
                    'inventory_id' => $inv->id,
                    'warehouse_id' => $inv->warehouse_id,
                    'warehouse_name' => $inv->warehouse?->name,
                    'bin_id' => $inv->bin_id,
                    'full_path' => $path,
                    'on_hand' => (int) $inv->on_hand,
                    'pick_locked_qty' => (int) $inv->pick_locked_qty,
                    'available' => max(0, (int) $inv->on_hand - (int) $inv->pick_locked_qty),
                ];
            });

        return response()->json([
            'product' => [
                'id' => $product->id,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'title' => $product->title150 ?? $product->sku,
            ],
            'locations' => $locations,
            'is_fast_moving' => $movements30d >= 10,
            'movement_count_30d' => $movements30d,
        ]);
    }
}
