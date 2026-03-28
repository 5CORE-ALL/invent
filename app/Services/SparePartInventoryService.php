<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\ProductMaster;
use App\Models\Wms\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SparePartInventoryService
{
    /** @var array<string, bool> */
    private static array $inventoryColumnCache = [];

    public function totalAvailableForSku(string $sku): int
    {
        return (int) Inventory::query()
            ->whereRaw('LOWER(sku) = ?', [strtolower($sku)])
            ->get()
            ->sum(fn (Inventory $row) => $this->rowAvailable($row));
    }

    public function rowAvailable(Inventory $row): int
    {
        foreach (['available_qty', 'on_hand', 'verified_stock'] as $field) {
            if (!$this->hasInventoryColumn($field)) {
                continue;
            }
            $v = $row->{$field};
            if ($v !== null && (int) $v > 0) {
                return (int) $v;
            }
        }

        return 0;
    }

    /**
     * @return array{0: bool, 1: string|null}
     */
    public function assertSufficientStock(ProductMaster $part, int $qty): array
    {
        if ($qty <= 0) {
            return [false, 'Quantity must be positive.'];
        }
        $sku = $part->sku;
        if ($sku === null || $sku === '') {
            return [false, 'Part has no SKU linked to inventory.'];
        }
        $avail = $this->totalAvailableForSku($sku);
        if ($avail < $qty) {
            return [false, "Insufficient stock (available: {$avail}, requested: {$qty})."];
        }

        return [true, null];
    }

    public function applyIssue(
        ProductMaster $part,
        int $quantity,
        ?string $referenceType,
        ?int $referenceId,
        ?int $userId,
        ?string $note = null
    ): void {
        DB::transaction(function () use ($part, $quantity, $referenceType, $referenceId, $userId, $note) {
            $sku = $part->sku;
            $remaining = $quantity;
            $firstInventoryId = null;

            $rows = Inventory::query()
                ->whereRaw('LOWER(sku) = ?', [strtolower((string) $sku)])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($rows as $inv) {
                if ($remaining <= 0) {
                    break;
                }
                $avail = $this->rowAvailable($inv);
                if ($avail <= 0) {
                    continue;
                }
                $take = min($avail, $remaining);
                if ($firstInventoryId === null) {
                    $firstInventoryId = $inv->id;
                }
                $this->decrementInventoryRow($inv, $take);
                $remaining -= $take;
            }

            if ($remaining > 0) {
                throw new \RuntimeException('Could not deduct full quantity from inventory rows.');
            }

            StockMovement::query()->create([
                'product_id' => $part->id,
                'sku' => (string) $sku,
                'from_bin_id' => null,
                'to_bin_id' => null,
                'qty' => $quantity,
                'type' => StockMovement::TYPE_ISSUE,
                'user_id' => $userId,
                'inventory_id' => $firstInventoryId,
                'note' => $note,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);
        });
    }

    public function applyPurchaseReceipt(
        ProductMaster $part,
        int $quantity,
        ?string $referenceType,
        ?int $referenceId,
        ?int $userId,
        ?string $note = null
    ): void {
        DB::transaction(function () use ($part, $quantity, $referenceType, $referenceId, $userId, $note) {
            $sku = $part->sku;
            $row = Inventory::query()
                ->whereRaw('LOWER(sku) = ?', [strtolower((string) $sku)])
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (!$row) {
                $row = new Inventory;
                $row->sku = $sku;
                if ($this->hasInventoryColumn('available_qty')) {
                    $row->available_qty = 0;
                }
                if ($this->hasInventoryColumn('on_hand')) {
                    $row->on_hand = 0;
                }
                $row->is_approved = true;
            }

            $this->incrementInventoryRow($row, $quantity);
            $row->save();

            StockMovement::query()->create([
                'product_id' => $part->id,
                'sku' => (string) $sku,
                'from_bin_id' => null,
                'to_bin_id' => null,
                'qty' => $quantity,
                'type' => StockMovement::TYPE_PURCHASE,
                'user_id' => $userId,
                'inventory_id' => $row->id,
                'note' => $note,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);
        });
    }

    public function applyManualAdjustment(
        ProductMaster $part,
        int $signedQuantity,
        ?string $referenceType,
        ?int $referenceId,
        ?int $userId,
        ?string $note = null
    ): void {
        DB::transaction(function () use ($part, $signedQuantity, $referenceType, $referenceId, $userId, $note) {
            $sku = $part->sku;
            $qty = abs($signedQuantity);
            $firstInventoryId = null;

            if ($signedQuantity < 0) {
                $ok = $this->assertSufficientStock($part, $qty);
                if (!$ok[0]) {
                    throw new \RuntimeException($ok[1]);
                }
                $remaining = $qty;
                $rows = Inventory::query()
                    ->whereRaw('LOWER(sku) = ?', [strtolower((string) $sku)])
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($rows as $inv) {
                    if ($remaining <= 0) {
                        break;
                    }
                    $avail = $this->rowAvailable($inv);
                    if ($avail <= 0) {
                        continue;
                    }
                    $take = min($avail, $remaining);
                    if ($firstInventoryId === null) {
                        $firstInventoryId = $inv->id;
                    }
                    $this->decrementInventoryRow($inv, $take);
                    $remaining -= $take;
                }

                if ($remaining > 0) {
                    throw new \RuntimeException('Could not deduct full quantity from inventory rows.');
                }
            } else {
                $row = Inventory::query()
                    ->whereRaw('LOWER(sku) = ?', [strtolower((string) $sku)])
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if (!$row) {
                    $row = new Inventory;
                    $row->sku = $sku;
                    if ($this->hasInventoryColumn('available_qty')) {
                        $row->available_qty = 0;
                    }
                    if ($this->hasInventoryColumn('on_hand')) {
                        $row->on_hand = 0;
                    }
                    $row->is_approved = true;
                }

                $this->incrementInventoryRow($row, $qty);
                $row->save();
                $firstInventoryId = $row->id;
            }

            StockMovement::query()->create([
                'product_id' => $part->id,
                'sku' => (string) $sku,
                'from_bin_id' => null,
                'to_bin_id' => null,
                'qty' => $qty,
                'type' => StockMovement::TYPE_SPARE_ADJUSTMENT,
                'user_id' => $userId,
                'inventory_id' => $firstInventoryId,
                'note' => $note ?? ($signedQuantity < 0 ? 'Spare parts adjustment (decrease)' : 'Spare parts adjustment (increase)'),
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);
        });
    }

    private function decrementInventoryRow(Inventory $inv, int $take): void
    {
        if ($this->hasInventoryColumn('available_qty') && $inv->available_qty !== null && (int) $inv->available_qty > 0) {
            $inv->available_qty = max(0, (int) $inv->available_qty - $take);
        } elseif ($this->hasInventoryColumn('on_hand') && $inv->on_hand !== null && (int) $inv->on_hand > 0) {
            $inv->on_hand = max(0, (int) $inv->on_hand - $take);
        } elseif ($this->hasInventoryColumn('verified_stock') && $inv->verified_stock !== null && (int) $inv->verified_stock > 0) {
            $inv->verified_stock = max(0, (int) $inv->verified_stock - $take);
        }
        $inv->save();
    }

    private function incrementInventoryRow(Inventory $inv, int $add): void
    {
        if ($this->hasInventoryColumn('available_qty')) {
            if ($inv->available_qty !== null) {
                $inv->available_qty = (int) $inv->available_qty + $add;
            } else {
                $inv->available_qty = $add;
            }
        }

        if ($this->hasInventoryColumn('on_hand') && $inv->on_hand !== null) {
            $inv->on_hand = (int) $inv->on_hand + $add;
        } elseif ($this->hasInventoryColumn('on_hand')) {
            $inv->on_hand = $add;
        }
    }

    private function hasInventoryColumn(string $column): bool
    {
        if (!array_key_exists($column, self::$inventoryColumnCache)) {
            self::$inventoryColumnCache[$column] = Schema::hasColumn('inventories', $column);
        }

        return self::$inventoryColumnCache[$column];
    }
}
