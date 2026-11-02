<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;
use App\Models\OrderFulfillmentTracking;
use App\Models\WayfairDailyData;
use App\Services\WayfairApiService;
use App\Services\WayfairDailyOrderFetchService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Copy Wayfair-generated label tracking onto the local PO and the linked Shopify order,
 * then send Wayfair an ASN for POs that already have a tracking number.
 * The order pull is a different job and is not called from here.
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
        if (! self::canPushTracking()) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Push tracking to Wayfair is Off in settings.',
            ];
        }

        try {
            return $this->sendAsnForPo((string) $order->po_number);
        } catch (\Throwable $e) {
            Log::warning('Wayfair ASN send failed', ['po' => $order->po_number, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Send ASNs for recent POs that have a tracking number and have not been accepted yet.
     * One PO failing is recorded and skipped. This never throws, so the order pull is not affected.
     *
     * @return array{success: bool, message: string, attempted: int, pushed: int, skipped: int}
     */
    public function syncPending(int $limit = 40): array
    {
        if (! self::canPushTracking()) {
            return [
                'success' => true,
                'message' => 'Tracking push disabled.',
                'attempted' => 0,
                'pushed' => 0,
                'skipped' => 0,
            ];
        }
        if (! Schema::hasTable('wayfair_daily_data')) {
            return ['success' => true, 'message' => 'wayfair_daily_data missing.', 'attempted' => 0, 'pushed' => 0, 'skipped' => 0];
        }

        $limit = max(1, min(40, $limit));
        $rows = WayfairDailyData::query()
            ->where('po_date', '>=', now()->subDays(14)->toDateString())
            ->orderByDesc('po_date')
            ->orderByDesc('id')
            ->limit($limit * 8)
            ->get();

        $byPo = [];
        foreach ($rows as $row) {
            $po = strtoupper(trim((string) $row->po_number));
            if ($po === '' || isset($byPo[$po])) {
                continue;
            }
            $byPo[$po] = $row;
            if (count($byPo) >= $limit) {
                break;
            }
        }

        $attempted = 0;
        $pushed = 0;
        $skipped = 0;
        foreach ($byPo as $po => $row) {
            try {
                $result = $this->sendAsnForPo($po, $row);
            } catch (\Throwable $e) {
                Log::warning('Wayfair ASN send failed', ['po' => $po, 'error' => $e->getMessage()]);
                $skipped++;
                continue;
            }
            if (! empty($result['skipped'])) {
                $skipped++;
                continue;
            }
            $attempted++;
            if (! empty($result['success'])) {
                $pushed++;
            }
        }

        return [
            'success' => true,
            'attempted' => $attempted,
            'pushed' => $pushed,
            'skipped' => $skipped,
            'message' => "Wayfair ASN: sent {$pushed}, not accepted ".max(0, $attempted - $pushed).", skipped {$skipped}.",
        ];
    }

    /**
     * @return array{success: bool, skipped?: bool, message: string, action?: string|null}
     */
    protected function sendAsnForPo(string $poNumber, ?WayfairDailyData $sample = null): array
    {
        $poNumber = trim($poNumber);
        $lines = WayfairDailyData::query()->where('po_number', $poNumber)->get();
        if ($lines->isEmpty() && $sample !== null) {
            $lines = collect([$sample]);
        }
        if ($lines->isEmpty()) {
            return ['success' => false, 'skipped' => true, 'message' => 'PO not stored.'];
        }

        $line = $lines->first();
        $payload = $this->payloadOf($line);
        if (trim((string) ($payload['asn_sent_at'] ?? '')) !== '') {
            return ['success' => true, 'skipped' => true, 'action' => 'already_sent', 'message' => 'ASN already sent.'];
        }
        $errorAt = trim((string) ($payload['asn_error_at'] ?? ''));
        if ($errorAt !== '' && abs(now()->diffInMinutes($errorAt)) < 360) {
            return ['success' => false, 'skipped' => true, 'message' => 'ASN retry is waiting after the last Wayfair error.'];
        }

        $tracking = $this->trackingForAsn($poNumber, $payload, (string) ($line->carrier_code ?? ''));
        if ($tracking === null) {
            return ['success' => false, 'skipped' => true, 'message' => 'No tracking number yet, so no ASN was sent.'];
        }

        $shipFrom = $this->api->shipFromForPo($poNumber);
        $supplierId = (int) ($shipFrom['supplierId'] ?? 0);
        if ($supplierId <= 0) {
            $supplierId = (int) ($line->warehouse_id ?? 0);
        }
        if ($supplierId <= 0) {
            $supplierId = (int) config('services.wayfair.supplier_id');
        }
        $source = $this->sourceAddress($shipFrom, $line);
        $destination = $this->destinationAddress($line);
        if ($source === null || $destination === null || $supplierId <= 0) {
            $this->rememberAsnError($lines, 'Ship-from or delivery address is missing, so the ASN was not sent.');

            return ['success' => false, 'message' => 'Ship-from or delivery address is missing, so the ASN was not sent.'];
        }

        $items = [];
        foreach ($lines as $row) {
            $sku = trim((string) $row->sku);
            if ($sku === '') {
                continue;
            }
            $items[] = ['partNumber' => $sku, 'quantity' => max(1, (int) $row->quantity)];
        }
        if ($items === []) {
            return ['success' => false, 'skipped' => true, 'message' => 'PO has no SKU lines.'];
        }

        $speed = strtoupper(trim((string) ($line->ship_speed ?? '')));
        $speed = $speed !== '' ? str_replace([' ', '-'], '_', $speed) : 'GROUND';
        $notice = [
            'poNumber' => $poNumber,
            'supplierId' => $supplierId,
            'packageCount' => 1,
            'weight' => 1,
            'volume' => 1,
            'carrierCode' => $tracking['carrier'],
            'shipSpeed' => $speed,
            'trackingNumber' => $tracking['number'],
            'shipDate' => now('America/New_York')->format('Y-m-d H:i:s.000000 P'),
            'sourceAddress' => $source,
            'destinationAddress' => $destination,
            'smallParcelShipments' => [[
                'package' => [
                    'code' => ['type' => 'TRACKING_NUMBER', 'value' => $tracking['number']],
                    'weight' => 1,
                ],
                'items' => $items,
            ]],
        ];

        $sent = $this->api->sendShipNotice($notice);
        $message = (string) ($sent['message'] ?? '');
        if (! empty($sent['success']) || $this->asnAlreadyRecorded($message)) {
            $this->rememberAsnSent($lines, $tracking['number'], (string) ($sent['handle'] ?? ''), (string) ($sent['status'] ?? ''));

            return ['success' => true, 'action' => 'asn_sent', 'message' => $message !== '' ? $message : 'ASN sent.'];
        }
        $this->rememberAsnError($lines, $message !== '' ? $message : 'Wayfair rejected the ASN.');

        return ['success' => false, 'message' => $message !== '' ? $message : 'Wayfair rejected the ASN.'];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{number: string, carrier: string}|null
     */
    protected function trackingForAsn(string $poNumber, array $payload, string $poCarrier = ''): ?array
    {
        $number = strtoupper(preg_replace('/\s+/', '', (string) ($payload['tracking_number'] ?? '')) ?? '');
        $carrier = trim((string) ($payload['tracking_company'] ?? ''));
        if ($number === '' && Schema::hasTable('order_fulfillment_trackings')) {
            $row = OrderFulfillmentTracking::query()
                ->where('mm_slug', 'wayfair')
                ->where('order_id', $poNumber)
                ->whereNotNull('tracking_number')
                ->where('tracking_number', '!=', '')
                ->orderByDesc('id')
                ->first(['tracking_number', 'carrier']);
            if ($row) {
                $number = strtoupper(preg_replace('/\s+/', '', (string) $row->tracking_number) ?? '');
                $carrier = trim((string) ($row->carrier ?? $carrier));
            }
        }
        if (strlen($number) < 8) {
            return null;
        }
        if ($carrier === '') {
            $carrier = $poCarrier;
        }
        $code = $this->wayfairCarrierCode($carrier);
        if ($code === '') {
            return null;
        }

        return ['number' => $number, 'carrier' => $code];
    }

    protected function wayfairCarrierCode(string $carrier): string
    {
        $key = strtoupper(trim($carrier));
        if ($key === '') {
            return '';
        }
        foreach (['UPSN' => 'UPSN', 'UPS' => 'UPSN', 'FDEG' => 'FDEG', 'FEDEX' => 'FDEG', 'FDX' => 'FDEG', 'USPS' => 'USPS'] as $needle => $code) {
            if ($key === $needle || str_contains($key, $needle)) {
                return $code;
            }
        }

        return $key;
    }

    /**
     * @param  array<string, mixed>|null  $shipFrom
     * @return array<string, string>|null
     */
    protected function sourceAddress(?array $shipFrom, WayfairDailyData $line): ?array
    {
        $name = trim((string) ($shipFrom['name'] ?? $line->warehouse_name ?? ''));
        $street = trim((string) ($shipFrom['street1'] ?? ''));
        $city = trim((string) ($shipFrom['city'] ?? ''));
        if ($street === '' || $city === '' || $name === '') {
            return null;
        }
        $address = [
            'name' => $name,
            'streetAddress1' => $street,
            'city' => $city,
            'state' => trim((string) ($shipFrom['state'] ?? '')),
            'postalCode' => trim((string) ($shipFrom['postal'] ?? '')),
            'country' => 'US',
        ];
        $street2 = trim((string) ($shipFrom['street2'] ?? ''));
        if ($street2 !== '') {
            $address['streetAddress2'] = $street2;
        }

        return $address;
    }

    /**
     * @return array<string, string>|null
     */
    protected function destinationAddress(WayfairDailyData $line): ?array
    {
        $street = trim((string) $line->customer_address1);
        $city = trim((string) $line->customer_city);
        $name = trim((string) $line->customer_name);
        if ($street === '' || $city === '' || $name === '') {
            return null;
        }
        $country = strtoupper(trim((string) $line->customer_country));
        if ($country === '' || str_contains($country, 'UNITED')) {
            $country = 'US';
        }
        $country = substr($country, 0, 3);
        $address = [
            'name' => $name,
            'streetAddress1' => $street,
            'city' => $city,
            'state' => trim((string) $line->customer_state),
            'postalCode' => trim((string) $line->customer_postal_code),
            'country' => $country !== '' ? $country : 'US',
        ];
        $street2 = trim((string) $line->customer_address2);
        if ($street2 !== '') {
            $address['streetAddress2'] = $street2;
        }

        return $address;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadOf(WayfairDailyData $row): array
    {
        $payload = $row->raw_payload;
        if (is_string($payload) && $payload !== '') {
            $decoded = json_decode($payload, true);
            $payload = is_array($decoded) ? $decoded : [];
        }

        return is_array($payload) ? $payload : [];
    }

    protected function asnAlreadyRecorded(string $message): bool
    {
        $message = strtolower($message);

        return str_contains($message, 'already') && (str_contains($message, 'ship') || str_contains($message, 'asn'));
    }

    protected function rememberAsnSent($lines, string $tracking, string $handle, string $status): void
    {
        foreach ($lines as $row) {
            $payload = $this->payloadOf($row);
            $payload['asn_sent_at'] = now()->toIso8601String();
            $payload['asn_tracking'] = $tracking;
            $payload['asn_handle'] = $handle;
            $payload['asn_status'] = $status;
            unset($payload['asn_error'], $payload['asn_error_at']);
            $row->raw_payload = $payload;
            $row->save();
        }
    }

    protected function rememberAsnError($lines, string $message): void
    {
        foreach ($lines as $row) {
            $payload = $this->payloadOf($row);
            $payload['asn_error'] = mb_substr($message, 0, 500);
            $payload['asn_error_at'] = now()->toIso8601String();
            $row->raw_payload = $payload;
            $row->save();
        }
    }

    public static function canPushTracking(?array $settings = null): bool
    {
        $settings ??= MarketplaceSyncSettings::getFor('wayfair');

        return (bool) ($settings['order']['push_tracking_to_wayfair'] ?? true);
    }
}
