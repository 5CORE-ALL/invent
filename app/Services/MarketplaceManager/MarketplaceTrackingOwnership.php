<?php

namespace App\Services\MarketplaceManager;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Detect when a tracking number already belongs to a different marketplace order
 * (e.g. Amazon 113-… label sitting on a Shein GSU1… copy).
 */
class MarketplaceTrackingOwnership
{
    /**
     * @return list<array{slug: string, order_id: string, shopify_order_id: string}>
     */
    public function owners(string $tracking): array
    {
        $want = app(ShopifyFulfillmentTrackingMatcher::class)->normalizeTracking($tracking);
        if ($want === '' || strlen($want) < 8) {
            return [];
        }
        if (! Schema::hasTable('shopify_raw_orders') || ! Schema::hasColumn('shopify_raw_orders', 'tracking_number')) {
            return [];
        }

        $cols = ['order_id', 'tracking_number'];
        foreach (['tags', 'note', 'order_number'] as $col) {
            if (Schema::hasColumn('shopify_raw_orders', $col)) {
                $cols[] = $col;
            }
        }

        $out = [];
        $seen = [];
        $matcher = app(ShopifyFulfillmentTrackingMatcher::class);
        $rows = DB::table('shopify_raw_orders')
            ->whereNotNull('tracking_number')
            ->where('tracking_number', '!=', '')
            ->where(function ($q) use ($tracking, $want) {
                $q->where('tracking_number', $tracking)
                    ->orWhere('tracking_number', $want)
                    ->orWhereRaw('UPPER(REPLACE(REPLACE(tracking_number, " ", ""), "-", "")) = ?', [$want]);
            })
            ->select($cols)
            ->limit(40)
            ->get();

        foreach ($rows as $row) {
            $tn = $matcher->normalizeTracking((string) ($row->tracking_number ?? ''));
            if ($tn === '' || $tn !== $want) {
                continue;
            }
            $identity = $this->identityFromRawRow($row);
            $key = $identity['slug'].'|'.$identity['order_id'].'|'.$identity['shopify_order_id'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $identity;
        }

        return $out;
    }

    public function looksLikeForeignChannelTracking(string $tracking, string $slug): bool
    {
        $slug = strtolower(trim($slug));
        $tn = trim($tracking);
        if ($slug === '' || $tn === '') {
            return false;
        }
        if (preg_match('/^\d{3}-\d{7}-\d{7}$/', $tn) && $slug !== 'amazon') {
            return true;
        }
        if (preg_match('/^TBA[A-Z0-9]+$/i', $tn) && $slug !== 'amazon') {
            return true;
        }

        return false;
    }

    public function isWrongFor(string $tracking, string $slug, string $channelOrderId, string $shopifyOrderId = ''): bool
    {
        $slug = strtolower(trim($slug));
        $channelOrderId = trim($channelOrderId);
        $shopifyOrderId = trim($shopifyOrderId);
        if ($this->looksLikeForeignChannelTracking($tracking, $slug)) {
            return true;
        }
        $matcher = app(ShopifyFulfillmentTrackingMatcher::class);
        $ourShopify = $matcher->numericShopifyId($shopifyOrderId);

        foreach ($this->owners($tracking) as $owner) {
            if ($owner['slug'] !== '' && $slug !== '' && $owner['slug'] !== $slug) {
                return true;
            }
            if (
                $channelOrderId !== ''
                && $owner['order_id'] !== ''
                && ! $this->sameChannelOrderId($owner['order_id'], $channelOrderId)
            ) {
                return true;
            }
            if ($ourShopify !== '' && $owner['shopify_order_id'] !== '' && $owner['shopify_order_id'] !== $ourShopify) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  object{tags?: mixed, note?: mixed, order_number?: mixed, order_id?: mixed}  $row
     * @return array{slug: string, order_id: string, shopify_order_id: string}
     */
    public function identityFromRawRow(object $row): array
    {
        $matcher = app(ShopifyFulfillmentTrackingMatcher::class);
        $order = [
            'tags' => (string) ($row->tags ?? ''),
            'note' => (string) ($row->note ?? ''),
            'name' => (string) ($row->order_number ?? ''),
        ];

        return [
            'slug' => $matcher->primaryMarketplaceSlug($order),
            'order_id' => $matcher->channelOrderIdFromOrder($order),
            'shopify_order_id' => $matcher->numericShopifyId((string) ($row->order_id ?? '')),
        ];
    }

    public function sameChannelOrderId(string $left, string $right): bool
    {
        $matcher = app(ShopifyFulfillmentTrackingMatcher::class);
        $a = $matcher->normalizeTracking($left);
        $b = $matcher->normalizeTracking($right);
        if ($a !== '' && $a === $b) {
            return true;
        }

        return strcasecmp(trim($left), trim($right)) === 0;
    }
}
