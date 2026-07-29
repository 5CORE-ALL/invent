<?php

namespace App\Console\Commands;

use App\Models\AmazonDatasheet;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Services\AmazonSpApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PullAmazonTitlesCommand extends Command
{
    public const STATUS_CACHE_KEY = 'amazon_titles_pull_status';

    public const LOCK_CACHE_KEY = 'amazon_titles_pull_lock';

    protected $signature = 'amazon:pull-titles
        {--lot=20 : SKUs per lot (SP-API rate-friendly batch size)}
        {--skus= : Comma-separated SKUs (optional; SKUs may contain spaces — do not split on whitespace)}
        {--skus-file= : Path to a text file with one SKU per line (preferred over --skus)}
        {--delay-ms=1200 : Delay between each SKU API call}
        {--lot-pause-ms=2000 : Extra pause between lots}
        {--skip-inv-check : Do not require Shopify INV ≥ 1}';

    protected $description = 'Pull Amazon listing titles (Listings API item_name) into product_master.title150 (Title 170)';

    public function handle(): int
    {
        $lock = Cache::lock(self::LOCK_CACHE_KEY, 7200);
        if (! $lock->get()) {
            $this->warn('Amazon titles pull already running.');
            self::writeStatus([
                'running' => true,
                'message' => 'Another titles pull is already running',
            ]);

            return self::SUCCESS;
        }

        try {
            $lotSize = max(1, min(40, (int) $this->option('lot')));
            $delayMs = max(200, (int) $this->option('delay-ms'));
            $lotPauseMs = max(0, (int) $this->option('lot-pause-ms'));
            $skipInv = (bool) $this->option('skip-inv-check');

            $skus = $skipInv
                ? $this->resolveRequestedSkus()
                : $this->resolveSkusWithInvAtLeastOne();
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
                    'message' => $skipInv
                        ? 'No SKUs to pull'
                        : 'No SKUs with INV ≥ 1 to pull',
                ]);
                $this->info($skipInv ? 'No SKUs.' : 'No SKUs with INV ≥ 1.');

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
                'message' => "Starting titles pull · {$total} SKU(s) · {$lots} lot(s) of {$lotSize}",
            ]);

            $this->info("Pulling Amazon titles for {$total} SKU(s) in lots of {$lotSize}…");

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
                    $details = $service->getListingsItemFullDetails($sellerSku);
                    $itemName = trim((string) ($details['item_name'] ?? ''));

                    if ($itemName === '') {
                        $fail++;
                    } else {
                        $title170 = mb_substr(str_replace("\u{00a0}", ' ', $itemName), 0, 170);
                        $updated = $this->writeTitle150($sku, $title170);
                        if ($updated) {
                            $ok++;
                        } else {
                            $fail++;
                        }
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
                'message' => "Done · {$ok} titles saved, {$fail} failed of {$total}",
            ]);

            Log::info('amazon:pull-titles finished', compact('total', 'ok', 'fail', 'lotSize'));
            $this->info("Done · ok={$ok} fail={$fail}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('amazon:pull-titles failed', ['error' => $e->getMessage()]);
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
     * Write Title 170 into product_master.title150 (same column as Title Master).
     */
    protected function writeTitle150(string $sku, string $title170): bool
    {
        $sku = trim($sku);
        if ($sku === '' || $title170 === '') {
            return false;
        }

        $payload = [
            'title150' => $title170,
            'updated_at' => now(),
        ];

        $updated = DB::table('product_master')
            ->whereNull('deleted_at')
            ->where('sku', $sku)
            ->update($payload);

        if ($updated === 0) {
            $updated = DB::table('product_master')
                ->whereNull('deleted_at')
                ->whereRaw(
                    "LOWER(TRIM(REPLACE(REPLACE(sku, UNHEX('C2A0'), ' '), CHAR(160), ' '))) = ?",
                    [mb_strtolower($sku)]
                )
                ->update($payload);
        }

        return $updated > 0;
    }

    /**
     * @return list<string>
     */
    protected function resolveSkusWithInvAtLeastOne(): array
    {
        $requested = $this->resolveRequestedSkus();

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
                $eligible[] = $sku;
            }
        }

        return array_values(array_unique($eligible));
    }

    /**
     * @return list<string>
     */
    protected function resolveRequestedSkus(): array
    {
        $requested = $this->parseRequestedSkus();
        if ($requested === []) {
            return ProductMaster::query()
                ->whereNull('deleted_at')
                ->whereRaw('UPPER(TRIM(sku)) NOT LIKE ?', ['PARENT%'])
                ->pluck('sku')
                ->map(static fn ($s) => trim((string) $s))
                ->filter(static fn ($s) => $s !== '' && ! str_starts_with(strtoupper($s), 'PARENT'))
                ->unique()
                ->values()
                ->all();
        }

        return array_values(array_filter($requested, static function ($s) {
            $u = strtoupper(trim((string) $s));

            return $u !== '' && ! str_starts_with($u, 'PARENT');
        }));
    }

    /**
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
