<?php

namespace App\Services\OrderFulfillment;

use App\Models\SheinOrderMetric;
use App\Services\MarketplaceManager\VeeqoShopifyFulfillmentService;
use App\Services\SheinApiService;
use App\Services\TikTok2ShopService;
use App\Services\TikTokShopService;
use App\Support\TrackingPayloadExtractor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Bulk tracking lookup for many orders of one marketplace: the synced
 * marketplace rows first (no API), then the channel's batch order-detail API
 * (TikTok 50 / Shein 30 orders per call). Labels bought in 4Seller/GOFO are
 * written back to the marketplace order, so this is where they show up.
 */
class ChannelBatchTrackingLookup
{
    public function __construct(
        protected VeeqoShopifyFulfillmentService $fulfillment
    ) {}

    /**
     * @param  list<string>  $orderIds  marketplace order ids as shown on Order Fulfillment
     * @return array<string, array{tracking: string, carrier: string}> keyed by the given order id
     */
    public function lookup(string $slug, array $orderIds, float $deadline): array
    {
        $slug = strtolower(trim($slug));
        $hits = [];
        $pending = [];
        foreach (array_values(array_unique(array_filter(array_map(static fn ($id) => trim((string) $id), $orderIds)))) as $id) {
            if (microtime(true) >= $deadline) {
                break;
            }
            $plain = ltrim($id, '#');
            try {
                $local = $this->fulfillment->localMarketplaceTracking($slug, array_values(array_unique([$plain, $id])));
            } catch (\Throwable $e) {
                $local = null;
            }
            if (is_array($local) && strlen(trim((string) ($local['tracking'] ?? ''))) >= 8) {
                $hits[$id] = [
                    'tracking' => trim((string) $local['tracking']),
                    'carrier' => trim((string) ($local['carrier'] ?? '')),
                ];
            } else {
                $pending[] = $id;
            }
        }

        if ($slug === 'shein' && $pending !== []) {
            foreach ($this->sheinStored($pending) as $id => $hit) {
                $hits[$id] = $hit;
            }
            $pending = array_values(array_filter($pending, static fn ($id) => ! isset($hits[$id])));
        }

        if ($pending === [] || microtime(true) >= $deadline) {
            return $hits;
        }

        $fromApi = match ($slug) {
            'tiktok', 'tiktok2' => $this->tiktok($slug, $pending, $deadline),
            'shein' => $this->shein($pending, $deadline),
            default => [],
        };
        foreach ($fromApi as $id => $hit) {
            $hits[(string) $id] = $hit;
        }

        return $hits;
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, array{tracking: string, carrier: string}>
     */
    protected function tiktok(string $slug, array $ids, float $deadline): array
    {
        $service = $slug === 'tiktok2' ? app(TikTok2ShopService::class) : app(TikTokShopService::class);
        if (method_exists($service, 'isAuthenticated') && ! $service->isAuthenticated()) {
            return [];
        }

        $byTikTokId = [];
        foreach ($ids as $id) {
            $plain = ltrim($id, '#');
            $tiktokId = VeeqoShopifyFulfillmentService::tiktokOrderIdFromShopifyName($plain);
            if ($tiktokId === '' && preg_match('/^\d{12,20}$/', $plain) === 1) {
                $tiktokId = $plain;
            }
            if ($tiktokId !== '') {
                $byTikTokId['t'.$tiktokId] = $id;
            }
        }

        $out = [];
        foreach (array_chunk(array_keys($byTikTokId), 50) as $chunk) {
            if (microtime(true) >= $deadline) {
                break;
            }
            $request = array_map(static fn (string $k) => substr($k, 1), $chunk);
            try {
                $details = $service->getOrderDetails($request);
            } catch (\Throwable $e) {
                Log::info('ChannelBatchTrackingLookup: TikTok order detail failed', ['slug' => $slug, 'error' => $e->getMessage()]);

                continue;
            }
            $orders = is_array($details) ? ($details['orders'] ?? $details['data']['orders'] ?? []) : [];
            foreach (is_array($orders) ? $orders : [] as $order) {
                if (! is_array($order)) {
                    continue;
                }
                $tiktokId = trim((string) ($order['id'] ?? $order['order_id'] ?? ''));
                $id = $byTikTokId['t'.$tiktokId] ?? null;
                if ($id === null) {
                    continue;
                }
                $hit = VeeqoShopifyFulfillmentService::trackingFromTikTokOrderPayload($order)
                    ?? TrackingPayloadExtractor::find($order, [$tiktokId]);
                if ($hit !== null) {
                    $out[$id] = $hit;
                }
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, array{tracking: string, carrier: string}>
     */
    protected function shein(array $ids, float $deadline): array
    {
        $api = app(SheinApiService::class);
        $out = [];
        foreach (array_chunk($ids, 30) as $chunk) {
            if (microtime(true) >= $deadline) {
                break;
            }
            try {
                $res = $api->getOrderDetails($chunk);
            } catch (\Throwable $e) {
                Log::info('ChannelBatchTrackingLookup: Shein order detail failed', ['error' => $e->getMessage()]);

                continue;
            }
            foreach ((array) ($res['orders'] ?? []) as $order) {
                if (! is_array($order)) {
                    continue;
                }
                $orderNo = trim((string) ($order['orderNo'] ?? ''));
                if ($orderNo === '' || ! in_array($orderNo, $chunk, true)) {
                    continue;
                }
                $hit = TrackingPayloadExtractor::find($order, self::sheinNonTrackingIds($order));
                if ($hit !== null) {
                    $out[$orderNo] = $hit;
                }
            }
        }

        return $out;
    }

    /**
     * Shein order-detail payloads already saved on shein_order_metrics.
     *
     * @param  list<string>  $ids
     * @return array<string, array{tracking: string, carrier: string}>
     */
    protected function sheinStored(array $ids): array
    {
        if (! Schema::hasTable('shein_order_metrics')) {
            return [];
        }
        $out = [];
        foreach (array_chunk($ids, 200) as $chunk) {
            foreach (SheinOrderMetric::query()->whereIn('order_id', $chunk)->get(['order_id', 'raw_payload']) as $line) {
                $id = trim((string) $line->order_id);
                if ($id === '' || isset($out[$id])) {
                    continue;
                }
                $raw = $line->raw_payload;
                if (is_string($raw)) {
                    $raw = json_decode($raw, true);
                }
                if (! is_array($raw)) {
                    continue;
                }
                $order = is_array($raw['order'] ?? null) ? $raw['order'] : $raw;
                $hit = TrackingPayloadExtractor::find($order, self::sheinNonTrackingIds($order));
                if ($hit !== null) {
                    $out[$id] = $hit;
                }
            }
        }

        return $out;
    }

    /**
     * Order / package numbers in a Shein payload that look like tracking but are not.
     *
     * @param  array<string, mixed>  $order
     * @return list<string>
     */
    public static function sheinNonTrackingIds(array $order): array
    {
        $ids = [(string) ($order['orderNo'] ?? '')];
        $walk = static function ($value, string $key) use (&$walk, &$ids): void {
            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    $walk($v, is_int($k) ? $key : strtolower((string) $k));
                }

                return;
            }
            if (is_scalar($value) && preg_match('/(package|order).?(no|sn|id|number)$/', $key) === 1) {
                $ids[] = (string) $value;
            }
        };
        $walk($order, '');

        return array_values(array_filter(array_unique($ids)));
    }
}
