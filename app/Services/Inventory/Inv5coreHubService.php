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
                        if (Schema::hasColumn('inv_5core_balances', 'qty_committed')) {
                            $balance->qty_committed = 0;
                            $balance->qty_unavailable = 0;
                        }
                        $balance->opening_seeded_at = $now;
                        $balance->sales_after_order_id = $orderWatermark;
                        $balance->sales_after_manual_id = $manualWatermark;
                        $balance->shopify_locked = true;
                        $balance->save();

                        $openingRow = [
                            'balance_id' => $balance->id,
                            'product_master_id' => (int) $product->id,
                            'sku' => $sku,
                            'txn_type' => 'opening',
                            'qty_delta' => $opening,
                            'qty_before' => 0,
                            'qty_after' => $opening,
                            'source' => 'shopify_opening',
                        ];
                        if (Schema::hasColumn('inv_5core_transactions', 'available_delta')) {
                            $openingRow['unavailable_delta'] = 0;
                            $openingRow['unavailable_after'] = 0;
                            $openingRow['committed_delta'] = 0;
                            $openingRow['committed_after'] = 0;
                            $openingRow['available_delta'] = $opening;
                            $openingRow['available_after'] = $opening;
                        }
                        Inv5coreTransaction::query()->create($openingRow + [
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
            $when = ! empty($line->order_date) ? Carbon::parse($line->order_date) : Carbon::now();
            $posted += $this->recordOrderStages(
                $balance,
                Inv5coreMarketplaceOrders::openKey($def['source']),
                Inv5coreMarketplaceOrders::fulfilledKey($def['source']),
                Inv5coreMarketplaceOrders::sourceKey($def['source']),
                (int) $line->id,
                $qty,
                Inv5coreLedger::statusIsFulfilled($line->status ?? null),
                $orderNo,
                $def['label'],
                $detail,
                $when,
                $userId
            );
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
            $source = (string) ($line->txn_source ?? Inv5coreMarketplaceOrders::sourceKey($def['source']));
            $reversal = (string) ($line->reversal_source ?? Inv5coreMarketplaceOrders::reversalKey($def['source']));
            if (str_ends_with($source, ':open')) {
                $fulfilledSource = substr($source, 0, -strlen(':open')).':fulfilled';
                if (Inv5coreTransaction::query()->where('source', $fulfilledSource)->where('source_id', (int) $line->line_id)->exists()) {
                    return;
                }
                $qty = abs((float) ($line->committed_delta ?? 0));
                if ($qty <= 0) {
                    return;
                }
                $moved = $this->postMovement(
                    $balance,
                    'return',
                    $reversal,
                    (int) $line->line_id,
                    0.0,
                    $orderNo,
                    $def['label'],
                    $detail,
                    Carbon::now(),
                    $userId,
                    Inv5coreLedger::deltaForSubtract($qty),
                    0.0,
                    Inv5coreLedger::deltaForAdd($qty)
                );
            } else {
                $moved = $this->postMovement(
                    $balance,
                    'return',
                    $reversal,
                    (int) $line->line_id,
                    Inv5coreLedger::deltaForAdd(abs((float) $line->qty_delta)),
                    $orderNo,
                    $def['label'],
                    $detail,
                    Carbon::now(),
                    $userId
                );
            }
            if ($moved) {
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
            $committed = Schema::hasColumn('inv_5core_balances', 'qty_committed') ? (float) ($balance->qty_committed ?? 0) : 0.0;
            $unavailable = Schema::hasColumn('inv_5core_balances', 'qty_unavailable') ? (float) ($balance->qty_unavailable ?? 0) : 0.0;
            $states = Inv5coreLedger::nextStates($before, $committed, $unavailable, $delta);
            $balance->qty_on_hand = $states['on_hand'];
            if (Schema::hasColumn('inv_5core_balances', 'qty_committed')) {
                $balance->qty_committed = $states['committed'];
                $balance->qty_unavailable = $states['unavailable'];
            }
            $balance->save();

            $adjustRow = [
                'balance_id' => $balance->id,
                'product_master_id' => $balance->product_master_id,
                'sku' => $balance->sku,
                'txn_type' => $txnType,
                'qty_delta' => $delta,
                'qty_before' => $before,
                'qty_after' => $states['on_hand'],
                'source' => 'adjustment',
            ];
            if (Schema::hasColumn('inv_5core_transactions', 'available_delta')) {
                $adjustRow['unavailable_delta'] = 0;
                $adjustRow['unavailable_after'] = $states['unavailable'];
                $adjustRow['committed_delta'] = 0;
                $adjustRow['committed_after'] = $states['committed'];
                $adjustRow['available_delta'] = $delta;
                $adjustRow['available_after'] = $states['available'];
            }
            Inv5coreTransaction::query()->create($adjustRow + [
                'source' => 'adjustment',
                'source_id' => null,
                'reference' => null,
                'channel' => 'App',
                'detail' => $detail,
                'occurred_at' => Carbon::now(),
                'created_by' => $userId,
            ]);

            return ['inv_app' => $states['on_hand'], 'qty_delta' => $delta];
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

        $ledgerRows = $query->get();
        $events = $this->historyEvents($balance, $ledgerRows);
        if ($events === []) {
            return ['balance' => $balance, 'rows' => $this->formatStoredHistory($ledgerRows)];
        }

        $syntheticCommitted = 0.0;
        $syntheticUnavailable = 0.0;
        foreach ($events as $event) {
            if (! empty($event['in_balance'])) {
                continue;
            }
            $syntheticCommitted += (float) $event['committed_delta'];
            $syntheticUnavailable += (float) $event['unavailable_delta'];
        }
        $endCommitted = (Schema::hasColumn('inv_5core_balances', 'qty_committed') ? (float) ($balance->qty_committed ?? 0) : 0.0) + $syntheticCommitted;
        $endUnavailable = (Schema::hasColumn('inv_5core_balances', 'qty_unavailable') ? (float) ($balance->qty_unavailable ?? 0) : 0.0) + $syntheticUnavailable;
        $rows = [];
        foreach (array_slice(Inv5coreLedger::replayHistory((float) $balance->qty_on_hand, $endCommitted, $endUnavailable, $events), 0, 500) as $row) {
            $at = $row['at'] > 0 ? Carbon::createFromTimestamp($row['at'])->timezone(config('app.timezone')) : null;
            $rows[] = [
                'id' => 0,
                'occurred_at' => $at ? $at->format('M j \a\t g:i a') : '',
                'activity' => Inv5coreLedger::historyActivity($row['txn_type'], $row['reference']),
                'created_by' => Inv5coreLedger::historyCreatedBy($row['txn_type'], $row['channel'], $row['user_name']),
                'committed_delta' => $row['committed_delta'],
                'committed_after' => $row['committed_after'],
                'available_delta' => $row['available_delta'],
                'available_after' => $row['available_after'],
                'on_hand_delta' => $row['on_hand_delta'],
                'on_hand_after' => $row['on_hand_after'],
                'detail' => '',
            ];
        }

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
                    $when = $line->order_date ? Carbon::parse($line->order_date) : Carbon::now();
                    $posted += $this->recordOrderStages(
                        $balance,
                        'manual_order:open',
                        'manual_order:fulfilled',
                        'manual_order',
                        (int) $line->id,
                        (float) $line->qty,
                        Inv5coreLedger::statusIsFulfilled($line->status ?? null),
                        (string) ($line->order_id ?? ''),
                        (string) ($line->marketplace ?? 'App'),
                        $detail,
                        $when,
                        $userId
                    );
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
        foreach ([
            ['manual_order', 'manual_order_reversal'],
            ['manual_order:open', 'manual_order:open:reversal'],
            ['manual_order:fulfilled', 'manual_order:fulfilled:reversal'],
        ] as [$source, $reversal]) {
            $reversed += $this->reverseManualStage($source, $reversal, $userId);
        }

        return $reversed;
    }

    private function reverseManualStage(string $source, string $reversal, ?int $userId): int
    {
        $reversed = 0;
        $skipped = Inv5coreLedger::SKIPPED_STATUSES;
        $select = ['t.id as id', 't.balance_id', 't.qty_delta', 'o.id as order_row_id', 'o.order_id', 'o.status', 'o.marketplace'];
        if (Schema::hasColumn('inv_5core_transactions', 'committed_delta')) {
            $select[] = 't.committed_delta';
        }
        DB::table('inv_5core_transactions as t')
            ->join('order_fulfillment_manual_orders as o', function ($join) use ($source) {
                $join->on('o.id', '=', 't.source_id')->where('t.source', '=', $source);
            })
            ->leftJoin('inv_5core_transactions as rev', function ($join) use ($reversal) {
                $join->on('rev.source_id', '=', 'o.id')->where('rev.source', '=', $reversal);
            })
            ->whereNull('rev.id')
            ->whereRaw('LOWER(o.status) IN ('.implode(',', array_fill(0, count($skipped), '?')).')', $skipped)
            ->orderBy('t.id')
            ->select($select)
            ->chunkById(400, function ($lines) use ($userId, &$reversed, $source, $reversal) {
                foreach ($lines as $line) {
                    $balance = Inv5coreBalance::query()->find($line->balance_id);
                    if (! $balance) {
                        continue;
                    }
                    $detail = 'Reversal · manual order '.($line->order_id ?: $line->order_row_id).' is now '.$line->status;
                    if (str_ends_with($source, ':open')) {
                        if (Inv5coreTransaction::query()->where('source', 'manual_order:fulfilled')->where('source_id', (int) $line->order_row_id)->exists()) {
                            continue;
                        }
                        $qty = abs((float) ($line->committed_delta ?? 0));
                        if ($qty <= 0) {
                            continue;
                        }
                        $moved = $this->postMovement(
                            $balance,
                            'return',
                            $reversal,
                            (int) $line->order_row_id,
                            0.0,
                            (string) ($line->order_id ?? ''),
                            (string) ($line->marketplace ?? 'App'),
                            $detail,
                            Carbon::now(),
                            $userId,
                            Inv5coreLedger::deltaForSubtract($qty),
                            0.0,
                            Inv5coreLedger::deltaForAdd($qty)
                        );
                    } else {
                        $moved = $this->postMovement(
                            $balance,
                            'return',
                            $reversal,
                            (int) $line->order_row_id,
                            Inv5coreLedger::deltaForAdd(abs((float) $line->qty_delta)),
                            (string) ($line->order_id ?? ''),
                            (string) ($line->marketplace ?? 'App'),
                            $detail,
                            Carbon::now(),
                            $userId
                        );
                    }
                    if ($moved) {
                        $reversed++;
                    }
                }
            }, 't.id', 'id');

        return $reversed;
    }

    /**
     * Order created commits quantity. Order fulfilled then reduces on hand
     * and releases that commitment, the same two steps Shopify shows.
     */
    private function recordOrderStages(
        Inv5coreBalance $balance,
        string $openSource,
        string $fulfilledSource,
        string $legacySource,
        int $sourceId,
        float $qty,
        bool $fulfilled,
        string $reference,
        string $channel,
        string $detail,
        Carbon $when,
        ?int $userId
    ): int {
        if (! Schema::hasColumn('inv_5core_transactions', 'committed_delta')) {
            return $this->postMovement(
                $balance,
                'sale',
                $legacySource,
                $sourceId,
                Inv5coreLedger::deltaForSubtract($qty),
                $reference,
                $channel,
                $detail,
                $when,
                $userId
            ) ? 1 : 0;
        }
        if ($this->movementExists($legacySource, $sourceId)) {
            return 0;
        }

        $posted = 0;
        $hadOpen = $this->movementExists($openSource, $sourceId);
        if (! $hadOpen && $this->postMovement(
            $balance,
            'order_created',
            $openSource,
            $sourceId,
            0.0,
            $reference,
            $channel,
            $detail,
            $when,
            $userId,
            Inv5coreLedger::deltaForAdd($qty),
            0.0,
            Inv5coreLedger::deltaForSubtract($qty)
        )) {
            $posted++;
        }
        if ($fulfilled && ! $this->movementExists($fulfilledSource, $sourceId) && $this->postMovement(
            $balance,
            'order_fulfilled',
            $fulfilledSource,
            $sourceId,
            Inv5coreLedger::deltaForSubtract($qty),
            $reference,
            $channel,
            $detail,
            $hadOpen ? Carbon::now() : $when,
            $userId,
            Inv5coreLedger::deltaForSubtract($qty),
            0.0,
            Inv5coreLedger::deltaForAdd($qty)
        )) {
            $posted++;
        }

        return $posted;
    }

    private function movementExists(string $source, int $sourceId): bool
    {
        return Inv5coreTransaction::query()->where('source', $source)->where('source_id', $sourceId)->exists();
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
        ?int $userId,
        float $committedDelta = 0.0,
        float $unavailableDelta = 0.0,
        ?float $availableDisplayDelta = null
    ): bool {
        return (bool) DB::transaction(function () use ($balance, $txnType, $source, $sourceId, $delta, $reference, $channel, $detail, $occurredAt, $userId, $committedDelta, $unavailableDelta, $availableDisplayDelta) {
            $locked = Inv5coreBalance::query()->where('id', $balance->id)->lockForUpdate()->first();
            if (! $locked || $locked->qty_on_hand === null) {
                return false;
            }
            $statesReady = Schema::hasColumn('inv_5core_balances', 'qty_committed')
                && Schema::hasColumn('inv_5core_transactions', 'available_delta');
            $before = (float) $locked->qty_on_hand;
            $committed = $statesReady ? (float) ($locked->qty_committed ?? 0) : 0.0;
            $unavailable = $statesReady ? (float) ($locked->qty_unavailable ?? 0) : 0.0;
            $states = Inv5coreLedger::nextStates(
                $before,
                $committed,
                $unavailable,
                $delta,
                $statesReady ? $committedDelta : 0.0,
                $statesReady ? $unavailableDelta : 0.0
            );
            $availableBefore = Inv5coreLedger::roundQty($before - $committed - $unavailable);
            $row = [
                'balance_id' => $locked->id,
                'product_master_id' => $locked->product_master_id,
                'sku' => $locked->sku,
                'txn_type' => $txnType,
                'qty_delta' => $delta,
                'qty_before' => $before,
                'qty_after' => $states['on_hand'],
                'source' => $source,
                'source_id' => $sourceId,
                'reference' => $reference !== '' ? $reference : null,
                'channel' => $channel !== '' ? mb_substr($channel, 0, 64) : null,
                'detail' => $detail,
                'occurred_at' => $occurredAt,
                'created_by' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
            if ($statesReady) {
                $row['unavailable_delta'] = $statesReady ? $unavailableDelta : 0.0;
                $row['unavailable_after'] = $states['unavailable'];
                $row['committed_delta'] = $committedDelta;
                $row['committed_after'] = $states['committed'];
                $row['available_delta'] = $availableDisplayDelta ?? Inv5coreLedger::roundQty($states['available'] - $availableBefore);
                $row['available_after'] = $states['available'];
            }
            $inserted = DB::table('inv_5core_transactions')->insertOrIgnore($row);
            if ($inserted < 1) {
                return false;
            }
            $locked->qty_on_hand = $states['on_hand'];
            if ($statesReady) {
                $locked->qty_committed = $states['committed'];
                $locked->qty_unavailable = $states['unavailable'];
            }
            $locked->save();
            $balance->qty_on_hand = $states['on_hand'];
            if ($statesReady) {
                $balance->qty_committed = $states['committed'];
                $balance->qty_unavailable = $states['unavailable'];
            }

            return true;
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $ledgerRows
     * @return list<array<string, mixed>>
     */
    private function formatStoredHistory($ledgerRows): array
    {
        return $ledgerRows->map(function ($row) {
            $onHandDelta = (float) $row->qty_delta;
            $onHandAfter = (float) $row->qty_after;
            $hasStates = $row->available_after !== null || $row->committed_after !== null;
            $txnType = (string) $row->txn_type;
            $userName = trim((string) ($row->user_name ?? ''));
            $at = $row->occurred_at ?: $row->created_at;

            return [
                'id' => (int) $row->id,
                'occurred_at' => $at ? $at->timezone(config('app.timezone'))->format('M j \a\t g:i a') : '',
                'activity' => Inv5coreLedger::historyActivity($txnType, (string) ($row->reference ?? '')),
                'created_by' => Inv5coreLedger::historyCreatedBy($txnType, (string) ($row->channel ?? ''), $userName),
                'committed_delta' => $hasStates ? (float) ($row->committed_delta ?? 0) : 0.0,
                'committed_after' => $hasStates ? (float) ($row->committed_after ?? 0) : 0.0,
                'available_delta' => $hasStates ? (float) ($row->available_delta ?? 0) : $onHandDelta,
                'available_after' => $hasStates ? (float) ($row->available_after ?? 0) : $onHandAfter,
                'on_hand_delta' => $onHandDelta,
                'on_hand_after' => $onHandAfter,
                'detail' => (string) ($row->detail ?? ''),
            ];
        })->all();
    }

    /**
     * Marketplace orders already inside the opening, plus later ledger movements.
     * A Shopify order copy is included only when the sale was placed on Shopify.
     *
     * @param  \Illuminate\Support\Collection<int, mixed>  $ledgerRows
     * @return list<array<string, mixed>>
     */
    private function historyEvents(Inv5coreBalance $balance, $ledgerRows): array
    {
        $posted = [];
        $events = [];
        foreach ($ledgerRows as $row) {
            $posted[(string) $row->source.'#'.(int) $row->source_id] = true;
            if ((string) $row->txn_type === 'opening' || (string) $row->source === 'shopify_opening') {
                continue;
            }
            $at = $row->occurred_at ?: $row->created_at;
            $txnType = (string) $row->txn_type;
            $stage = match ($txnType) {
                'order_created' => 1,
                'order_fulfilled', 'sale' => 2,
                'return' => 3,
                default => 2,
            };
            $onHandDelta = (float) $row->qty_delta;
            $hasStates = $row->available_after !== null || $row->committed_after !== null;
            $events[] = [
                'at' => $at ? $at->getTimestamp() : 0,
                'stage' => $stage,
                'txn_type' => $txnType,
                'reference' => trim((string) ($row->reference ?? '')),
                'channel' => trim((string) ($row->channel ?? '')),
                'user_name' => trim((string) ($row->user_name ?? '')),
                'on_hand_delta' => $onHandDelta,
                'committed_delta' => $hasStates ? (float) ($row->committed_delta ?? 0) : 0.0,
                'unavailable_delta' => $hasStates ? (float) ($row->unavailable_delta ?? 0) : 0.0,
                'available_delta' => $hasStates ? (float) ($row->available_delta ?? 0) : $onHandDelta,
                'in_balance' => true,
            ];
        }

        $maxIds = [];
        if (Schema::hasTable('inv_5core_source_watermarks')) {
            foreach (DB::table('inv_5core_source_watermarks')->get(['source', 'watermark_id']) as $mark) {
                $maxIds[(string) $mark->source] = (int) $mark->watermark_id;
            }
        }
        $compactSku = (string) ($balance->sku_compact ?: ShopifySku::compactSkuForLookup((string) $balance->sku));
        foreach (Inv5coreMarketplaceOrders::linesForCompactSku($compactSku, $maxIds) as $line) {
            if ($this->orderLinePosted($posted, (string) $line->source, (int) $line->id)) {
                continue;
            }
            $reference = trim((string) ($line->order_number ?? ''));
            $status = isset($line->status) ? (string) $line->status : null;
            $fulfilledAt = $this->historyTimestamp($line->fulfilled_at ?? null, true);
            if ($fulfilledAt !== null && ! Inv5coreLedger::statusSkipsSale($status) && ! Inv5coreLedger::statusIsFulfilled($status)) {
                $status = 'shipped';
            }
            array_push($events, ...Inv5coreLedger::orderMovementEvents(
                (float) ($line->qty ?? 0),
                $status,
                $reference !== '' ? $reference : (string) $line->id,
                (string) $line->channel,
                $this->historyTimestamp($line->order_date ?? null) ?? 0,
                $fulfilledAt
            ));
        }

        $this->appendManualHistory($events, $posted, $balance);
        $this->appendShopifyStoreHistory($events, $posted, $balance);

        return $events;
    }

    /**
     * @param  array<string, bool>  $posted
     */
    private function orderLinePosted(array $posted, string $source, int $id): bool
    {
        foreach ([
            Inv5coreMarketplaceOrders::openKey($source).'#'.$id,
            Inv5coreMarketplaceOrders::fulfilledKey($source).'#'.$id,
            Inv5coreMarketplaceOrders::sourceKey($source).'#'.$id,
        ] as $key) {
            if (isset($posted[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @param  array<string, bool>  $posted
     */
    private function appendManualHistory(array &$events, array $posted, Inv5coreBalance $balance): void
    {
        if (! Schema::hasTable('order_fulfillment_manual_orders') || $balance->sales_after_manual_id === null) {
            return;
        }
        $compact = ShopifySku::compactSkuForLookup((string) $balance->sku);
        $rows = DB::table('order_fulfillment_manual_orders')
            ->where('id', '<=', (int) $balance->sales_after_manual_id)
            ->whereRaw(Inv5coreMarketplaceOrders::compactSkuSql('sku').' = ?', [$compact])
            ->orderByDesc('id')
            ->limit(300)
            ->get(['id', 'qty', 'order_id', 'order_date', 'marketplace', 'status']);
        foreach ($rows as $line) {
            if (isset($posted['manual_order:open#'.$line->id]) || isset($posted['manual_order:fulfilled#'.$line->id]) || isset($posted['manual_order#'.$line->id])) {
                continue;
            }
            array_push($events, ...Inv5coreLedger::orderMovementEvents(
                (float) ($line->qty ?? 0),
                isset($line->status) ? (string) $line->status : null,
                trim((string) ($line->order_id ?? '')) ?: (string) $line->id,
                $this->marketplaceChannel((string) ($line->marketplace ?? '')),
                $this->historyTimestamp($line->order_date ?? null) ?? 0,
                null
            ));
        }
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @param  array<string, bool>  $posted
     */
    private function appendShopifyStoreHistory(array &$events, array $posted, Inv5coreBalance $balance): void
    {
        if (! Schema::hasTable('shopify_raw_orders') || $balance->sales_after_order_id === null) {
            return;
        }
        $compact = ShopifySku::compactSkuForLookup((string) $balance->sku);
        $columns = ['id', 'quantity', 'order_number', 'order_date', 'source_name', 'financial_status'];
        $hasTags = Schema::hasColumn('shopify_raw_orders', 'tags');
        $hasFulfillment = Schema::hasColumn('shopify_raw_orders', 'fulfillment_status');
        if ($hasTags) {
            $columns[] = 'tags';
        }
        if ($hasFulfillment) {
            $columns[] = 'fulfillment_status';
        }
        $rows = DB::table('shopify_raw_orders')
            ->where('id', '<=', (int) $balance->sales_after_order_id)
            ->whereRaw(Inv5coreMarketplaceOrders::compactSkuSql('sku').' = ?', [$compact])
            ->orderByDesc('id')
            ->limit(300)
            ->get($columns);
        foreach ($rows as $line) {
            if (isset($posted['shopify_raw_orders#'.$line->id]) || isset($posted['shopify_raw_orders_reversal#'.$line->id])) {
                continue;
            }
            $channel = Inv5coreLedger::shopifyOrderChannel(
                (string) ($line->source_name ?? ''),
                $hasTags ? (string) ($line->tags ?? '') : ''
            );
            if ($channel === null || $channel === '') {
                continue;
            }
            $fulfillment = $hasFulfillment ? strtolower(trim((string) ($line->fulfillment_status ?? ''))) : '';
            if (Inv5coreLedger::statusSkipsSale($line->financial_status ?? null)) {
                $status = (string) $line->financial_status;
            } elseif (in_array($fulfillment, ['partial', 'partially_fulfilled'], true)) {
                $status = 'shipped';
            } else {
                $status = $fulfillment;
            }
            array_push($events, ...Inv5coreLedger::orderMovementEvents(
                (float) ($line->quantity ?? 0),
                $status,
                trim((string) ($line->order_number ?? '')) ?: (string) $line->id,
                $channel,
                $this->historyTimestamp($line->order_date ?? null) ?? 0,
                null
            ));
        }
    }

    private function marketplaceChannel(string $raw): string
    {
        $raw = trim($raw);
        foreach (Inv5coreMarketplaceOrders::definitions() as $def) {
            if (strcasecmp($def['label'], $raw) === 0 || strcasecmp($def['source'], $raw) === 0) {
                return $def['label'];
            }
        }

        return $raw !== '' ? $raw : 'Manual';
    }

    private function historyTimestamp(mixed $value, bool $allowEmpty = false): ?int
    {
        if ($value === null || $value === '') {
            return $allowEmpty ? null : 0;
        }
        try {
            return Carbon::parse($value)->getTimestamp();
        } catch (\Throwable) {
            return $allowEmpty ? null : 0;
        }
    }
}
