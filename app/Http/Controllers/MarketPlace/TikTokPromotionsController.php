<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\TikTokProduct;
use App\Models\TikTokProductTwo;
use App\Services\TikTok2ShopService;
use App\Services\TikTokPromotionApiService;
use App\Services\TikTokShopService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * /promotions → TikTok 1 / TikTok 2: seller percentage-off (DIRECT_DISCOUNT) activities through the Partner API.
 *
 * The % is yours and can be changed later ("Update %"). Nothing is sent until a confirmed POST.
 * A SKU is only discounted when it is listed (product_id + sku_id known), in stock, and — unless
 * allow_below_cost=1 — the discounted price stays above cost (LP + ship; TikTok fees are not included).
 * Coupons cannot be created through TikTok's API.
 */
class TikTokPromotionsController extends Controller
{
    /** channel key => [shop service, product table model] */
    private const CHANNELS = [
        'tiktok' => [TikTokShopService::class, TikTokProduct::class],
        'tiktok2' => [TikTok2ShopService::class, TikTokProductTwo::class],
    ];

    private const MAX_SKUS = 300;

    private function channel(Request $request): ?string
    {
        $key = strtolower(trim((string) $request->input('channel', '')));

        return isset(self::CHANNELS[$key]) ? $key : null;
    }

    private function api(string $channel): TikTokPromotionApiService
    {
        return new TikTokPromotionApiService(app(self::CHANNELS[$channel][0]));
    }

    private function bad(string $message, int $status = 422)
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }

    /** SKUs from an array or a pasted block (new lines / commas). */
    private function skuList(Request $request): array
    {
        $raw = $request->input('skus', []);
        if (is_string($raw)) {
            $raw = preg_split('/[\r\n,;]+/', $raw) ?: [];
        }
        $out = [];
        foreach ((array) $raw as $s) {
            $s = trim((string) $s);
            if ($s !== '') {
                $out[strtoupper($s)] = $s;
            }
        }

        return array_values($out);
    }

    private function pct(Request $request): ?float
    {
        $pct = $request->input('pct');
        if (! is_numeric($pct)) {
            return null;
        }
        $pct = round((float) $pct, 2);

        return ($pct >= 1 && $pct <= 90) ? $pct : null;
    }

    /** LP + ship per product SKU (null when LP is unknown). */
    private function costFor(string $sku): ?float
    {
        $pm = ProductMaster::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])->first();
        if (! $pm) {
            return null;
        }
        $v = is_array($pm->Values) ? $pm->Values : (json_decode((string) $pm->Values, true) ?: []);
        $lp = (float) ($v['lp'] ?? 0);
        if (! ($lp > 0)) {
            return null;
        }

        return round($lp + (float) ($v['ship'] ?? 0), 2);
    }

    /**
     * What applying pct% to these SKUs would do. No TikTok call.
     *
     * @param  list<string>  $skus
     * @return list<array<string, mixed>>
     */
    private function plan(string $channel, array $skus, float $pct, bool $allowBelowCost): array
    {
        $model = self::CHANNELS[$channel][1];
        $found = [];
        foreach (array_chunk($skus, 400) as $chunk) {
            $upper = array_map('strtoupper', $chunk);
            foreach ($model::query()->whereIn(DB::raw('UPPER(TRIM(sku))'), $upper)->get() as $row) {
                $found[strtoupper(trim((string) $row->sku))][] = $row;
            }
        }

        $rows = [];
        foreach ($skus as $sku) {
            $hits = $found[strtoupper($sku)] ?? [];
            $row = ['sku' => $sku, 'status' => 'ready', 'reason' => null];
            if ($hits === []) {
                $rows[] = [
                    'sku' => $sku, 'status' => 'skip', 'reason' => 'SKU not found in TikTok listings',
                    'product_id' => null, 'sku_id' => null, 'price' => null, 'new_price' => null,
                    'cost' => null, 'margin' => null, 'stock' => null,
                ];
                continue;
            }
            $hit = $hits[0];
            $price = (float) $hit->price;
            $stock = (int) $hit->stock;
            $newPrice = round($price * (1 - $pct / 100), 2);
            $cost = $this->costFor($sku);
            $margin = $cost !== null ? round($newPrice - $cost, 2) : null;

            $row += [
                'product_id' => (string) $hit->product_id,
                'sku_id' => (string) $hit->sku_id,
                'price' => $price,
                'new_price' => $newPrice,
                'cost' => $cost,
                'margin' => $margin,
                'stock' => $stock,
            ];
            if (trim((string) $hit->product_id) === '' || trim((string) $hit->sku_id) === '') {
                $row['status'] = 'skip';
                $row['reason'] = 'No TikTok product/SKU id';
            } elseif (! ($price > 0)) {
                $row['status'] = 'skip';
                $row['reason'] = 'No current price';
            } elseif ($stock < 1) {
                $row['status'] = 'skip';
                $row['reason'] = 'Out of stock';
            } elseif ($margin !== null && $margin < 0 && ! $allowBelowCost) {
                $row['status'] = 'skip';
                $row['reason'] = 'Below cost ('.number_format($margin, 2).' per unit)';
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /** Our activities (percentage-off) with status/time. */
    public function activities(Request $request)
    {
        $channel = $this->channel($request);
        if ($channel === null) {
            return $this->bad('channel must be tiktok or tiktok2');
        }
        $res = $this->api($channel)->searchActivities('DIRECT_DISCOUNT');
        if (! $res['success']) {
            return $this->bad($res['message'], 502);
        }
        $out = [];
        foreach ($res['activities'] as $a) {
            $out[] = [
                'activity_id' => (string) ($a['id'] ?? $a['activity_id'] ?? ''),
                'title' => (string) ($a['title'] ?? ''),
                'status' => (string) ($a['status'] ?? ''),
                'begin_time' => (int) ($a['begin_time'] ?? 0),
                'end_time' => (int) ($a['end_time'] ?? 0),
                'product_level' => (string) ($a['product_level'] ?? ''),
            ];
        }
        usort($out, fn ($x, $y) => $y['begin_time'] <=> $x['begin_time']);

        return response()->json(['success' => true, 'channel' => $channel, 'activities' => $out]);
    }

    /** Dry run. */
    public function preview(Request $request)
    {
        $channel = $this->channel($request);
        if ($channel === null) {
            return $this->bad('channel must be tiktok or tiktok2');
        }
        $pct = $this->pct($request);
        if ($pct === null) {
            return $this->bad('pct must be between 1 and 90');
        }
        $skus = $this->skuList($request);
        if ($skus === []) {
            return $this->bad('Enter at least one SKU');
        }
        if (count($skus) > self::MAX_SKUS) {
            return $this->bad('At most '.self::MAX_SKUS.' SKUs per request');
        }

        $rows = $this->plan($channel, $skus, $pct, $request->boolean('allow_below_cost'));
        $ready = count(array_filter($rows, fn ($r) => $r['status'] === 'ready'));

        return response()->json([
            'success' => true,
            'channel' => $channel,
            'pct' => $pct,
            'ready' => $ready,
            'skipped' => count($rows) - $ready,
            'rows' => $rows,
        ]);
    }

    /** Create a new percentage-off activity and put the ready SKUs in it. */
    public function apply(Request $request)
    {
        $channel = $this->channel($request);
        if ($channel === null) {
            return $this->bad('channel must be tiktok or tiktok2');
        }
        if (! $request->boolean('confirm')) {
            return $this->bad('Confirmation required.');
        }
        $pct = $this->pct($request);
        if ($pct === null) {
            return $this->bad('pct must be between 1 and 90');
        }
        $skus = $this->skuList($request);
        if ($skus === [] || count($skus) > self::MAX_SKUS) {
            return $this->bad('Enter 1 to '.self::MAX_SKUS.' SKUs');
        }
        $begin = (int) $request->input('begin_time');
        $end = (int) $request->input('end_time');
        if ($begin < time() + 60) {
            return $this->bad('Start time must be in the future.');
        }
        if ($end < $begin + 600) {
            return $this->bad('End time must be at least 10 minutes after the start.');
        }

        $rows = $this->plan($channel, $skus, $pct, $request->boolean('allow_below_cost'));
        $ready = array_values(array_filter($rows, fn ($r) => $r['status'] === 'ready'));
        if ($ready === []) {
            return response()->json(['success' => false, 'message' => 'No SKU is ready to discount.', 'rows' => $rows], 422);
        }

        $api = $this->api($channel);
        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            $title = 'Disc '.rtrim(rtrim(number_format($pct, 2), '0'), '.').'% '.now()->format('md-Hi');
        }
        $created = $api->createDirectDiscount($title, $begin, $end);
        if (! $created['success']) {
            return response()->json(['success' => false, 'message' => 'Create failed: '.$created['message']], 502);
        }
        $activityId = $created['activity_id'];

        $set = $api->setSkuDiscounts($activityId, array_map(fn ($r) => [
            'product_id' => $r['product_id'], 'sku_id' => $r['sku_id'], 'discount' => $pct,
        ], $ready));
        Log::info('Promotions TikTok apply', [
            'channel' => $channel, 'activity_id' => $activityId, 'pct' => $pct, 'skus' => count($ready),
            'ok' => $set['success'], 'msg' => $set['message'], 'user' => optional($request->user())->email,
        ]);
        if (! $set['success']) {
            return response()->json([
                'success' => false,
                'activity_id' => $activityId,
                'message' => 'Activity '.$activityId.' was created but adding SKUs failed: '.$set['message'].' (deactivate it or retry with Update %).',
                'rows' => $rows,
            ], 502);
        }

        return response()->json([
            'success' => true,
            'channel' => $channel,
            'activity_id' => $activityId,
            'pct' => $pct,
            'applied' => count($ready),
            'skipped' => count($rows) - count($ready),
            'rows' => $rows,
            'message' => count($ready).' SKU(s) at '.$pct.'% off in activity '.$activityId.'.',
        ]);
    }

    /** Change the % of SKUs in an existing activity (adds them if they are not in it yet). */
    public function update(Request $request)
    {
        $channel = $this->channel($request);
        if ($channel === null) {
            return $this->bad('channel must be tiktok or tiktok2');
        }
        if (! $request->boolean('confirm')) {
            return $this->bad('Confirmation required.');
        }
        $activityId = trim((string) $request->input('activity_id', ''));
        if ($activityId === '' || ! ctype_digit($activityId)) {
            return $this->bad('activity_id (numeric) required');
        }
        $pct = $this->pct($request);
        if ($pct === null) {
            return $this->bad('pct must be between 1 and 90');
        }
        $skus = $this->skuList($request);
        if ($skus === [] || count($skus) > self::MAX_SKUS) {
            return $this->bad('Enter 1 to '.self::MAX_SKUS.' SKUs');
        }

        $rows = $this->plan($channel, $skus, $pct, $request->boolean('allow_below_cost'));
        $ready = array_values(array_filter($rows, fn ($r) => $r['status'] === 'ready'));
        if ($ready === []) {
            return response()->json(['success' => false, 'message' => 'No SKU is ready to discount.', 'rows' => $rows], 422);
        }
        $set = $this->api($channel)->setSkuDiscounts($activityId, array_map(fn ($r) => [
            'product_id' => $r['product_id'], 'sku_id' => $r['sku_id'], 'discount' => $pct,
        ], $ready));
        Log::info('Promotions TikTok update %', [
            'channel' => $channel, 'activity_id' => $activityId, 'pct' => $pct, 'skus' => count($ready),
            'ok' => $set['success'], 'msg' => $set['message'], 'user' => optional($request->user())->email,
        ]);
        if (! $set['success']) {
            return response()->json(['success' => false, 'message' => $set['message'], 'rows' => $rows], 502);
        }

        return response()->json([
            'success' => true,
            'activity_id' => $activityId,
            'pct' => $pct,
            'applied' => count($ready),
            'skipped' => count($rows) - count($ready),
            'rows' => $rows,
            'message' => count($ready).' SKU(s) now '.$pct.'% off in activity '.$activityId.'.',
        ]);
    }

    public function deactivate(Request $request)
    {
        $channel = $this->channel($request);
        if ($channel === null) {
            return $this->bad('channel must be tiktok or tiktok2');
        }
        if (! $request->boolean('confirm')) {
            return $this->bad('Confirmation required.');
        }
        $activityId = trim((string) $request->input('activity_id', ''));
        if ($activityId === '' || ! ctype_digit($activityId)) {
            return $this->bad('activity_id (numeric) required');
        }
        $res = $this->api($channel)->deactivate($activityId);
        Log::info('Promotions TikTok deactivate', [
            'channel' => $channel, 'activity_id' => $activityId, 'ok' => $res['success'], 'msg' => $res['message'],
            'user' => optional($request->user())->email,
        ]);

        return response()->json($res, $res['success'] ? 200 : 502);
    }
}
