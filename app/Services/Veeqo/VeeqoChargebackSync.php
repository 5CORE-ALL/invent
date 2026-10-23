<?php

namespace App\Services\Veeqo;

use App\Models\VeeqoChargeback;
use App\Services\AmazonSpApiService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Veeqo labels are Amazon Buy Shipping labels, so carrier chargebacks land in Amazon
 * Finances as "Labman*" adjustments (LabmanLabelChargeBack = chargeback debit outbound).
 * Finances 2024-06-19 /transactions allows at most 180 days per request; we walk 30-day windows.
 */
class VeeqoChargebackSync
{
    private const ENDPOINT = 'https://sellingpartnerapi-na.amazon.com/finances/2024-06-19/transactions';

    private const MARKETPLACE_ID = 'ATVPDKIKX0DER';

    private const WINDOW_DAYS = 30;

    private ?string $token = null;

    /**
     * @param  callable(string): void|null  $log
     * @return array{done: bool, message: string, pages: int, transactions: int, saved: int}
     */
    public function sync(int $days, ?callable $log = null): array
    {
        $end = now()->subMinutes(5);
        $cursor = $end->copy()->subDays(max(1, $days))->startOfDay();
        $pages = 0;
        $seen = 0;
        $saved = 0;

        while ($cursor->lt($end)) {
            $windowEnd = $cursor->copy()->addDays(self::WINDOW_DAYS);
            if ($windowEnd->gt($end)) {
                $windowEnd = $end->copy();
            }

            $next = null;
            do {
                $query = $next
                    ? ['nextToken' => $next]
                    : [
                        'postedAfter' => $cursor->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                        'postedBefore' => $windowEnd->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                        'marketplaceId' => self::MARKETPLACE_ID,
                    ];
                $res = $this->get($query);
                if (! $res['ok']) {
                    return ['done' => false, 'message' => $res['message'], 'pages' => $pages, 'transactions' => $seen, 'saved' => $saved];
                }
                $transactions = $res['transactions'];
                $seen += count($transactions);
                $saved += $this->store($transactions);
                $next = $res['next'];
                $pages++;
                if ($log) {
                    $log($cursor->toDateString().' → '.$windowEnd->toDateString().': page '.$pages.', '.$saved.' label adjustments saved so far.');
                }
                usleep(550000);
            } while ($next);

            $cursor = $windowEnd;
        }

        return [
            'done' => true,
            'message' => 'Checked '.$seen.' Amazon transactions; saved '.$saved.' Veeqo label adjustments.',
            'pages' => $pages,
            'transactions' => $seen,
            'saved' => $saved,
        ];
    }

    /**
     * @param  array<string, string>  $query
     * @return array{ok: bool, message: string, transactions: list<array<string, mixed>>, next: ?string}
     */
    private function get(array $query): array
    {
        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $token = $this->token();
            if ($token === '') {
                return ['ok' => false, 'message' => 'Amazon SP-API access token unavailable (check SPAPI_* credentials).', 'transactions' => [], 'next' => null];
            }
            try {
                $res = Http::withoutVerifying()
                    ->timeout(90)
                    ->acceptJson()
                    ->withHeaders(['x-amz-access-token' => $token])
                    ->get(self::ENDPOINT, $query);
            } catch (\Throwable $e) {
                if ($attempt === 6) {
                    return ['ok' => false, 'message' => 'Amazon Finances request failed: '.$e->getMessage(), 'transactions' => [], 'next' => null];
                }
                sleep(3);

                continue;
            }

            if ($res->status() === 429 || $res->serverError()) {
                sleep(min(30, 2 * $attempt));

                continue;
            }
            if (in_array($res->status(), [401, 403], true) && $attempt === 1) {
                $this->token = null;

                continue;
            }
            if (! $res->successful()) {
                $err = (string) ($res->json('errors.0.message') ?? substr($res->body(), 0, 300));
                Log::warning('Veeqo chargeback sync failed', ['status' => $res->status(), 'error' => $err]);

                return ['ok' => false, 'message' => 'Amazon Finances '.$res->status().': '.$err, 'transactions' => [], 'next' => null];
            }

            $list = $res->json('payload.transactions');

            return [
                'ok' => true,
                'message' => 'OK',
                'transactions' => is_array($list) ? array_values(array_filter($list, 'is_array')) : [],
                'next' => ($n = $res->json('payload.nextToken')) ? (string) $n : null,
            ];
        }

        return ['ok' => false, 'message' => 'Amazon Finances kept throttling; run the sync again.', 'transactions' => [], 'next' => null];
    }

    private function token(): string
    {
        if ($this->token !== null) {
            return $this->token;
        }
        try {
            $this->token = (string) (app(AmazonSpApiService::class)->getAccessToken() ?? '');
        } catch (\Throwable $e) {
            $this->token = '';
        }
        if ($this->token !== '') {
            return $this->token;
        }

        try {
            $res = Http::withoutVerifying()->asForm()->timeout(30)->post('https://api.amazon.com/auth/o2/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => config('services.amazon_sp.refresh_token'),
                'client_id' => config('services.amazon_sp.client_id'),
                'client_secret' => config('services.amazon_sp.client_secret'),
            ]);
            $this->token = (string) ($res->json('access_token') ?? '');
            if ($this->token === '') {
                Log::warning('Veeqo chargeback sync: Amazon token refresh failed', [
                    'status' => $res->status(),
                    'error' => $res->json('error_description') ?? $res->json('error'),
                ]);
            }
        } catch (\Throwable $e) {
            $this->token = '';
        }

        return $this->token;
    }

    /**
     * @param  list<array<string, mixed>>  $transactions
     */
    private function store(array $transactions): int
    {
        $rows = [];
        $now = now()->format('Y-m-d H:i:s');
        foreach ($transactions as $tx) {
            $description = (string) ($tx['description'] ?? '');
            if (stripos($description, 'labman') !== 0 || empty($tx['transactionId'])) {
                continue;
            }

            $ids = [];
            foreach ((array) ($tx['relatedIdentifiers'] ?? []) as $rel) {
                $name = (string) ($rel['relatedIdentifierName'] ?? '');
                if ($name !== '') {
                    $ids[$name] = (string) ($rel['relatedIdentifierValue'] ?? '');
                }
            }

            $itemTypes = [];
            $components = [];
            $customer = null;
            $carrier = null;
            foreach ((array) ($tx['items'] ?? []) as $item) {
                $itemTypes[] = (string) ($item['description'] ?? '');
                $this->walkBreakdowns((array) ($item['breakdowns'] ?? []), $components, $customer, $carrier);
            }
            if ($components === []) {
                foreach ((array) ($tx['items'] ?? []) as $item) {
                    $type = (string) ($item['description'] ?? '');
                    $amount = $item['totalAmount']['currencyAmount'] ?? null;
                    if ($type !== '' && is_numeric($amount)) {
                        $components[$type] = round(($components[$type] ?? 0) + (float) $amount, 2);
                    }
                }
            }

            $total = $tx['totalAmount']['currencyAmount'] ?? null;
            $total = is_numeric($total) ? round((float) $total, 2) : null;

            $rows[] = [
                'transaction_id' => mb_substr((string) $tx['transactionId'], 0, 128),
                'source' => 'amazon_api',
                'order_number' => $this->str($ids['ORDER_ID'] ?? null, 100),
                'transaction_type' => $this->str($tx['transactionType'] ?? null, 64),
                'description' => $this->str($description, 100),
                'transaction_status' => $this->str($tx['transactionStatus'] ?? null, 32),
                'posted_at' => $this->date($tx['postedDate'] ?? null),
                'amazon_order_id' => $this->str($ids['ORDER_ID'] ?? null, 64),
                'settlement_id' => $this->str($ids['SETTLEMENT_ID'] ?? null, 64),
                'financial_event_group_id' => $this->str($ids['FINANCIAL_EVENT_GROUP_ID'] ?? null, 128),
                'marketplace_name' => $this->str($tx['marketplaceDetails']['marketplaceName'] ?? null, 100),
                'total_amount' => $total,
                'currency' => $this->str($tx['totalAmount']['currencyCode'] ?? null, 10),
                'direction' => $total === null ? null : ($total < 0 ? 'debit' : 'credit'),
                'item_types' => $this->str(implode(', ', array_unique(array_filter($itemTypes))), 500),
                'customer_charge' => $customer,
                'carrier_charge' => $carrier,
                'components' => json_encode($components),
                'raw' => json_encode($tx),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if ($rows === []) {
            return 0;
        }

        $update = array_values(array_diff(array_keys($rows[0]), ['transaction_id', 'created_at']));
        foreach (array_chunk($rows, 200) as $chunk) {
            VeeqoChargeback::query()->upsert($chunk, ['transaction_id'], $update);
        }

        return count($rows);
    }

    /**
     * Leaf breakdowns become components (PostageBilling_Postage, ..._FuelSurcharge, ...).
     * CustomerPackagingCharge / CarrierPackagingCharge are what you paid vs what the carrier billed.
     *
     * @param  list<array<string, mixed>>  $breakdowns
     * @param  array<string, float>  $components
     */
    private function walkBreakdowns(array $breakdowns, array &$components, ?float &$customer, ?float &$carrier): void
    {
        foreach ($breakdowns as $node) {
            if (! is_array($node)) {
                continue;
            }
            $type = (string) ($node['breakdownType'] ?? '');
            $amount = $node['breakdownAmount']['currencyAmount'] ?? null;
            $amount = is_numeric($amount) ? (float) $amount : null;
            if ($type === 'CustomerPackagingCharge' && $amount !== null) {
                $customer = round(($customer ?? 0) + $amount, 2);
            }
            if ($type === 'CarrierPackagingCharge' && $amount !== null) {
                $carrier = round(($carrier ?? 0) + $amount, 2);
            }
            $children = is_array($node['breakdowns'] ?? null) ? $node['breakdowns'] : [];
            if ($children !== []) {
                $this->walkBreakdowns($children, $components, $customer, $carrier);

                continue;
            }
            if ($type !== '' && $amount !== null && ! in_array($type, ['Other', 'CustomerPackagingCharge', 'CarrierPackagingCharge'], true)) {
                $components[$type] = round(($components[$type] ?? 0) + $amount, 2);
            }
        }
    }

    private function str(mixed $value, int $max): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private function date(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        try {
            return Carbon::parse($value)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
