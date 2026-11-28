<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ShopifySku;
use App\Models\Temu2Metric;
use App\Models\Temu3Metric;
use App\Models\TemuMetric;
use App\Services\Temu2ApiService;
use App\Services\Temu3ApiService;
use App\Services\TemuApiService;
use App\Services\TemuPromotionApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * /promotions → Temu 1 / 2 / 3: join the promotion activities Temu runs (Lightning Deal, Clearance, ...).
 *
 * Temu has no "create promotion" API. A seller enrolls goods in an activity Temu already published
 * (bg.promotion.activity.goods.enroll). This controller lists those activities, lists which of our
 * goods Temu lets us enroll (candidate goods) and enrolls the ones a person picks.
 *
 * Activity price + quantity are Temu's own recommendation for every eligible SKU
 * (recommendActivitySupplierPrice / recommendActivityQuantity), quantity capped to our INV.
 * Each store uses its own credentials (TemuApiService / Temu2ApiService / Temu3ApiService).
 * Enrolling is manual only: preview first, then an explicit confirmed POST.
 */
class TemuPromotionsController extends Controller
{
    /** channel key => [api service, metric model] */
    private const CHANNELS = [
        'temu' => [TemuApiService::class, TemuMetric::class],
        'temu2' => [Temu2ApiService::class, Temu2Metric::class],
        'temu3' => [Temu3ApiService::class, Temu3Metric::class],
    ];

    /** activityType values bg.promotion.activity.query accepts. */
    private const ACTIVITY_TYPES = [
        2 => 'Lightning deals',
        27 => 'Clearance deals',
        100 => 'Official big sale',
        13 => 'Advanced big sale',
    ];

    private const MAX_ENROLL_PER_REQUEST = 50;

    private function channel(Request $request): ?string
    {
        $key = strtolower(trim((string) $request->input('channel', '')));

        return isset(self::CHANNELS[$key]) ? $key : null;
    }

    private function promo(string $channel): TemuPromotionApiService
    {
        return new TemuPromotionApiService(app(self::CHANNELS[$channel][0]));
    }

    private function badChannel()
    {
        return response()->json(['success' => false, 'message' => 'channel must be temu, temu2 or temu3'], 422);
    }

    /** Open (ongoing + not started) activities of one type for the store. */
    public function activities(Request $request)
    {
        $channel = $this->channel($request);
        if ($channel === null) {
            return $this->badChannel();
        }
        $type = (int) $request->input('activity_type', TemuPromotionApiService::TYPE_LIGHTNING_DEAL);
        if (! isset(self::ACTIVITY_TYPES[$type])) {
            return response()->json(['success' => false, 'message' => 'Unsupported activity_type'], 422);
        }

        $promo = $this->promo($channel);
        $now = time();
        $out = [];
        foreach ([TemuPromotionApiService::STATUS_ONGOING, TemuPromotionApiService::STATUS_NOT_STARTED] as $status) {
            $res = $promo->queryActivities($type, $status);
            if (! $res['success']) {
                return response()->json(['success' => false, 'message' => $res['message']], 502);
            }
            foreach ($res['activities'] as $a) {
                $end = (int) ($a['activityEndTime'] ?? 0);
                if (empty($a['activityId']) || ($end !== 0 && $end <= $now)) {
                    continue;
                }
                $a['_status'] = $status === TemuPromotionApiService::STATUS_ONGOING ? 'Ongoing' : 'Not started';
                $out[(string) $a['activityId']] = $a;
            }
        }

        return response()->json([
            'success' => true,
            'channel' => $channel,
            'activity_type' => $type,
            'activities' => array_values($out),
        ]);
    }

    /**
     * Dry run: eligible goods of an activity with the price/qty that would be sent. Sends nothing to Temu.
     */
    public function preview(Request $request)
    {
        $channel = $this->channel($request);
        if ($channel === null) {
            return $this->badChannel();
        }
        $activityId = trim((string) $request->input('activity_id', ''));
        if ($activityId === '' || ! ctype_digit($activityId)) {
            return response()->json(['success' => false, 'message' => 'activity_id (numeric) required'], 422);
        }

        $plan = $this->buildPlan($channel, $activityId, $request->boolean('refresh'));
        if (! $plan['success']) {
            return response()->json($plan, 502);
        }

        $ready = count(array_filter($plan['rows'], fn ($r) => $r['status'] === 'ready'));

        return response()->json([
            'success' => true,
            'channel' => $channel,
            'activity_id' => $activityId,
            'eligible' => count($plan['rows']),
            'ready' => $ready,
            'skipped' => count($plan['rows']) - $ready,
            'rows' => $plan['rows'],
        ]);
    }

    /**
     * Enroll the picked goods. Manual and explicit: needs confirm=1 and goods_ids.
     * Goods that are not "ready" in the plan (INV 0, no price, not eligible) are skipped, never forced.
     */
    public function enroll(Request $request)
    {
        $channel = $this->channel($request);
        if ($channel === null) {
            return $this->badChannel();
        }
        if (! $request->boolean('confirm')) {
            return response()->json(['success' => false, 'message' => 'Confirmation required to enroll on Temu.'], 422);
        }
        $activityId = trim((string) $request->input('activity_id', ''));
        if ($activityId === '' || ! ctype_digit($activityId)) {
            return response()->json(['success' => false, 'message' => 'activity_id (numeric) required'], 422);
        }
        $goodsIds = array_values(array_unique(array_map('strval', (array) $request->input('goods_ids', []))));
        if ($goodsIds === []) {
            return response()->json(['success' => false, 'message' => 'goods_ids required'], 422);
        }
        if (count($goodsIds) > self::MAX_ENROLL_PER_REQUEST) {
            return response()->json(['success' => false, 'message' => 'Enroll at most '.self::MAX_ENROLL_PER_REQUEST.' goods per request.'], 422);
        }

        $plan = $this->buildPlan($channel, $activityId, false);
        if (! $plan['success']) {
            return response()->json($plan, 502);
        }
        $byGoods = [];
        foreach ($plan['rows'] as $row) {
            $byGoods[(string) $row['goods_id']] = $row;
        }

        $promo = $this->promo($channel);
        $results = [];
        $ok = 0;
        $fail = 0;
        foreach ($goodsIds as $goodsId) {
            $row = $byGoods[$goodsId] ?? null;
            if ($row === null) {
                $fail++;
                $results[] = ['goods_id' => $goodsId, 'status' => 'failed', 'message' => 'Not eligible for this activity.'];
                continue;
            }
            if ($row['status'] !== 'ready') {
                $fail++;
                $results[] = ['goods_id' => $goodsId, 'sku' => $row['sku'], 'status' => 'skipped', 'message' => $row['reason']];
                continue;
            }

            $skuPrices = [];
            foreach ($row['skus'] as $s) {
                $skuPrices[(string) $s['sku_id']] = (float) $s['price'];
            }
            $res = $promo->enrollGoods(
                $activityId,
                $goodsId,
                $skuPrices,
                (int) $row['qty'],
                'p'.$channel.substr(md5($goodsId.$activityId.json_encode($skuPrices).$row['qty']), 0, 24)
            );
            Log::info('Promotions Temu enroll', [
                'channel' => $channel,
                'activity_id' => $activityId,
                'goods_id' => $goodsId,
                'qty' => $row['qty'],
                'prices' => $skuPrices,
                'success' => $res['success'],
                'message' => $res['message'],
                'user' => optional($request->user())->email,
            ]);

            if ($res['success']) {
                $ok++;
                $results[] = ['goods_id' => $goodsId, 'sku' => $row['sku'], 'status' => 'ok', 'message' => $res['message'], 'draft_id' => $res['draft_id'] ?? null];
            } else {
                $fail++;
                $results[] = ['goods_id' => $goodsId, 'sku' => $row['sku'], 'status' => 'failed', 'message' => $res['message']];
            }
        }

        return response()->json([
            'success' => $ok > 0 || $fail === 0,
            'channel' => $channel,
            'activity_id' => $activityId,
            'ok' => $ok,
            'failed' => $fail,
            'results' => $results,
            'message' => "Temu promotion: {$ok} enrolled".($fail ? ", {$fail} not enrolled" : '').' (activity '.$activityId.').',
        ]);
    }

    /**
     * Candidate goods of the activity (Temu, cached 10 min) joined with our SKUs / INV.
     *
     * @return array{success: bool, message?: string, rows?: list<array<string, mixed>>}
     */
    private function buildPlan(string $channel, string $activityId, bool $refresh): array
    {
        $cacheKey = 'promotions_temu_candidates:'.$channel.':'.$activityId;
        if ($refresh) {
            Cache::forget($cacheKey);
        }
        $goods = Cache::get($cacheKey);
        if (! is_array($goods)) {
            $res = $this->promo($channel)->candidateGoods($activityId);
            if (! $res['success']) {
                return ['success' => false, 'message' => $res['message'], 'rows' => []];
            }
            $goods = $res['goods'];
            Cache::put($cacheKey, $goods, now()->addMinutes(10));
        }

        $goodsIds = array_map('strval', array_keys($goods));
        $metricClass = self::CHANNELS[$channel][1];

        // goodsId => skuId => [names]
        $local = [];
        foreach (array_chunk($goodsIds, 500) as $chunk) {
            foreach ($metricClass::query()->whereIn('goods_id', $chunk)->get(['goods_id', 'sku_id', 'sku']) as $m) {
                $skuId = trim((string) $m->sku_id);
                $name = trim((string) $m->sku);
                if ($skuId === '' || $name === '') {
                    continue;
                }
                $local[(string) $m->goods_id][$skuId][] = $name;
            }
        }

        $allNames = [];
        foreach ($local as $skus) {
            foreach ($skus as $names) {
                foreach ($names as $n) {
                    $allNames[$n] = $n;
                }
            }
        }
        $inventory = $allNames === [] ? collect() : ShopifySku::mapByProductSkus(array_values($allNames));

        $rows = [];
        foreach ($goods as $goodsId => $g) {
            $goodsId = (string) $goodsId;
            $skus = [];
            $inv = 0;
            $names = [];
            $problem = null;

            if (! ($g['allowed'] ?? false)) {
                $problem = 'Temu does not allow enrolling this goods.';
            }
            foreach (($g['skus'] ?? []) as $skuId => $price) {
                $skuId = (string) $skuId;
                $skuNames = $local[$goodsId][$skuId] ?? [];
                $skuInv = 0;
                foreach ($skuNames as $n) {
                    $skuInv = max($skuInv, (int) floor((float) ($inventory->get($n)->inv ?? 0)));
                    $names[$n] = $n;
                }
                $inv += $skuInv;
                if (! ((float) $price > 0)) {
                    $problem = $problem ?? 'Temu gave no activity price for SKU '.($skuNames[0] ?? $skuId).'.';
                }
                $skus[] = ['sku_id' => $skuId, 'sku' => $skuNames[0] ?? '', 'price' => round((float) $price, 2)];
            }

            $recQty = (int) ($g['qty'] ?? 0);
            $qty = min($recQty, $inv);
            if ($skus === []) {
                $problem = $problem ?? 'No SKUs returned by Temu.';
            } elseif (! isset($local[$goodsId])) {
                $problem = $problem ?? 'Goods not found in our Temu data (cannot check INV).';
            } elseif ($qty < 1) {
                $problem = $problem ?? ($inv < 1 ? 'INV is 0.' : 'Temu recommended quantity is 0.');
            }

            $rows[] = [
                'goods_id' => $goodsId,
                'sku' => (string) (array_key_first($names) ?? ($skus[0]['sku'] ?? '')),
                'skus' => $skus,
                'rec_qty' => $recQty,
                'inv' => $inv,
                'qty' => max(0, $qty),
                'status' => $problem === null ? 'ready' : 'skip',
                'reason' => $problem,
            ];
        }

        usort($rows, fn ($a, $b) => [$a['status'] === 'ready' ? 0 : 1, $a['sku']] <=> [$b['status'] === 'ready' ? 0 : 1, $b['sku']]);

        return ['success' => true, 'rows' => $rows];
    }
}
