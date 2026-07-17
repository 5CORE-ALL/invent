<?php

namespace App\Services\MarketplaceManager;

use App\Models\Inventory;
use App\Models\MmInventoryLedger;
use App\Models\ShopifyCatalogVariant;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Operational inventory mirror for Marketplace Manager pushes.
 */
class InventoryLedgerService
{
    public function store(): string
    {
        return (string) config('marketplace_manager.default_store', 'main');
    }

    /**
     * @return list<string>
     */
    public function skusForInventoryItemId(string $inventoryItemId): array
    {
        $inventoryItemId = preg_replace('/\D+/', '', $inventoryItemId) ?: '';
        if ($inventoryItemId === '') {
            return [];
        }

        $skus = MmInventoryLedger::query()
            ->where('store', $this->store())
            ->where('shopify_inventory_item_id', $inventoryItemId)
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->pluck('sku')
            ->all();

        return array_values(array_unique(array_filter(array_map(
            static fn ($s) => trim((string) $s),
            $skus
        ))));
    }

    /**
     * Apply inventory_levels webhook qty onto the ledger.
     *
     * @return array{skus: list<string>, available: int}|null
     */
    public function applyWebhook(
        string $inventoryItemId,
        ?int $available,
        ?string $locationId = null,
        array $knownSkus = [],
    ): ?array {
        $inventoryItemId = preg_replace('/\D+/', '', $inventoryItemId) ?: '';
        if ($inventoryItemId === '') {
            return null;
        }

        $rawQty = (int) ($available ?? 0);
        $qty = max(0, $rawQty);
        $skus = $knownSkus !== []
            ? array_values(array_unique(array_filter(array_map(
                static fn ($s) => trim((string) $s),
                $knownSkus
            ))))
            : $this->skusForInventoryItemId($inventoryItemId);

        if ($skus === []) {
            return null;
        }

        $store = $this->store();
        $now = now();

        foreach ($skus as $sku) {
            $row = MmInventoryLedger::query()
                ->where('store', $store)
                ->where(function ($q) use ($sku, $inventoryItemId) {
                    $q->where('sku', $sku)
                        ->orWhere('shopify_inventory_item_id', $inventoryItemId);
                })
                ->first();

            if ($row) {
                $row->sku = $sku;
                $row->shopify_inventory_item_id = $inventoryItemId;
                if ($locationId !== null && $locationId !== '') {
                    $row->location_id = $locationId;
                }
                $row->on_hand = $qty;
                $row->available = $qty;
                $row->version = (int) $row->version + 1;
                $row->source = 'webhook';
                $row->synced_at = $now;
                $row->save();
            } else {
                MmInventoryLedger::query()->create([
                    'store' => $store,
                    'sku' => $sku,
                    'shopify_inventory_item_id' => $inventoryItemId,
                    'location_id' => $locationId,
                    'on_hand' => $qty,
                    'available' => $qty,
                    'version' => 1,
                    'source' => 'webhook',
                    'synced_at' => $now,
                ]);
            }

            // Catalog UI + shopify_skus: use Shopify's raw available (may be negative).
            $this->writeThroughLocalMirrors($sku, $rawQty);
        }

        return ['skus' => $skus, 'available' => $qty];
    }

    /**
     * Upsert mapping + optional qty (resolve job / live fallback).
     */
    public function upsertMapping(
        string $sku,
        ?string $inventoryItemId = null,
        ?string $variantId = null,
        ?int $available = null,
        string $source = 'resolve',
        ?string $locationId = null,
    ): MmInventoryLedger {
        $sku = trim($sku);
        $store = $this->store();
        $inventoryItemId = $inventoryItemId !== null
            ? (preg_replace('/\D+/', '', $inventoryItemId) ?: null)
            : null;
        $variantId = $variantId !== null
            ? (preg_replace('/\D+/', '', $variantId) ?: null)
            : null;

        $row = MmInventoryLedger::query()
            ->where('store', $store)
            ->where('sku', $sku)
            ->first();

        if (! $row && $inventoryItemId) {
            $row = MmInventoryLedger::query()
                ->where('store', $store)
                ->where('shopify_inventory_item_id', $inventoryItemId)
                ->first();
        }

        if (! $row) {
            $row = new MmInventoryLedger([
                'store' => $store,
                'sku' => $sku,
                'version' => 0,
            ]);
        }

        $row->sku = $sku;
        if ($inventoryItemId) {
            $row->shopify_inventory_item_id = $inventoryItemId;
        }
        if ($variantId) {
            $row->shopify_variant_id = $variantId;
        }
        if ($locationId !== null && $locationId !== '') {
            $row->location_id = $locationId;
        }
        if ($available !== null) {
            $qty = max(0, $available);
            $row->on_hand = $qty;
            $row->available = $qty;
            $row->version = (int) $row->version + 1;
            $this->writeThroughLocalMirrors($sku, (int) $available);
        }
        $row->source = $source;
        $row->synced_at = now();
        $row->save();

        return $row;
    }

    /**
     * @param  array<int, string>  $skus
     * @return array<string, int> original sku casing => available
     */
    public function qtyBySkus(array $skus): array
    {
        $skus = array_values(array_unique(array_filter(array_map(
            static fn ($s) => trim((string) $s),
            $skus
        ))));
        if ($skus === []) {
            return [];
        }

        $store = $this->store();
        $upperMap = [];
        foreach ($skus as $sku) {
            $upperMap[strtoupper($sku)] = $sku;
        }

        $rows = MmInventoryLedger::query()
            ->where('store', $store)
            ->where(function ($q) use ($skus, $upperMap) {
                $q->whereIn('sku', $skus);
                foreach (array_keys($upperMap) as $upper) {
                    $q->orWhereRaw('UPPER(sku) = ?', [$upper]);
                }
            })
            ->get(['sku', 'available']);

        $out = [];
        foreach ($rows as $row) {
            $key = strtoupper(trim((string) $row->sku));
            $orig = $upperMap[$key] ?? (string) $row->sku;
            $qty = max(0, (int) $row->available);
            $out[$orig] = $qty;
            $out[(string) $row->sku] = $qty;
        }

        return $out;
    }

    /**
     * Seed ledger from catalog + WMS inventory_item_id map.
     *
     * @return array{upserted: int, with_item_id: int}
     */
    public function bootstrapFromCatalog(): array
    {
        $store = $this->store();
        $upserted = 0;
        $withItemId = 0;

        $itemIdBySku = [];
        if (Schema::hasTable('inventories') && Schema::hasColumn('inventories', 'shopify_inventory_item_id')) {
            Inventory::query()
                ->whereNotNull('shopify_inventory_item_id')
                ->where('shopify_inventory_item_id', '!=', '')
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->orderBy('id')
                ->select(['id', 'sku', 'shopify_inventory_item_id'])
                ->chunkById(500, function ($chunk) use (&$itemIdBySku) {
                    foreach ($chunk as $inv) {
                        $sku = trim((string) $inv->sku);
                        $itemId = preg_replace('/\D+/', '', (string) $inv->shopify_inventory_item_id) ?: '';
                        if ($sku === '' || $itemId === '') {
                            continue;
                        }
                        $itemIdBySku[strtoupper($sku)] = $itemId;
                    }
                });
        }

        if (! Schema::hasTable('shopify_catalog_variants')) {
            Log::warning('InventoryLedgerService: shopify_catalog_variants missing — bootstrap skipped');

            return ['upserted' => 0, 'with_item_id' => 0];
        }

        ShopifyCatalogVariant::query()
            ->where('store', $store)
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('id')
            ->chunkById(500, function ($chunk) use ($itemIdBySku, &$upserted, &$withItemId) {
                foreach ($chunk as $variant) {
                    $sku = trim((string) $variant->sku);
                    if ($sku === '') {
                        continue;
                    }
                    $qty = max(0, (int) ($variant->inventory_quantity ?? 0));
                    $itemId = $itemIdBySku[strtoupper($sku)] ?? null;
                    if ($itemId) {
                        $withItemId++;
                    }

                    $this->upsertMapping(
                        $sku,
                        $itemId,
                        $variant->shopify_variant_id ? (string) $variant->shopify_variant_id : null,
                        $qty,
                        'bootstrap',
                    );
                    $upserted++;
                }
            });

        return ['upserted' => $upserted, 'with_item_id' => $withItemId];
    }

    /**
     * Keep MM UI sources in sync when inventory changes via webhook/resolve.
     * Updates shopify_skus + shopify_catalog_variants (the SKUs list page).
     */
    protected function writeThroughLocalMirrors(string $sku, int $qty): void
    {
        try {
            $row = ShopifySku::firstForProductSku($sku);
            if ($row) {
                $row->available_to_sell = $qty;
                $row->inv = $qty;
                $row->save();
            }
        } catch (\Throwable $e) {
            // non-fatal
        }

        try {
            if (! Schema::hasTable('shopify_catalog_variants')) {
                return;
            }
            ShopifyCatalogVariant::query()
                ->where('store', $this->store())
                ->where(function ($q) use ($sku) {
                    $q->where('sku', $sku)
                        ->orWhereRaw('UPPER(sku) = ?', [strtoupper($sku)]);
                })
                ->update([
                    'inventory_quantity' => $qty,
                    'synced_at' => now(),
                    'updated_at' => now(),
                ]);
        } catch (\Throwable $e) {
            Log::warning('InventoryLedgerService: catalog write-through failed', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** @deprecated */
    protected function writeThroughShopifySku(string $sku, int $qty): void
    {
        $this->writeThroughLocalMirrors($sku, $qty);
    }
}
