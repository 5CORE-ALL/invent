<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Channels\ChannelMasterController;
use App\Http\Controllers\Controller;
use App\Jobs\RunChannelPushCpnJob;
use App\Jobs\RunChannelPushPrcJob;
use App\Jobs\RunChannelPushPrmtJob;
use App\Jobs\RunChannelPushSpriceJob;
use App\Models\AmazonDataView;
use App\Models\ChannelTabulatorColumnSetting;
use App\Models\EbayDataView;
use App\Models\EbayMetric;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Services\ChannelPromoPricingService;
use App\Services\Ebay1CouponService;
use App\Services\Ebay1PromotionService;
use App\Services\Support\ChannelPushCpnJobStore;
use App\Services\Support\ChannelPushPrcJobStore;
use App\Services\Support\ChannelPushPrmtJobStore;
use App\Services\Support\ChannelPushSpriceJobStore;
use App\Services\Support\ChannelPushSpriceRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChannelPromoPricingController extends Controller
{
    /** Channels that support background Push Prc queue (Std listing + sale + coupon). */
    private const PUSH_QUEUE_CHANNELS = ['ebay1', 'ebay2', 'ebay2op', 'ebay3'];

    /** Channels that support background Push PRMT % sale-event queue (chunked). */
    private const PUSH_PRMT_QUEUE_CHANNELS = ['ebay2', 'ebay2op', 'ebay3'];

    /** Channels that support background Push CPN % coded-coupon queue (chunked). */
    private const PUSH_CPN_QUEUE_CHANNELS = ['ebay2', 'ebay2op', 'ebay3', 'temu'];

    public function __construct(
        private readonly ChannelPromoPricingService $promo
    ) {}

    /**
     * Queue Push Prc jobs (background). Appends if a job is already running.
     * Same pattern as /amazon-push-prc.
     */
    public function queuePushPrc(Request $request, string $channel): JsonResponse
    {
        $channel = strtolower(trim($channel));
        if (! in_array($channel, self::PUSH_QUEUE_CHANNELS, true) || ! $this->promo->isSupported($channel)) {
            return response()->json(['success' => false, 'message' => 'Unsupported channel for Push Prc queue'], 422);
        }

        $items = $request->input('items', []);
        if (! is_array($items) || $items === []) {
            return response()->json(['success' => false, 'message' => 'No items to push'], 400);
        }

        $tasks = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $tasks[] = [
                'sku' => $item['sku'] ?? null,
                'std' => $item['std'] ?? $item['price'] ?? null,
                'sale' => $item['sale'] ?? $item['sale_price'] ?? null,
                'max' => $item['max'] ?? $item['max_price'] ?? null,
                'min' => $item['min'] ?? $item['min_price'] ?? null,
                'business' => $item['business'] ?? $item['business_price'] ?? null,
                'effective' => $item['effective'] ?? $item['std'] ?? $item['price'] ?? null,
                'prmt' => $item['prmt'] ?? $item['prmt_pct'] ?? 0,
                'cpn' => $item['cpn'] ?? $item['cpn_pct'] ?? 0,
                'cvr_disc' => $item['cvr_disc'] ?? $item['cvrDisc'] ?? 0,
            ];
        }

        $store = ChannelPushPrcJobStore::for($channel);
        $result = $store->createOrAppend($tasks);
        $state = $result['state'];
        $mode = $result['mode'];
        if ((int) ($state['total'] ?? 0) === 0) {
            return response()->json(['success' => false, 'message' => 'No valid push items (need SKU + Std > 0)'], 400);
        }

        $this->releaseUniqueJobLock($channel);
        $spawned = $this->spawnPushPrcWorker($channel);
        if (! $spawned) {
            try {
                RunChannelPushPrcJob::dispatch($channel);
                Log::warning('Channel Push Prc sync spawn failed — fell back to queue dispatch', ['channel' => $channel]);
            } catch (\Throwable $e) {
                Log::error('Channel Push Prc queue dispatch also failed', [
                    'channel' => $channel,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $store->update(function (array $s) use ($spawned, $mode) {
            $s['worker_spawned_at'] = now()->toDateTimeString();
            if ($mode === 'append') {
                $s['last_message'] = $spawned
                    ? ('Appended — worker continuing ('.$s['total'].' total)…')
                    : ('Appended — waiting for worker ('.$s['total'].' total)');
            } else {
                $s['last_message'] = $spawned
                    ? ('Worker started — pushing '.$s['total'].' SKU(s)…')
                    : ('Queued — waiting for worker (run: php artisan channel:push-prc-run '.$s['channel'].' --sync)');
            }

            return $s;
        });

        $api = $store->toApiResponse($store->load());

        return response()->json(array_merge($api, [
            'success' => true,
            'mode' => $mode,
            'worker_spawned' => $spawned,
            'message' => $mode === 'append'
                ? ('Added to running Push Prc queue ('.$api['total'].' total).')
                : ('Push Prc started in background ('.$api['total'].' SKU(s)). You can refresh or queue more.'),
        ]));
    }

    public function pushPrcJobStatus(string $channel): JsonResponse
    {
        $channel = strtolower(trim($channel));
        if (! in_array($channel, self::PUSH_QUEUE_CHANNELS, true)) {
            return response()->json(['success' => false, 'message' => 'Unsupported channel'], 422);
        }

        $store = ChannelPushPrcJobStore::for($channel);
        $state = $store->load();

        if ($store->isActive($state) && $store->isStale($state, 180) && ! $this->runnerLockHeld($channel)) {
            $this->releaseUniqueJobLock($channel);
            $kicked = $this->spawnPushPrcWorker($channel);
            $store->update(function (array $s) use ($kicked) {
                $s['last_message'] = $kicked
                    ? 'Worker re-started after stall — continuing Push Prc…'
                    : 'Push Prc stalled — could not start worker. Cancel and retry, or run: php artisan channel:push-prc-run --sync';
                $s['worker_spawned_at'] = now()->toDateTimeString();

                return $s;
            });
            $state = $store->load();
        }

        return response()->json($store->toApiResponse($state));
    }

    public function cancelPushPrc(string $channel): JsonResponse
    {
        $channel = strtolower(trim($channel));
        if (! in_array($channel, self::PUSH_QUEUE_CHANNELS, true)) {
            return response()->json(['success' => false, 'message' => 'Unsupported channel'], 422);
        }

        $store = ChannelPushPrcJobStore::for($channel);
        $job = $store->forceStop('Cancelled by user.');
        $this->releaseUniqueJobLock($channel);

        return response()->json(array_merge($store->toApiResponse($job), [
            'success' => true,
            'message' => 'Push Prc cancelled.',
        ]));
    }

    /**
     * Queue S PRC → live listing price (background). Survives page close.
     * ebay1 / ebay2 / ebay2op / ebay3. Does not create sale or coupon.
     */
    public function queuePushSprice(Request $request, string $channel): JsonResponse
    {
        $channel = strtolower(trim($channel));
        if (! in_array($channel, self::PUSH_QUEUE_CHANNELS, true)) {
            return response()->json(['success' => false, 'message' => 'Unsupported channel for S PRC queue'], 422);
        }

        $items = $request->input('items', []);
        if (! is_array($items) || $items === []) {
            return response()->json(['success' => false, 'message' => 'No items to push'], 400);
        }

        $tasks = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $tasks[] = [
                'sku' => $item['sku'] ?? null,
                'price' => $item['price'] ?? $item['sprice'] ?? $item['sale'] ?? null,
            ];
        }

        $store = ChannelPushSpriceJobStore::for($channel);
        $result = $store->createOrAppend($tasks);
        $state = $result['state'];
        $mode = $result['mode'];
        if ((int) ($state['total'] ?? 0) === 0) {
            return response()->json(['success' => false, 'message' => 'No valid S PRC items (need SKU + price > 0)'], 400);
        }

        $this->releaseUniqueSpriceJobLock($channel);
        $spawned = ChannelPushSpriceRunner::spawnWorker($channel);
        if (! $spawned) {
            try {
                RunChannelPushSpriceJob::dispatch($channel);
                Log::warning('Channel S PRC sync spawn failed — fell back to queue dispatch', ['channel' => $channel]);
            } catch (\Throwable $e) {
                Log::error('Channel S PRC queue dispatch also failed', [
                    'channel' => $channel,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $store->update(function (array $s) use ($spawned, $mode) {
            $s['worker_spawned_at'] = now()->toDateTimeString();
            if ($mode === 'append') {
                $s['last_message'] = $spawned
                    ? ('Appended — S PRC worker continuing ('.$s['total'].' total)…')
                    : ('Appended — waiting for S PRC worker ('.$s['total'].' total)');
            } else {
                $s['last_message'] = $spawned
                    ? ('S PRC worker started — pushing '.$s['total'].' SKU(s)…')
                    : ('Queued — waiting for worker (run: php artisan channel:push-sprice-run '.$s['channel'].' --sync)');
            }

            return $s;
        });

        $api = $store->toApiResponse($store->load());

        return response()->json(array_merge($api, [
            'success' => true,
            'mode' => $mode,
            'worker_spawned' => $spawned,
            'message' => $mode === 'append'
                ? ('Added to running S PRC queue ('.$api['total'].' total). Page close is OK.')
                : ('S PRC push started in background ('.$api['total'].' SKU(s)). Page close is OK.'),
        ]));
    }

    public function pushSpriceJobStatus(string $channel): JsonResponse
    {
        $channel = strtolower(trim($channel));
        if (! in_array($channel, self::PUSH_QUEUE_CHANNELS, true)) {
            return response()->json(['success' => false, 'message' => 'Unsupported channel'], 422);
        }

        $store = ChannelPushSpriceJobStore::for($channel);
        $state = $store->load();

        if ($store->isActive($state) && $store->isStale($state, 180) && ! ChannelPushSpriceRunner::lockHeld($channel)) {
            $this->releaseUniqueSpriceJobLock($channel);
            $kicked = ChannelPushSpriceRunner::spawnWorker($channel);
            $store->update(function (array $s) use ($kicked) {
                $s['last_message'] = $kicked
                    ? 'S PRC worker re-started after stall — continuing…'
                    : 'S PRC push stalled — could not start worker. Cancel and retry, or run: php artisan channel:push-sprice-run --sync';
                $s['worker_spawned_at'] = now()->toDateTimeString();

                return $s;
            });
            $state = $store->load();
        }

        return response()->json($store->toApiResponse($state));
    }

    public function cancelPushSprice(string $channel): JsonResponse
    {
        $channel = strtolower(trim($channel));
        if (! in_array($channel, self::PUSH_QUEUE_CHANNELS, true)) {
            return response()->json(['success' => false, 'message' => 'Unsupported channel'], 422);
        }

        $store = ChannelPushSpriceJobStore::for($channel);
        $job = $store->forceStop('Cancelled by user.');
        $this->releaseUniqueSpriceJobLock($channel);

        return response()->json(array_merge($store->toApiResponse($job), [
            'success' => true,
            'message' => 'S PRC push cancelled.',
        ]));
    }

    /**
     * End every active eBay markdown sale event for the channel.
     */
    public function endAllSales(string $channel): JsonResponse
    {
        $channel = strtolower(trim($channel));
        if (! in_array($channel, ['ebay1', 'ebay2', 'ebay2op', 'ebay3'], true)) {
            return response()->json(['success' => false, 'message' => 'Unsupported channel'], 422);
        }

        $res = Ebay1PromotionService::for($channel)->endAllSales();
        Log::info('Channel end-all sales', ['channel' => $channel] + $res);

        return response()->json(array_merge($res, [
            'message' => 'Sales ended: '.((int) ($res['ended'] ?? 0))
                .' · failed '.((int) ($res['failed'] ?? 0))
                .' · already ended '.((int) ($res['skipped'] ?? 0)),
        ]), ! empty($res['success']) ? 200 : 400);
    }

    /**
     * Pause/end every active eBay coded coupon for the channel.
     */
    public function endAllCoupons(string $channel): JsonResponse
    {
        $channel = strtolower(trim($channel));
        if (! in_array($channel, ['ebay1', 'ebay2', 'ebay2op', 'ebay3'], true)) {
            return response()->json(['success' => false, 'message' => 'Unsupported channel'], 422);
        }

        $res = Ebay1CouponService::for($channel)->endAllCoupons();
        Log::info('Channel end-all coupons', ['channel' => $channel] + $res);

        return response()->json(array_merge($res, [
            'message' => 'Coupons ended: '.((int) ($res['ended'] ?? 0))
                .' · failed '.((int) ($res['failed'] ?? 0))
                .' · already ended '.((int) ($res['skipped'] ?? 0)),
        ]), ! empty($res['success']) ? 200 : 400);
    }

    /**
     * S PRC = Std × (1 − T Promo/100). T Promo = PRMT% + CPN%. Skips INV = 0.
     */
    public function applySpriceFromTPromo(Request $request, string $channel): JsonResponse
    {
        $channel = strtolower(trim($channel));
        if ($channel !== 'ebay1') {
            return response()->json(['success' => false, 'message' => 'Sprice vs T promo apply is wired for ebay1'], 422);
        }

        $only = [];
        $onlySkus = $request->input('skus', []);
        if (is_array($onlySkus)) {
            foreach ($onlySkus as $sku) {
                $sku = strtoupper(trim((string) $sku));
                if ($sku !== '') {
                    $only[$sku] = true;
                }
            }
        }

        $skus = [];
        EbayMetric::query()
            ->whereNotNull('item_id')
            ->where('item_id', '!=', '')
            ->orderBy('sku')
            ->pluck('sku')
            ->each(function ($raw) use (&$skus, $only) {
                $sku = strtoupper(trim((string) $raw));
                if ($sku === '' || isset($skus[$sku])) {
                    return;
                }
                if ($only !== [] && ! isset($only[$sku])) {
                    return;
                }
                $skus[$sku] = $sku;
            });
        $skus = array_values($skus);
        if ($skus === []) {
            return response()->json([
                'success' => true,
                'ok' => 0,
                'fail' => 0,
                'skipped' => 0,
                'skipped_inv' => 0,
                'message' => 'No listed eBay1 SKUs to fill',
            ]);
        }

        $stdBySku = [];
        $invBySku = [];
        $lpBySku = [];
        foreach (array_chunk($skus, 400) as $chunk) {
            $in = 'UPPER(TRIM(sku)) in ('.implode(',', array_fill(0, count($chunk), '?')).')';
            AmazonDataView::query()
                ->whereRaw($in, $chunk)
                ->get(['sku', 'value'])
                ->each(function ($row) use (&$stdBySku) {
                    $val = is_array($row->value) ? $row->value : [];
                    $std = $val['STANDARD_PRICE'] ?? null;
                    if (is_numeric($std) && (float) $std > 0) {
                        $stdBySku[strtoupper(trim((string) $row->sku))] = round((float) $std, 2);
                    }
                });
            ShopifySku::query()
                ->whereRaw($in, $chunk)
                ->get(['sku', 'inv'])
                ->each(function ($row) use (&$invBySku) {
                    $invBySku[strtoupper(trim((string) $row->sku))] = (int) ($row->inv ?? 0);
                });
            ProductMaster::query()
                ->whereRaw($in, $chunk)
                ->get(['sku', 'Values'])
                ->each(function ($pm) use (&$lpBySku) {
                    $values = is_array($pm->Values)
                        ? $pm->Values
                        : (is_string($pm->Values) ? (json_decode($pm->Values, true) ?: []) : []);
                    $lp = 0.0;
                    foreach ($values as $k => $v) {
                        if (strtolower((string) $k) === 'lp') {
                            $lp = (float) $v;
                            break;
                        }
                    }
                    if ($lp <= 0 && isset($pm->lp)) {
                        $lp = (float) $pm->lp;
                    }
                    $ship = isset($values['ship'])
                        ? (float) $values['ship']
                        : (isset($pm->ship) ? (float) $pm->ship : 0.0);
                    $lpBySku[strtoupper(trim((string) $pm->sku))] = ['lp' => $lp, 'ship' => $ship];
                });
        }

        $promoMap = $this->promo->mapForSkus('ebay1', $skus);
        $percentage = MarketplacePercentage::takeHomeDecimal('Ebay');
        try {
            $adPercent = (float) app(ChannelMasterController::class)->getEbayMasterAdsPercent();
        } catch (\Throwable $e) {
            $adPercent = 0.0;
        }
        $adDecimal = $adPercent / 100;

        $ok = 0;
        $fail = 0;
        $skipped = 0;
        $skippedInv = 0;
        foreach ($skus as $sku) {
            if (($invBySku[$sku] ?? 0) <= 0) {
                $skippedInv++;

                continue;
            }
            $std = $stdBySku[$sku] ?? 0;
            if (! ($std > 0)) {
                $skipped++;

                continue;
            }
            $promo = $promoMap[$sku] ?? [];
            $prmt = is_numeric($promo['prmt_pct'] ?? null)
                ? (float) $promo['prmt_pct']
                : (float) ($promo['_prmt_pct_applied'] ?? 0);
            $cpn = is_numeric($promo['cpn_pct'] ?? null)
                ? (float) $promo['cpn_pct']
                : (float) ($promo['_cpn_pct_applied'] ?? 0);
            $t = min(99.99, max(0, $prmt + $cpn));
            $sprice = $t > 0 ? round($std * (1 - $t / 100), 2) : $std;
            if ($sprice < 0.01) {
                $fail++;

                continue;
            }

            $lp = (float) (($lpBySku[$sku]['lp'] ?? 0));
            $ship = (float) (($lpBySku[$sku]['ship'] ?? 0));
            $sgpft = $sprice > 0 ? round((($sprice * $percentage - $ship - $lp) / $sprice) * 100, 2) : 0;
            $spft = round($sgpft - $adPercent, 2);
            $sgroi = round($lp > 0 ? (($sprice * $percentage - $lp - $ship) / $lp) * 100 : 0, 2);
            $sroi = round(
                $lp > 0 ? ((($sprice * $percentage - $ship - $lp) - ($sprice * $adDecimal)) / $lp) * 100 : 0,
                2
            );

            try {
                DB::transaction(function () use ($sku, $sprice, $spft, $sroi, $sgroi, $sgpft) {
                    $dv = EbayDataView::whereRaw('LOWER(TRIM(sku)) = ?', [strtolower($sku)])
                        ->lockForUpdate()
                        ->first();
                    if (! $dv) {
                        $dv = new EbayDataView();
                        $dv->sku = $sku;
                    }
                    $existing = is_array($dv->value)
                        ? $dv->value
                        : (json_decode((string) $dv->value, true) ?: []);
                    $dv->value = array_merge($existing, [
                        'SPRICE' => $sprice,
                        'SPFT' => $spft,
                        'SROI' => $sroi,
                        'SGROI' => $sgroi,
                        'SGPFT' => $sgpft,
                    ]);
                    $dv->save();
                });
                $ok++;
            } catch (\Throwable $e) {
                $fail++;
                Log::warning('Sprice vs T promo save failed', [
                    'sku' => $sku,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => $fail === 0,
            'ok' => $ok,
            'fail' => $fail,
            'skipped' => $skipped,
            'skipped_inv' => $skippedInv,
            'message' => 'S PRC = Std − T Promo: '.$ok.' filled'
                .($fail ? (', '.$fail.' failed') : '')
                .($skippedInv ? (', '.$skippedInv.' skipped INV=0') : '')
                .($skipped ? (', '.$skipped.' skipped (no Std)') : ''),
        ]);
    }

    /**
     * Queue Push PRMT % jobs in chunks (background markdown sale events).
     */
    public function queuePushPrmt(Request $request, string $channel): JsonResponse
    {
        $channel = strtolower(trim($channel));
        if (! in_array($channel, self::PUSH_PRMT_QUEUE_CHANNELS, true) || ! $this->promo->isSupported($channel)) {
            return response()->json(['success' => false, 'message' => 'Unsupported channel for Push PRMT% queue'], 422);
        }

        $items = $request->input('items', []);
        if (! is_array($items) || $items === []) {
            return response()->json(['success' => false, 'message' => 'No items to push'], 400);
        }

        $tasks = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $tasks[] = [
                'sku' => $item['sku'] ?? null,
                'prmt' => $item['prmt'] ?? $item['prmt_pct'] ?? $item['percent'] ?? 0,
            ];
        }

        $store = ChannelPushPrmtJobStore::for($channel);
        $result = $store->createOrAppend($tasks);
        $state = $result['state'];
        $mode = $result['mode'];
        if ((int) ($state['total'] ?? 0) === 0) {
            return response()->json(['success' => false, 'message' => 'No valid Push PRMT% items (need SKU)'], 400);
        }

        $this->releaseUniquePrmtJobLock($channel);
        $spawned = $this->spawnPushPrmtWorker($channel);
        if (! $spawned) {
            try {
                RunChannelPushPrmtJob::dispatch($channel);
            } catch (\Throwable $e) {
                Log::error('Channel Push PRMT% queue dispatch failed', [
                    'channel' => $channel,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $store->update(function (array $s) use ($spawned, $mode) {
            $s['worker_spawned_at'] = now()->toDateTimeString();
            $s['last_message'] = $mode === 'append'
                ? ($spawned
                    ? ('Appended — worker continuing ('.$s['total'].' total)…')
                    : ('Appended — waiting for worker ('.$s['total'].' total)'))
                : ($spawned
                    ? ('Worker started — pushing PRMT% for '.$s['total'].' SKU(s) in chunks of 10…')
                    : ('Queued — waiting for worker (run: php artisan channel:push-prmt-run '.$s['channel'].' --sync)'));

            return $s;
        });

        $api = $store->toApiResponse($store->load());

        return response()->json(array_merge($api, [
            'success' => true,
            'mode' => $mode,
            'worker_spawned' => $spawned,
            'chunk_size' => 10,
            'message' => $mode === 'append'
                ? ('Added to running Push PRMT% queue ('.$api['total'].' total).')
                : ('Push PRMT% started in background ('.$api['total'].' SKU(s), chunks of 10).'),
        ]));
    }

    public function pushPrmtJobStatus(string $channel): JsonResponse
    {
        $channel = strtolower(trim($channel));
        if (! in_array($channel, self::PUSH_PRMT_QUEUE_CHANNELS, true)) {
            return response()->json(['success' => false, 'message' => 'Unsupported channel'], 422);
        }

        $store = ChannelPushPrmtJobStore::for($channel);
        $state = $store->load();

        if ($store->isActive($state) && $store->isStale($state, 180) && ! $this->prmtRunnerLockHeld($channel)) {
            $this->releaseUniquePrmtJobLock($channel);
            $kicked = $this->spawnPushPrmtWorker($channel);
            $store->update(function (array $s) use ($kicked) {
                $s['last_message'] = $kicked
                    ? 'Worker re-started after stall — continuing Push PRMT%…'
                    : 'Push PRMT% stalled — could not start worker.';
                $s['worker_spawned_at'] = now()->toDateTimeString();

                return $s;
            });
            $state = $store->load();
        }

        return response()->json($store->toApiResponse($state));
    }

    public function cancelPushPrmt(string $channel): JsonResponse
    {
        $channel = strtolower(trim($channel));
        if (! in_array($channel, self::PUSH_PRMT_QUEUE_CHANNELS, true)) {
            return response()->json(['success' => false, 'message' => 'Unsupported channel'], 422);
        }

        $store = ChannelPushPrmtJobStore::for($channel);
        $job = $store->forceStop('Cancelled by user.');
        $this->releaseUniquePrmtJobLock($channel);

        return response()->json(array_merge($store->toApiResponse($job), [
            'success' => true,
            'message' => 'Push PRMT% cancelled.',
        ]));
    }

    /**
     * Queue Push CPN % jobs in chunks (background public coded coupons).
     */
    public function queuePushCpn(Request $request, string $channel): JsonResponse
    {
        $channel = strtolower(trim($channel));
        if (! in_array($channel, self::PUSH_CPN_QUEUE_CHANNELS, true) || ! $this->promo->isSupported($channel)) {
            return response()->json(['success' => false, 'message' => 'Unsupported channel for Push CPN% queue'], 422);
        }

        $items = $request->input('items', []);
        if (! is_array($items) || $items === []) {
            return response()->json(['success' => false, 'message' => 'No items to push'], 400);
        }

        $tasks = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $tasks[] = [
                'sku' => $item['sku'] ?? null,
                'cpn' => $item['cpn'] ?? $item['cpn_pct'] ?? $item['percent'] ?? 0,
            ];
        }

        $store = ChannelPushCpnJobStore::for($channel);
        $result = $store->createOrAppend($tasks);
        $state = $result['state'];
        $mode = $result['mode'];
        if ((int) ($state['total'] ?? 0) === 0) {
            return response()->json(['success' => false, 'message' => 'No valid Push CPN% items (need SKU)'], 400);
        }

        $this->releaseUniqueCpnJobLock($channel);
        $spawned = $this->spawnPushCpnWorker($channel);
        if (! $spawned) {
            try {
                RunChannelPushCpnJob::dispatch($channel);
            } catch (\Throwable $e) {
                Log::error('Channel Push CPN% queue dispatch failed', [
                    'channel' => $channel,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $store->update(function (array $s) use ($spawned, $mode) {
            $s['worker_spawned_at'] = now()->toDateTimeString();
            $s['last_message'] = $mode === 'append'
                ? ($spawned
                    ? ('Appended — worker continuing ('.$s['total'].' total)…')
                    : ('Appended — waiting for worker ('.$s['total'].' total)'))
                : ($spawned
                    ? ('Worker started — pushing CPN% for '.$s['total'].' SKU(s) in chunks of 10…')
                    : ('Queued — waiting for worker (run: php artisan channel:push-cpn-run '.$s['channel'].' --sync)'));

            return $s;
        });

        $api = $store->toApiResponse($store->load());

        return response()->json(array_merge($api, [
            'success' => true,
            'mode' => $mode,
            'worker_spawned' => $spawned,
            'chunk_size' => 10,
            'message' => $mode === 'append'
                ? ('Added to running Push CPN% queue ('.$api['total'].' total).')
                : ('Push CPN% started in background ('.$api['total'].' SKU(s), chunks of 10).'),
        ]));
    }

    public function pushCpnJobStatus(string $channel): JsonResponse
    {
        $channel = strtolower(trim($channel));
        if (! in_array($channel, self::PUSH_CPN_QUEUE_CHANNELS, true)) {
            return response()->json(['success' => false, 'message' => 'Unsupported channel'], 422);
        }

        $store = ChannelPushCpnJobStore::for($channel);
        $state = $store->load();

        if ($store->isActive($state) && $store->isStale($state, 180) && ! $this->cpnRunnerLockHeld($channel)) {
            $this->releaseUniqueCpnJobLock($channel);
            $kicked = $this->spawnPushCpnWorker($channel);
            $store->update(function (array $s) use ($kicked) {
                $s['last_message'] = $kicked
                    ? 'Worker re-started after stall — continuing Push CPN%…'
                    : 'Push CPN% stalled — could not start worker.';
                $s['worker_spawned_at'] = now()->toDateTimeString();

                return $s;
            });
            $state = $store->load();
        }

        return response()->json($store->toApiResponse($state));
    }

    public function cancelPushCpn(string $channel): JsonResponse
    {
        $channel = strtolower(trim($channel));
        if (! in_array($channel, self::PUSH_CPN_QUEUE_CHANNELS, true)) {
            return response()->json(['success' => false, 'message' => 'Unsupported channel'], 422);
        }

        $store = ChannelPushCpnJobStore::for($channel);
        $job = $store->forceStop('Cancelled by user.');
        $this->releaseUniqueCpnJobLock($channel);

        return response()->json(array_merge($store->toApiResponse($job), [
            'success' => true,
            'message' => 'Push CPN% cancelled.',
        ]));
    }

    private function spawnPushCpnWorker(string $channel): bool
    {
        try {
            if ($this->cpnRunnerLockHeld($channel)) {
                return true;
            }
            $php = PHP_BINARY ?: 'php';
            if (stripos($php, 'fpm') !== false || stripos($php, 'cgi') !== false) {
                $cli = trim((string) shell_exec('command -v php 2>/dev/null'));
                if ($cli !== '') {
                    $php = $cli;
                }
            }
            $artisan = base_path('artisan');
            $log = storage_path('logs/'.$channel.'-push-cpn.log');
            if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
                pclose(popen('start /B '.escapeshellarg($php).' '.escapeshellarg($artisan).' channel:push-cpn-run '.escapeshellarg($channel).' --sync', 'r'));

                return true;
            }
            $cmd = 'nohup '.escapeshellarg($php).' '.escapeshellarg($artisan)
                .' channel:push-cpn-run '.escapeshellarg($channel)
                .' --sync >> '.escapeshellarg($log).' 2>&1 &';
            exec($cmd);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Channel Push CPN% worker spawn failed', [
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function cpnRunnerLockHeld(string $channel): bool
    {
        $lockPath = storage_path('app/'.$channel.'-push-cpn/runner.lock');
        if (! is_file($lockPath)) {
            return false;
        }
        $h = @fopen($lockPath, 'c+');
        if (! $h) {
            return false;
        }
        $got = flock($h, LOCK_EX | LOCK_NB);
        if ($got) {
            flock($h, LOCK_UN);
        }
        fclose($h);

        return ! $got;
    }

    private function releaseUniqueCpnJobLock(string $channel): void
    {
        try {
            \Illuminate\Support\Facades\Cache::lock(
                'laravel_unique_job:'.RunChannelPushCpnJob::class.':'.$channel.'-push-cpn'
            )->forceRelease();
        } catch (\Throwable) {
            // ignore
        }
    }

    private function spawnPushPrmtWorker(string $channel): bool
    {
        try {
            if ($this->prmtRunnerLockHeld($channel)) {
                return true;
            }
            $php = PHP_BINARY ?: 'php';
            if (stripos($php, 'fpm') !== false || stripos($php, 'cgi') !== false) {
                $cli = trim((string) shell_exec('command -v php 2>/dev/null'));
                if ($cli !== '') {
                    $php = $cli;
                }
            }
            $artisan = base_path('artisan');
            $log = storage_path('logs/'.$channel.'-push-prmt.log');
            if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
                pclose(popen('start /B '.escapeshellarg($php).' '.escapeshellarg($artisan).' channel:push-prmt-run '.escapeshellarg($channel).' --sync', 'r'));

                return true;
            }
            $cmd = 'nohup '.escapeshellarg($php).' '.escapeshellarg($artisan)
                .' channel:push-prmt-run '.escapeshellarg($channel)
                .' --sync >> '.escapeshellarg($log).' 2>&1 &';
            // pclose(popen) returns immediately; exec() can wait for the worker on macOS/XAMPP.
            pclose(popen($cmd, 'r'));

            return true;
        } catch (\Throwable $e) {
            Log::warning('Channel Push PRMT% worker spawn failed', [
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function prmtRunnerLockHeld(string $channel): bool
    {
        $lockPath = storage_path('app/'.$channel.'-push-prmt/runner.lock');
        if (! is_file($lockPath)) {
            return false;
        }
        $h = @fopen($lockPath, 'c+');
        if (! $h) {
            return false;
        }
        $got = flock($h, LOCK_EX | LOCK_NB);
        if ($got) {
            flock($h, LOCK_UN);
        }
        fclose($h);

        return ! $got;
    }

    private function releaseUniquePrmtJobLock(string $channel): void
    {
        try {
            \Illuminate\Support\Facades\Cache::lock(
                'laravel_unique_job:'.RunChannelPushPrmtJob::class.':'.$channel.'-push-prmt'
            )->forceRelease();
        } catch (\Throwable) {
            // ignore
        }
    }

    private function spawnPushSpriceWorker(string $channel): bool
    {
        try {
            if ($this->spriceRunnerLockHeld($channel)) {
                return true;
            }
            $php = PHP_BINARY ?: 'php';
            if (stripos($php, 'fpm') !== false || stripos($php, 'cgi') !== false) {
                $cli = trim((string) shell_exec('command -v php 2>/dev/null'));
                if ($cli !== '') {
                    $php = $cli;
                }
            }
            $artisan = base_path('artisan');
            $log = storage_path('logs/'.$channel.'-push-sprice.log');
            if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
                pclose(popen('start /B '.escapeshellarg($php).' '.escapeshellarg($artisan).' channel:push-sprice-run '.escapeshellarg($channel).' --sync', 'r'));

                return true;
            }
            $cmd = 'nohup '.escapeshellarg($php).' '.escapeshellarg($artisan)
                .' channel:push-sprice-run '.escapeshellarg($channel)
                .' --sync >> '.escapeshellarg($log).' 2>&1 &';
            // pclose(popen) returns immediately; exec() can wait for the worker on macOS/XAMPP.
            pclose(popen($cmd, 'r'));

            return true;
        } catch (\Throwable $e) {
            Log::warning('Channel S PRC worker spawn failed', [
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function spriceRunnerLockHeld(string $channel): bool
    {
        $lockPath = storage_path('app/'.$channel.'-push-sprice/runner.lock');
        if (! is_file($lockPath)) {
            return false;
        }
        $h = @fopen($lockPath, 'c+');
        if (! $h) {
            return false;
        }
        $got = flock($h, LOCK_EX | LOCK_NB);
        if ($got) {
            flock($h, LOCK_UN);
        }
        fclose($h);

        return ! $got;
    }

    private function releaseUniqueSpriceJobLock(string $channel): void
    {
        try {
            \Illuminate\Support\Facades\Cache::lock(
                'laravel_unique_job:'.RunChannelPushSpriceJob::class.':'.$channel.'-push-sprice'
            )->forceRelease();
        } catch (\Throwable) {
            // ignore
        }
    }

    private function spawnPushPrcWorker(string $channel): bool
    {
        try {
            if ($this->runnerLockHeld($channel)) {
                return true;
            }
            $php = PHP_BINARY ?: 'php';
            if (stripos($php, 'fpm') !== false || stripos($php, 'cgi') !== false) {
                $cli = trim((string) shell_exec('command -v php 2>/dev/null'));
                if ($cli !== '') {
                    $php = $cli;
                }
            }
            $artisan = base_path('artisan');
            $log = storage_path('logs/'.$channel.'-push-prc.log');
            if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
                pclose(popen('start /B '.escapeshellarg($php).' '.escapeshellarg($artisan).' channel:push-prc-run '.escapeshellarg($channel).' --sync', 'r'));

                return true;
            }
            $cmd = 'nohup '.escapeshellarg($php).' '.escapeshellarg($artisan)
                .' channel:push-prc-run '.escapeshellarg($channel)
                .' --sync >> '.escapeshellarg($log).' 2>&1 &';
            exec($cmd);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Channel Push Prc worker spawn failed', [
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function runnerLockHeld(string $channel): bool
    {
        $lockPath = storage_path('app/'.$channel.'-push-prc/runner.lock');
        if (! is_file($lockPath)) {
            return false;
        }
        $h = @fopen($lockPath, 'c+');
        if (! $h) {
            return false;
        }
        $got = flock($h, LOCK_EX | LOCK_NB);
        if ($got) {
            flock($h, LOCK_UN);
        }
        fclose($h);

        return ! $got;
    }

    private function releaseUniqueJobLock(string $channel): void
    {
        try {
            \Illuminate\Support\Facades\Cache::lock(
                'laravel_unique_job:'.RunChannelPushPrcJob::class.':'.$channel.'-push-prc'
            )->forceRelease();
        } catch (\Throwable) {
            // ignore
        }
    }

    public function save(Request $request): JsonResponse
    {
        $channel = strtolower(trim((string) $request->input('channel', '')));
        $sku = trim((string) $request->input('sku', ''));

        if ($channel === '' || ! $this->promo->isSupported($channel)) {
            return response()->json(['success' => false, 'message' => 'Unsupported channel'], 422);
        }
        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU required'], 422);
        }

        $fields = [];
        foreach (['prmt_pct', 'zero_sold_prmt', 'cpn_pct', 'dsc_pct', 'dsc', 'appr', 'push_prc_status', 'push_prc_value', 'push_std_prc_status', 'push_std_prc_value', 'push_std_prc_pushed_at'] as $key) {
            if ($request->exists($key)) {
                $fields[$key] = $request->input($key);
            }
        }
        if ($request->boolean('record_push_prc')) {
            $fields['push_prc_status'] = 'pushed';
            if ($request->exists('push_prc_value')) {
                $fields['push_prc_value'] = $request->input('push_prc_value');
            }
            $fields['push_prc_pushed_at'] = now();
        }
        if ($request->boolean('record_push_std_prc')) {
            $fields['push_std_prc_status'] = 'pushed';
            if ($request->exists('push_std_prc_value')) {
                $fields['push_std_prc_value'] = $request->input('push_std_prc_value');
            }
            $fields['push_std_prc_pushed_at'] = now();
        }

        if ($fields === []) {
            return response()->json(['success' => false, 'message' => 'No fields to save'], 422);
        }

        try {
            // Writes Amazon-format keys into the channel's *_data_view.value
            // (e.g. ebay1 → ebay_data_view: PEF_PRMT_PCT, PEF_CPN_PCT, PUSH_PRC_*)
            $saved = $this->promo->upsert($channel, $sku, $fields);
            $prmt = $this->nullablePct($saved['prmt_pct'] ?? null);
            $zeroSold = $this->nullablePct($saved['zero_sold_prmt'] ?? null);
            $cpn = $this->nullablePct($saved['cpn_pct'] ?? null);

            return response()->json([
                'success' => true,
                'message' => 'Promo pricing saved',
                'channel' => $channel,
                'sku' => $sku,
                'prmt_pct' => $prmt,
                'zero_sold_prmt' => $zeroSold,
                'cpn_pct' => $cpn,
                'dsc' => $this->nullablePct($saved['dsc'] ?? null),
                'appr' => (bool) ($saved['appr'] ?? false),
                'PUSH_PRC_STATUS' => $saved['PUSH_PRC_STATUS'] ?? null,
                'PUSH_PRC_VALUE' => isset($saved['PUSH_PRC_VALUE']) && is_numeric($saved['PUSH_PRC_VALUE'])
                    ? round((float) $saved['PUSH_PRC_VALUE'], 2)
                    : null,
                'PUSH_STD_PRC_STATUS' => $saved['PUSH_STD_PRC_STATUS'] ?? null,
                'PUSH_STD_PRC_VALUE' => isset($saved['PUSH_STD_PRC_VALUE']) && is_numeric($saved['PUSH_STD_PRC_VALUE'])
                    ? round((float) $saved['PUSH_STD_PRC_VALUE'], 2)
                    : null,
                '_prmt_pct_applied' => is_numeric($prmt) ? (float) $prmt : 0,
                '_zero_sold_prmt_applied' => is_numeric($zeroSold) ? (float) $zeroSold : 0,
                '_cpn_pct_applied' => is_numeric($cpn) ? (float) $cpn : 0,
                '_dsc_applied' => is_numeric($saved['dsc'] ?? null) ? (float) $saved['dsc'] : 0,
            ]);
        } catch (\Throwable $e) {
            Log::error('ChannelPromoPricing save failed', [
                'channel' => $channel,
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => 'Save failed'], 500);
        }
    }

    public function dilPrmtRules(Request $request, string $channel): JsonResponse
    {
        $channel = strtolower(trim($channel));
        if (! $this->promo->isSupported($channel)) {
            return response()->json(['success' => false, 'message' => 'Unsupported channel'], 422);
        }

        return $this->loadRules(
            $channel.'_dil_vs_prmt',
            $this->defaultDilPrmtRules($channel),
            'prmt'
        );
    }

    public function saveDilPrmtRules(Request $request, string $channel): JsonResponse
    {
        $channel = strtolower(trim($channel));
        if (! $this->promo->isSupported($channel)) {
            return response()->json(['success' => false, 'message' => 'Unsupported channel'], 422);
        }

        $incoming = $request->input('rules');
        if (! is_array($incoming)) {
            return response()->json(['success' => false, 'message' => 'rules array required'], 422);
        }

        $rules = $this->persistRules(
            $channel.'_dil_vs_prmt',
            $this->defaultDilPrmtRules($channel),
            $incoming,
            'prmt'
        );

        return response()->json(['success' => true, 'channel' => $channel, 'rules' => $rules]);
    }

    public function dilBumpRules(Request $request, string $channel): JsonResponse
    {
        $channel = strtolower(trim($channel));
        if (! $this->promo->isSupported($channel)) {
            return response()->json(['success' => false, 'message' => 'Unsupported channel'], 422);
        }

        return $this->loadRules(
            $channel === 'reverb' ? $channel.'_sold_vs_bump' : $channel.'_dil_vs_bump',
            $this->defaultDilBumpRules($channel),
            'bump'
        );
    }

    public function saveDilBumpRules(Request $request, string $channel): JsonResponse
    {
        $channel = strtolower(trim($channel));
        if (! $this->promo->isSupported($channel)) {
            return response()->json(['success' => false, 'message' => 'Unsupported channel'], 422);
        }

        $incoming = $request->input('rules');
        if (! is_array($incoming)) {
            return response()->json(['success' => false, 'message' => 'rules array required'], 422);
        }

        $rules = $this->persistRules(
            $channel === 'reverb' ? $channel.'_sold_vs_bump' : $channel.'_dil_vs_bump',
            $this->defaultDilBumpRules($channel),
            $incoming,
            'bump'
        );

        return response()->json(['success' => true, 'channel' => $channel, 'rules' => $rules]);
    }

    public function zeroSoldPrcRules(Request $request, string $channel): JsonResponse
    {
        $channel = strtolower(trim($channel));
        if (! $this->promo->isSupported($channel)) {
            return response()->json(['success' => false, 'message' => 'Unsupported channel'], 422);
        }

        return $this->loadRules(
            $channel.'_zero_sold_prc',
            $this->defaultZeroSoldPrcRules(),
            'groi'
        );
    }

    public function saveZeroSoldPrcRules(Request $request, string $channel): JsonResponse
    {
        $channel = strtolower(trim($channel));
        if (! $this->promo->isSupported($channel)) {
            return response()->json(['success' => false, 'message' => 'Unsupported channel'], 422);
        }

        $incoming = $request->input('rules');
        if (! is_array($incoming)) {
            return response()->json(['success' => false, 'message' => 'rules array required'], 422);
        }

        $rules = $this->persistRules(
            $channel.'_zero_sold_prc',
            $this->defaultZeroSoldPrcRules(),
            $incoming,
            'groi'
        );

        return response()->json(['success' => true, 'channel' => $channel, 'rules' => $rules]);
    }

    public function gtSoldPrcRules(Request $request, string $channel): JsonResponse
    {
        $channel = strtolower(trim($channel));
        if (! $this->promo->isSupported($channel)) {
            return response()->json(['success' => false, 'message' => 'Unsupported channel'], 422);
        }

        return $this->loadRules(
            $channel.'_gt_sold_prc',
            $this->defaultGtSoldPrcRules(),
            'pct',
            ['dir']
        );
    }

    public function saveGtSoldPrcRules(Request $request, string $channel): JsonResponse
    {
        $channel = strtolower(trim($channel));
        if (! $this->promo->isSupported($channel)) {
            return response()->json(['success' => false, 'message' => 'Unsupported channel'], 422);
        }

        $incoming = $request->input('rules');
        if (! is_array($incoming)) {
            return response()->json(['success' => false, 'message' => 'rules array required'], 422);
        }

        $rules = $this->persistRules(
            $channel.'_gt_sold_prc',
            $this->defaultGtSoldPrcRules(),
            $incoming,
            'pct',
            ['dir']
        );

        return response()->json(['success' => true, 'channel' => $channel, 'rules' => $rules]);
    }

    public function cvrCpnRules(Request $request, string $channel): JsonResponse
    {
        $channel = strtolower(trim($channel));
        if (! $this->promo->isSupported($channel)) {
            return response()->json(['success' => false, 'message' => 'Unsupported channel'], 422);
        }

        return $this->loadRules(
            $channel.'_cvr_vs_cpn',
            $this->defaultCvrCpnRules($channel),
            'cpn'
        );
    }

    public function saveCvrCpnRules(Request $request, string $channel): JsonResponse
    {
        $channel = strtolower(trim($channel));
        if (! $this->promo->isSupported($channel)) {
            return response()->json(['success' => false, 'message' => 'Unsupported channel'], 422);
        }

        $incoming = $request->input('rules');
        if (! is_array($incoming)) {
            return response()->json(['success' => false, 'message' => 'rules array required'], 422);
        }

        $rules = $this->persistRules(
            $channel.'_cvr_vs_cpn',
            $this->defaultCvrCpnRules($channel),
            $incoming,
            'cpn'
        );

        return response()->json(['success' => true, 'channel' => $channel, 'rules' => $rules]);
    }

    /**
     * Same slabs as PEF_DIL_PRMT_DEFAULTS / pefDefaultDilPrmtRules.
     *
     * @return list<array{key:string,label:string,prmt:float|int}>
     */
    private function defaultDilPrmtRules(string $channel = ''): array
    {
        if (in_array($channel, ['macys', 'macy', 'bestbuy'], true)) {
            return [
                ['key' => '0-sold-red', 'label' => '0 Sold · Red (<25%)', 'prmt' => 10],
                ['key' => '0-sold-green', 'label' => '0 Sold · Green (25–50%)', 'prmt' => 8],
                ['key' => '0-sold-pink', 'label' => '0 Sold · Pink (50%+)', 'prmt' => 3],
                ['key' => '0-20', 'label' => '0–20%', 'prmt' => 10],
                ['key' => '20-40', 'label' => '20–40%', 'prmt' => 8],
                ['key' => '40-60', 'label' => '40–60%', 'prmt' => 5],
                ['key' => '60-80', 'label' => '60–80%', 'prmt' => 3],
                ['key' => '80-100', 'label' => '80–100%', 'prmt' => 1],
                ['key' => 'gt-100', 'label' => '> 100%', 'prmt' => 0],
            ];
        }

        if ($channel === 'reverb') {
            return [
                ['key' => '0-20', 'label' => '0.1–20%', 'prmt' => 10],
                ['key' => '20-40', 'label' => '20–40%', 'prmt' => 8],
                ['key' => '40-60', 'label' => '40–60%', 'prmt' => 5],
                ['key' => '60-80', 'label' => '60–80%', 'prmt' => 3],
                ['key' => '80-100', 'label' => '80–100%', 'prmt' => 1],
                ['key' => 'gt-100', 'label' => '> 100%', 'prmt' => 0],
            ];
        }

        return [
            ['key' => '0-10', 'label' => '0–10%', 'prmt' => 10],
            ['key' => '10-20', 'label' => '10–20%', 'prmt' => 9],
            ['key' => '20-30', 'label' => '20–30%', 'prmt' => 8],
            ['key' => '30-40', 'label' => '30–40%', 'prmt' => 7],
            ['key' => '40-50', 'label' => '40–50%', 'prmt' => 6],
            ['key' => '50-60', 'label' => '50–60%', 'prmt' => 5],
            ['key' => '60-70', 'label' => '60–70%', 'prmt' => 4],
            ['key' => '70-80', 'label' => '70–80%', 'prmt' => 3],
            ['key' => '80-90', 'label' => '80–90%', 'prmt' => 2],
            ['key' => '90-100', 'label' => '90–100%', 'prmt' => 1],
            ['key' => 'gt-100', 'label' => '> 100%', 'prmt' => 0],
        ];
    }

    /**
     * Sold (RV L30) → S Bump% defaults (Reverb: 10 sold slabs, 0 / 1 / … / > 10).
     *
     * @return list<array{key:string,label:string,bump:float|int}>
     */
    private function defaultDilBumpRules(string $channel = ''): array
    {
        if ($channel === 'reverb') {
            return [
                ['key' => '0', 'label' => '0', 'bump' => 10],
                ['key' => '1', 'label' => '1', 'bump' => 9],
                ['key' => '2', 'label' => '2', 'bump' => 8],
                ['key' => '3', 'label' => '3', 'bump' => 7],
                ['key' => '4', 'label' => '4', 'bump' => 6],
                ['key' => '5', 'label' => '5', 'bump' => 5],
                ['key' => '6', 'label' => '6', 'bump' => 4],
                ['key' => '7', 'label' => '7', 'bump' => 3],
                ['key' => '8-10', 'label' => '8–10', 'bump' => 2],
                ['key' => 'gt-10', 'label' => '> 10', 'bump' => 0],
            ];
        }

        return [
            ['key' => '0-10', 'label' => '0–10%', 'bump' => 10],
            ['key' => '10-20', 'label' => '10–20%', 'bump' => 9],
            ['key' => '20-30', 'label' => '20–30%', 'bump' => 8],
            ['key' => '30-40', 'label' => '30–40%', 'bump' => 7],
            ['key' => '40-50', 'label' => '40–50%', 'bump' => 6],
            ['key' => '50-60', 'label' => '50–60%', 'bump' => 5],
            ['key' => '60-70', 'label' => '60–70%', 'bump' => 4],
            ['key' => '70-80', 'label' => '70–80%', 'bump' => 3],
            ['key' => '80-90', 'label' => '80–90%', 'bump' => 2],
            ['key' => '90-100', 'label' => '90–100%', 'bump' => 1],
            ['key' => 'gt-100', 'label' => '> 100%', 'bump' => 0],
        ];
    }

    /**
     * 0 Sold Dil% slabs (10% steps) → Target ROI% / GROI% for suggested SPRICE.
     * eBay 2 uses this as Target SNROI%; Reverb uses Target GROI%.
     *
     * @return list<array{key:string,label:string,groi:float|int}>
     */
    private function defaultZeroSoldPrcRules(): array
    {
        return [
            ['key' => '0-10', 'label' => '0–10%', 'groi' => 40],
            ['key' => '10-20', 'label' => '10–20%', 'groi' => 35],
            ['key' => '20-30', 'label' => '20–30%', 'groi' => 30],
            ['key' => '30-40', 'label' => '30–40%', 'groi' => 25],
            ['key' => '40-50', 'label' => '40–50%', 'groi' => 20],
            ['key' => '50-60', 'label' => '50–60%', 'groi' => 15],
            ['key' => '60-70', 'label' => '60–70%', 'groi' => 12],
            ['key' => '70-80', 'label' => '70–80%', 'groi' => 10],
            ['key' => '80-90', 'label' => '80–90%', 'groi' => 8],
            ['key' => '90-100', 'label' => '90–100%', 'groi' => 5],
            ['key' => 'gt-100', 'label' => '> 100%', 'groi' => 0],
        ];
    }

    /**
     * >0 Sold Dil color → % of Std Prc (increase or decrease).
     *
     * @return list<array{key:string,label:string,pct:float|int,dir:string}>
     */
    private function defaultGtSoldPrcRules(): array
    {
        return [
            ['key' => 'gt-sold-red', 'label' => 'Red Dil (<25%)', 'pct' => 0, 'dir' => 'increase'],
            ['key' => 'gt-sold-green', 'label' => 'Green Dil (25–50%)', 'pct' => 0, 'dir' => 'increase'],
            ['key' => 'gt-sold-pink', 'label' => 'Pink Dil (50%+)', 'pct' => 0, 'dir' => 'increase'],
        ];
    }

    /**
     * Same slabs as PEF_CVR_CPN_DEFAULTS / pefDefaultCvrCpnRules.
     * eBay 2 / 3 omit the 0% slab (Apply is only for SKUs with eBay sale > 0).
     *
     * @return list<array{key:string,label:string,cpn:float|int}>
     */
    private function defaultCvrCpnRules(string $channel = ''): array
    {
        $rules = [
            ['key' => 'eq-0', 'label' => '0%', 'cpn' => 10],
            ['key' => '0.01-1', 'label' => '0.01–1%', 'cpn' => 9],
            ['key' => '1-1.5', 'label' => '1–1.5%', 'cpn' => 8],
            ['key' => '1.5-2', 'label' => '1.5–2%', 'cpn' => 7],
            ['key' => '2-3', 'label' => '2–3%', 'cpn' => 6],
            ['key' => '3-4', 'label' => '3–4%', 'cpn' => 5],
            ['key' => '4-5', 'label' => '4–5%', 'cpn' => 4],
            ['key' => '5-6', 'label' => '5–6%', 'cpn' => 3],
            ['key' => '6-6.5', 'label' => '6–6.5%', 'cpn' => 2],
            ['key' => '6.5-7', 'label' => '6.5–7%', 'cpn' => 1],
            ['key' => 'gt-7', 'label' => '> 7%', 'cpn' => 0],
        ];
        if (in_array($channel, ['ebay2', 'ebay2op', 'ebay3'], true)) {
            return array_values(array_filter($rules, static fn (array $r) => ($r['key'] ?? '') !== 'eq-0'));
        }

        return $rules;
    }

    /**
     * @param  list<array{key:string,label:string}>  $defaults
     * @param  list<string>  $extraKeys
     */
    private function loadRules(string $channelName, array $defaults, string $valueKey, array $extraKeys = []): JsonResponse
    {
        $row = ChannelTabulatorColumnSetting::query()
            ->where('channel_name', $channelName)
            ->first();
        $saved = is_array($row?->visibility) ? $row->visibility : null;
        if (! is_array($saved) || $saved === []) {
            return response()->json([
                'success' => true,
                'is_default' => true,
                'rules' => $defaults,
            ]);
        }

        $byKey = [];
        foreach ($saved as $item) {
            if (! is_array($item)) {
                continue;
            }
            $k = (string) ($item['key'] ?? '');
            if ($k !== '') {
                $byKey[$k] = $item;
            }
        }

        $rules = [];
        foreach ($defaults as $def) {
            $k = $def['key'];
            $raw = $byKey[$k][$valueKey] ?? null;
            // CVR disc historically accepted cpn as alias
            if ($valueKey === 'disc' && $raw === null) {
                $raw = $byKey[$k]['cpn'] ?? null;
            }
            $val = is_numeric($raw) ? (float) $raw : $def[$valueKey];
            $rule = [
                'key' => $k,
                'label' => $def['label'],
                $valueKey => $val,
            ];
            $this->mergeRuleExtras($rule, $def, $byKey[$k] ?? null, $extraKeys);
            $rules[] = $rule;
        }

        return response()->json([
            'success' => true,
            'is_default' => false,
            'rules' => $rules,
        ]);
    }

    /**
     * @param  list<array{key:string,label:string}>  $defaults
     * @param  list<array<string, mixed>>  $incoming
     * @param  list<string>  $extraKeys
     * @return list<array{key:string,label:string}>
     */
    private function persistRules(string $channelName, array $defaults, array $incoming, string $valueKey, array $extraKeys = []): array
    {
        $byKey = [];
        foreach ($incoming as $item) {
            if (! is_array($item)) {
                continue;
            }
            $k = (string) ($item['key'] ?? '');
            if ($k !== '') {
                $byKey[$k] = $item;
            }
        }

        $rules = [];
        foreach ($defaults as $def) {
            $k = $def['key'];
            $raw = $byKey[$k][$valueKey] ?? null;
            if ($valueKey === 'disc' && $raw === null) {
                $raw = $byKey[$k]['cpn'] ?? null;
            }
            $val = is_numeric($raw) ? round((float) $raw, 2) : (float) $def[$valueKey];
            if ($val < 0) {
                $val = 0;
            }
            $rule = [
                'key' => $k,
                'label' => $def['label'],
                $valueKey => $val,
            ];
            $this->mergeRuleExtras($rule, $def, $byKey[$k] ?? null, $extraKeys);
            $rules[] = $rule;
        }

        ChannelTabulatorColumnSetting::query()->updateOrCreate(
            ['channel_name' => $channelName],
            ['visibility' => $rules, 'column_order' => array_column($rules, 'key')]
        );

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  array<string, mixed>  $def
     * @param  array<string, mixed>|null  $saved
     * @param  list<string>  $extraKeys
     */
    private function mergeRuleExtras(array &$rule, array $def, ?array $saved, array $extraKeys): void
    {
        foreach ($extraKeys as $ek) {
            $raw = is_array($saved) ? ($saved[$ek] ?? null) : null;
            if ($raw === null) {
                $raw = $def[$ek] ?? null;
            }
            if ($ek === 'dir') {
                $dir = strtolower(trim((string) ($raw ?? '')));
                $rule[$ek] = in_array($dir, ['increase', 'decrease'], true)
                    ? $dir
                    : (string) ($def[$ek] ?? 'increase');
                continue;
            }
            $rule[$ek] = $raw;
        }
    }

    private function nullablePct(mixed $val): ?float
    {
        if ($val === null || $val === '' || ! is_numeric($val)) {
            return null;
        }

        return round((float) $val, 2);
    }
}
