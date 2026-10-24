<?php

namespace App\Services\Veeqo;

use App\Services\VeeqoApiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Looks up order numbers in the Veeqo API (GET /orders?query=) and stores their SKUs in veeqo_order_skus.
 */
class VeeqoOrderSkuResolver
{
    private const RECHECK_NOT_FOUND_DAYS = 7;

    public function __construct(private VeeqoApiService $veeqo) {}

    /**
     * Chargeback order numbers from the last 13 months that no local source has a SKU for.
     *
     * @return list<string>
     */
    public function missingChargebackOrders(): array
    {
        if (! Schema::hasTable('veeqo_chargebacks')) {
            return [];
        }
        $orders = DB::table('veeqo_chargebacks')
            ->whereNotNull('order_number')->where('order_number', '!=', '')
            ->where('posted_at', '>=', now()->subMonths(13))
            ->whereIn('description', \App\Http\Controllers\Channels\VeeqoChargebackController::CHARGEBACK_TYPES)
            ->whereNotIn('source', ['temu1_csv', 'temu2_csv', 'temu3_csv'])
            ->distinct()->pluck('order_number')->map(fn ($o) => (string) $o)->all();
        $found = app(OrderSkuLookup::class)->forOrders($orders);

        return array_values(array_filter($orders, fn ($o) => ! isset($found[$o])));
    }

    /**
     * @param  list<string>  $orderNumbers
     * @param  callable(string): void|null  $log
     * @return array{checked: int, found: int, skipped: int, error: ?string}
     */
    public function resolve(array $orderNumbers, int $limit = 500, ?callable $log = null): array
    {
        $out = ['checked' => 0, 'found' => 0, 'skipped' => 0, 'error' => null];
        if (! Schema::hasTable('veeqo_order_skus') || ! $this->veeqo->isConfigured()) {
            $out['error'] = 'Veeqo API not configured or veeqo_order_skus missing.';

            return $out;
        }

        $recent = DB::table('veeqo_order_skus')
            ->whereIn('order_number', $orderNumbers)
            ->where(fn ($q) => $q->where('found', true)->orWhere('checked_at', '>=', now()->subDays(self::RECHECK_NOT_FOUND_DAYS)))
            ->pluck('order_number')->flip();

        foreach ($orderNumbers as $number) {
            if (isset($recent[$number])) {
                $out['skipped']++;

                continue;
            }
            if ($out['checked'] >= $limit) {
                break;
            }

            $res = $this->veeqo->listOrdersPage(['query' => $number, 'page_size' => 25]);
            if (! $res['ok']) {
                $out['error'] = 'Veeqo '.$res['http_status'].': '.$res['msg'];
                if (in_array($res['http_status'], [401, 403], true)) {
                    break;
                }
                usleep(1000000);

                continue;
            }
            $out['checked']++;

            $want = ltrim($number, '#');
            $skus = [];
            $orderId = null;
            $channel = null;
            foreach ($res['data'] as $order) {
                if (ltrim((string) ($order['number'] ?? ''), '#') !== $want) {
                    continue;
                }
                $orderId ??= isset($order['id']) ? (int) $order['id'] : null;
                $channel ??= $order['channel']['name'] ?? null;
                foreach ((array) ($order['line_items'] ?? []) as $line) {
                    $sku = trim((string) ($line['sellable']['sku_code'] ?? ''));
                    if ($sku !== '') {
                        $skus[$sku] = true;
                    }
                }
            }

            DB::table('veeqo_order_skus')->updateOrInsert(
                ['order_number' => $number],
                [
                    'veeqo_order_id' => $orderId,
                    'channel_name' => is_string($channel) ? mb_substr($channel, 0, 100) : null,
                    'skus' => $skus ? implode(', ', array_keys($skus)) : null,
                    'found' => $skus !== [],
                    'checked_at' => now(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            if ($skus !== []) {
                $out['found']++;
            }
            if ($log) {
                $log($number.' → '.($skus ? implode(', ', array_keys($skus)) : ($orderId ? 'order has no SKU' : 'not in Veeqo')));
            }
            usleep(250000);
        }

        return $out;
    }
}
