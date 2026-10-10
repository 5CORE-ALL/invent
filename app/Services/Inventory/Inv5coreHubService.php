<?php

namespace App\Services\Inventory;

use App\Models\Inv5coreBalance;
use App\Models\Inv5coreTransaction;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Support\CpMasterDil;
use App\Support\Inv5coreLedger;
use App\Support\Inv5coreMarketplaceOrders;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Inv5coreHubService
{
    private const L30_CACHE_KEY = 'inv5core.l30.v1';

    /**
     * @return array{rows: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function rows(): array
    {
        $l30 = $this->l30ByCompact();
        $shopifyBySku = $this->shopifyByDisplaySku();
        $balances = $this->balancesBySku();

        $built = [];
        $products = ProductMaster::query()
            ->orderBy('parent')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku')
            ->get(['id', 'parent', 'sku', 'Values']);

        foreach ($products as $product) {
            $sku = trim(str_replace("\u{00a0}", ' ', (string) $product->sku));
            if ($sku === '') {
                continue;
            }
            $values = is_array($product->Values) ? $product->Values : [];
            $shopify = $shopifyBySku[$sku] ?? null;
            $localImage = isset($values['image_path']) ? (string) $values['image_path'] : null;
            $isParent = Inv5coreLedger::isParentSku($sku);
            $row = [
                'id' => (int) $product->id,
                'image' => Inv5coreLedger::imagePath($localImage !== '' ? $localImage : null, $shopify->image_src ?? null),
                'parent' => (string) ($product->parent ?? ''),
                'sku' => $sku,
                'is_parent' => $isParent,
                'inv' => $shopify && $shopify->inv !== null ? (float) $shopify->inv : 0.0,
                'l30' => $shopify && $shopify->quantity !== null ? (float) $shopify->quantity : 0.0,
                'dil' => null,
                'inv_app' => null,
                'l30_app' => $isParent ? 0.0 : (float) ($l30[ShopifySku::compactSkuForLookup($sku)] ?? 0),
                'seeded' => false,
            ];
            if (! $isParent) {
                $balance = $balances[$sku] ?? null;
                if ($balance && $balance->shopify_locked) {
                    $row['inv_app'] = (float) $balance->qty_on_hand;
                    $row['seeded'] = true;
                }
            }
            $row['dil'] = CpMasterDil::percent($row['l30'], $row['inv']);
            $built[] = $row;
        }

        $totals = [];
        foreach ($built as $row) {
            if ($row['is_parent'] || $row['parent'] === '') {
                continue;
            }
            $parent = $row['parent'];
            if (! isset($totals[$parent])) {
                $totals[$parent] = ['inv' => 0.0, 'l30' => 0.0, 'inv_app' => 0.0, 'l30_app' => 0.0, 'seeded' => 0];
            }
            $totals[$parent]['inv'] += (float) $row['inv'];
            $totals[$parent]['l30'] += (float) $row['l30'];
            $totals[$parent]['l30_app'] += (float) $row['l30_app'];
            if ($row['seeded']) {
                $totals[$parent]['inv_app'] += (float) $row['inv_app'];
                $totals[$parent]['seeded']++;
            }
        }

        $seeded = 0;
        $childCount = 0;
        foreach ($built as &$row) {
            if ($row['is_parent']) {
                $sum = $totals[$row['parent']] ?? null;
                if ($sum !== null) {
                    $row['inv'] = Inv5coreLedger::roundQty($sum['inv']);
                    $row['l30'] = Inv5coreLedger::roundQty($sum['l30']);
                    $row['l30_app'] = Inv5coreLedger::roundQty($sum['l30_app']);
                    $row['inv_app'] = $sum['seeded'] > 0 ? Inv5coreLedger::roundQty($sum['inv_app']) : null;
                    $row['seeded'] = $sum['seeded'] > 0;
                    $row['dil'] = CpMasterDil::percent($row['l30'], $row['inv']);
                }
                continue;
            }
            $childCount++;
            if ($row['seeded']) {
                $seeded++;
            }
        }
        unset($row);

        return [
            'rows' => $built,
            'meta' => [
                'child_count' => $childCount,
                'seeded_count' => $seeded,
                'unseeded_count' => $childCount - $seeded,
                'l30_through' => Cache::get(self::L30_CACHE_KEY.'.at'),
            ],
        ];
    }

    /**
     * Copy Shopify on-hand into INV APP once per SKU. Locked SKUs are left alone.
     *
     * @return array{seeded: int, skipped: int}
     */
    public function seedOpening(?int $userId): array
    {
        $orderWatermark = $this->maxId('shopify_raw_orders');
        $manualWatermark = $this->maxId('order_fulfillment_manual_orders');
        $shopifyBySku = $this->shopifyByDisplaySku();
        $now = Carbon::now();
        $seeded = 0;
        $skipped = 0;
        $seen = [];

        ProductMaster::query()
            ->orderBy('id')
            ->select(['id', 'sku'])
            ->chunkById(400, function ($products) use (&$seen, &$seeded, &$skipped, $shopifyBySku, $orderWatermark, $manualWatermark, $now, $userId) {
                foreach ($products as $product) {
                    $sku = trim(str_replace("\u{00a0}", ' ', (string) $product->sku));
                    if ($sku === '' || Inv5coreLedger::isParentSku($sku) || isset($seen[$sku])) {
                        continue;
                    }
                    $seen[$sku] = true;
                    $shopify = $shopifyBySku[$sku] ?? null;
                    $opening = Inv5coreLedger::roundQty($shopify && $shopify->inv !== null ? (float) $shopify->inv : 0.0);

                    $result = DB::transaction(function () use ($product, $sku, $opening, $orderWatermark, $manualWatermark, $now, $userId) {
                        $balance = Inv5coreBalance::query()->where('sku', $sku)->lockForUpdate()->first();
                        if ($balance && $balance->shopify_locked) {
                            return 'skipped';
                        }
                        if (! $balance) {
                            $balance = new Inv5coreBalance(['sku' => $sku]);
                        }
                        $balance->product_master_id = (int) $product->id;
                        $balance->sku_compact = ShopifySku::compactSkuForLookup($sku);
                        $balance->opening_qty = $opening;
                        $balance->qty_on_hand = $opening;
                        $balance->opening_seeded_at = $now;
                        $balance->sales_after_order_id = $orderWatermark;
                        $balance->sales_after_manual_id = $manualWatermark;
                        $balance->shopify_locked = true;
                        $balance->save();

                        Inv5coreTransaction::query()->create([
                            'balance_id' => $balance->id,
                            'product_master_id' => (int) $product->id,
                            'sku' => $sku,
                            'txn_type' => 'opening',
                            'qty_delta' => $opening,
                            'qty_before' => 0,
                            'qty_after' => $opening,
                            'source' => 'shopify_opening',
                            'source_id' => (int) $product->id,
                            'reference' => null,
                            'channel' => 'Shopify',
                            'detail' => 'Opening inventory copied once from Shopify INV. This SKU is not imported from Shopify again.',
                            'occurred_at' => $now,
                            'created_by' => $userId,
                        ]);

                        return 'seeded';
                    });

                    if ($result === 'seeded') {
                        $seeded++;
                    } else {
                        $skipped++;
                    }
                }
            });

        Inv5coreMarketplaceOrders::snapshotWatermarks();
        $this->forgetL30();

        return ['seeded' => $seeded, 'skipped' => $skipped];
    }

    /**
     * Deduct marketplace orders recorded after the opening, and write each
     * line into the SKU history. Cancelled lines are reversed.
     *
     * @return array{posted: int, reversed: int}
     */
    public function recordAppSales(?int $userId): array
    {
        $posted = 0;
        $reversed = 0;
        $balances = $this->lockedBalancesByCompact();
        if ($balances === []) {
            return ['posted' => 0, 'reversed' => 0];
        }

        $minManualId = null;
        foreach ($balances as $balance) {
            if ($balance->sales_after_manual_id !== null) {
                $minManualId = $minManualId === null ? (int) $balance->sales_after_manual_id : min($minManualId, (int) $balance->sales_after_manual_id);
            }
        }

        Inv5coreMarketplaceOrders::snapshotWatermarks();
        $posted += $this->postMarketplaceOrderLines($balances, $userId);
        $reversed += $this->reverseMarketplaceOrderLines($userId);
        if (Schema::hasTable('order_fulfillment_manual_orders') && $minManualId !== null) {
            $posted += $this->postManualOrderLines($balances, $minManualId, $userId);
            $reversed += $this->reverseManualOrderLines($userId);
        }

        $this->forgetL30();

        return ['posted' => $posted, 'reversed' => $reversed];
    }

    /**
     * @param  array<string, Inv5coreBalance>  $balances
     */
    private function postMarketplaceOrderLines(array $balances, ?int $userId): int
    {
        $posted = 0;
        Inv5coreMarketplaceOrders::eachNewLine(function ($line, array $def) use ($balances, $userId, &$posted) {
            if (Inv5coreLedger::statusSkipsSale($line->status ?? null)) {
                return;
            }
            $qty = (float) ($line->qty ?? 0);
            if ($qty <= 0) {
                return;
            }
            $balance = $balances[ShopifySku::compactSkuForLookup((string) ($line->sku ?? ''))] ?? null;
            if (! $balance) {
                return;
            }
            $orderNo = trim((string) ($line->order_number ?? ''));
            $detail = $def['label'].' order '.($orderNo !== '' ? $orderNo : $line->id).' · Qty '.$qty;
            if ($this->postMovement(
                $balance,
                'sale',
                Inv5coreMarketplaceOrders::sourceKey($def['source']),
                (int) $line->id,
                Inv5coreLedger::deltaForSubtract($qty),
                $orderNo,
                $def['label'],
                $detail,
                ! empty($line->order_date) ? Carbon::parse($line->order_date) : Carbon::now(),
                $userId
            )) {
                $posted++;
            }
        });

        return $posted;
    }

    private function reverseMarketplaceOrderLines(?int $userId): int
    {
        $reversed = 0;
        Inv5coreMarketplaceOrders::eachReversal(function ($line, array $def) use ($userId, &$reversed) {
            $balance = Inv5coreBalance::query()->find($line->balance_id);
            if (! $balance) {
                return;
            }
            $orderNo = trim((string) ($line->order_number ?? ''));
            $detail = 'Reversal · '.$def['label'].' order '.($orderNo !== '' ? $orderNo : $line->line_id).' is now '.$line->status;
            if ($this->postMovement(
                $balance,
                'return',
                Inv5coreMarketplaceOrders::reversalKey($def['source']),
                (int) $line->line_id,
                Inv5coreLedger::deltaForAdd(abs((float) $line->qty_delta)),
                $orderNo,
                $def['label'],
                $detail,
                Carbon::now(),
                $userId
            )) {
                $reversed++;
            }
        });

        return $reversed;
    }

    /**
     * @return array{inv_app: float, qty_delta: float}
     */
    public function adjust(string $sku, string $txnType, string $mode, float $qty, string $detail, ?int $userId): array
    {
        $sku = trim($sku);
        if ($sku === '' || Inv5coreLedger::isParentSku($sku)) {
            throw new \InvalidArgumentException('Choose a child SKU.');
        }
        $detail = trim($detail);
        if ($detail === '') {
            throw new \InvalidArgumentException('Enter a reason for this adjustment.');
        }
        if (! in_array($txnType, ['adjustment', 'incoming', 'return', 'write_off'], true)) {
            throw new \InvalidArgumentException('Unknown adjustment type.');
        }
        if (! in_array($mode, ['set', 'add', 'subtract'], true)) {
            throw new \InvalidArgumentException('Unknown adjustment mode.');
        }

        return DB::transaction(function () use ($sku, $txnType, $mode, $qty, $detail, $userId) {
            $balance = Inv5coreBalance::query()->where('sku', $sku)->lockForUpdate()->first();
            if (! $balance || ! $balance->shopify_locked || $balance->qty_on_hand === null) {
                throw new \InvalidArgumentException('Seed opening inventory for this SKU before adjusting it.');
            }

            $before = (float) $balance->qty_on_hand;
            $delta = match ($mode) {
                'set' => Inv5coreLedger::deltaForSet($before, $qty),
                'add' => Inv5coreLedger::deltaForAdd($qty),
                'subtract' => Inv5coreLedger::deltaForSubtract($qty),
            };
            $after = Inv5coreLedger::applyDelta($before, $delta);
            $balance->qty_on_hand = $after;
            $balance->save();

            Inv5coreTransaction::query()->create([
                'balance_id' => $balance->id,
                'product_master_id' => $balance->product_master_id,
                'sku' => $balance->sku,
                'txn_type' => $txnType,
                'qty_delta' => $delta,
                'qty_before' => $before,
                'qty_after' => $after,
                'source' => 'adjustment',
                'source_id' => null,
                'reference' => null,
                'channel' => 'App',
                'detail' => $detail,
                'occurred_at' => Carbon::now(),
                'created_by' => $userId,
            ]);

            return ['inv_app' => $after, 'qty_delta' => $delta];
        });
    }

    /**
     * @return array{balance: ?Inv5coreBalance, rows: list<array<string, mixed>>}
     */
    public function history(string $sku): array
    {
        $sku = trim($sku);
        $balance = Inv5coreBalance::query()->where('sku', $sku)->first();
        if (! $balance) {
            return ['balance' => null, 'rows' => []];
        }

        $query = Inv5coreTransaction::query()
            ->where('balance_id', $balance->id)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(500);

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'name')) {
            $query->leftJoin('users', 'users.id', '=', 'inv_5core_transactions.created_by')
                ->select('inv_5core_transactions.*', 'users.name as user_name');
        }

        $rows = $query->get()->map(function ($row) {
            return [
                'id' => (int) $row->id,
                'occurred_at' => optional($row->occurred_at)->format('Y-m-d H:i') ?: optional($row->created_at)->format('Y-m-d H:i'),
                'txn_type' => (string) $row->txn_type,
                'qty_delta' => (float) $row->qty_delta,
                'qty_before' => (float) $row->qty_before,
                'qty_after' => (float) $row->qty_after,
                'reference' => (string) ($row->reference ?? ''),
                'channel' => (string) ($row->channel ?? ''),
                'detail' => (string) ($row->detail ?? ''),
                'user_name' => (string) ($row->user_name ?? ''),
            ];
        })->all();

        return ['balance' => $balance, 'rows' => $rows];
    }

    /**
     * @return array<string, float> compact SKU => units sold in the last 30 days
     */
    public function l30ByCompact(bool $force = false): array
    {
        if ($force) {
            $this->forgetL30();
        }

        $cached = Cache::get(self::L30_CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $map = $this->queryL30();
        $this->persistL30($map);
        Cache::put(self::L30_CACHE_KEY, $map, 600);
        Cache::put(self::L30_CACHE_KEY.'.at', Carbon::now()->format('Y-m-d H:i'), 600);

        return $map;
    }

    public function forgetL30(): void
    {
        Cache::forget(self::L30_CACHE_KEY);
        Cache::forget(self::L30_CACHE_KEY.'.at');
    }

    /**
     * @return array<string, object> display SKU (NBSP normalized) => shopify row
     */
    private function shopifyByDisplaySku(): array
    {
        if (! Schema::hasTable('shopify_skus')) {
            return [];
        }

        $map = [];
        foreach (ShopifySku::query()->orderBy('id')->get(['id', 'sku', 'inv', 'quantity', 'image_src']) as $row) {
            $key = str_replace("\u{00a0}", ' ', (string) $row->sku);
            if ($key !== '') {
                $map[$key] = $row;
            }
        }

        return $map;
    }

    /**
     * @return array<string, Inv5coreBalance>
     */
    private function balancesBySku(): array
    {
        if (! Schema::hasTable('inv_5core_balances')) {
            return [];
        }

        $map = [];
        foreach (Inv5coreBalance::query()->get() as $balance) {
            $map[(string) $balance->sku] = $balance;
        }

        return $map;
    }

    /**
     * @return array<string, Inv5coreBalance> compact SKU => locked balance
     */
    private function lockedBalancesByCompact(): array
    {
        $map = [];
        foreach (Inv5coreBalance::query()->where('shopify_locked', true)->get() as $balance) {
            $key = (string) ($balance->sku_compact ?: ShopifySku::compactSkuForLookup((string) $balance->sku));
            if ($key !== '') {
                $map[$key] = $balance;
            }
        }

        return $map;
    }

    private function maxId(string $table): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id')) {
            return 0;
        }

        return (int) (DB::table($table)->max('id') ?? 0);
    }

    /**
     * @return array<string, float>
     */
    private function queryL30(): array
    {
        $since = Carbon::now('America/Los_Angeles')->subDays(30)->startOfDay()->toDateString();
        $map = [];

        Inv5coreMarketplaceOrders::addL30($map, $since);

        if (Schema::hasTable('order_fulfillment_manual_orders')) {
            $query = DB::table('order_fulfillment_manual_orders')
                ->select('sku', DB::raw('SUM(qty) as qty'))
                ->where('order_date', '>=', $since)
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->where('qty', '>', 0);
            $this->excludeSkippedStatus($query, 'status');
            foreach ($query->groupBy('sku')->get() as $row) {
                $this->addCompactQty($map, (string) $row->sku, (float) $row->qty);
            }
        }

        return $map;
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private function excludeSkippedStatus($query, string $column): void
    {
        if (! Schema::hasColumn($query->from, $column)) {
            return;
        }
        $skipped = Inv5coreLedger::SKIPPED_STATUSES;
        $query->where(function ($inner) use ($column, $skipped) {
            $inner->whereNull($column)->orWhereRaw(
                'LOWER('.$column.') NOT IN ('.implode(',', array_fill(0, count($skipped), '?')).')',
                $skipped
            );
        });
    }

    /**
     * @param  array<string, float>  $map
     */
    private function addCompactQty(array &$map, string $sku, float $qty): void
    {
        $key = ShopifySku::compactSkuForLookup($sku);
        if ($key === '') {
            return;
        }
        $map[$key] = Inv5coreLedger::roundQty(($map[$key] ?? 0) + $qty);
    }

    /**
     * @param  array<string, float>  $map
     */
    private function persistL30(array $map): void
    {
        if (! Schema::hasTable('inv_5core_balances')) {
            return;
        }
        Inv5coreBalance::query()->orderBy('id')->select(['id', 'sku', 'sku_compact', 'l30_sold'])->chunkById(500, function ($rows) use ($map) {
            foreach ($rows as $balance) {
                $key = (string) ($balance->sku_compact ?: ShopifySku::compactSkuForLookup((string) $balance->sku));
                $sold = Inv5coreLedger::roundQty((float) ($map[$key] ?? 0));
                if ((float) $balance->l30_sold !== $sold) {
                    Inv5coreBalance::query()->where('id', $balance->id)->update(['l30_sold' => $sold]);
                }
            }
        });
    }

    /**
     * @param  array<string, Inv5coreBalance>  $balances
     */
    private function postShopifyOrderLines(array $balances, int $minOrderId, ?int $userId): int
    {
        $posted = 0;
        DB::table('shopify_raw_orders')
            ->where('id', '>', $minOrderId)
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->where('quantity', '>', 0)
            ->orderBy('id')
            ->select(['id', 'sku', 'quantity', 'order_number', 'order_date', 'source_name', 'financial_status', 'product_title'])
            ->chunkById(400, function ($lines) use ($balances, $userId, &$posted) {
                foreach ($lines as $line) {
                    if (Inv5coreLedger::statusSkipsSale($line->financial_status ?? null)) {
                        continue;
                    }
                    $balance = $balances[ShopifySku::compactSkuForLookup((string) $line->sku)] ?? null;
                    if (! $balance || ! Inv5coreLedger::saleIdAffectsBalance((int) $line->id, $balance->sales_after_order_id)) {
                        continue;
                    }
                    $detail = trim(implode(' · ', array_filter([
                        $line->order_number ? 'Order '.$line->order_number : null,
                        $line->source_name ? (string) $line->source_name : null,
                        $line->product_title ? (string) $line->product_title : null,
                        'Qty '.(int) $line->quantity,
                    ])));
                    if ($this->postMovement(
                        $balance,
                        'sale',
                        'shopify_raw_orders',
                        (int) $line->id,
                        Inv5coreLedger::deltaForSubtract((float) $line->quantity),
                        (string) ($line->order_number ?? ''),
                        (string) ($line->source_name ?? 'App'),
                        $detail,
                        $line->order_date ? Carbon::parse($line->order_date) : Carbon::now(),
                        $userId
                    )) {
                        $posted++;
                    }
                }
            });

        return $posted;
    }

    /**
     * @param  array<string, Inv5coreBalance>  $balances
     */
    private function postManualOrderLines(array $balances, int $minManualId, ?int $userId): int
    {
        $posted = 0;
        DB::table('order_fulfillment_manual_orders')
            ->where('id', '>', $minManualId)
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->where('qty', '>', 0)
            ->orderBy('id')
            ->select(['id', 'sku', 'qty', 'order_id', 'order_date', 'marketplace', 'status'])
            ->chunkById(400, function ($lines) use ($balances, $userId, &$posted) {
                foreach ($lines as $line) {
                    if (Inv5coreLedger::statusSkipsSale($line->status ?? null)) {
                        continue;
                    }
                    $balance = $balances[ShopifySku::compactSkuForLookup((string) $line->sku)] ?? null;
                    if (! $balance || ! Inv5coreLedger::saleIdAffectsBalance((int) $line->id, $balance->sales_after_manual_id)) {
                        continue;
                    }
                    $detail = trim(implode(' · ', array_filter([
                        $line->order_id ? 'Order '.$line->order_id : null,
                        $line->marketplace ? (string) $line->marketplace : null,
                        'Qty '.(int) $line->qty,
                        'Manual app order',
                    ])));
                    if ($this->postMovement(
                        $balance,
                        'sale',
                        'manual_order',
                        (int) $line->id,
                        Inv5coreLedger::deltaForSubtract((float) $line->qty),
                        (string) ($line->order_id ?? ''),
                        (string) ($line->marketplace ?? 'App'),
                        $detail,
                        $line->order_date ? Carbon::parse($line->order_date) : Carbon::now(),
                        $userId
                    )) {
                        $posted++;
                    }
                }
            });

        return $posted;
    }

    private function reverseShopifyOrderLines(?int $userId): int
    {
        if (! Schema::hasColumn('shopify_raw_orders', 'financial_status')) {
            return 0;
        }

        $reversed = 0;
        $skipped = Inv5coreLedger::SKIPPED_STATUSES;
        DB::table('inv_5core_transactions as t')
            ->join('shopify_raw_orders as o', function ($join) {
                $join->on('o.id', '=', 't.source_id')->where('t.source', '=', 'shopify_raw_orders');
            })
            ->leftJoin('inv_5core_transactions as rev', function ($join) {
                $join->on('rev.source_id', '=', 'o.id')->where('rev.source', '=', 'shopify_raw_orders_reversal');
            })
            ->whereNull('rev.id')
            ->whereRaw('LOWER(o.financial_status) IN ('.implode(',', array_fill(0, count($skipped), '?')).')', $skipped)
            ->orderBy('t.id')
            ->select(['t.id as id', 't.balance_id', 't.sku', 't.qty_delta', 'o.id as order_row_id', 'o.order_number', 'o.financial_status', 'o.source_name'])
            ->chunkById(400, function ($lines) use ($userId, &$reversed) {
                foreach ($lines as $line) {
                    $balance = Inv5coreBalance::query()->find($line->balance_id);
                    if (! $balance) {
                        continue;
                    }
                    $detail = 'Reversal · order '.($line->order_number ?: $line->order_row_id).' is now '.$line->financial_status;
                    if ($this->postMovement(
                        $balance,
                        'return',
                        'shopify_raw_orders_reversal',
                        (int) $line->order_row_id,
                        Inv5coreLedger::deltaForAdd(abs((float) $line->qty_delta)),
                        (string) ($line->order_number ?? ''),
                        (string) ($line->source_name ?? 'App'),
                        $detail,
                        Carbon::now(),
                        $userId
                    )) {
                        $reversed++;
                    }
                }
            }, 't.id', 'id');

        return $reversed;
    }

    private function reverseManualOrderLines(?int $userId): int
    {
        if (! Schema::hasColumn('order_fulfillment_manual_orders', 'status')) {
            return 0;
        }
        $reversed = 0;
        $skipped = Inv5coreLedger::SKIPPED_STATUSES;
        DB::table('inv_5core_transactions as t')
            ->join('order_fulfillment_manual_orders as o', function ($join) {
                $join->on('o.id', '=', 't.source_id')->where('t.source', '=', 'manual_order');
            })
            ->leftJoin('inv_5core_transactions as rev', function ($join) {
                $join->on('rev.source_id', '=', 'o.id')->where('rev.source', '=', 'manual_order_reversal');
            })
            ->whereNull('rev.id')
            ->whereRaw('LOWER(o.status) IN ('.implode(',', array_fill(0, count($skipped), '?')).')', $skipped)
            ->orderBy('t.id')
            ->select(['t.id as id', 't.balance_id', 't.qty_delta', 'o.id as order_row_id', 'o.order_id', 'o.status', 'o.marketplace'])
            ->chunkById(400, function ($lines) use ($userId, &$reversed) {
                foreach ($lines as $line) {
                    $balance = Inv5coreBalance::query()->find($line->balance_id);
                    if (! $balance) {
                        continue;
                    }
                    $detail = 'Reversal · manual order '.($line->order_id ?: $line->order_row_id).' is now '.$line->status;
                    if ($this->postMovement(
                        $balance,
                        'return',
                        'manual_order_reversal',
                        (int) $line->order_row_id,
                        Inv5coreLedger::deltaForAdd(abs((float) $line->qty_delta)),
                        (string) ($line->order_id ?? ''),
                        (string) ($line->marketplace ?? 'App'),
                        $detail,
                        Carbon::now(),
                        $userId
                    )) {
                        $reversed++;
                    }
                }
            }, 't.id', 'id');

        return $reversed;
    }

    private function postMovement(
        Inv5coreBalance $balance,
        string $txnType,
        string $source,
        int $sourceId,
        float $delta,
        string $reference,
        string $channel,
        string $detail,
        Carbon $occurredAt,
        ?int $userId
    ): bool {
        return (bool) DB::transaction(function () use ($balance, $txnType, $source, $sourceId, $delta, $reference, $channel, $detail, $occurredAt, $userId) {
            $locked = Inv5coreBalance::query()->where('id', $balance->id)->lockForUpdate()->first();
            if (! $locked || $locked->qty_on_hand === null) {
                return false;
            }
            $before = (float) $locked->qty_on_hand;
            $after = Inv5coreLedger::applyDelta($before, $delta);
            $inserted = DB::table('inv_5core_transactions')->insertOrIgnore([
                'balance_id' => $locked->id,
                'product_master_id' => $locked->product_master_id,
                'sku' => $locked->sku,
                'txn_type' => $txnType,
                'qty_delta' => $delta,
                'qty_before' => $before,
                'qty_after' => $after,
                'source' => $source,
                'source_id' => $sourceId,
                'reference' => $reference !== '' ? $reference : null,
                'channel' => $channel !== '' ? mb_substr($channel, 0, 64) : null,
                'detail' => $detail,
                'occurred_at' => $occurredAt,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
            if ($inserted < 1) {
                return false;
            }
            $locked->qty_on_hand = $after;
            $locked->save();
            $balance->qty_on_hand = $after;

            return true;
        });
    }
}
