<?php

namespace App\Console\Commands;

use App\Models\AmazonBuyboxData;
use App\Models\AmazonDatasheet;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Services\AmazonSpApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PullAmazonBuyboxCommand extends Command
{
    public const STATUS_CACHE_KEY = 'amazon_buybox_pull_status';

    public const LOCK_CACHE_KEY = 'amazon_buybox_pull_lock';

    protected $signature = 'amazon:pull-buybox
        {--lot=40 : SKUs per lot (SP-API rate-friendly batch size)}
        {--skus= : Comma-separated SKUs (optional; SKUs may contain spaces — do not split on whitespace)}
        {--skus-file= : Path to a text file with one SKU per line (preferred over --skus)}
        {--delay-ms=1100 : Delay between each SKU API call}
        {--lot-pause-ms=2000 : Extra pause between lots}';

    protected $description = 'Pull Amazon Buy Box (getListingOffers) in lots of 40; skips INV < 1';

    public function handle(): int
    {
        if (! Schema::hasTable('amazon_buybox_data')) {
            $this->error('Table amazon_buybox_data missing. Run migrations.');

            return self::FAILURE;
        }

        $lock = Cache::lock(self::LOCK_CACHE_KEY, 7200);
        if (! $lock->get()) {
            $this->warn('Buy Box pull already running.');
            self::writeStatus([
                'running' => true,
                'message' => 'Another Buy Box pull is already running',
            ]);

            return self::SUCCESS;
        }

        try {
            $lotSize = max(1, min(40, (int) $this->option('lot')));
            $delayMs = max(200, (int) $this->option('delay-ms'));
            $lotPauseMs = max(0, (int) $this->option('lot-pause-ms'));

            $skus = $this->resolveSkusWithInvAtLeastOne();
            $total = count($skus);

            if ($total === 0) {
                self::writeStatus([
                    'running' => false,
                    'total' => 0,
                    'done' => 0,
                    'ok' => 0,
                    'fail' => 0,
                    'lot' => $lotSize,
                    'finished_at' => now()->toDateTimeString(),
                    'message' => 'No SKUs with INV ≥ 1 to pull',
                ]);
                $this->info('No SKUs with INV ≥ 1.');

                return self::SUCCESS;
            }

            $lots = (int) ceil($total / $lotSize);
            self::writeStatus([
                'running' => true,
                'total' => $total,
                'done' => 0,
                'ok' => 0,
                'fail' => 0,
                'lot' => $lotSize,
                'lot_index' => 0,
                'lots' => $lots,
                'started_at' => now()->toDateTimeString(),
                'finished_at' => null,
                'message' => "Starting Buy Box pull · {$total} SKU(s) · {$lots} lot(s) of {$lotSize}",
            ]);

            $this->info("Pulling Buy Box for {$total} SKU(s) in lots of {$lotSize}…");

            $service = new AmazonSpApiService();
            $ok = 0;
            $fail = 0;
            $done = 0;

            foreach (array_chunk($skus, $lotSize) as $lotIndex => $lot) {
                $lotNum = $lotIndex + 1;
                $this->info("Lot {$lotNum}/{$lots} · ".count($lot).' SKU(s)');

                self::patchStatus([
                    'lot_index' => $lotNum,
                    'lots' => $lots,
                    'message' => "Lot {$lotNum}/{$lots} · pulled {$done}/{$total}",
                ]);

                foreach ($lot as $i => $sku) {
                    if ($done > 0 || $i > 0) {
                        usleep($delayMs * 1000);
                    }

                    $sellerSku = AmazonDatasheet::resolveSellerMskuByProductKey($sku) ?: $sku;
                    $result = $service->getListingOffers($sellerSku);

                    if (! ($result['success'] ?? false)) {
                        $fail++;
                        $msg = (string) ($result['error'] ?? 'Unknown error');
                        AmazonBuyboxData::updateOrCreate(
                            ['sku' => $sku],
                            [
                                'error_message' => $msg,
                                'fetched_at' => now(),
                            ]
                        );
                    } else {
                        $flat = $service->flattenBuyboxOffersPayload($result['payload'], $sku);
                        AmazonBuyboxData::updateOrCreate(['sku' => $sku], $flat);
                        $ok++;
                    }

                    $done++;
                    if ($done % 5 === 0 || $done === $total) {
                        self::patchStatus([
                            'done' => $done,
                            'ok' => $ok,
                            'fail' => $fail,
                            'message' => "Lot {$lotNum}/{$lots} · pulled {$done}/{$total} (ok {$ok}, fail {$fail})",
                        ]);
                    }
                }

                if ($lotNum < $lots && $lotPauseMs > 0) {
                    usleep($lotPauseMs * 1000);
                }
            }

            self::writeStatus([
                'running' => false,
                'total' => $total,
                'done' => $done,
                'ok' => $ok,
                'fail' => $fail,
                'lot' => $lotSize,
                'lot_index' => $lots,
                'lots' => $lots,
                'started_at' => Cache::get(self::STATUS_CACHE_KEY)['started_at'] ?? now()->toDateTimeString(),
                'finished_at' => now()->toDateTimeString(),
                'message' => "Done · {$ok} ok, {$fail} failed of {$total} (INV ≥ 1)",
            ]);

            Log::info('amazon:pull-buybox finished', compact('total', 'ok', 'fail', 'lotSize'));
            $this->info("Done · ok={$ok} fail={$fail}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('amazon:pull-buybox failed', ['error' => $e->getMessage()]);
            self::writeStatus([
                'running' => false,
                'finished_at' => now()->toDateTimeString(),
                'message' => 'Failed: '.$e->getMessage(),
                'error' => $e->getMessage(),
            ]);
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * @return list<string> Uppercased SKUs with Shopify INV ≥ 1
     */
    protected function resolveSkusWithInvAtLeastOne(): array
    {
        $requested = $this->parseRequestedSkus();

        if ($requested === []) {
            $requested = ProductMaster::query()
                ->whereNull('deleted_at')
                ->whereRaw('UPPER(TRIM(sku)) NOT LIKE ?', ['PARENT%'])
                ->pluck('sku')
                ->map(static fn ($s) => trim((string) $s))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        if ($requested === []) {
            return [];
        }

        $eligible = [];
        foreach (array_chunk($requested, 500) as $chunk) {
            $shopifyBySku = ShopifySku::mapByProductSkus($chunk);
            foreach ($chunk as $sku) {
                $row = $shopifyBySku[$sku] ?? null;
                $inv = (float) ($row->inv ?? 0);
                if ($inv < 1) {
                    continue;
                }
                $eligible[] = strtoupper($sku);
            }
        }

        return array_values(array_unique($eligible));
    }

    /**
     * SKUs often contain spaces (e.g. "CS 04 2W") — never split on whitespace.
     *
     * @return list<string>
     */
    protected function parseRequestedSkus(): array
    {
        $file = trim((string) $this->option('skus-file'));
        if ($file !== '') {
            if (! is_readable($file)) {
                $this->warn("SKUs file not readable: {$file}");

                return [];
            }
            $lines = preg_split("/\r\n|\n|\r/", (string) file_get_contents($file)) ?: [];

            return array_values(array_unique(array_filter(array_map(static function ($s) {
                return trim((string) $s);
            }, $lines), static fn ($s) => $s !== '')));
        }

        $raw = trim((string) $this->option('skus'));
        if ($raw === '') {
            return [];
        }

        // Comma-separated only — spaces are part of the SKU
        return array_values(array_unique(array_filter(array_map(static function ($s) {
            return trim((string) $s);
        }, explode(',', $raw)), static fn ($s) => $s !== '')));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function writeStatus(array $data): void
    {
        $prev = Cache::get(self::STATUS_CACHE_KEY, []);
        Cache::put(self::STATUS_CACHE_KEY, array_merge(is_array($prev) ? $prev : [], $data), now()->addHours(6));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function patchStatus(array $data): void
    {
        self::writeStatus($data);
    }

    /**
     * @return array<string, mixed>
     */
    public static function status(): array
    {
        $s = Cache::get(self::STATUS_CACHE_KEY, []);

        return is_array($s) ? $s : [];
    }
}
