<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;
use App\Models\WayfairDailyData;
use App\Services\WayfairApiService;
use App\Services\WayfairDailyOrderFetchService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Copy Wayfair-generated label tracking onto the local PO and the linked Shopify order.
 * Declaring the shipment back to Wayfair stays a stub.
 */
class WayfairTrackingSyncService
{
    public function __construct(private WayfairApiService $api)
    {
    }

    /**
     * Copy Wayfair-generated label tracking onto local PO rows.
     *
     * @return array{success: bool, message: string, checked: int, filled: int, skipped: int}
     */
    public function fillMissingSofTracking(int $limit = 40, ?float $deadline = null): array
    {
        if (! Schema::hasTable('wayfair_daily_data')) {
            return ['success' => true, 'message' => 'wayfair_daily_data missing.', 'checked' => 0, 'filled' => 0, 'skipped' => 0];
        }

        $limit = max(1, min(80, $limit));
        $pos = WayfairDailyData::query()
            ->where('po_date', '>=', now()->subDays(45)->toDateString())
            ->where(function ($q) {
                $q->whereNull('raw_payload')
                    ->orWhereRaw("IFNULL(JSON_UNQUOTE(JSON_EXTRACT(raw_payload, '$.tracking_number')), '') = ''");
            })
            ->orderByDesc('po_date')
            ->orderByDesc('id')
            ->limit($limit * 4)
            ->get(['id', 'po_number', 'raw_payload']);

        $byPo = [];
        foreach ($pos as $row) {
            $po = trim((string) $row->po_number);
            if ($po === '' || isset($byPo[$po])) {
                continue;
            }
            $byPo[$po] = true;
            if (count($byPo) >= $limit) {
                break;
            }
        }
        $poNumbers = array_keys($byPo);
        if ($poNumbers === []) {
            $shopify = $this->pushStoredTrackingToShopify(min(25, $limit), $deadline);

            return [
                'success' => true,
                'message' => 'Wayfair: no orders missing tracking. Shopify fulfillments written: '.$shopify.'.',
                'checked' => 0,
                'filled' => 0,
                'skipped' => 0,
            ];
        }

        $found = $this->labelTrackingByPo($poNumbers, $deadline);
        $filled = 0;
        foreach ($found as $po => $hit) {
            $tn = trim((string) ($hit['tracking'] ?? ''));
            if ($tn === '') {
                continue;
            }
            $rows = WayfairDailyData::query()->where('po_number', $po)->get();
            foreach ($rows as $row) {
                $payload = is_array($row->raw_payload) ? $row->raw_payload : [];
                if (is_string($row->raw_payload) && $row->raw_payload !== '') {
                    $decoded = json_decode($row->raw_payload, true);
                    $payload = is_array($decoded) ? $decoded : [];
                }
                $payload['tracking_number'] = $tn;
                if (trim((string) ($hit['carrier'] ?? '')) !== '') {
                    $payload['tracking_company'] = trim((string) $hit['carrier']);
                }
                $row->raw_payload = $payload;
                $row->save();
                $filled++;
            }
        }

        $checked = count($poNumbers);
        $skipped = max(0, $checked - count($found));
        $shopify = $this->pushStoredTrackingToShopify(min(25, $limit), $deadline);

        return [
            'success' => true,
            'checked' => $checked,
            'filled' => $filled,
            'skipped' => $skipped,
            'message' => "Wayfair SOF tracking: checked {$checked} POs, saved tracking on {$filled} rows, still missing {$skipped}. Shopify fulfillments written: {$shopify}.",
        ];
    }

    /**
     * Wayfair label-generation tracking for one PO. Does not invent a number.
     *
     * @return array{tracking: string, carrier: string}|null
     */
    public function trackingForPo(string $poNumber): ?array
    {
        $poNumber = strtoupper(trim($poNumber));
        if ($poNumber === '' || preg_match('/^[A-Z]{2}[A-Z0-9-]{6,}$/', $poNumber) !== 1) {
            return null;
        }

        $cacheKey = 'wayfair.label-tracking.'.$poNumber;
        $cached = Cache::get($cacheKey);
        if ($cached === 'miss') {
            return null;
        }
        if (is_array($cached) && strlen(trim((string) ($cached['tracking'] ?? ''))) >= 8) {
            return [
                'tracking' => strtoupper(preg_replace('/\s+/', '', (string) $cached['tracking']) ?? ''),
                'carrier' => trim((string) ($cached['carrier'] ?? '')),
            ];
        }

        $found = $this->labelTrackingByPo([$poNumber], null);
        $hit = null;
        foreach ($found as $po => $row) {
            if (strcasecmp((string) $po, $poNumber) === 0 && is_array($row)) {
                $hit = $row;
                break;
            }
        }
        $tn = strtoupper(preg_replace('/\s+/', '', (string) ($hit['tracking'] ?? '')) ?? '');
        if ($hit === null || strlen($tn) < 8) {
            Cache::put($cacheKey, 'miss', now()->addMinutes(10));

            return null;
        }

        $ready = [
            'tracking' => $tn,
            'carrier' => trim((string) ($hit['carrier'] ?? '')),
        ];
        Cache::put($cacheKey, $ready, now()->addMinutes(30));
        $this->saveTrackingForPo($poNumber, $ready);

        return $ready;
    }

    /**
     * Wayfair label tracking for many POs in a few calls; hits are saved on the PO rows and
     * cached like trackingForPo, so later per-order lookups need no Wayfair call.
     *
     * @param  list<string>  $poNumbers
     * @return array<string, array{tracking: string, carrier: string}> upper-case PO => tracking
     */
    public function prefetchLabelTracking(array $poNumbers, ?float $deadline = null): array
    {
        $want = [];
        foreach ($poNumbers as $po) {
            $po = strtoupper(trim((string) $po));
            if ($po !== '' && preg_match('/^[A-Z]{2}[A-Z0-9-]{6,}$/', $po) === 1) {
                $want[$po] = true;
            }
        }

        $out = [];
        foreach (array_chunk(array_keys($want), 25) as $chunk) {
            if ($deadline !== null && microtime(true) >= $deadline) {
                break;
            }
            $found = [];
            foreach ($this->labelTrackingByPo($chunk, $deadline) as $po => $hit) {
                $found[strtoupper(trim((string) $po))] = $hit;
            }
            foreach ($chunk as $po) {
                $tn = strtoupper(preg_replace('/\s+/', '', (string) ($found[$po]['tracking'] ?? '')) ?? '');
                if (strlen($tn) < 8) {
                    Cache::put('wayfair.label-tracking.'.$po, 'miss', now()->addMinutes(10));

                    continue;
                }
                $ready = ['tracking' => $tn, 'carrier' => trim((string) ($found[$po]['carrier'] ?? ''))];
                Cache::put('wayfair.label-tracking.'.$po, $ready, now()->addMinutes(30));
                $this->saveTrackingForPo($po, $ready);
                $out[$po] = $ready;
            }
        }

        return $out;
    }

    /**
     * @param  array{tracking: string, carrier: string}  $hit
     */
    public function saveTrackingForPo(string $poNumber, array $hit): void
    {
        $poNumber = trim($poNumber);
        $tn = strtoupper(preg_replace('/\s+/', '', (string) ($hit['tracking'] ?? '')) ?? '');
        if ($poNumber === '' || strlen($tn) < 8 || ! Schema::hasTable('wayfair_daily_data')) {
            return;
        }

        $rows = WayfairDailyData::query()->where('po_number', $poNumber)->get();
        foreach ($rows as $row) {
            $payload = is_array($row->raw_payload) ? $row->raw_payload : [];
            if (is_string($row->raw_payload) && $row->raw_payload !== '') {
                $decoded = json_decode($row->raw_payload, true);
                $payload = is_array($decoded) ? $decoded : [];
            }
            $payload['tracking_number'] = $tn;
            $carrier = trim((string) ($hit['carrier'] ?? ''));
            if ($carrier !== '') {
                $payload['tracking_company'] = $carrier;
                $payload['carrier'] = $carrier;
            }
            $row->raw_payload = $payload;
            $row->save();
        }
    }

    /**
     * Write a real Wayfair label number onto the linked Shopify order.
     */
    public function pushStoredTrackingToShopify(int $limit = 25, ?float $deadline = null): int
    {
        if (! Schema::hasTable('wayfair_daily_data') || ! Schema::hasColumn('wayfair_daily_data', 'shopify_order_id')) {
            return 0;
        }

        $limit = max(1, min(40, $limit));
        $rows = WayfairDailyData::query()
            ->where('po_date', '>=', now()->subDays(21)->toDateString())
            ->whereNotNull('shopify_order_id')
            ->where('shopify_order_id', '!=', '')
            ->where('shopify_order_id', 'not like', 'manual%')
            ->whereRaw("IFNULL(JSON_UNQUOTE(JSON_EXTRACT(raw_payload, '$.tracking_number')), '') <> ''")
            ->orderByDesc('po_date')
            ->orderByDesc('id')
            ->limit($limit * 4)
            ->get(['id', 'po_number', 'shopify_order_id']);

        $written = 0;
        $seenPo = [];
        foreach ($rows as $row) {
            if ($written >= $limit) {
                break;
            }
            if ($deadline !== null && microtime(true) >= $deadline) {
                break;
            }
            $po = strtoupper(trim((string) $row->po_number));
            if ($po === '' || isset($seenPo[$po])) {
                continue;
            }
            $seenPo[$po] = true;
            $cacheKey = 'wayfair.shopify-fulfill-tried.'.(int) $row->id;
            if (Cache::has($cacheKey)) {
                continue;
            }
            try {
                $result = app(VeeqoShopifyFulfillmentService::class)->fulfillMarketplaceOrder('wayfair', (int) $row->id);
            } catch (\Throwable $e) {
                Log::info('WayfairTrackingSyncService: Shopify fulfill failed', [
                    'po' => $po,
                    'error' => $e->getMessage(),
                ]);
                Cache::put($cacheKey, 1, now()->addMinutes(10));
                continue;
            }
            $action = (string) ($result['action'] ?? '');
            if ($action === 'shopify_fulfilled') {
                Cache::put($cacheKey, 1, now()->addHours(12));
                $written++;
            } elseif ($action === 'already_on_shopify') {
                Cache::put($cacheKey, 1, now()->addHours(12));
            } else {
                Cache::put($cacheKey, 1, now()->addMinutes(15));
            }
        }

        return $written;
    }

    /**
     * @param  list<string>  $poNumbers
     * @return array<string, array{tracking: string, carrier: string}>
     */
    protected function labelTrackingByPo(array $poNumbers, ?float $deadline): array
    {
        if ($deadline !== null && microtime(true) >= $deadline) {
            return [];
        }

        try {
            $token = $this->api->getAccessTokenWithScope(null);
        } catch (\Throwable $e) {
            Log::warning('WayfairTrackingSyncService: token failed', ['error' => $e->getMessage()]);

            return [];
        }
        if ($token === '') {
            return [];
        }

        $query = <<<'GRAPHQL'
        query LabelEvents($limit: Int32, $filters: [LabelGenerationEventFilterInput]) {
            labelGenerationEvents(limit: $limit, filters: $filters) {
                poNumber
                shippingLabelInfo {
                    carrier
                    carrierCode
                    trackingNumber
                }
                generatedShippingLabels {
                    poNumber
                    fullPoNumber
                    carrier
                    carrierCode
                    trackingNumber
                }
            }
        }
        GRAPHQL;

        $response = Http::withoutVerifying()
            ->connectTimeout(8)
            ->timeout(20)
            ->withToken($token)
            ->post(WayfairDailyOrderFetchService::GRAPHQL_URL, [
                'query' => $query,
                'variables' => [
                    'limit' => max(20, count($poNumbers) * 4),
                    'filters' => [[
                        'field' => 'poNumber',
                        'in' => array_values($poNumbers),
                    ]],
                ],
            ]);

        $json = $response->json();
        $errors = is_array($json['errors'] ?? null) ? $json['errors'] : [];
        if (! $response->successful() || $errors !== []) {
            Log::warning('WayfairTrackingSyncService: label events query failed', [
                'status' => $response->status(),
                'errors' => $errors,
            ]);

            return $this->labelTrackingOneByOne($token, $poNumbers, $deadline);
        }

        return $this->mapLabelEvents(is_array($json['data']['labelGenerationEvents'] ?? null) ? $json['data']['labelGenerationEvents'] : []);
    }

    /**
     * @param  list<string>  $poNumbers
     * @return array<string, array{tracking: string, carrier: string}>
     */
    protected function labelTrackingOneByOne(string $token, array $poNumbers, ?float $deadline): array
    {
        $out = [];
        $query = <<<'GRAPHQL'
        query LabelEvent($limit: Int32, $filters: [LabelGenerationEventFilterInput]) {
            labelGenerationEvents(limit: $limit, filters: $filters) {
                poNumber
                shippingLabelInfo { carrier carrierCode trackingNumber }
                generatedShippingLabels { poNumber carrier carrierCode trackingNumber }
            }
        }
        GRAPHQL;

        foreach (array_slice($poNumbers, 0, 15) as $po) {
            if ($deadline !== null && microtime(true) >= $deadline) {
                break;
            }
            $response = Http::withoutVerifying()
                ->connectTimeout(8)
                ->timeout(15)
                ->withToken($token)
                ->post(WayfairDailyOrderFetchService::GRAPHQL_URL, [
                    'query' => $query,
                    'variables' => [
                        'limit' => 5,
                        'filters' => [[
                            'field' => 'poNumber',
                            'equals' => $po,
                        ]],
                    ],
                ]);
            $json = $response->json();
            if (! $response->successful() || ! empty($json['errors'])) {
                Log::info('WayfairTrackingSyncService: single PO label lookup failed', [
                    'po' => $po,
                    'errors' => $json['errors'] ?? $response->status(),
                ]);
                continue;
            }
            $out = array_merge($out, $this->mapLabelEvents(is_array($json['data']['labelGenerationEvents'] ?? null) ? $json['data']['labelGenerationEvents'] : []));
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @return array<string, array{tracking: string, carrier: string}>
     */
    protected function mapLabelEvents(array $events): array
    {
        $out = [];
        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }
            $po = trim((string) ($event['poNumber'] ?? ''));
            $hit = $this->trackingFromLabelNode($event['shippingLabelInfo'] ?? null);
            if ($hit === null && is_array($event['generatedShippingLabels'] ?? null)) {
                $labels = $event['generatedShippingLabels'];
                $labels = array_is_list($labels) ? $labels : [$labels];
                foreach ($labels as $label) {
                    $hit = $this->trackingFromLabelNode($label);
                    if ($hit !== null) {
                        if ($po === '') {
                            $po = trim((string) ($label['poNumber'] ?? $label['fullPoNumber'] ?? ''));
                        }
                        break;
                    }
                }
            }
            if ($po !== '' && $hit !== null) {
                $out[$po] = $hit;
            }
        }

        return $out;
    }

    /**
     * @return array{tracking: string, carrier: string}|null
     */
    protected function trackingFromLabelNode(mixed $node): ?array
    {
        if (! is_array($node)) {
            return null;
        }
        $tn = trim((string) ($node['trackingNumber'] ?? ''));
        if ($tn === '' || strlen($tn) < 6) {
            return null;
        }
        $carrier = trim((string) ($node['carrier'] ?? $node['carrierCode'] ?? ''));

        return ['tracking' => $tn, 'carrier' => $carrier];
    }

    /**
     * @return array{success: bool, skipped?: bool, message: string, action?: string|null}
     */
    public function pushTrackingForOrder(WayfairDailyData $order): array
    {
        unset($order);

        if (! self::canPushTracking()) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Push tracking to Wayfair is Off in settings.',
            ];
        }

        return [
            'success' => false,
            'skipped' => true,
            'action' => 'not_implemented',
            'message' => 'Wayfair tracking push is not implemented yet.',
        ];
    }

    /**
     * @return array{success: bool, message: string, attempted: int, pushed: int, skipped: int}
     */
    public function syncPending(int $limit = 40): array
    {
        unset($limit);

        if (! self::canPushTracking()) {
            return [
                'success' => true,
                'message' => 'Tracking push disabled.',
                'attempted' => 0,
                'pushed' => 0,
                'skipped' => 0,
            ];
        }

        Log::info('WayfairTrackingSyncService: syncPending skipped (not implemented)');

        return [
            'success' => true,
            'message' => 'Wayfair tracking push is not implemented yet.',
            'attempted' => 0,
            'pushed' => 0,
            'skipped' => 0,
        ];
    }

    public static function canPushTracking(?array $settings = null): bool
    {
        $settings ??= MarketplaceSyncSettings::getFor('wayfair');

        return (bool) ($settings['order']['push_tracking_to_wayfair'] ?? true);
    }
}
