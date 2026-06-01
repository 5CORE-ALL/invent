<?php

namespace App\Http\Controllers\Api\Wms;

use App\Http\Controllers\Controller;
use App\Models\Wms\StockMovement;
use App\Services\Wms\StockMovementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WmsStockApiController extends Controller
{
    public function __construct(
        private readonly StockMovementService $stockMovementService
    ) {}

    public function move(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:product_master,id'],
            'type' => ['required', 'string', Rule::in(StockMovement::types())],
            'qty' => ['required', 'integer'],
            'from_bin_id' => ['nullable', 'integer', 'min:1', 'exists:bins,id'],
            'from_warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'source_inventory_id' => ['nullable', 'integer', 'exists:inventories,id'],
            'to_bin_id' => ['nullable', 'integer', 'min:1', 'exists:bins,id'],
            'note' => ['nullable', 'string', 'max:2000'],
            'force_pick_without_lock' => ['sometimes', 'boolean'],
        ]);

        try {
            $movement = $this->stockMovementService->move($validated, $request->user());
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'OK',
            'movement' => $movement,
        ], 201);
    }

    public function lock(Request $request): JsonResponse
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
                $validated['bin_id'] ?? null,
                (int) $validated['qty'],
                $request->user()
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['inventory' => $inv]);
    }

    public function unlock(Request $request): JsonResponse
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
                $validated['bin_id'] ?? null,
                (int) $validated['qty'],
                $request->user()
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['inventory' => $inv]);
    }
}
