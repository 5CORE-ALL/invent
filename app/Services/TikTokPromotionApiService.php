<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * TikTok Shop Partner API — Promotion (seller product discounts), API version 202309.
 *
 * Only DIRECT_DISCOUNT (percentage off) at VARIATION (SKU) level is used here:
 *   create activity → add SKUs with a discount % → change the % later → deactivate.
 * Coupons cannot be created through the API (TikTok only exposes search/get for coupons).
 *
 * The seller account must have the Promotion scope authorised for the app; without it every call
 * fails with a permission error that is returned as the message.
 */
class TikTokPromotionApiService
{
    public const VERSION = '202309';

    public function __construct(protected TikTokShopService $shop)
    {
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    protected function call(string $method, string $path, array $query = [], ?array $body = null): array
    {
        try {
            $data = $this->shop->promotionRequest($method, $path, $query, $body);

            return ['success' => true, 'message' => 'OK', 'data' => $data];
        } catch (Throwable $e) {
            Log::warning('TikTok promotion API failed', [
                'channel' => $this->shop->configKey(),
                'method' => $method,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage(), 'data' => []];
        }
    }

    /**
     * Search Promotion Activities (POST /promotion/202309/activities/search).
     *
     * @return array{success: bool, message: string, activities: list<array<string, mixed>>}
     */
    public function searchActivities(?string $activityType = 'DIRECT_DISCOUNT', ?string $status = null, int $pageSize = 50): array
    {
        $out = [];
        $token = null;
        for ($page = 0; $page < 20; $page++) {
            $body = ['page_size' => min(100, max(1, $pageSize))];
            if ($activityType !== null && $activityType !== '') {
                $body['activity_type'] = $activityType;
            }
            if ($status !== null && $status !== '') {
                $body['status'] = $status;
            }
            if ($token !== null && $token !== '') {
                $body['page_token'] = $token;
            }
            $res = $this->call('POST', '/promotion/'.self::VERSION.'/activities/search', [], $body);
            if (! $res['success']) {
                return ['success' => false, 'message' => $res['message'], 'activities' => $out];
            }
            foreach (($res['data']['activities'] ?? []) as $a) {
                if (is_array($a)) {
                    $out[] = $a;
                }
            }
            $token = (string) ($res['data']['next_page_token'] ?? '');
            if ($token === '') {
                break;
            }
        }

        return ['success' => true, 'message' => 'OK', 'activities' => $out];
    }

    /**
     * Create a SKU-level percentage-off activity (DIRECT_DISCOUNT / VARIATION).
     *
     * @return array{success: bool, message: string, activity_id?: string}
     */
    public function createDirectDiscount(string $title, int $beginTime, int $endTime): array
    {
        $res = $this->call('POST', '/promotion/'.self::VERSION.'/activities', [], [
            'title' => mb_substr($title, 0, 100),
            'activity_type' => 'DIRECT_DISCOUNT',
            'product_level' => 'VARIATION',
            'begin_time' => $beginTime,
            'end_time' => $endTime,
        ]);
        if (! $res['success']) {
            return ['success' => false, 'message' => $res['message']];
        }
        $id = (string) ($res['data']['activity_id'] ?? '');
        if ($id === '') {
            return ['success' => false, 'message' => 'TikTok did not return an activity_id.'];
        }

        return ['success' => true, 'message' => 'Activity created.', 'activity_id' => $id];
    }

    /**
     * Add SKUs to the activity, or change their discount (same call). Max 300 SKUs per request.
     *
     * @param  list<array{product_id: string, sku_id: string, discount: float|int|string}>  $skus
     * @return array{success: bool, message: string, data?: array<string, mixed>}
     */
    public function setSkuDiscounts(string $activityId, array $skus): array
    {
        if ($skus === []) {
            return ['success' => false, 'message' => 'No SKUs.'];
        }
        $byProduct = [];
        foreach ($skus as $s) {
            $byProduct[(string) $s['product_id']][] = [
                'id' => (string) $s['sku_id'],
                'discount' => rtrim(rtrim(number_format((float) $s['discount'], 2, '.', ''), '0'), '.'),
                'quantity_limit' => -1,
                'quantity_per_user' => -1,
            ];
        }
        $products = [];
        foreach ($byProduct as $productId => $skuRows) {
            $products[] = [
                'id' => (string) $productId,
                'quantity_limit' => -1,
                'quantity_per_user' => -1,
                'skus' => $skuRows,
            ];
        }

        $res = $this->call('PUT', '/promotion/'.self::VERSION.'/activities/'.rawurlencode($activityId).'/products', [], [
            // TikTok rejects the call ("ActivityId is a required field") unless the id is also in the body.
            'activity_id' => $activityId,
            'products' => $products,
        ]);
        if (! $res['success']) {
            return ['success' => false, 'message' => $res['message']];
        }

        return ['success' => true, 'message' => 'SKU discounts saved.', 'data' => $res['data']];
    }

    /**
     * Remove SKUs from the activity (DELETE .../products).
     *
     * @param  list<string>  $skuIds
     * @param  list<string>  $productIds
     * @return array{success: bool, message: string}
     */
    public function removeSkus(string $activityId, array $productIds, array $skuIds): array
    {
        $res = $this->call('DELETE', '/promotion/'.self::VERSION.'/activities/'.rawurlencode($activityId).'/products', [], [
            'activity_id' => $activityId,
            'product_ids' => array_values($productIds),
            'sku_ids' => array_values($skuIds),
        ]);

        return ['success' => $res['success'], 'message' => $res['success'] ? 'SKUs removed.' : $res['message']];
    }

    /**
     * Deactivate Activity (POST .../deactivate). Cannot be undone.
     *
     * @return array{success: bool, message: string}
     */
    public function deactivate(string $activityId): array
    {
        $res = $this->call('POST', '/promotion/'.self::VERSION.'/activities/'.rawurlencode($activityId).'/deactivate', [], ['activity_id' => $activityId]);

        return ['success' => $res['success'], 'message' => $res['success'] ? 'Activity deactivated.' : $res['message']];
    }

    /**
     * Get Activity (GET .../activities/{id}) incl. products.
     *
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public function getActivity(string $activityId): array
    {
        return $this->call('GET', '/promotion/'.self::VERSION.'/activities/'.rawurlencode($activityId));
    }
}
