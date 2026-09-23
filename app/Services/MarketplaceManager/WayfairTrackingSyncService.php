<?php

namespace App\Services\MarketplaceManager;

use App\Models\MarketplaceSyncSettings;
use App\Models\WayfairDailyData;
use App\Services\WayfairApiService;
use App\Services\WayfairDailyOrderFetchService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Tracking push stub — Mirakl shipment API may be wired later.
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
            return ['success' => true, 'message' => 'Wayfair: no orders missing tracking.', 'checked' => 0, 'filled' => 0, 'skipped' => 0];
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

        return [
            'success' => true,
            'checked' => $checked,
            'filled' => $filled,
            'skipped' => $skipped,
            'message' => "Wayfair SOF tracking: checked {$checked} POs, saved tracking on {$filled} rows, still missing {$skipped}.",
        ];
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
