<?php

namespace App\Services\MarketplaceManager;

use Illuminate\Support\Facades\DB;

/**
 * Parse Temu / Temu 2 bg.order.amount.query payloads.
 *
 * Live responses nest money as {amount: <integer cents>, currency: "USD"}
 * under orderList[].basePrice and parentOrderMap.basePriceTotal — not the
 * scalar goodsAmount keys the original fetch parser looked for.
 */
class TemuOrderAmountParser
{
    /**
     * @return array<string, array{base: float|null, total: float|null}>
     */
    public static function parseResult(array $result): array
    {
        $result = self::unwrapResult($result);

        $entries = [];
        foreach (['orderList', 'skuList', 'subOrderList', 'orderAmountList', 'amountList', 'items'] as $listKey) {
            if (! isset($result[$listKey]) || ! is_array($result[$listKey])) {
                continue;
            }
            foreach ($result[$listKey] as $entry) {
                if (is_array($entry) && isset($entry['orderSn']) && trim((string) $entry['orderSn']) !== '') {
                    $entries[] = $entry;
                }
            }
            if ($entries !== []) {
                break;
            }
        }

        if ($entries === [] && isset($result['orderSn']) && is_array($result)) {
            $entries[] = $result;
        }

        $out = [];
        foreach ($entries as $entry) {
            $orderSn = trim((string) ($entry['orderSn'] ?? ''));
            if ($orderSn === '') {
                continue;
            }
            $out[$orderSn] = [
                'base' => self::pickMoney($entry, [
                    'basePrice', 'unitBasePrice', 'goodsAmount', 'baseAmount',
                    'productAmount', 'skuAmount', 'goodsAmountTotal', 'salesAmount',
                    'basePriceTotal',
                ]),
                'total' => self::pickMoney($entry, [
                    'retailPrice', 'unitRetailPrice', 'orderAmount', 'totalAmount',
                    'payAmount', 'settleAmount', 'actualAmount', 'customerPaid',
                    'retailPriceTotal',
                ]),
            ];
        }

        return $out;
    }

    /**
     * Line sale (dollars) from stored amount JSON, then columns.
     */
    public static function amountFromOrder(object $order): ?float
    {
        $decoded = self::decodePayload($order->amount_raw_json ?? null)
            ?? self::decodePayload($order->raw_json ?? null);
        if ($decoded !== null) {
            $bySn = self::parseResult($decoded);
            $orderSn = trim((string) ($order->order_sn ?? ''));
            if ($orderSn !== '' && isset($bySn[$orderSn])) {
                $picked = self::firstPositive($bySn[$orderSn]['base'] ?? null, $bySn[$orderSn]['total'] ?? null);
                if ($picked !== null) {
                    return $picked;
                }
            }
            $parent = $decoded['parentOrderMap'] ?? null;
            if (is_array($parent)) {
                $picked = self::firstPositive(
                    self::pickMoney($parent, ['basePriceTotal', 'estimatedRevenue', 'retailPriceTotal', 'customerPaid']),
                );
                if ($picked !== null) {
                    return $picked;
                }
            }
        }

        return self::firstPositive(
            $order->order_base_amount ?? null,
            $order->order_total_amount ?? null
        );
    }

    /**
     * Write order_base_amount / order_total_amount from JSON already on the row.
     *
     * @param  class-string  $modelClass
     */
    public static function backfillStoredAmounts(string $modelClass): int
    {
        if (! class_exists($modelClass) || ! method_exists($modelClass, 'query')) {
            return 0;
        }

        $table = (new $modelClass)->getTable();
        $updated = 0;
        $modelClass::query()
            ->whereNotNull('amount_raw_json')
            ->where('amount_raw_json', '!=', '')
            ->where(function ($q) {
                $q->whereNull('order_base_amount')->orWhere('order_base_amount', '<=', 0);
            })
            ->orderBy('id')
            ->select(['id', 'order_sn', 'amount_raw_json'])
            ->chunkById(300, function ($rows) use ($table, &$updated) {
                $ids = [];
                $baseSql = 'CASE `id`';
                $totalSql = 'CASE `id`';
                $baseBindings = [];
                $totalBindings = [];
                foreach ($rows as $row) {
                    $decoded = self::decodePayload($row->amount_raw_json ?? null);
                    if ($decoded === null) {
                        continue;
                    }
                    $bySn = self::parseResult($decoded);
                    $orderSn = trim((string) ($row->order_sn ?? ''));
                    $amt = ($orderSn !== '' && isset($bySn[$orderSn])) ? $bySn[$orderSn] : null;
                    $base = $amt['base'] ?? null;
                    $total = $amt['total'] ?? null;
                    if ($base === null && $total === null) {
                        $parent = $decoded['parentOrderMap'] ?? null;
                        if (is_array($parent)) {
                            $base = self::pickMoney($parent, ['basePriceTotal']);
                            $total = self::pickMoney($parent, ['retailPriceTotal', 'customerPaid', 'estimatedRevenue']);
                        }
                    }
                    if ($base === null && $total === null) {
                        continue;
                    }
                    $id = (int) $row->id;
                    $ids[] = $id;
                    $baseSql .= ' WHEN ? THEN COALESCE(?, `order_base_amount`)';
                    $totalSql .= ' WHEN ? THEN COALESCE(?, `order_total_amount`)';
                    $baseBindings[] = $id;
                    $baseBindings[] = $base;
                    $totalBindings[] = $id;
                    $totalBindings[] = $total;
                    $updated++;
                }
                if ($ids === []) {
                    return;
                }
                $baseSql .= ' ELSE `order_base_amount` END';
                $totalSql .= ' ELSE `order_total_amount` END';
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                DB::update(
                    "UPDATE `{$table}` SET `order_base_amount` = {$baseSql}, `order_total_amount` = {$totalSql} WHERE `id` IN ({$placeholders})",
                    array_merge($baseBindings, $totalBindings, $ids)
                );
            });

        return $updated;
    }

    /**
     * @return array<string, mixed>
     */
    public static function unwrapResult(array $result): array
    {
        if (isset($result['parentOrderMap']) || isset($result['orderList'])) {
            return $result;
        }
        if (isset($result['result']) && is_array($result['result'])) {
            return $result['result'];
        }

        return $result;
    }

    public static function decodePayload(mixed $payload): ?array
    {
        if (is_array($payload)) {
            return $payload;
        }
        if (! is_string($payload) || trim($payload) === '') {
            return null;
        }
        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  list<string>  $keys
     */
    public static function pickMoney(array $entry, array $keys): ?float
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $entry)) {
                continue;
            }
            $dollars = self::moneyToDollars($entry[$key]);
            if ($dollars !== null) {
                return $dollars;
            }
        }

        return null;
    }

    public static function moneyToDollars(mixed $value): ?float
    {
        if (is_array($value) && array_key_exists('amount', $value)) {
            if (! is_numeric($value['amount'])) {
                return null;
            }
            $n = (float) $value['amount'];
            if ($n <= 0) {
                return null;
            }
            // Nested Temu money.amount is integer minor units (cents).
            if (abs($n - round($n)) < 0.0001) {
                $n = $n / 100;
            }

            return round($n, 2);
        }

        if (! is_numeric($value)) {
            return null;
        }
        $n = (float) $value;

        return $n > 0 ? round($n, 2) : null;
    }

    public static function firstPositive(mixed ...$values): ?float
    {
        foreach ($values as $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (is_string($value)) {
                $value = str_replace([',', '$', ' '], '', trim($value));
            }
            if (! is_numeric($value)) {
                continue;
            }
            $n = round((float) $value, 2);
            if ($n > 0) {
                return $n;
            }
        }

        return null;
    }
}
