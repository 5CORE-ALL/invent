<?php

namespace App\Support;

use App\Models\ShopifySku;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Marketplace order lines used to deduct INV APP.
 * Shopify inventory is not read here. Each line is one order-management row.
 */
class Inv5coreMarketplaceOrders
{
    /**
     * @return list<array<string, string>>
     */
    public static function definitions(): array
    {
        $metric = static function (string $source, string $label, string $table): array {
            return [
                'source' => $source,
                'label' => $label,
                'table' => $table,
                'id_sql' => 'id',
                'sku_sql' => 'sku',
                'qty_sql' => 'quantity',
                'order_sql' => "COALESCE(NULLIF(order_number, ''), NULLIF(order_id, ''))",
                'date_sql' => 'order_date',
                'status_sql' => 'status',
                'where_sql' => '',
            ];
        };

        return [
            [
                'source' => 'amazon',
                'label' => 'Amazon',
                'table' => 'amazon_order_items',
                'id_sql' => 'i.id',
                'sku_sql' => 'i.sku',
                'qty_sql' => 'i.quantity',
                'order_sql' => 'o.amazon_order_id',
                'date_sql' => 'o.order_date',
                'status_sql' => 'o.status',
                'where_sql' => "UPPER(TRIM(COALESCE(o.fulfillment_channel, ''))) != 'AFN'",
                'join_sql' => 'inner join amazon_orders as o on o.id = i.amazon_order_id',
            ],
            $metric('ebay1', 'eBay', 'ebay1_order_metrics'),
            $metric('ebay2', 'eBay 2', 'ebay2_order_metrics'),
            $metric('ebay3', 'eBay 3', 'ebay3_order_metrics'),
            $metric('shein', 'Shein', 'shein_order_metrics'),
            $metric('reverb', 'Reverb', 'reverb_order_metrics'),
            $metric('aliexpress', 'AliExpress', 'aliexpress_order_metrics'),
            $metric('alibaba', 'Alibaba', 'alibaba_order_metrics'),
            $metric('newegg', 'Newegg', 'newegg_order_metrics'),
            $metric('faire', 'Faire', 'faire_order_metrics'),
            $metric('topdawg', 'TopDawg', 'topdawg_order_metrics'),
            [
                'source' => 'temu',
                'label' => 'Temu',
                'table' => 'temu_orders',
                'id_sql' => 'id',
                'sku_sql' => "COALESCE(NULLIF(display_sku, ''), NULLIF(ext_code, ''), product_sku_id)",
                'qty_sql' => 'quantity',
                'order_sql' => "COALESCE(NULLIF(parent_order_sn, ''), order_sn)",
                'date_sql' => 'parent_order_time',
                'status_sql' => "COALESCE(parent_order_status_text, order_status_text, '')",
                'where_sql' => '',
            ],
            [
                'source' => 'temu2',
                'label' => 'Temu 2',
                'table' => 'temu2_orders',
                'id_sql' => 'id',
                'sku_sql' => "COALESCE(NULLIF(display_sku, ''), NULLIF(ext_code, ''), product_sku_id)",
                'qty_sql' => 'quantity',
                'order_sql' => "COALESCE(NULLIF(parent_order_sn, ''), order_sn)",
                'date_sql' => 'parent_order_time',
                'status_sql' => "COALESCE(parent_order_status_text, order_status_text, '')",
                'where_sql' => '',
            ],
            [
                'source' => 'temu3',
                'label' => 'Temu 3',
                'table' => 'temu3_orders',
                'id_sql' => 'id',
                'sku_sql' => 'contribution_sku',
                'qty_sql' => 'quantity_purchased',
                'order_sql' => 'order_id',
                'date_sql' => 'purchase_date',
                'status_sql' => 'order_status',
                'where_sql' => '',
            ],
            [
                'source' => 'tiktok',
                'label' => 'TikTok',
                'table' => 'tiktok_orders',
                'id_sql' => 'id',
                'sku_sql' => 'seller_sku',
                'qty_sql' => 'quantity',
                'order_sql' => 'order_id',
                'date_sql' => 'order_created_at',
                'status_sql' => 'order_status',
                'where_sql' => "(line_item_id is null or line_item_id != '__order__')",
            ],
            [
                'source' => 'tiktok2',
                'label' => 'TikTok 2',
                'table' => 'tiktok2_orders',
                'id_sql' => 'id',
                'sku_sql' => 'seller_sku',
                'qty_sql' => 'quantity',
                'order_sql' => 'order_id',
                'date_sql' => 'order_created_at',
                'status_sql' => 'order_status',
                'where_sql' => "(line_item_id is null or line_item_id != '__order__')",
            ],
            [
                'source' => 'bestbuy',
                'label' => 'Best Buy',
                'table' => 'mirakl_daily_data',
                'id_sql' => 'id',
                'sku_sql' => 'sku',
                'qty_sql' => 'quantity',
                'order_sql' => "COALESCE(NULLIF(channel_order_id, ''), order_id)",
                'date_sql' => 'order_created_at',
                'status_sql' => 'status',
                'where_sql' => "channel_name = 'Best Buy USA'",
            ],
            [
                'source' => 'macy',
                'label' => "Macy's",
                'table' => 'mirakl_daily_data',
                'id_sql' => 'id',
                'sku_sql' => 'sku',
                'qty_sql' => 'quantity',
                'order_sql' => "COALESCE(NULLIF(channel_order_id, ''), order_id)",
                'date_sql' => 'order_created_at',
                'status_sql' => 'status',
                'where_sql' => "channel_name = 'Macy''s, Inc.'",
            ],
            [
                'source' => 'wayfair',
                'label' => 'Wayfair',
                'table' => 'wayfair_daily_data',
                'id_sql' => 'id',
                'sku_sql' => 'sku',
                'qty_sql' => 'quantity',
                'order_sql' => 'po_number',
                'date_sql' => 'po_date',
                'status_sql' => 'status',
                'where_sql' => '',
            ],
            [
                'source' => 'doba',
                'label' => 'Doba',
                'table' => 'doba_daily_data',
                'id_sql' => 'id',
                'sku_sql' => 'sku',
                'qty_sql' => 'quantity',
                'order_sql' => "COALESCE(NULLIF(platform_order_no, ''), order_no)",
                'date_sql' => 'order_time',
                'status_sql' => 'order_status',
                'where_sql' => '',
            ],
            [
                'source' => 'purchasingpower',
                'label' => 'Purchasing Power',
                'table' => 'purchasing_power_sales',
                'id_sql' => 'id',
                'sku_sql' => 'offer_sku',
                'qty_sql' => 'quantity',
                'order_sql' => "COALESCE(NULLIF(order_number, ''), order_id)",
                'date_sql' => 'date_created',
                'status_sql' => 'status',
                'where_sql' => '',
            ],
        ];
    }

    public static function sourceKey(string $source): string
    {
        return 'mp:'.$source;
    }

    public static function reversalKey(string $source): string
    {
        return 'mp:'.$source.':reversal';
    }

    public static function openKey(string $source): string
    {
        return 'mp:'.$source.':open';
    }

    public static function fulfilledKey(string $source): string
    {
        return 'mp:'.$source.':fulfilled';
    }

    /**
     * Remember the latest order line already in the app. Later lines are the
     * ones that deduct INV APP. Existing lines stay inside the Shopify opening.
     */
    public static function snapshotWatermarks(): void
    {
        if (! Schema::hasTable('inv_5core_source_watermarks')) {
            return;
        }

        $now = now();
        foreach (self::definitions() as $def) {
            if (! Schema::hasTable($def['table'])) {
                continue;
            }
            $exists = DB::table('inv_5core_source_watermarks')->where('source', $def['source'])->exists();
            if ($exists) {
                continue;
            }
            DB::table('inv_5core_source_watermarks')->insert([
                'source' => $def['source'],
                'watermark_id' => self::maxLineId($def),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * @param  callable(object, array<string, string>): void  $callback
     */
    public static function eachNewLine(callable $callback): void
    {
        if (! Schema::hasTable('inv_5core_source_watermarks')) {
            return;
        }

        foreach (self::definitions() as $def) {
            if (! Schema::hasTable($def['table'])) {
                continue;
            }
            $watermark = DB::table('inv_5core_source_watermarks')->where('source', $def['source'])->value('watermark_id');
            if ($watermark === null) {
                continue;
            }
            self::lineQuery($def)
                ->whereRaw($def['id_sql'].' > ?', [(int) $watermark])
                ->orderByRaw($def['id_sql'])
                ->chunkById(400, function ($lines) use ($callback, $def) {
                    foreach ($lines as $line) {
                        $callback($line, $def);
                    }
                }, $def['id_sql'], 'id');
        }
    }

    /**
     * @param  callable(object, array<string, string>): void  $callback
     */
    public static function eachReversal(callable $callback): void
    {
        if (! Schema::hasTable('inv_5core_transactions')) {
            return;
        }

        $skipped = Inv5coreLedger::SKIPPED_STATUSES;
        $placeholders = implode(',', array_fill(0, count($skipped), '?'));

        foreach (self::definitions() as $def) {
            if (! Schema::hasTable($def['table'])) {
                continue;
            }
            $status = 'LOWER('.$def['status_sql'].')';
            $stateCols = Schema::hasColumn('inv_5core_transactions', 'committed_delta')
                ? ', t.txn_type, t.committed_delta'
                : '';
            $stages = [
                [self::sourceKey($def['source']), self::reversalKey($def['source'])],
                [self::openKey($def['source']), self::openKey($def['source']).':reversal'],
                [self::fulfilledKey($def['source']), self::fulfilledKey($def['source']).':reversal'],
            ];
            foreach ($stages as [$source, $reversal]) {
                $query = DB::table('inv_5core_transactions as t')
                    ->where('t.source', $source)
                    ->leftJoin('inv_5core_transactions as rev', function ($join) use ($reversal) {
                        $join->on('rev.source_id', '=', 't.source_id')
                            ->where('rev.source', '=', $reversal);
                    })
                    ->whereNull('rev.id');
                self::applyLineJoin($query, $def, 't.source_id');
                $query->whereRaw($status.' IN ('.$placeholders.')', $skipped)
                    ->selectRaw('t.id as id, t.balance_id, t.qty_delta, t.source_id as line_id, '.$def['order_sql'].' as order_number, '.$def['status_sql'].' as status'.$stateCols)
                    ->orderBy('t.id')
                    ->chunkById(400, function ($lines) use ($callback, $def, $source, $reversal) {
                        foreach ($lines as $line) {
                            $line->txn_source = $source;
                            $line->reversal_source = $reversal;
                            $callback($line, $def);
                        }
                    }, 't.id', 'id');
            }
        }
    }

    /**
     * @param  array<string, float>  $map
     */
    public static function addL30(array &$map, string $since): void
    {
        foreach (self::definitions() as $def) {
            if (! Schema::hasTable($def['table'])) {
                continue;
            }
            $query = self::baseQuery($def)
                ->whereRaw($def['date_sql'].' >= ?', [$since])
                ->whereRaw($def['qty_sql'].' > 0');
            self::excludeSkipped($query, $def['status_sql']);
            foreach ($query->selectRaw($def['sku_sql'].' as sku, SUM('.$def['qty_sql'].') as qty')->groupBy(DB::raw($def['sku_sql']))->get() as $row) {
                $sku = trim((string) ($row->sku ?? ''));
                if ($sku === '') {
                    continue;
                }
                $key = ShopifySku::compactSkuForLookup($sku);
                if ($key === '') {
                    continue;
                }
                $map[$key] = Inv5coreLedger::roundQty(($map[$key] ?? 0) + (float) $row->qty);
            }
        }
    }

    /**
     * @param  array<string, string>  $def
     */
    private static function maxLineId(array $def): int
    {
        return (int) (self::baseQuery($def)->max(DB::raw($def['id_sql'])) ?? 0);
    }

    /**
     * Every order line for one SKU from the marketplace order tables, plus any
     * line whose Shopify copy carries this SKU. Watermarks only mark lines
     * already inside the opening on-hand; they are not hidden from history.
     *
     * @param  array<string, int>  $maxIdBySource
     * @param  list<string>  $shopifyOrderIds
     * @return list<object>
     */
    public static function linesForCompactSku(string $sku, array $maxIdBySource = [], array $shopifyOrderIds = []): array
    {
        $compact = ShopifySku::compactSkuForLookup($sku);
        if ($compact === '') {
            return [];
        }
        $shopifyLookup = array_fill_keys($shopifyOrderIds, true);

        $lines = [];
        foreach (self::definitions() as $def) {
            if (! Schema::hasTable($def['table'])) {
                continue;
            }
            $shopifyCol = self::shopifyOrderColumn($def);
            try {
                $query = self::lineQuery($def);
                $query->where(function ($inner) use ($def, $compact, $shopifyCol, $shopifyOrderIds) {
                    self::whereSkuLike($inner, $def['sku_sql'], $compact);
                    if ($shopifyCol !== null && $shopifyOrderIds !== []) {
                        $inner->orWhereIn(DB::raw($shopifyCol), $shopifyOrderIds);
                    }
                });
                $rows = $query->orderByRaw($def['date_sql'].' desc')->limit(1000)->get();
            } catch (\Throwable $e) {
                Log::warning('INV 5Core history skipped '.$def['source'].': '.$e->getMessage());
                continue;
            }
            $mark = $maxIdBySource[$def['source']] ?? null;
            foreach ($rows as $row) {
                $row->shopify_order_id = self::shopifyId($row->shopify_order_id ?? null);
                $linked = $row->shopify_order_id !== '' && isset($shopifyLookup[$row->shopify_order_id]);
                if (! $linked && ShopifySku::compactSkuForLookup((string) ($row->sku ?? '')) !== $compact) {
                    continue;
                }
                $row->channel = $def['label'];
                $row->source = $def['source'];
                $row->in_snapshot = $mark === null || (int) $row->id <= (int) $mark;
                $lines[] = $row;
            }
        }

        return $lines;
    }

    public static function shopifyId(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return '';
        }
        if (preg_match('/(\d{5,})\s*$/', $value, $match)) {
            return $match[1];
        }

        return ctype_digit($value) ? $value : '';
    }

    /**
     * Loose SQL prefilter: every SKU character in order, so spacing and dashes
     * do not matter. Callers compare compactSkuForLookup in PHP afterwards.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    public static function whereSkuLike($query, string $skuSql, string $compact): void
    {
        $pattern = implode('%', str_split($compact));
        $columns = self::skuColumns($skuSql);
        if ($columns === []) {
            $query->whereRaw('UPPER(TRIM('.$skuSql.')) LIKE ?', [$pattern]);

            return;
        }
        $query->where(function ($inner) use ($columns, $pattern) {
            foreach ($columns as $column) {
                $inner->orWhereRaw('UPPER(TRIM('.$column.')) LIKE ?', [$pattern]);
            }
        });
    }

    /**
     * @param  array<string, string>  $def
     */
    private static function shopifyOrderColumn(array $def): ?string
    {
        if (! empty($def['join_sql'])) {
            return Schema::hasColumn('amazon_orders', 'shopify_order_id') ? 'o.shopify_order_id' : null;
        }

        return Schema::hasColumn($def['table'], 'shopify_order_id') ? 'shopify_order_id' : null;
    }

    /**
     * @return list<string>
     */
    public static function skuColumns(string $skuSql): array
    {
        preg_match_all('/[A-Za-z_][A-Za-z0-9_.]*/', $skuSql, $matches);
        $skip = ['coalesce', 'nullif', 'null', 'upper', 'trim', 'replace', 'if'];
        $columns = [];
        foreach ($matches[0] as $name) {
            if (in_array(strtolower($name), $skip, true) || ! preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $name)) {
                continue;
            }
            $columns[$name] = $name;
        }

        return array_values($columns);
    }

    /**
     * @param  array<string, string>  $def
     */
    private static function lineQuery(array $def)
    {
        $fulfilled = 'NULL';
        if (empty($def['join_sql']) && Schema::hasColumn($def['table'], 'ship_time')) {
            $fulfilled = 'ship_time';
        }
        $shopify = self::shopifyOrderColumn($def) ?? 'NULL';

        return self::baseQuery($def)->selectRaw(
            $def['id_sql'].' as id, '.$def['sku_sql'].' as sku, '.$def['qty_sql'].' as qty, '.$def['order_sql'].' as order_number, '.$def['status_sql'].' as status, '.$def['date_sql'].' as order_date, '.$fulfilled.' as fulfilled_at, '.$shopify.' as shopify_order_id'
        );
    }

    /**
     * @param  array<string, string>  $def
     */
    private static function baseQuery(array $def)
    {
        if (! empty($def['join_sql'])) {
            $query = DB::table('amazon_order_items as i')
                ->join('amazon_orders as o', 'o.id', '=', 'i.amazon_order_id');
        } else {
            $query = DB::table($def['table']);
        }
        if (! empty($def['where_sql'])) {
            $query->whereRaw($def['where_sql']);
        }

        return $query;
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array<string, string>  $def
     */
    private static function applyLineJoin($query, array $def, string $sourceIdColumn): void
    {
        if (! empty($def['join_sql'])) {
            $query->join('amazon_order_items as i', 'i.id', '=', $sourceIdColumn)
                ->join('amazon_orders as o', 'o.id', '=', 'i.amazon_order_id');

            return;
        }

        $query->join($def['table'].' as lines', 'lines.id', '=', $sourceIdColumn);
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private static function excludeSkipped($query, string $statusSql): void
    {
        $skipped = Inv5coreLedger::SKIPPED_STATUSES;
        $query->where(function ($inner) use ($statusSql, $skipped) {
            $inner->whereRaw($statusSql.' IS NULL')
                ->orWhereRaw(
                    'LOWER('.$statusSql.') NOT IN ('.implode(',', array_fill(0, count($skipped), '?')).')',
                    $skipped
                );
        });
    }
};
