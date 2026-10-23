<?php

namespace App\Services\Veeqo;

use App\Models\VeeqoBillingShipment;
use App\Services\VeeqoApiService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Copies the label charges on Veeqo order shipments into veeqo_billing_shipments.
 *
 * Veeqo ignores created_at_max and returns orders newest first, so the backfill
 * pages from a fixed created_at_min. New orders only push older ones onto later
 * pages, which are fetched again and upserted; nothing is skipped.
 */
class VeeqoBillingSync
{
    public const BACKFILL_STATE_KEY = 'backfill';

    public const LAST_INCREMENTAL_KEY = 'last_incremental_at';

    public const PAGE_SIZE = 100;

    /** Survives cache clears; the backfill takes hours and must resume where it stopped. */
    private const STATE_FILE = 'veeqo_billing_sync_state.json';

    public function __construct(private VeeqoApiService $api)
    {
        $this->api->setTimeout(120);
    }

    /**
     * @param  callable(string): void|null  $log
     * @return array{pages: int, orders: int, shipments: int, done: bool, message: string}
     */
    public function backfill(int $days = 365, bool $resume = true, ?int $maxPages = null, ?callable $log = null): array
    {
        $state = $resume ? $this->state(self::BACKFILL_STATE_KEY) : null;
        if (! is_array($state) || (int) ($state['days'] ?? 0) !== $days) {
            $state = [
                'days' => $days,
                'since' => now()->subDays($days)->startOfDay()->toIso8601String(),
                'page' => 1,
                'started_at' => now()->toDateTimeString(),
            ];
        }

        $page = max(1, (int) $state['page']);
        $query = ['created_at_min' => $state['since']];

        return $this->walk($query, $page, $maxPages, $log, function (int $nextPage) use (&$state) {
            $state['page'] = $nextPage;
            $this->saveState(self::BACKFILL_STATE_KEY, $state);
        }, function () {
            $this->saveState(self::BACKFILL_STATE_KEY, null);
        });
    }

    /**
     * Orders updated since the last run, minus an overlap so slow writes are not missed.
     *
     * @param  callable(string): void|null  $log
     * @return array{pages: int, orders: int, shipments: int, done: bool, message: string}
     */
    public function incremental(?Carbon $since = null, ?callable $log = null): array
    {
        $startedAt = now();
        if ($since === null) {
            $last = $this->state(self::LAST_INCREMENTAL_KEY);
            $since = $last ? Carbon::parse($last)->subHours(2) : now()->subDays(2);
        }

        $result = $this->walk(['updated_at_min' => $since->toIso8601String()], 1, null, $log);
        if ($result['done']) {
            $this->saveState(self::LAST_INCREMENTAL_KEY, $startedAt->toIso8601String());
        }

        return $result;
    }

    /**
     * @return array{running: bool, page: ?int, since: ?string, started_at: ?string, last_incremental_at: ?string}
     */
    public function status(): array
    {
        $state = $this->state(self::BACKFILL_STATE_KEY);

        return [
            'running' => is_array($state),
            'page' => is_array($state) ? (int) ($state['page'] ?? 1) : null,
            'since' => is_array($state) ? (string) ($state['since'] ?? '') : null,
            'started_at' => is_array($state) ? (string) ($state['started_at'] ?? '') : null,
            'last_incremental_at' => $this->state(self::LAST_INCREMENTAL_KEY),
        ];
    }

    private function state(string $key): mixed
    {
        $path = storage_path('app/'.self::STATE_FILE);
        if (! is_file($path)) {
            return null;
        }
        $data = json_decode((string) @file_get_contents($path), true);

        return is_array($data) ? ($data[$key] ?? null) : null;
    }

    private function saveState(string $key, mixed $value): void
    {
        $path = storage_path('app/'.self::STATE_FILE);
        $data = is_file($path) ? json_decode((string) @file_get_contents($path), true) : [];
        $data = is_array($data) ? $data : [];
        if ($value === null) {
            unset($data[$key]);
        } else {
            $data[$key] = $value;
        }
        @file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  callable(string): void|null  $log
     * @param  callable(int): void|null  $savePage
     * @param  callable(): void|null  $finished
     * @return array{pages: int, orders: int, shipments: int, done: bool, message: string}
     */
    private function walk(array $query, int $page, ?int $maxPages, ?callable $log, ?callable $savePage = null, ?callable $finished = null): array
    {
        $pages = 0;
        $orders = 0;
        $shipments = 0;
        $totalPages = null;

        while (true) {
            if ($maxPages !== null && $pages >= $maxPages) {
                return $this->result($pages, $orders, $shipments, false, 'Stopped after '.$pages.' pages; run again to continue from page '.$page.'.');
            }

            $res = $this->api->listOrdersPage($query + ['page' => $page, 'page_size' => self::PAGE_SIZE]);
            if (! $res['ok']) {
                $message = 'Veeqo page '.$page.' failed ('.$res['http_status'].'): '.$res['msg'];
                Log::warning('Veeqo billing sync failed', ['page' => $page, 'status' => $res['http_status'], 'error' => $res['msg']]);

                return $this->result($pages, $orders, $shipments, false, $message);
            }
            $totalPages = $res['total_pages'] ?? $totalPages;

            $rows = $res['data'];
            if ($rows === []) {
                break;
            }

            $shipments += $this->store($rows);
            $orders += count($rows);
            $pages++;
            $page++;
            if ($savePage) {
                $savePage($page);
            }
            if ($log) {
                $log('Page '.($page - 1).($totalPages ? '/'.$totalPages : '').': '.count($rows).' orders, '.$shipments.' shipments saved so far.');
            }
            if ($totalPages !== null && $page > $totalPages) {
                break;
            }
        }

        if ($finished) {
            $finished();
        }

        return $this->result($pages, $orders, $shipments, true, 'Synced '.$orders.' orders and '.$shipments.' shipments.');
    }

    /**
     * @param  list<array<string, mixed>>  $orders
     */
    public function store(array $orders): int
    {
        $rows = [];
        foreach ($orders as $order) {
            if (! is_array($order)) {
                continue;
            }
            foreach ((array) ($order['allocations'] ?? []) as $allocation) {
                $shipment = is_array($allocation) ? ($allocation['shipment'] ?? null) : null;
                if (! is_array($shipment) || empty($shipment['id'])) {
                    continue;
                }
                $rows[] = $this->row($order, $allocation, $shipment);
            }
        }
        if ($rows === []) {
            return 0;
        }

        $columns = array_keys($rows[0]);
        $update = array_values(array_diff($columns, ['shipment_id', 'created_at']));
        foreach (array_chunk($rows, 200) as $chunk) {
            VeeqoBillingShipment::query()->upsert($chunk, ['shipment_id'], $update);
        }

        return count($rows);
    }

    /**
     * @param  array<string, mixed>  $order
     * @param  array<string, mixed>  $allocation
     * @param  array<string, mixed>  $shipment
     * @return array<string, mixed>
     */
    private function row(array $order, array $allocation, array $shipment): array
    {
        $tracking = is_array($shipment['tracking_number'] ?? null) ? $shipment['tracking_number'] : [];
        $carrier = is_array($shipment['carrier'] ?? null) ? $shipment['carrier'] : [];
        $label = is_array($shipment['outbound_label_charges'] ?? null) ? $shipment['outbound_label_charges'] : [];
        $charges = $this->list($shipment['charges'] ?? null);
        $fees = $this->list($shipment['carrier_fees'] ?? null);
        if ($shipment['carrier_fee'] ?? null) {
            $fees[] = is_array($shipment['carrier_fee']) ? $shipment['carrier_fee'] : ['value' => $shipment['carrier_fee']];
        }

        $labelCost = $this->money($label['value'] ?? $label['amount'] ?? null);
        $surcharge = $this->sum(array_values(array_filter(
            $charges,
            fn (array $row) => strtoupper((string) ($row['chargeId'] ?? '')) !== 'BASE_RATE'
        )));
        $feeTotal = $this->sum($fees);

        $skus = [];
        foreach ((array) ($order['line_items'] ?? []) as $line) {
            $sku = trim((string) ($line['sellable']['sku_code'] ?? ''));
            if ($sku !== '') {
                $skus[$sku] = $sku;
            }
        }
        $skuText = implode(', ', $skus);

        $raw = $shipment;
        unset($raw['shipped_by']['email']);
        $now = now()->format('Y-m-d H:i:s');

        return [
            'shipment_id' => (int) $shipment['id'],
            'allocation_id' => $this->int($allocation['id'] ?? $shipment['allocation_id'] ?? null),
            'order_id' => (int) ($order['id'] ?? $shipment['order_id'] ?? 0),
            'order_number' => $this->str($order['number'] ?? null, 100),
            'order_status' => $this->str($order['status'] ?? null, 50),
            'channel_name' => $this->str($order['channel']['name'] ?? null),
            'channel_type' => $this->str($order['channel']['type_code'] ?? null, 100),
            'warehouse_name' => $this->str($allocation['warehouse']['name'] ?? $order['channel']['default_warehouse']['name'] ?? null),
            'order_created_at' => $this->date($order['created_at'] ?? null),
            'order_shipped_at' => $this->date($order['shipped_at'] ?? null),
            'shipment_created_at' => $this->date($shipment['created_at'] ?? null),
            'outbound_shipment_date' => $this->date($shipment['outbound_shipment_date'] ?? null),
            'carrier_id' => $this->int($shipment['carrier_id'] ?? $carrier['id'] ?? null),
            'carrier_name' => $this->str($carrier['name'] ?? null),
            'carrier_slug' => $this->str($carrier['slug'] ?? null, 100),
            'provider_type' => $this->str($carrier['provider_type'] ?? null, 100),
            'sub_carrier_id' => $this->str($shipment['sub_carrier_id'] ?? null, 100),
            'service_type' => $this->str($shipment['service_type'] ?? null),
            'service_name' => $this->str($shipment['service_name'] ?? null),
            'short_service_name' => $this->str($shipment['short_service_name'] ?? null),
            'service_carrier_name' => $this->str($shipment['service_carrier_name'] ?? null),
            'tracking_number' => $this->str($tracking['tracking_number'] ?? null, 100),
            'tracking_status' => $this->str($tracking['status'] ?? null, 50),
            'tracking_billed' => $this->bool($tracking['billed'] ?? null),
            'tracking_cancelled' => $this->bool($tracking['cancelled'] ?? null),
            'tracking_used' => $this->bool($tracking['used'] ?? null),
            'in_transit_at' => $this->date($tracking['in_transit_at'] ?? null),
            'delivered_at' => $this->date($tracking['delivered_at'] ?? null),
            'cancelled_at' => $this->date($tracking['cancelled_at'] ?? null),
            'returned_to_sender_at' => $this->date($tracking['returned_to_sender_at'] ?? null),
            'label_cost' => $labelCost,
            'label_currency' => $this->str($label['unit'] ?? $label['currency'] ?? null, 10),
            'surcharge_total' => $surcharge,
            'carrier_fee_total' => $feeTotal,
            'total_cost' => $labelCost === null && $feeTotal === null ? null : round((float) $labelCost + (float) $feeTotal, 2),
            'insured_value' => $this->money($shipment['insured_value'] ?? null),
            'shipment_weight' => $this->num($shipment['weight'] ?? null),
            'allocation_weight' => $this->num($allocation['total_weight'] ?? null),
            'weight_unit' => $this->str($allocation['weight_unit'] ?? null, 20),
            'has_return_label' => $this->bool($shipment['has_return_label'] ?? null),
            'shipped_by' => $this->str($shipment['shipped_by']['login'] ?? null),
            'order_total_price' => $this->money($order['total_price'] ?? null),
            'order_subtotal_price' => $this->money($order['subtotal_price'] ?? null),
            'order_delivery_cost' => $this->money($order['delivery_cost'] ?? null),
            'order_currency' => $this->str($order['currency_code'] ?? null, 10),
            'delivery_method' => $this->str($order['delivery_method']['name'] ?? null),
            'line_item_count' => count((array) ($order['line_items'] ?? [])),
            'skus' => $skuText !== '' ? mb_substr($skuText, 0, 1000) : null,
            'charges' => json_encode($charges),
            'carrier_fees' => json_encode($fees),
            'raw_shipment' => json_encode($raw),
            'veeqo_updated_at' => $this->date($shipment['updated_at'] ?? $order['updated_at'] ?? null),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function list(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_array'));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function sum(array $rows): ?float
    {
        if ($rows === []) {
            return null;
        }
        $total = 0.0;
        foreach ($rows as $row) {
            $value = $row['value'] ?? $row['amount'] ?? $row['fee'] ?? 0;
            if (is_array($value)) {
                $value = $value['value'] ?? $value['amount'] ?? 0;
            }
            $total += is_numeric($value) ? (float) $value : 0.0;
        }

        return round($total, 2);
    }

    private function money(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    private function num(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 4) : null;
    }

    private function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function bool(mixed $value): ?bool
    {
        return $value === null ? null : (bool) $value;
    }

    private function str(mixed $value, int $max = 255): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private function date(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        try {
            return Carbon::parse($value)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @return array{pages: int, orders: int, shipments: int, done: bool, message: string}
     */
    private function result(int $pages, int $orders, int $shipments, bool $done, string $message): array
    {
        return compact('pages', 'orders', 'shipments', 'done', 'message');
    }
}
