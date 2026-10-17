<?php

namespace App\Services\Support;

use App\Jobs\RunChannelPushSpriceJob;
use App\Models\MacysPriceData;
use App\Models\MacyDataView;
use App\Models\Shopifyb2cDataView;
use App\Models\ShopifySku;
use App\Models\TemuDataView;
use App\Models\TemuMetric;
use App\Services\TemuShopifySalesService;
use App\Services\ChannelPromoPricingService;
use App\Services\DilRuleSpriceApplyService;
use App\Support\Marketplace\EbayListingEnded;
use App\Support\Marketplace\EbaySpricePush;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Page-less daily enqueue: listed SKUs whose S PRC ≠ live Price.
 * Never consult the analytics "Reload push" switch — that flag only
 * stops auto-push on page reload. This cron always runs.
 */
class ChannelPushSpriceDailyEnqueue
{
    /** No channel is queued by the bare `channel:push-sprice-daily` (all) run. */
    public const CHANNELS = [];

    /** Sprc Dil pages whose live push is not in the Dil channel list. */
    public const EXTRA_PUSH_CHANNELS = ['shopify_b2c', 'macys', 'newtemuone', 'ebay1', 'ebay2', 'ebay3'];

    /**
     * @return list<string>
     */
    public static function channelsFromArg(string $arg): array
    {
        $arg = strtolower(trim($arg));
        if ($arg === '' || $arg === 'all') {
            return self::CHANNELS;
        }
        if ($arg === 'dil') {
            return DilRuleSpriceApplyService::PUSH_CHANNELS;
        }
        if (in_array($arg, self::CHANNELS, true)
            || in_array($arg, DilRuleSpriceApplyService::PUSH_CHANNELS, true)
            || in_array($arg, self::EXTRA_PUSH_CHANNELS, true)) {
            return [$arg];
        }

        return [];
    }

    public function __construct(
        private readonly ChannelPromoPricingService $promo
    ) {}

    /**
     * @return array{channel: string, queued: int, total: int, spawned: bool, message: string}
     */
    public function enqueueChannel(string $channel): array
    {
        $channel = strtolower(trim($channel));
        if (! ChannelPushSpriceRunner::livePushAllowed()) {
            return [
                'channel' => $channel,
                'queued' => 0,
                'total' => 0,
                'spawned' => false,
                'message' => 'Skipped — live S PRC push is disabled on local',
            ];
        }
        $allowed = array_merge(self::CHANNELS, DilRuleSpriceApplyService::PUSH_CHANNELS, self::EXTRA_PUSH_CHANNELS);
        if (! in_array($channel, $allowed, true)) {
            return [
                'channel' => $channel,
                'queued' => 0,
                'total' => 0,
                'spawned' => false,
                'message' => 'Unsupported channel',
            ];
        }

        $tasks = $this->collect($channel);
        if ($tasks === []) {
            return [
                'channel' => $channel,
                'queued' => 0,
                'total' => 0,
                'spawned' => false,
                'message' => 'No S PRC ≠ Price listings',
            ];
        }

        $store = ChannelPushSpriceJobStore::for($channel);
        $result = $store->createOrAppend($tasks, 'daily');
        $state = $result['state'];
        $queued = count($tasks);

        $this->releaseUniqueLock($channel);
        $spawned = ChannelPushSpriceRunner::spawnWorker($channel);
        if (! $spawned) {
            try {
                RunChannelPushSpriceJob::dispatch($channel);
                $spawned = true;
            } catch (\Throwable $e) {
                Log::error('Daily S PRC queue dispatch failed', [
                    'channel' => $channel,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $store->update(function (array $s) use ($spawned, $queued) {
            $s['worker_spawned_at'] = now()->toDateTimeString();
            $s['last_message'] = $spawned
                ? ('Daily S PRC queue — '.$queued.' SKU(s), worker running…')
                : ('Daily S PRC queued '.$queued.' SKU(s) — waiting for worker');

            return $s;
        });

        $api = $store->toApiResponse($store->load());

        Log::info('Daily S PRC enqueue', [
            'channel' => $channel,
            'queued' => $queued,
            'total' => $api['total'] ?? ($state['total'] ?? 0),
            'spawned' => $spawned,
        ]);

        return [
            'channel' => $channel,
            'queued' => $queued,
            'total' => (int) ($api['total'] ?? 0),
            'spawned' => $spawned,
            'message' => 'Queued '.$queued.' SKU(s)',
        ];
    }

    /**
     * @return list<array{sku: string, price: float}>
     */
    public function collect(string $channel): array
    {
        if (in_array($channel, DilRuleSpriceApplyService::CHANNELS, true)) {
            return DilRuleSpriceApplyService::for($channel)->collectPushTasks();
        }
        if ($channel === 'shopify_b2c') {
            return $this->collectSavedAgainstLive(Shopifyb2cDataView::class, ShopifySku::class, 'price', 'b2c_price');
        }
        if ($channel === 'macys') {
            return $this->collectSavedAgainstLive(MacyDataView::class, MacysPriceData::class, 'price');
        }
        if ($channel === 'newtemuone') {
            return $this->collectNewTemuonePushTasks();
        }
        if (EbaySpricePush::supports($channel)) {
            return $this->collectEbayPushTasks($channel);
        }

        return [];
    }

    /**
     * eBay 1/2/3 blue triangle: INV > 0, live listing, saved S PRC ≠ live Price.
     * The listing is the same preferred (live) metric row the page and the push use.
     *
     * @return list<array{sku: string, price: float}>
     */
    private function collectEbayPushTasks(string $channel): array
    {
        $viewClass = EbaySpricePush::viewClass($channel);
        $metricClass = EbaySpricePush::metricClass($channel);
        if (! $viewClass || ! $metricClass) {
            return [];
        }
        $out = [];
        $seen = [];
        $viewClass::query()
            ->orderBy('id')
            ->chunkById(400, function ($rows) use ($channel, $metricClass, &$out, &$seen) {
                $saved = [];
                foreach ($rows as $row) {
                    $sku = strtoupper(trim((string) $row->sku));
                    if ($sku === '' || str_contains($sku, 'PARENT') || isset($seen[$sku])) {
                        continue;
                    }
                    $value = is_array($row->value) ? $row->value : (json_decode((string) ($row->value ?? ''), true) ?: []);
                    $sprice = $value['SPRICE'] ?? null;
                    if (! is_numeric($sprice) || ! ((float) $sprice > 0)) {
                        continue;
                    }
                    $seen[$sku] = true;
                    $saved[$sku] = round((float) $sprice, 2);
                }
                if ($saved === []) {
                    return;
                }
                $keys = array_keys($saved);
                $inv = [];
                foreach (ShopifySku::query()->whereIn(DB::raw('UPPER(TRIM(sku))'), $keys)->get(['sku', 'inv']) as $s) {
                    $inv[strtoupper(trim((string) $s->sku))] = (float) ($s->inv ?? 0);
                }
                $metrics = [];
                foreach ($metricClass::query()->whereIn(DB::raw('UPPER(TRIM(sku))'), $keys)->orderBy('id')->get() as $m) {
                    $metrics[strtoupper(trim((string) $m->sku))][] = $m;
                }
                foreach ($saved as $sku => $price) {
                    if (($inv[$sku] ?? 0) <= 0 || empty($metrics[$sku])) {
                        continue;
                    }
                    $metric = EbayListingEnded::preferLiveMetric($metrics[$sku]);
                    if (! $metric || trim((string) ($metric->item_id ?? '')) === ''
                        || EbayListingEnded::isEnded($metric->listing_status ?? null)) {
                        continue;
                    }
                    $live = round((float) ($metric->ebay_price ?? 0), 2);
                    if (! ($live > 0) || abs($price - $live) < 0.005
                        || EbaySpricePush::inCooldown($channel, $sku, $price)) {
                        continue;
                    }
                    $out[] = ['sku' => $sku, 'price' => $price];
                }
            });

        return $out;
    }

    /**
     * @param  class-string  $viewClass
     * @param  list<string>  $skus
     * @return array<string, float>
     */
    private function savedSprices(string $viewClass, array $skus): array
    {
        $map = [];
        foreach ($viewClass::whereIn(DB::raw('UPPER(TRIM(sku))'), $skus)->get(['sku', 'value']) as $row) {
            $val = is_array($row->value)
                ? $row->value
                : (json_decode((string) ($row->value ?? ''), true) ?: []);
            $sprice = $val['SPRICE'] ?? null;
            if (is_numeric($sprice) && (float) $sprice > 0) {
                $map[strtoupper(trim((string) $row->sku))] = round((float) $sprice, 2);
            }
        }

        return $map;
    }

    /**
     * Saved SPRICE that differs from the live listing price.
     *
     * @param  class-string  $viewClass
     * @param  class-string  $liveClass
     * @return list<array{sku: string, price: float}>
     */
    private function collectSavedAgainstLive(string $viewClass, string $liveClass, string $priceColumn, ?string $fallbackColumn = null): array
    {
        $out = [];
        $seen = [];
        $liveClass::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('id')
            ->chunkById(400, function ($rows) use ($viewClass, $priceColumn, $fallbackColumn, &$out, &$seen) {
                $skus = [];
                $liveBySku = [];
                foreach ($rows as $row) {
                    $sku = strtoupper(trim((string) $row->sku));
                    if ($sku === '' || str_contains($sku, 'PARENT') || isset($seen[$sku])) {
                        continue;
                    }
                    $live = round((float) ($row->{$priceColumn} ?? 0), 2);
                    if (! ($live > 0) && $fallbackColumn !== null) {
                        $live = round((float) ($row->{$fallbackColumn} ?? 0), 2);
                    }
                    if (! ($live > 0)) {
                        continue;
                    }
                    $seen[$sku] = true;
                    $skus[] = $sku;
                    $liveBySku[$sku] = $live;
                }
                if ($skus === []) {
                    return;
                }
                $saved = $this->savedSprices($viewClass, $skus);
                foreach ($skus as $sku) {
                    $price = $saved[$sku] ?? 0;
                    $live = $liveBySku[$sku] ?? 0;
                    if (! ($price > 0) || ! ($live > 0) || abs($price - $live) < 0.005) {
                        continue;
                    }
                    $out[] = ['sku' => $sku, 'price' => $price];
                }
            });

        return $out;
    }

    /**
     * Temu 1 analytics pushes S Base (the listing base), not the full S PRC.
     *
     * @return list<array{sku: string, price: float}>
     */
    private function collectNewTemuonePushTasks(): array
    {
        $out = [];
        TemuDataView::query()
            ->orderBy('id')
            ->chunkById(400, function ($rows) use (&$out) {
                $bases = [];
                foreach ($rows as $row) {
                    $sku = strtoupper(trim((string) $row->sku));
                    if ($sku === '' || str_contains($sku, 'PARENT')) {
                        continue;
                    }
                    $value = is_array($row->value) ? $row->value : [];
                    $full = is_numeric($value['NTO_SPRICE'] ?? null) ? (float) $value['NTO_SPRICE'] : 0.0;
                    $base = is_numeric($value['NTO_S_BASE'] ?? null) ? round((float) $value['NTO_S_BASE'], 2) : 0.0;
                    if (! ($base > 0) && $full > 0) {
                        $base = round(TemuShopifySalesService::computeBaseFromFullTemuPrice($full), 2);
                    }
                    if ($base > 0) {
                        $bases[$sku] = $base;
                    }
                }
                if ($bases === []) {
                    return;
                }
                $liveBySku = [];
                foreach (TemuMetric::query()->whereIn(DB::raw('UPPER(TRIM(sku))'), array_keys($bases))->get(['sku', 'base_price']) as $metric) {
                    $key = strtoupper(trim((string) $metric->sku));
                    $live = round((float) ($metric->base_price ?? 0), 2);
                    if ($live > 0) {
                        $liveBySku[$key] = $live;
                    }
                }
                foreach ($bases as $sku => $base) {
                    $live = $liveBySku[$sku] ?? 0;
                    if (! ($live > 0) || abs($base - $live) < 0.015) {
                        continue;
                    }
                    $out[] = ['sku' => $sku, 'price' => $base];
                }
            });

        return $out;
    }

    private function releaseUniqueLock(string $channel): void
    {
        try {
            \Illuminate\Support\Facades\Cache::lock(
                'laravel_unique_job:'.RunChannelPushSpriceJob::class.':'.$channel.'-push-sprice'
            )->forceRelease();
        } catch (\Throwable) {
            // ignore
        }
    }
}
