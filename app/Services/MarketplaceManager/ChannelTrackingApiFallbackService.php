<?php

namespace App\Services\MarketplaceManager;

use App\Models\AlibabaOrderMetric;
use App\Models\AliexpressOrderMetric;
use App\Models\DobaDailyData;
use App\Models\FaireOrderMetric;
use App\Models\NeweggOrderMetric;
use App\Models\PurchasingPowerSale;
use App\Models\ReverbOrderMetric;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * When Shopify has no tracking for an order, pull tracking from that channel's own API.
 * Temu/Temu2 are handled separately (TemuOrderTrackingPullService).
 */
class ChannelTrackingApiFallbackService
{
    /** Channels that refresh one order at a time via OrderDetailService. */
    protected const DETAIL_SLUGS = [
        'newegg',
        'reverb',
        'aliexpress',
        'alibaba',
        'faire',
    ];

    /** Channels that re-sync a batch from their API (tracking lives on local columns). */
    protected const BATCH_SLUGS = [
        'purchasingpower',
        'doba',
    ];

    /**
     * @param  list<array<string, mixed>>  $candidateRows  SOF rows missing tracking_number
     * @return array{
     *   checked: int,
     *   updated: int,
     *   with_tracking: int,
     *   message: string,
     *   rows: list<array<string, mixed>>
     * }
     */
    public function pullForMissingRows(array $candidateRows, int $limit = 40, ?string $channelFilter = null): array
    {
        $limit = max(1, min(100, $limit));
        $channelFilter = $channelFilter !== null ? strtolower(trim($channelFilter)) : '';

        $bySlug = [];
        foreach ($candidateRows as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (trim((string) ($row['tracking_number'] ?? '')) !== '') {
                continue;
            }
            $slug = strtolower(trim((string) ($row['mm_slug'] ?? '')));
            if ($slug === '' || in_array($slug, ['temu', 'temu2'], true)) {
                continue;
            }
            if ($channelFilter !== '' && $slug !== $channelFilter) {
                continue;
            }
            if (! in_array($slug, array_merge(self::DETAIL_SLUGS, self::BATCH_SLUGS), true)) {
                continue;
            }
            $orderKey = trim((string) (
                $row['order_id_api']
                ?? $row['order_id']
                ?? $row['order_number']
                ?? ''
            ));
            if ($orderKey === '') {
                continue;
            }
            $bySlug[$slug][$orderKey] = $row;
        }

        $checked = 0;
        $updated = 0;
        $withTracking = 0;
        $outRows = [];
        $parts = [];

        foreach (self::BATCH_SLUGS as $slug) {
            if ($channelFilter !== '' && $slug !== $channelFilter) {
                continue;
            }
            $orders = $bySlug[$slug] ?? [];
            if ($orders === []) {
                continue;
            }
            $result = $this->pullBatchChannel($slug, array_slice(array_keys($orders), 0, $limit));
            $checked += (int) ($result['checked'] ?? 0);
            $updated += (int) ($result['updated'] ?? 0);
            $withTracking += (int) ($result['with_tracking'] ?? 0);
            $outRows = array_merge($outRows, $result['rows'] ?? []);
            if (($result['message'] ?? '') !== '') {
                $parts[] = $result['message'];
            }
        }

        foreach (self::DETAIL_SLUGS as $slug) {
            if ($channelFilter !== '' && $slug !== $channelFilter) {
                continue;
            }
            $orders = $bySlug[$slug] ?? [];
            if ($orders === []) {
                continue;
            }
            $keys = array_slice(array_keys($orders), 0, $limit);
            $result = $this->pullDetailChannel($slug, $keys);
            $checked += (int) ($result['checked'] ?? 0);
            $updated += (int) ($result['updated'] ?? 0);
            $withTracking += (int) ($result['with_tracking'] ?? 0);
            $outRows = array_merge($outRows, $result['rows'] ?? []);
            if (($result['message'] ?? '') !== '') {
                $parts[] = $result['message'];
            }
        }

        return [
            'checked' => $checked,
            'updated' => $updated,
            'with_tracking' => $withTracking,
            'message' => $parts !== []
                ? ('Channel API fallback: '.implode(' ', $parts))
                : 'Channel API fallback: no supported orders missing Shopify tracking.',
            'rows' => $outRows,
        ];
    }

    /**
     * @param  list<string>  $orderIds
     * @return array{checked:int,updated:int,with_tracking:int,message:string,rows:list<array<string,mixed>>}
     */
    protected function pullBatchChannel(string $slug, array $orderIds): array
    {
        $checked = count($orderIds);
        $updated = 0;
        $withTracking = 0;
        $rows = [];

        try {
            if ($slug === 'purchasingpower') {
                $sync = app(PurchasingPowerOrderSyncService::class)->fetchAndStore(60);
                if (empty($sync['success'])) {
                    return [
                        'checked' => $checked,
                        'updated' => 0,
                        'with_tracking' => 0,
                        'message' => 'Purchasing Power API: '.((string) ($sync['message'] ?? 'failed')),
                        'rows' => [],
                    ];
                }
                if (! Schema::hasTable('purchasing_power_sales')) {
                    return $this->emptyResult($checked, 'Purchasing Power: table missing.');
                }
                foreach ($orderIds as $oid) {
                    $line = PurchasingPowerSale::query()
                        ->where('order_id', $oid)
                        ->orWhere('order_number', $oid)
                        ->orderByDesc('id')
                        ->first();
                    if (! $line) {
                        continue;
                    }
                    $tn = trim((string) ($line->tracking_number ?? ''));
                    $carrier = trim((string) ($line->shipping_company ?? ''));
                    if ($tn === '') {
                        continue;
                    }
                    $withTracking++;
                    $updated++;
                    $rows[] = $this->resultRow($oid, $tn, $carrier, 'Purchasing Power API');
                }

                return [
                    'checked' => $checked,
                    'updated' => $updated,
                    'with_tracking' => $withTracking,
                    'message' => "Purchasing Power API: refreshed, found tracking on {$withTracking}/{$checked}.",
                    'rows' => $rows,
                ];
            }

            if ($slug === 'doba') {
                $sync = app(DobaOrderSyncService::class)->fetchAndStore(60);
                if (empty($sync['success'])) {
                    return [
                        'checked' => $checked,
                        'updated' => 0,
                        'with_tracking' => 0,
                        'message' => 'Doba API: '.((string) ($sync['message'] ?? 'failed')),
                        'rows' => [],
                    ];
                }
                if (! Schema::hasTable('doba_daily_data')) {
                    return $this->emptyResult($checked, 'Doba: table missing.');
                }
                foreach ($orderIds as $oid) {
                    $line = DobaDailyData::query()
                        ->where('order_no', $oid)
                        ->orWhere('platform_order_no', $oid)
                        ->orderByDesc('id')
                        ->first();
                    if (! $line) {
                        continue;
                    }
                    $tn = trim((string) ($line->tracking_number ?? ''));
                    $carrier = trim((string) ($line->carrier_name ?? $line->carrier ?? ''));
                    if ($tn === '') {
                        continue;
                    }
                    $withTracking++;
                    $updated++;
                    $rows[] = $this->resultRow($oid, $tn, $carrier, 'Doba API');
                }

                return [
                    'checked' => $checked,
                    'updated' => $updated,
                    'with_tracking' => $withTracking,
                    'message' => "Doba API: refreshed, found tracking on {$withTracking}/{$checked}.",
                    'rows' => $rows,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('ChannelTrackingApiFallbackService batch failed', [
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);

            return $this->emptyResult($checked, strtoupper($slug).' API: '.$e->getMessage());
        }

        return $this->emptyResult($checked, '');
    }

    /**
     * @param  list<string>  $orderIds
     * @return array{checked:int,updated:int,with_tracking:int,message:string,rows:list<array<string,mixed>>}
     */
    protected function pullDetailChannel(string $slug, array $orderIds): array
    {
        $checked = 0;
        $updated = 0;
        $withTracking = 0;
        $rows = [];
        $service = $this->detailServiceFor($slug);
        if ($service === null) {
            return $this->emptyResult(0, '');
        }

        foreach ($orderIds as $oid) {
            $checked++;
            try {
                $res = $service->fetchAndPersistOrderDetail($oid);
                if (empty($res['success'])) {
                    continue;
                }
                $hit = $this->readTrackingAfterDetail($slug, $oid);
                if (($hit['tracking_number'] ?? '') === '') {
                    continue;
                }
                $withTracking++;
                $updated++;
                $rows[] = $this->resultRow(
                    $oid,
                    $hit['tracking_number'],
                    $hit['carrier'] ?? '',
                    strtoupper($slug).' API'
                );
            } catch (\Throwable $e) {
                Log::info('ChannelTrackingApiFallbackService detail failed', [
                    'slug' => $slug,
                    'order' => $oid,
                    'error' => $e->getMessage(),
                ]);
            }
            usleep(120000);
        }

        $label = match ($slug) {
            'newegg' => 'Newegg',
            'reverb' => 'Reverb',
            'aliexpress' => 'AliExpress',
            'alibaba' => 'Alibaba',
            'faire' => 'Faire',
            default => $slug,
        };

        return [
            'checked' => $checked,
            'updated' => $updated,
            'with_tracking' => $withTracking,
            'message' => "{$label} API: checked {$checked}, found tracking on {$withTracking}.",
            'rows' => $rows,
        ];
    }

    protected function detailServiceFor(string $slug): mixed
    {
        return match ($slug) {
            'newegg' => app(NeweggOrderDetailService::class),
            'reverb' => app(ReverbOrderDetailService::class),
            'aliexpress' => app(AliexpressOrderDetailService::class),
            'alibaba' => app(AlibabaOrderDetailService::class),
            'faire' => app(FaireOrderDetailService::class),
            default => null,
        };
    }

    /**
     * @return array{tracking_number: string, carrier: string}
     */
    protected function readTrackingAfterDetail(string $slug, string $orderId): array
    {
        $raw = null;
        $carrier = '';

        switch ($slug) {
            case 'newegg':
                $raw = NeweggOrderMetric::query()->where('order_id', $orderId)->value('raw_payload');
                break;
            case 'reverb':
                $raw = ReverbOrderMetric::query()->where('order_id', $orderId)->value('raw_payload');
                break;
            case 'aliexpress':
                $raw = AliexpressOrderMetric::query()->where('order_id', $orderId)->value('raw_payload');
                break;
            case 'alibaba':
                $raw = AlibabaOrderMetric::query()->where('order_id', $orderId)->value('raw_payload');
                break;
            case 'faire':
                $raw = FaireOrderMetric::query()->where('order_id', $orderId)->value('raw_payload');
                break;
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : null;
        }
        if (! is_array($raw)) {
            return ['tracking_number' => '', 'carrier' => ''];
        }

        $tn = $this->extractTrackingFromPayload($raw);
        $carrier = $this->extractCarrierFromPayload($raw);

        return [
            'tracking_number' => $tn,
            'carrier' => $carrier,
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    protected function extractTrackingFromPayload(array $raw): string
    {
        $order = is_array($raw['order'] ?? null) ? $raw['order'] : $raw;

        foreach ([
            'shipping_code', 'tracking_number', 'trackingNumber', 'TrackingNumber',
            'logistics_no', 'tracking', 'waybillNo', 'tracking_code', 'trackingCode',
        ] as $key) {
            if (! empty($order[$key]) && is_scalar($order[$key])) {
                $val = trim((string) $order[$key]);
                if ($val !== '') {
                    return $val;
                }
            }
        }

        foreach (['shipments', 'shipment', 'packages', 'PackageInfoList', 'packageInfoList', 'logistic_info_list'] as $listKey) {
            $list = $order[$listKey] ?? null;
            if (! is_array($list)) {
                continue;
            }
            // Faire: shipments may be list or nested
            $items = array_is_list($list) ? $list : [$list];
            if (isset($list['aeop_tp_logistics_info_dto']) && is_array($list['aeop_tp_logistics_info_dto'])) {
                $items = array_is_list($list['aeop_tp_logistics_info_dto'])
                    ? $list['aeop_tp_logistics_info_dto']
                    : [$list['aeop_tp_logistics_info_dto']];
            }
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                foreach ([
                    'tracking_number', 'trackingNumber', 'TrackingNumber', 'tracking_code',
                    'trackingCode', 'logistics_no', 'tracking', 'shipping_code',
                ] as $key) {
                    if (! empty($item[$key]) && is_scalar($item[$key])) {
                        $val = trim((string) $item[$key]);
                        if ($val !== '') {
                            return $val;
                        }
                    }
                }
            }
        }

        $href = $order['_links']['web_tracking']['href'] ?? null;
        if (is_string($href) && $href !== '') {
            foreach ([
                '/tracknumbers=([A-Za-z0-9]+)/i',
                '/qtc_tLabels1=([A-Za-z0-9]+)/i',
                '/tracknum=([A-Za-z0-9]+)/i',
                '/tracking_numbers?=([A-Za-z0-9]+)/i',
            ] as $pattern) {
                if (preg_match($pattern, $href, $m) === 1) {
                    return $m[1];
                }
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    protected function extractCarrierFromPayload(array $raw): string
    {
        $order = is_array($raw['order'] ?? null) ? $raw['order'] : $raw;
        foreach ([
            'shipping_carrier', 'carrier', 'carrier_name', 'CarrierName',
            'ShipService', 'logistics_type', 'shipping_company', 'courier',
        ] as $key) {
            if (! empty($order[$key]) && is_scalar($order[$key])) {
                $val = trim((string) $order[$key]);
                if ($val !== '') {
                    return $val;
                }
            }
        }

        foreach (['shipments', 'PackageInfoList', 'packageInfoList'] as $listKey) {
            $list = $order[$listKey] ?? null;
            if (! is_array($list) || $list === []) {
                continue;
            }
            $first = array_is_list($list) ? ($list[0] ?? null) : $list;
            if (! is_array($first)) {
                continue;
            }
            foreach (['carrier', 'carrier_name', 'ShipCarrier', 'logistics_service_name', 'service'] as $key) {
                if (! empty($first[$key]) && is_scalar($first[$key])) {
                    $val = trim((string) $first[$key]);
                    if ($val !== '') {
                        return $val;
                    }
                }
            }
        }

        return '';
    }

    /**
     * @return array{order_number:string,shopify_order_id:null,tracking_number:string,tracking_company:string,fulfillment_status:string,shipment_status:string,note:string}
     */
    protected function resultRow(string $orderId, string $tracking, string $carrier, string $source): array
    {
        return [
            'order_number' => $orderId,
            'shopify_order_id' => null,
            'tracking_number' => $tracking,
            'tracking_company' => $carrier,
            'fulfillment_status' => $source,
            'shipment_status' => '',
            'note' => 'Pulled from channel API (Shopify had no tracking)',
        ];
    }

    /**
     * @return array{checked:int,updated:int,with_tracking:int,message:string,rows:list<array<string,mixed>>}
     */
    protected function emptyResult(int $checked, string $message): array
    {
        return [
            'checked' => $checked,
            'updated' => 0,
            'with_tracking' => 0,
            'message' => $message,
            'rows' => [],
        ];
    }
}
