<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Temu Open API — promotion activities (bg.promotion.activity.*).
 *
 * Temu has no "create a promotion" call. A seller joins an activity Temu already runs
 * (lightning deal, clearance, ...) by enrolling a goods with an activity supplier price,
 * so "apply Sum disc as promotion" = enroll the goods at base price × (1 − Sum disc / 100).
 *
 * Signing and credentials are reused from TemuApiService::signRequest().
 */
class TemuPromotionApiService
{
    public const ROUTER_URL = 'https://openapi-b-us.temu.com/openapi/router';

    /** activityType values accepted by bg.promotion.activity.query. */
    public const TYPE_LIGHTNING_DEAL = 2;

    public const STATUS_NOT_STARTED = 1;

    public const STATUS_ONGOING = 2;

    public const STATUS_ENDED = 3;

    public function __construct(protected TemuApiService $temu)
    {
    }

    /**
     * bg.promotion.activity.query
     *
     * @return array{success: bool, message: string, activities: list<array<string, mixed>>}
     */
    public function queryActivities(int $activityType = self::TYPE_LIGHTNING_DEAL, ?int $status = null, int $page = 1, int $pageSize = 50): array
    {
        $body = [
            'type' => 'bg.promotion.activity.query',
            'activityType' => $activityType,
            'pageNumber' => $page,
            'pageSize' => min(100, max(1, $pageSize)),
            // onlyQueryJoinedActivity is optional and left out on purpose: TemuApiService::generateSignValue()
            // stringifies false as "" while Temu signs "false", which makes the sign invalid.
        ];
        if ($status !== null) {
            $body['activityStatus'] = $status;
        }

        $res = $this->post($body);
        if (! $res['success']) {
            return ['success' => false, 'message' => $res['message'], 'activities' => []];
        }

        $list = $res['data']['result']['activityList'] ?? [];

        return [
            'success' => true,
            'message' => 'OK',
            'activities' => is_array($list) ? array_values($list) : [],
        ];
    }

    /**
     * First activity a seller can still enroll goods in: ongoing or not started, not ended yet.
     * Earliest end time wins so the nearest deal is used first.
     *
     * @return array{success: bool, message: string, activity: array<string, mixed>|null}
     */
    public function resolveOpenActivity(int $activityType = self::TYPE_LIGHTNING_DEAL): array
    {
        $now = time();
        $open = [];
        foreach ([self::STATUS_ONGOING, self::STATUS_NOT_STARTED] as $status) {
            $res = $this->queryActivities($activityType, $status);
            if (! $res['success']) {
                return ['success' => false, 'message' => $res['message'], 'activity' => null];
            }
            foreach ($res['activities'] as $a) {
                $end = (int) ($a['activityEndTime'] ?? 0);
                if (! empty($a['activityId']) && ($end === 0 || $end > $now)) {
                    $open[] = $a;
                }
            }
        }

        if ($open === []) {
            return ['success' => false, 'message' => 'No open Temu promotion activity (lightning deal) found to enroll in.', 'activity' => null];
        }

        usort($open, fn ($x, $y) => ((int) ($x['activityEndTime'] ?? 0)) <=> ((int) ($y['activityEndTime'] ?? 0)));

        return ['success' => true, 'message' => 'OK', 'activity' => $open[0]];
    }

    /**
     * bg.promotion.activity.candidate.goods.query, every page.
     * Goods Temu lists here can be enrolled in the activity; anything else is not eligible.
     *
     * @return array{success: bool, message: string, goods: array<string, array{allowed: bool, qty: int, skus: array<string, float>}>}
     */
    public function candidateGoods(int|string $activityId, int $maxPages = 30): array
    {
        $out = [];
        for ($page = 1; $page <= $maxPages; $page++) {
            $res = $this->post([
                'type' => 'bg.promotion.activity.candidate.goods.query',
                'activityId' => is_numeric($activityId) ? (int) $activityId : $activityId,
                'pageNumber' => $page,
                'pageSize' => 100,
            ]);
            if (! $res['success']) {
                return ['success' => false, 'message' => $res['message'], 'goods' => $out];
            }
            $result = $res['data']['result'] ?? [];
            $list = is_array($result['goodsList'] ?? null) ? $result['goodsList'] : [];
            foreach ($list as $g) {
                $goodsId = (string) ($g['goodsId'] ?? '');
                if ($goodsId === '') {
                    continue;
                }
                $skus = [];
                foreach (($g['skuList'] ?? []) as $sku) {
                    // recommendActivitySupplierPrice is in cents.
                    $skus[(string) ($sku['skuId'] ?? '')] = round(((float) ($sku['recommendActivitySupplierPrice'] ?? 0)) / 100, 2);
                }
                $out[$goodsId] = [
                    'allowed' => (bool) ($g['isAllowedEnroll'] ?? false),
                    'qty' => (int) ($g['recommendActivityQuantity'] ?? 0),
                    'skus' => $skus,
                ];
            }
            if ($list === [] || $page * 100 >= (int) ($result['total'] ?? 0)) {
                break;
            }
        }

        return ['success' => true, 'message' => 'OK', 'goods' => $out];
    }

    /**
     * bg.promotion.activity.goods.enroll for one goods / one SKU.
     * Temu refuses a goods unless all its eligible SKUs are submitted together, so use enrollGoods()
     * for goods with several SKUs.
     *
     * @return array{success: bool, message: string, draft_id?: string|null, response?: mixed}
     */
    public function enrollSku(int|string $activityId, int|string $goodsId, int|string $skuId, float $activityPrice, int $quantity, string $traceCode = ''): array
    {
        return $this->enrollGoods($activityId, $goodsId, [(string) $skuId => $activityPrice], $quantity, $traceCode);
    }

    /**
     * bg.promotion.activity.goods.enroll for one goods with all of its SKUs.
     * activitySupplierPrice is an integer in minor units (cents), same as Temu's examples (1999 = $19.99).
     *
     * @param  array<int|string, float>  $skuPrices  skuId => promotion price in dollars
     * @return array{success: bool, message: string, draft_id?: string|null, response?: mixed}
     */
    public function enrollGoods(int|string $activityId, int|string $goodsId, array $skuPrices, int $quantity, string $traceCode = ''): array
    {
        if ($skuPrices === []) {
            return ['success' => false, 'message' => 'No SKUs to enroll.'];
        }
        if ($quantity < 1) {
            return ['success' => false, 'message' => 'Promotion quantity must be at least 1.'];
        }

        $skuList = [];
        foreach ($skuPrices as $skuId => $price) {
            if (! ((float) $price > 0)) {
                return ['success' => false, 'message' => 'Promotion price must be greater than 0.'];
            }
            $skuList[] = [
                'skuId' => is_numeric($skuId) ? (int) $skuId : $skuId,
                'activitySupplierPrice' => (int) round(((float) $price) * 100),
            ];
        }

        $enrollGoods = [
            'goodsId' => is_numeric($goodsId) ? (int) $goodsId : $goodsId,
            'activityQuantity' => $quantity,
            'enrollSkuList' => $skuList,
        ];
        if ($traceCode !== '') {
            // Idempotency key, max 32 chars.
            $enrollGoods['traceCode'] = substr($traceCode, 0, 32);
        }

        $res = $this->post([
            'type' => 'bg.promotion.activity.goods.enroll',
            'activityId' => is_numeric($activityId) ? (int) $activityId : $activityId,
            'enrollGoods' => $enrollGoods,
        ]);

        if (! $res['success']) {
            return ['success' => false, 'message' => $res['message'], 'response' => $res['data']];
        }

        $result = $res['data']['result'] ?? [];
        $fail = trim((string) ($result['failReason'] ?? ''));
        if ($fail !== '') {
            return ['success' => false, 'message' => $fail, 'response' => $res['data']];
        }

        return [
            'success' => true,
            'message' => 'Enrolled in Temu promotion.',
            'draft_id' => isset($result['draftId']) ? (string) $result['draftId'] : null,
            'response' => $res['data'],
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    protected function post(array $body): array
    {
        $signed = $this->temu->signRequest($body);

        $request = Http::withHeaders(['Content-Type' => 'application/json'])->timeout(60);
        if (config('filesystems.default') === 'local') {
            $request = $request->withoutVerifying();
        }

        try {
            $response = $request->post(self::ROUTER_URL, $signed);
            $data = $response->json();
            $data = is_array($data) ? $data : [];

            Log::info('Temu promotion API', [
                'type' => $body['type'] ?? null,
                'status' => $response->status(),
                'success' => $data['success'] ?? null,
                'errorCode' => $data['errorCode'] ?? null,
                'errorMsg' => $data['errorMsg'] ?? null,
            ]);

            $ok = ($data['success'] ?? false) === true || ($data['success'] ?? null) === 1 || ($data['success'] ?? null) === '1';
            if (! $response->successful() || ! $ok) {
                $code = $data['errorCode'] ?? null;
                $msg = trim((string) ($data['errorMsg'] ?? $data['message'] ?? ''));
                if ($msg === '') {
                    $msg = $data === [] ? 'Empty or non-JSON response from Temu.' : 'Temu rejected the request.';
                }

                return [
                    'success' => false,
                    'message' => ($code !== null && $code !== '' ? '['.$code.'] ' : '').$msg,
                    'data' => $data,
                ];
            }

            return ['success' => true, 'message' => 'OK', 'data' => $data];
        } catch (\Throwable $e) {
            Log::error('Temu promotion API exception', ['type' => $body['type'] ?? null, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Temu promotion request failed: '.$e->getMessage(), 'data' => []];
        }
    }
}
