<?php

namespace App\Services\Veeqo;

use App\Models\VeeqoChargeback;
use App\Services\TikTok2ShopService;
use App\Services\TikTokShopService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TikTok Shop shipping charges from the Finance API, stored next to the Veeqo chargebacks.
 * Shipping adjustment transactions (carrier weight/dimension corrections, compensation,
 * reimbursements) become chargeback rows; ORDER transactions with a TikTok label cost become
 * "TikTok Shipping Label" rows with the shipping_cost_breakdown as components.
 */
class TikTokChargebackSync
{
    public const CHANNELS = [
        'tiktok' => ['service' => TikTokShopService::class, 'source' => 'tiktok_api', 'name' => 'TikTok 1', 'orders' => 'tiktok_orders'],
        'tiktok2' => ['service' => TikTok2ShopService::class, 'source' => 'tiktok2_api', 'name' => 'TikTok 2', 'orders' => 'tiktok2_orders'],
    ];

    public const ADJUSTMENT_TYPES = [
        'SHIPPING_FEE_ADJUSTMENT' => 'TikTok Shipping Fee Adjustment',
        'SHIPPING_FEE_COMPENSATION' => 'TikTok Shipping Fee Compensation',
        'LOGISTICS_REIMBURSEMENT' => 'TikTok Logistics Reimbursement',
        'SHIPPING_FEE_REBATE' => 'TikTok Shipping Fee Rebate',
        'SAMPLE_SHIPPING_FEE' => 'TikTok Sample Shipping Fee',
    ];

    public const LABEL_TYPE = 'TikTok Shipping Label';

    private const TRANSACTION_VERSIONS = ['202501', '202309'];

    public const BACKFILL_DAYS = 365;

    private const WINDOW_DAYS = 30;

    /** @var array<string, int> */
    private array $typeCounts = [];

    public static function hasRows(string $channel): bool
    {
        return VeeqoChargeback::where('source', self::CHANNELS[$channel]['source'])->exists();
    }

    /**
     * @param  callable(string): void|null  $log
     * @return array{done: bool, message: string, statements: int, transactions: int, saved: int, types: array<string, int>}
     */
    public function sync(string $channel, int $days, ?callable $log = null): array
    {
        $cfg = self::CHANNELS[$channel];
        $service = app($cfg['service']);
        $this->typeCounts = [];
        $end = now();
        $cursor = $end->copy()->subDays(max(1, $days))->startOfDay();
        $statements = 0;
        $seen = 0;
        $saved = 0;

        while ($cursor->lt($end)) {
            $windowEnd = $cursor->copy()->addDays(self::WINDOW_DAYS)->min($end);
            $pageToken = null;
            do {
                $res = $service->financeGet('statements', array_filter([
                    'statement_time_ge' => $cursor->timestamp,
                    'statement_time_lt' => $windowEnd->timestamp,
                    'sort_field' => 'statement_time',
                    'page_size' => 100,
                    'page_token' => $pageToken,
                ]));
                if (! $res['ok']) {
                    return $this->result(false, $cfg['name'].' statements: '.$res['error'], $statements, $seen, $saved);
                }
                foreach ((array) ($res['data']['statements'] ?? []) as $statement) {
                    $statements++;
                    $r = $this->syncStatement($service, $cfg, (array) $statement);
                    if ($r['error']) {
                        return $this->result(false, $cfg['name'].' statement '.($statement['id'] ?? '?').': '.$r['error'], $statements, $seen, $saved);
                    }
                    $seen += $r['seen'];
                    $saved += $r['saved'];
                }
                $pageToken = $res['data']['next_page_token'] ?? null;
                if ($log) {
                    $log($cfg['name'].' '.$cursor->toDateString().' → '.$windowEnd->toDateString().': '.$statements.' statements, '.$saved.' shipping rows saved so far.');
                }
            } while ($pageToken);
            $cursor = $windowEnd;
        }

        return $this->result(true, $cfg['name'].': checked '.$statements.' statements ('.$seen.' transactions); saved '.$saved.' shipping rows.', $statements, $seen, $saved);
    }

    /**
     * @return array{seen: int, saved: int, error: ?string}
     */
    private function syncStatement(TikTokShopService $service, array $cfg, array $statement): array
    {
        $id = (string) ($statement['id'] ?? '');
        if ($id === '') {
            return ['seen' => 0, 'saved' => 0, 'error' => null];
        }
        $seen = 0;
        $saved = 0;
        foreach (self::TRANSACTION_VERSIONS as $i => $version) {
            $pageToken = null;
            $failed = null;
            do {
                $res = $service->financeGet('statements/'.$id.'/statement_transactions', array_filter([
                    'sort_field' => 'order_create_time',
                    'page_size' => 100,
                    'page_token' => $pageToken,
                ]), $version);
                if (! $res['ok']) {
                    $failed = $res['error'];
                    break;
                }
                $transactions = (array) ($res['data']['transactions'] ?? $res['data']['statement_transactions'] ?? []);
                $seen += count($transactions);
                $saved += $this->store($cfg, $statement, $transactions, (string) ($res['data']['currency'] ?? $statement['currency'] ?? 'USD'));
                $pageToken = $res['data']['next_page_token'] ?? null;
                usleep(200000);
            } while ($pageToken);

            if ($failed === null) {
                return ['seen' => $seen, 'saved' => $saved, 'error' => null];
            }
            if ($seen > 0 || $i === count(self::TRANSACTION_VERSIONS) - 1) {
                return ['seen' => $seen, 'saved' => $saved, 'error' => $failed];
            }
        }

        return ['seen' => $seen, 'saved' => $saved, 'error' => null];
    }

    private function store(array $cfg, array $statement, array $transactions, string $currency): int
    {
        $rows = [];
        $now = now()->format('Y-m-d H:i:s');
        $postedAt = $this->time($statement['statement_time'] ?? null);

        foreach ($transactions as $tx) {
            if (! is_array($tx)) {
                continue;
            }
            $type = strtoupper((string) ($tx['type'] ?? ''));
            $this->typeCounts[$type] = ($this->typeCounts[$type] ?? 0) + 1;

            $breakdown = $this->flatAmounts((array) ($tx['shipping_cost_breakdown'] ?? []));
            if (isset(self::ADJUSTMENT_TYPES[$type])) {
                $total = $this->num($tx['adjustment_amount'] ?? $tx['settlement_amount'] ?? null);
                $description = self::ADJUSTMENT_TYPES[$type];
                $orderId = $this->str($tx['adjustment_order_id'] ?? null, 100) ?? $this->str($tx['order_id'] ?? null, 100);
                $txId = $tx['id'] ?? $tx['adjustment_id'] ?? null;
            } elseif ($type === 'ORDER' && ($label = $this->num($tx['shipping_cost_breakdown']['actual_shipping_fee_amount'] ?? null)) !== null && $label != 0.0) {
                $total = $this->num($tx['shipping_cost_amount'] ?? null) ?? $label;
                $description = self::LABEL_TYPE;
                $orderId = $this->str($tx['order_id'] ?? null, 100);
                $txId = $tx['id'] ?? null;
            } else {
                continue;
            }
            if ($txId === null || $total === null) {
                continue;
            }

            $rows[] = [
                'transaction_id' => mb_substr($cfg['source'].':'.$txId, 0, 128),
                'source' => $cfg['source'],
                'order_number' => $orderId,
                'transaction_type' => $this->str($type, 64),
                'description' => $description,
                'reason' => $this->reason($breakdown),
                'transaction_status' => $this->str($statement['payment_status'] ?? $statement['status'] ?? null, 32),
                'posted_at' => $postedAt,
                'settlement_id' => $this->str($statement['id'] ?? null, 64),
                'marketplace_name' => $cfg['name'],
                'total_amount' => round($total, 2),
                'currency' => $this->str($currency, 10),
                'direction' => $total < 0 ? 'debit' : 'credit',
                'item_types' => $this->str(implode(', ', array_keys($breakdown)), 500),
                'customer_charge' => $breakdown['customer_paid_shipping_fee_amount'] ?? null,
                'carrier_charge' => $breakdown['actual_shipping_fee_amount'] ?? null,
                'components' => json_encode($breakdown),
                'raw' => json_encode($tx),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if ($rows === []) {
            return 0;
        }

        $carriers = $this->carriers($cfg['orders'], array_filter(array_column($rows, 'order_number')));
        foreach ($rows as &$row) {
            $row['carrier'] = $carriers[$row['order_number']] ?? null;
        }
        unset($row);

        $update = array_values(array_diff(array_keys($rows[0]), ['transaction_id', 'created_at']));
        foreach (array_chunk($rows, 200) as $chunk) {
            VeeqoChargeback::query()->upsert($chunk, ['transaction_id'], $update);
        }

        return count($rows);
    }

    /**
     * Non-zero money fields of shipping_cost_breakdown (nested objects flattened).
     *
     * @return array<string, float>
     */
    private function flatAmounts(array $node): array
    {
        $out = [];
        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $out += $this->flatAmounts($value);
            } elseif (($n = $this->num($value)) !== null && $n != 0.0) {
                $out[(string) $key] = round($n, 2);
            }
        }

        return $out;
    }

    private function reason(array $breakdown): ?string
    {
        $labels = [
            'non_standard_weight_fee' => 'Non-standard weight fee',
            'non_standard_dimension_fee' => 'Non-standard dimension fee',
            'hazmat_noncompliance_fee' => 'Hazmat non-compliance fee',
            'return_shipping_fee_amount' => 'Return shipping fee',
            'return_shipping_label_fee_amount' => 'Return label fee',
            'signature_confirmation_fee_amount' => 'Signature confirmation fee',
            'shipping_insurance_fee_amount' => 'Shipping insurance',
        ];
        $hits = array_values(array_intersect_key($labels, $breakdown));

        return $hits ? mb_substr(implode(', ', $hits), 0, 255) : null;
    }

    /**
     * @param  list<string>  $orderIds
     * @return array<string, string>
     */
    private function carriers(string $table, array $orderIds): array
    {
        if ($orderIds === [] || ! Schema::hasTable($table) || ! Schema::hasColumn($table, 'shipping_provider')) {
            return [];
        }

        return DB::table($table)
            ->whereIn('order_id', array_values(array_unique($orderIds)))
            ->whereNotNull('shipping_provider')
            ->where('shipping_provider', '!=', '')
            ->pluck('shipping_provider', 'order_id')
            ->map(fn ($v) => mb_substr((string) $v, 0, 100))
            ->all();
    }

    private function result(bool $done, string $message, int $statements, int $seen, int $saved): array
    {
        arsort($this->typeCounts);

        return ['done' => $done, 'message' => $message, 'statements' => $statements, 'transactions' => $seen, 'saved' => $saved, 'types' => $this->typeCounts];
    }

    private function num(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function str(mixed $value, int $max): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private function time(mixed $value): ?string
    {
        if (! is_numeric($value) || (int) $value <= 0) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $value)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
    }
}
