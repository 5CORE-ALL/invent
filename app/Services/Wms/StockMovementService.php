<?php

namespace App\Services\Wms;

use App\Models\Inventory;
use App\Models\ProductMaster;
use App\Models\User;
use App\Models\Wms\Bin;
use App\Models\Wms\StockMovement;
use App\Repositories\Wms\BinRepository;
use App\Repositories\Wms\InventoryStockRepository;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

class StockMovementService
{
    private static ?bool $hasAvailableQtyColumn = null;

    public function __construct(
        private readonly InventoryStockRepository $inventories,
        private readonly BinRepository $bins,
        private readonly WmsAuditService $audit,
    ) {}

    private function syncAvailableQty(Inventory $inv): void
    {
        if (self::$hasAvailableQtyColumn === null) {
            self::$hasAvailableQtyColumn = Schema::hasColumn('inventories', 'available_qty');
        }
        if (self::$hasAvailableQtyColumn) {
            $inv->available_qty = max(0, (int) $inv->on_hand - (int) $inv->pick_locked_qty);
        }
    }

    /**
     * Treat missing, null, empty, or non-positive values as null (avoids (int) null → 0 → findOrFail(0)).
     *
     * @param  array<string, mixed>  $payload
     */
    private function positiveBinId(array $payload, string $key): ?int
    {
        if (! array_key_exists($key, $payload)) {
            return null;
        }
        $v = $payload[$key];
        if ($v === null || $v === '') {
            return null;
        }
        $i = (int) $v;

        return $i > 0 ? $i : null;
    }

    /**
     * Lock `inventories` rows with no assigned bin.
     * Use $sourceInventoryId when several no-bin rows exist (exact row from Scan list).
     */
    private function lockNoBinInventory(ProductMaster $product, ?int $warehouseId, ?int $sourceInventoryId = null): Inventory
    {
        if ($sourceInventoryId !== null && $sourceInventoryId > 0) {
            $inv = Inventory::query()
                ->whereKey($sourceInventoryId)
                ->where('sku', $product->sku)
                ->whereNull('bin_id')
                ->lockForUpdate()
                ->first();
            if (! $inv) {
                throw new RuntimeException('source_inventory_id must be a no-bin `inventories` row for this product.');
            }
            if ($warehouseId !== null && $warehouseId > 0 && (int) $inv->warehouse_id !== (int) $warehouseId) {
                throw new RuntimeException('That inventory row is not in the warehouse you indicated (from_warehouse_id).');
            }

            return $inv;
        }

        $q = Inventory::query()
            ->where('sku', $product->sku)
            ->whereNull('bin_id')
            ->lockForUpdate();
        if ($warehouseId !== null && $warehouseId > 0) {
            $q->where('warehouse_id', $warehouseId);
        }
        $rows = $q->get();
        if ($rows->isEmpty()) {
            throw new RuntimeException($warehouseId
                ? 'No unassigned (no-bin) stock for this SKU in that warehouse.'
                : 'No unassigned (no-bin) stock for this SKU.');
        }
        if ($rows->count() > 1) {
            throw new RuntimeException('Multiple no-bin rows — tap "Use this row" on the WMS list (or send source_inventory_id = inv row #).');
        }

        return $rows->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function move(array $payload, User $user): StockMovement
    {
        return $this->inventories->transaction(function () use ($payload, $user) {
            $product = ProductMaster::query()->findOrFail($payload['product_id']);
            $type = strtoupper((string) $payload['type']);

            if ($type === StockMovement::TYPE_ADJUSTMENT) {
                if (! WmsAuthorization::canAdjustStock($user)) {
                    throw new RuntimeException('Only warehouse managers or admins can adjust stock.');
                }

                return $this->handleAdjustment($product, $payload, $user);
            }

            if (! WmsAuthorization::canMoveStock($user)) {
                throw new RuntimeException('You are not allowed to move stock.');
            }

            $qty = (int) $payload['qty'];
            if ($qty < 1) {
                throw new InvalidArgumentException('Quantity must be at least 1.');
            }

            $note = isset($payload['note']) ? (string) $payload['note'] : null;
            $force = (bool) ($payload['force_pick_without_lock'] ?? false);

            $fromBinId = $this->positiveBinId($payload, 'from_bin_id');
            $toBinId = $this->positiveBinId($payload, 'to_bin_id');
            $fromWarehouseId = isset($payload['from_warehouse_id']) && (int) $payload['from_warehouse_id'] > 0
                ? (int) $payload['from_warehouse_id']
                : null;
            $sourceInventoryId = isset($payload['source_inventory_id']) && (int) $payload['source_inventory_id'] > 0
                ? (int) $payload['source_inventory_id']
                : null;

            return match ($type) {
                StockMovement::TYPE_GRN => $this->handleGrn($product, $qty, $toBinId, $user, $note),
                StockMovement::TYPE_PUTAWAY => $this->handlePutaway(
                    $product,
                    $qty,
                    $fromBinId,
                    $toBinId,
                    $user,
                    $note,
                    $fromWarehouseId,
                    $sourceInventoryId
                ),
                StockMovement::TYPE_PICK => $this->handlePick(
                    $product,
                    $qty,
                    $fromBinId,
                    $toBinId,
                    $user,
                    $note,
                    $force,
                    StockMovement::TYPE_PICK,
                    $fromWarehouseId,
                    $sourceInventoryId
                ),
                StockMovement::TYPE_PACK => $this->handlePack(
                    $product,
                    $qty,
                    $fromBinId,
                    $toBinId,
                    $user,
                    $note
                ),
                StockMovement::TYPE_DISPATCH => $this->handlePick(
                    $product,
                    $qty,
                    $fromBinId,
                    null,
                    $user,
                    $note,
                    true,
                    StockMovement::TYPE_DISPATCH,
                    $fromWarehouseId,
                    $sourceInventoryId
                ),
                default => throw new InvalidArgumentException('Unsupported movement type: '.$type),
            };
        });
    }

    public function lockForPick(string $sku, int $warehouseId, ?int $binId, int $qty, User $user): Inventory
    {
        if (! WmsAuthorization::canMoveStock($user)) {
            throw new RuntimeException('You are not allowed to lock stock.');
        }

        return $this->inventories->transaction(function () use ($sku, $warehouseId, $binId, $qty, $user) {
            $inv = $this->inventories->firstOrNew($sku, $warehouseId, $binId);
            if (! $inv->exists) {
                throw new RuntimeException('No inventory row to lock.');
            }
            $available = (int) $inv->on_hand - (int) $inv->pick_locked_qty;
            if ($available < $qty) {
                throw new RuntimeException('Not enough available stock to lock.');
            }
            $inv->pick_locked_qty = (int) $inv->pick_locked_qty + $qty;
            $this->syncAvailableQty($inv);
            $inv->save();

            $this->audit->log($user, 'stock.lock', Inventory::class, (int) $inv->id, [
                'sku' => $sku,
                'warehouse_id' => $warehouseId,
                'bin_id' => $binId,
                'qty' => $qty,
            ]);

            return $inv->fresh();
        });
    }

    public function releasePickLock(string $sku, int $warehouseId, ?int $binId, int $qty, User $user): Inventory
    {
        return $this->inventories->transaction(function () use ($sku, $warehouseId, $binId, $qty, $user) {
            $inv = $this->inventories->findLocked($sku, $warehouseId, $binId);
            if (! $inv) {
                throw new RuntimeException('Inventory row not found.');
            }
            if ((int) $inv->pick_locked_qty < $qty) {
                throw new RuntimeException('Cannot release more than locked quantity.');
            }
            $inv->pick_locked_qty = (int) $inv->pick_locked_qty - $qty;
            $this->syncAvailableQty($inv);
            $inv->save();

            $this->audit->log($user, 'stock.unlock', Inventory::class, (int) $inv->id, [
                'sku' => $sku,
                'warehouse_id' => $warehouseId,
                'bin_id' => $binId,
                'qty' => $qty,
            ]);

            return $inv->fresh();
        });
    }

    private function handleGrn(ProductMaster $product, int $qty, ?int $toBinId, User $user, ?string $note): StockMovement
    {
        if (! $toBinId) {
            throw new InvalidArgumentException('GRN requires a valid to_bin_id.');
        }

        $bin = Bin::query()->with('shelf.rack.zone')->findOrFail($toBinId);
        $warehouseId = $this->bins->warehouseIdForBin($bin);
        if (! $warehouseId) {
            throw new RuntimeException('Could not resolve warehouse for bin.');
        }

        $inv = $this->inventories->firstOrNew($product->sku, $warehouseId, $toBinId);
        $inv->on_hand = (int) ($inv->on_hand ?? 0) + $qty;
        $this->syncAvailableQty($inv);
        $inv->save();

        $movement = $this->recordMovement($product, null, $toBinId, $qty, StockMovement::TYPE_GRN, $user, (int) $inv->id, $note);
        $this->audit->log($user, 'stock.grn', StockMovement::class, (int) $movement->id, ['qty' => $qty, 'bin_id' => $toBinId]);

        return $movement;
    }

    private function handlePutaway(ProductMaster $product, int $qty, ?int $fromBinId, ?int $toBinId, User $user, ?string $note, ?int $fromWarehouseId = null, ?int $sourceInventoryId = null): StockMovement
    {
        if (! $toBinId) {
            throw new InvalidArgumentException('Putaway requires a valid to_bin_id.');
        }

        $toBin = Bin::query()->with('shelf.rack.zone')->findOrFail($toBinId);
        $warehouseId = $this->bins->warehouseIdForBin($toBin);
        if (! $warehouseId) {
            throw new RuntimeException('Could not resolve warehouse for destination bin.');
        }

        if ($fromBinId) {
            $fromBin = Bin::query()->with('shelf.rack.zone')->findOrFail($fromBinId);
            $whFrom = $this->bins->warehouseIdForBin($fromBin);
            if ($whFrom !== $warehouseId) {
                throw new RuntimeException('Putaway source bin must be in the same warehouse as destination.');
            }
            $fromInv = $this->inventories->findLocked($product->sku, $warehouseId, $fromBinId);
            if (! $fromInv) {
                throw new RuntimeException('Source inventory row not found: no `inventories` row for this SKU in that warehouse and from-bin (use WMS list on Scan page, or GRN first).');
            }
        } else {
            $whScope = $fromWarehouseId ?: $warehouseId;
            $fromInv = $this->lockNoBinInventory($product, $whScope, $sourceInventoryId);
            if ((int) $fromInv->warehouse_id !== (int) $warehouseId) {
                throw new RuntimeException('Putaway: unassigned stock must be in the same warehouse as the destination bin.');
            }
        }
        $available = (int) $fromInv->on_hand - (int) $fromInv->pick_locked_qty;
        if ($available < $qty) {
            throw new RuntimeException('Insufficient available quantity at source.');
        }

        $fromInv->on_hand = (int) $fromInv->on_hand - $qty;
        $this->syncAvailableQty($fromInv);
        $fromInv->save();
        if ((int) $fromInv->on_hand < 0) {
            throw new RuntimeException('Stock would become negative.');
        }

        $toInv = $this->inventories->firstOrNew($product->sku, $warehouseId, $toBinId);
        $toInv->on_hand = (int) ($toInv->on_hand ?? 0) + $qty;
        $this->syncAvailableQty($toInv);
        $toInv->save();

        $movement = $this->recordMovement($product, $fromBinId ?: null, $toBinId, $qty, StockMovement::TYPE_PUTAWAY, $user, (int) $toInv->id, $note);
        $this->audit->log($user, 'stock.putaway', StockMovement::class, (int) $movement->id, ['qty' => $qty]);

        return $movement;
    }

    private function handlePick(
        ProductMaster $product,
        int $qty,
        ?int $fromBinId,
        ?int $toBinId,
        User $user,
        ?string $note,
        bool $forceWithoutLock,
        string $recordType = StockMovement::TYPE_PICK,
        ?int $fromWarehouseId = null,
        ?int $sourceInventoryId = null,
    ): StockMovement {
        $warehouseId = null;
        $fromInv = null;
        $recordFromBinId = ($fromBinId !== null && $fromBinId > 0) ? $fromBinId : null;

        if ($recordFromBinId !== null) {
            $fromBin = Bin::query()->with('shelf.rack.zone')->findOrFail($recordFromBinId);
            $warehouseId = $this->bins->warehouseIdForBin($fromBin);
            if (! $warehouseId) {
                throw new RuntimeException('Could not resolve warehouse for source bin.');
            }
            $fromInv = $this->inventories->findLocked($product->sku, $warehouseId, $recordFromBinId);
            if (! $fromInv) {
                throw new RuntimeException('Source inventory row not found: no `inventories` row for this SKU + from bin (see WMS warehouse list on Scan).');
            }
        } else {
            $fromInv = $this->lockNoBinInventory($product, $fromWarehouseId, $sourceInventoryId);
            $warehouseId = (int) $fromInv->warehouse_id;
        }

        if ($forceWithoutLock || WmsAuthorization::canPickWithoutLock($user)) {
            if ((int) $fromInv->on_hand < $qty) {
                throw new RuntimeException('Insufficient stock at source bin.');
            }
            $fromInv->on_hand = (int) $fromInv->on_hand - $qty;
            $fromInv->pick_locked_qty = max(0, (int) $fromInv->pick_locked_qty - $qty);
        } else {
            if ((int) $fromInv->pick_locked_qty < $qty) {
                throw new RuntimeException('Pick requires locked stock first (or manager override).');
            }
            if ((int) $fromInv->on_hand < $qty) {
                throw new RuntimeException('Insufficient stock at source bin.');
            }
            $fromInv->on_hand = (int) $fromInv->on_hand - $qty;
            $fromInv->pick_locked_qty = (int) $fromInv->pick_locked_qty - $qty;
        }

        $this->syncAvailableQty($fromInv);
        $fromInv->save();
        if ((int) $fromInv->on_hand < 0) {
            throw new RuntimeException('Stock would become negative.');
        }

        $destInvId = (int) $fromInv->id;
        if ($toBinId) {
            $toBin = Bin::query()->with('shelf.rack.zone')->findOrFail($toBinId);
            $whTo = $this->bins->warehouseIdForBin($toBin);
            if ($whTo !== $warehouseId) {
                throw new RuntimeException('Pick destination bin must belong to the same warehouse.');
            }
            $toInv = $this->inventories->firstOrNew($product->sku, $warehouseId, $toBinId);
            $toInv->on_hand = (int) ($toInv->on_hand ?? 0) + $qty;
            $this->syncAvailableQty($toInv);
            $toInv->save();
            $destInvId = (int) $toInv->id;
        }

        $movement = $this->recordMovement($product, $recordFromBinId, $toBinId, $qty, $recordType, $user, $destInvId, $note);
        $this->audit->log(
            $user,
            $recordType === StockMovement::TYPE_DISPATCH ? 'stock.dispatch' : 'stock.pick',
            StockMovement::class,
            (int) $movement->id,
            ['qty' => $qty]
        );

        return $movement;
    }

    private function handlePack(ProductMaster $product, int $qty, ?int $fromBinId, ?int $toBinId, User $user, ?string $note): StockMovement
    {
        if (! $fromBinId || ! $toBinId) {
            throw new InvalidArgumentException('Pack requires valid from_bin_id and to_bin_id.');
        }

        $fromBin = Bin::query()->with('shelf.rack.zone')->findOrFail($fromBinId);
        $warehouseId = $this->bins->warehouseIdForBin($fromBin);
        if (! $warehouseId) {
            throw new RuntimeException('Could not resolve warehouse for source bin.');
        }

        $fromInv = $this->inventories->findLocked($product->sku, $warehouseId, $fromBinId);
        if (! $fromInv) {
            throw new RuntimeException('Source inventory row not found: no `inventories` row for this SKU + from bin (see WMS warehouse list on Scan).');
        }
        $available = (int) $fromInv->on_hand - (int) $fromInv->pick_locked_qty;
        if ($available < $qty) {
            throw new RuntimeException('Insufficient available quantity at source.');
        }

        $fromInv->on_hand = (int) $fromInv->on_hand - $qty;
        $this->syncAvailableQty($fromInv);
        $fromInv->save();

        $toBin = Bin::query()->with('shelf.rack.zone')->findOrFail($toBinId);
        $whTo = $this->bins->warehouseIdForBin($toBin);
        if ($whTo !== $warehouseId) {
            throw new RuntimeException('Pack destination must be in the same warehouse.');
        }

        $toInv = $this->inventories->firstOrNew($product->sku, $warehouseId, $toBinId);
        $toInv->on_hand = (int) ($toInv->on_hand ?? 0) + $qty;
        $this->syncAvailableQty($toInv);
        $toInv->save();

        $movement = $this->recordMovement($product, $fromBinId, $toBinId, $qty, StockMovement::TYPE_PACK, $user, (int) $toInv->id, $note);
        $this->audit->log($user, 'stock.pack', StockMovement::class, (int) $movement->id, ['qty' => $qty]);

        return $movement;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleAdjustment(ProductMaster $product, array $payload, User $user): StockMovement
    {
        $signedQty = (int) $payload['qty'];
        if ($signedQty === 0) {
            throw new InvalidArgumentException('Adjustment quantity cannot be zero.');
        }

        $binId = $this->positiveBinId($payload, 'to_bin_id') ?? $this->positiveBinId($payload, 'from_bin_id');
        if (! $binId) {
            throw new InvalidArgumentException('Adjustment requires a bin (from_bin_id or to_bin_id).');
        }

        $bin = Bin::query()->with('shelf.rack.zone')->findOrFail($binId);
        $warehouseId = $this->bins->warehouseIdForBin($bin);
        if (! $warehouseId) {
            throw new RuntimeException('Could not resolve warehouse for bin.');
        }

        $inv = $this->inventories->firstOrNew($product->sku, $warehouseId, $binId);
        if (! $inv->exists && $signedQty < 0) {
            throw new RuntimeException('Cannot decrease stock on a non-existent inventory row.');
        }
        if (! $inv->exists) {
            $inv->on_hand = 0;
            $inv->pick_locked_qty = 0;
        }

        $inv->on_hand = (int) $inv->on_hand + $signedQty;
        if ((int) $inv->on_hand < 0) {
            throw new RuntimeException('Adjustment would result in negative stock.');
        }
        $this->syncAvailableQty($inv);
        $inv->save();

        $from = $signedQty < 0 ? $binId : null;
        $to = $signedQty > 0 ? $binId : null;
        $movement = $this->recordMovement(
            $product,
            $from,
            $to,
            abs($signedQty),
            StockMovement::TYPE_ADJUSTMENT,
            $user,
            (int) $inv->id,
            isset($payload['note']) ? (string) $payload['note'] : null
        );

        $this->audit->log($user, 'stock.adjustment', StockMovement::class, (int) $movement->id, [
            'signed_qty' => $signedQty,
            'bin_id' => $binId,
        ]);

        return $movement;
    }

    private function recordMovement(
        ProductMaster $product,
        ?int $fromBinId,
        ?int $toBinId,
        int $qty,
        string $type,
        User $user,
        int $inventoryId,
        ?string $note
    ): StockMovement {
        return StockMovement::create([
            'product_id' => $product->id,
            'sku' => $product->sku,
            'from_bin_id' => $fromBinId,
            'to_bin_id' => $toBinId,
            'qty' => $qty,
            'type' => $type,
            'user_id' => $user->id,
            'inventory_id' => $inventoryId,
            'note' => $note,
        ]);
    }
}
