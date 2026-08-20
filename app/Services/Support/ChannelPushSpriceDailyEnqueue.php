<?php

namespace App\Services\Support;

use App\Jobs\RunChannelPushSpriceJob;
use App\Models\AmazonDataView;
use App\Models\Ebay2Metric;
use App\Models\Ebay3Metric;
use App\Models\EbayDataView;
use App\Models\EbayMetric;
use App\Models\EbayThreeDataView;
use App\Models\EbayTwoDataView;
use App\Services\ChannelPromoPricingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Page-less daily enqueue: listed SKUs whose S PRC ≠ live Price.
 */
class ChannelPushSpriceDailyEnqueue
{
    public const CHANNELS = ['ebay1', 'ebay2', 'ebay3'];

    public function __construct(
        private readonly ChannelPromoPricingService $promo
    ) {}

    /**
     * @return array{channel: string, queued: int, total: int, spawned: bool, message: string}
     */
    public function enqueueChannel(string $channel): array
    {
        $channel = strtolower(trim($channel));
        if (! in_array($channel, self::CHANNELS, true)) {
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
        $result = $store->createOrAppend($tasks);
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
        $metricClass = match ($channel) {
            'ebay2' => Ebay2Metric::class,
            'ebay3' => Ebay3Metric::class,
            default => EbayMetric::class,
        };
        $viewClass = match ($channel) {
            'ebay2' => EbayTwoDataView::class,
            'ebay3' => EbayThreeDataView::class,
            default => EbayDataView::class,
        };

        $out = [];
        $seen = [];

        $metricClass::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->whereNotNull('item_id')
            ->where('item_id', '!=', '')
            ->where('ebay_price', '>', 0)
            ->orderBy('id')
            ->chunkById(400, function ($rows) use ($channel, $viewClass, &$out, &$seen) {
                $skus = [];
                $liveBySku = [];
                foreach ($rows as $row) {
                    $sku = strtoupper(trim((string) $row->sku));
                    if ($sku === '' || str_contains($sku, 'PARENT') || isset($seen[$sku])) {
                        continue;
                    }
                    $live = round((float) $row->ebay_price, 2);
                    if (! ($live > 0)) {
                        continue;
                    }
                    $skus[] = $sku;
                    $liveBySku[$sku] = $live;
                    $seen[$sku] = true;
                }
                if ($skus === []) {
                    return;
                }

                $stdBySku = $this->standardPrices($skus);
                $promoBySku = $this->promo->mapForSkus($channel, $skus);
                $savedSprice = $this->savedSprices($viewClass, $skus);

                foreach ($skus as $sku) {
                    $live = $liveBySku[$sku] ?? 0;
                    $std = $stdBySku[$sku] ?? 0;
                    $fill = $this->spriceForChannel($channel, $std, $promoBySku[$sku] ?? [], $savedSprice[$sku] ?? 0);
                    if (! ($fill > 0) || ! ($live > 0)) {
                        continue;
                    }
                    if (abs($fill - $live) < 0.005) {
                        continue;
                    }
                    $out[] = [
                        'sku' => $sku,
                        'price' => $fill,
                    ];
                }
            });

        return $out;
    }

    /**
     * @param  array<string, mixed>  $promo
     */
    private function spriceForChannel(string $channel, float $std, array $promo, float $saved): float
    {
        if ($channel === 'ebay3') {
            if ($saved > 0) {
                return $saved;
            }

            return $std > 0 ? $std : 0.0;
        }

        if (! ($std > 0)) {
            return 0.0;
        }
        $prmt = is_numeric($promo['prmt_pct'] ?? null)
            ? (float) $promo['prmt_pct']
            : (float) ($promo['_prmt_pct_applied'] ?? 0);
        $cpn = is_numeric($promo['cpn_pct'] ?? null)
            ? (float) $promo['cpn_pct']
            : (float) ($promo['_cpn_pct_applied'] ?? 0);
        $t = min(99.99, max(0, $prmt + $cpn));
        $price = $t > 0 ? round($std * (1 - $t / 100), 2) : round($std, 2);

        return $price >= 0.01 ? $price : 0.0;
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, float>
     */
    private function standardPrices(array $skus): array
    {
        $map = [];
        foreach (AmazonDataView::whereIn(DB::raw('UPPER(TRIM(sku))'), $skus)->get(['sku', 'value']) as $row) {
            $val = is_array($row->value)
                ? $row->value
                : (json_decode((string) ($row->value ?? ''), true) ?: []);
            $std = $val['STANDARD_PRICE'] ?? null;
            if (is_numeric($std) && (float) $std > 0) {
                $map[strtoupper(trim((string) $row->sku))] = round((float) $std, 2);
            }
        }

        return $map;
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
