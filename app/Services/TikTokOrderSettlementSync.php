<?php

namespace App\Services;

use App\Models\TiktokOrderSettlement;
use App\Services\Veeqo\TikTokChargebackSync;
use Carbon\Carbon;

/**
 * Copies what TikTok actually paid us per order (Finance API statement_transactions
 * -> settlement_amount) into tiktok_order_settlements.
 *
 * The Orders API only knows customer-side prices (sale_price, discounts), so it cannot tell
 * what the seller receives when TikTok funds a discount. The Finance API can.
 */
class TikTokOrderSettlementSync
{
    /** Transaction types that belong to an order's payout. REFUND rows are negative and net the order down. */
    public const ORDER_TYPES = ['ORDER', 'REFUND'];

    public const BACKFILL_DAYS = 90;

    private const WINDOW_DAYS = 30;

    /** Newest version first; older shops/regions only answer on 202309. */
    private const VERSIONS = ['202501', '202309'];

    /** @var array<string, int> */
    private array $typeCounts = [];

    public static function hasRows(string $channel): bool
    {
        return TiktokOrderSettlement::tableReady()
            && TiktokOrderSettlement::query()->where('channel', $channel)->exists();
    }

    /**
     * @param  callable(string): void|null  $log
     * @return array{done: bool, message: string, statements: int, transactions: int, saved: int, types: array<string, int>}
     */
    public function sync(string $channel, int $days, ?callable $log = null): array
    {
        $cfg = TikTokChargebackSync::CHANNELS[$channel];
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
                    $r = $this->syncStatement($service, $channel, (array) $statement);
                    if ($r['error']) {
                        return $this->result(false, $cfg['name'].' statement '.($statement['id'] ?? '?').': '.$r['error'], $statements, $seen, $saved);
                    }
                    $seen += $r['seen'];
                    $saved += $r['saved'];
                }
                $pageToken = $res['data']['next_page_token'] ?? null;
                if ($log) {
                    $log($cfg['name'].' '.$cursor->toDateString().' → '.$windowEnd->toDateString().': '.$statements.' statements, '.$saved.' order settlements saved so far.');
                }
            } while ($pageToken);
            $cursor = $windowEnd;
        }

        return $this->result(true, $cfg['name'].': checked '.$statements.' statements ('.$seen.' transactions); saved '.$saved.' order settlements.', $statements, $seen, $saved);
    }

    /**
     * Settlement transactions of ONE order straight from the Finance API. Read-only: used to verify
     * which field carries the payout; nothing is stored (the statement sync is the single writer).
     *
     * @return array{ok: bool, version: ?string, transactions: list<array<string, mixed>>, error: ?string}
     */
    public function fetchForOrder(string $channel, string $orderId): array
    {
        $service = app(TikTokChargebackSync::CHANNELS[$channel]['service']);
        $error = null;
        foreach (self::VERSIONS as $version) {
            $res = $service->financeGet('orders/'.$orderId.'/statement_transactions', [], $version);
            if (! $res['ok']) {
                $error = $res['error'];

                continue;
            }
            $tx = (array) ($res['data']['statement_transactions'] ?? $res['data']['transactions'] ?? []);

            return ['ok' => true, 'version' => $version, 'transactions' => array_values(array_filter($tx, 'is_array')), 'error' => null];
        }

        return ['ok' => false, 'version' => null, 'transactions' => [], 'error' => $error];
    }

    /**
     * @return array{seen: int, saved: int, error: ?string}
     */
    private function syncStatement(TikTokShopService $service, string $channel, array $statement): array
    {
        $id = (string) ($statement['id'] ?? '');
        if ($id === '') {
            return ['seen' => 0, 'saved' => 0, 'error' => null];
        }
        $seen = 0;
        $saved = 0;
        foreach (self::VERSIONS as $i => $version) {
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
                $saved += $this->store($channel, $statement, $transactions, (string) ($res['data']['currency'] ?? $statement['currency'] ?? 'USD'));
                $pageToken = $res['data']['next_page_token'] ?? null;
                usleep(200000);
            } while ($pageToken);

            if ($failed === null) {
                return ['seen' => $seen, 'saved' => $saved, 'error' => null];
            }
            if ($seen > 0 || $i === count(self::VERSIONS) - 1) {
                return ['seen' => $seen, 'saved' => $saved, 'error' => $failed];
            }
        }

        return ['seen' => $seen, 'saved' => $saved, 'error' => null];
    }

    /**
     * @param  list<mixed>  $transactions
     */
    private function store(string $channel, array $statement, array $transactions, string $currency): int
    {
        $rows = [];
        $now = now()->format('Y-m-d H:i:s');
        $statementId = $this->str($statement['id'] ?? null, 64);
        $statementTime = $this->time($statement['statement_time'] ?? null);

        foreach ($transactions as $tx) {
            if (! is_array($tx)) {
                continue;
            }
            $type = strtoupper((string) ($tx['type'] ?? ''));
            $this->typeCounts[$type] = ($this->typeCounts[$type] ?? 0) + 1;

            $orderId = $this->str($tx['order_id'] ?? null, 64);
            $settlement = $this->num($tx['settlement_amount'] ?? null);
            if (! in_array($type, self::ORDER_TYPES, true) || $orderId === null || $settlement === null) {
                continue;
            }

            $txId = (string) ($tx['id'] ?? $tx['transaction_id'] ?? '');
            $rows[] = [
                'channel' => $channel,
                'transaction_key' => sha1($channel.'|'.($statementId ?? ($tx['statement_id'] ?? '')).'|'.$type.'|'.($txId !== '' ? $txId : $orderId)),
                'order_id' => $orderId,
                'transaction_type' => $this->str($type, 64),
                'statement_id' => $statementId ?? $this->str($tx['statement_id'] ?? null, 64),
                'statement_time' => $statementTime,
                'payment_status' => $this->str($statement['payment_status'] ?? $statement['status'] ?? null, 32),
                'settlement_amount' => round($settlement, 2),
                'revenue_amount' => $this->rounded($tx['revenue_amount'] ?? null),
                'fee_amount' => $this->rounded($tx['fee_amount'] ?? $tx['fee_tax_amount'] ?? null),
                'shipping_cost_amount' => $this->rounded($tx['shipping_cost_amount'] ?? null),
                'currency' => $this->str($tx['currency'] ?? $currency, 16),
                'raw_json' => json_encode($tx),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if ($rows === []) {
            return 0;
        }

        $update = array_values(array_diff(array_keys($rows[0]), ['transaction_key', 'created_at']));
        foreach (array_chunk($rows, 200) as $chunk) {
            TiktokOrderSettlement::query()->upsert($chunk, ['transaction_key'], $update);
        }

        return count($rows);
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

    private function rounded(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 2) : null;
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
