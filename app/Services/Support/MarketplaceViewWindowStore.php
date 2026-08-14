<?php

namespace App\Services\Support;

use App\Models\AmazonDatasheet;
use App\Models\ChannelMasterCalculatedData;
use App\Models\ShopifySku;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Writes L1 (yesterday) and L7 listing views onto marketplace tables that already store views.
 * L1 is used for Yesterday CVR = qty ÷ views × 100.
 */
class MarketplaceViewWindowStore
{
    private const TZ = 'America/Los_Angeles';

    /**
     * @return array<string, array{l1: int, l7: int, source: string}>
     */
    public function storeAll(string $date, YesterdayViewsService $viewsApi, bool $includeAmazon = true): array
    {
        $out = [];

        $safe = function (string $channel, callable $fn) use (&$out) {
            try {
                $out[$channel] = $fn();
            } catch (\Throwable $e) {
                Log::warning("L1/L7 views store failed for {$channel}: ".$e->getMessage());
            }
        };

        $safe('ebay', fn () => $this->fromDailyJson('ebay_sku_daily_data', 'ebay_metrics', 'l1_views', 'l7_views', $date, 'sku_daily_delta'));
        $safe('ebay2', fn () => $this->fromDailyJson('ebay2_sku_daily_data', 'ebay_2_metrics', 'l1_views', 'l7_views', $date, 'sku_daily_delta'));
        $safe('walmart', function () use ($date) {
            $row = $this->fromDailyJson('walmart_sku_daily_data', 'walmart_pricing_sales', 'page_views_l1', 'page_views_l7', $date, 'sku_daily_delta');
            $this->applySkuWindows('walmart_product_sheets', 'views_l1', 'views_l7', $this->lastSkuWindows);

            return $row;
        });
        $safe('temu', function () use ($date) {
            $row = $this->fromDailyColumn('temu_sku_daily_data', 'product_clicks', 'temu_metrics', 'product_clicks_l1', 'product_clicks_l7', $date, 'sku_daily_delta');
            $this->applySkuWindows('temu2_metrics', 'product_clicks_l1', 'product_clicks_l7', $this->lastSkuWindows);

            return $row;
        });
        $safe('shopify', function () use ($date, $viewsApi) {
            $row = $this->storeShopifyWindows($date, $viewsApi);
            if ($row === null) {
                throw new \RuntimeException('Shopify L1/L7 views unavailable');
            }

            return $row;
        });
        if ($includeAmazon) {
            $safe('amazon', function () use ($date, $viewsApi) {
                $row = $this->storeAmazonL1($date, $viewsApi);
                if ($row === null) {
                    throw new \RuntimeException('Amazon L1 views unavailable');
                }

                return $row;
            });
        }

        foreach ($out as $channel => $row) {
            $viewsApi->store($channel, $date, (int) $row['l1'], $row['source']);
            $this->storeChannelCalculated($channel, (int) $row['l1'], (int) $row['l7']);
            $this->storeChannelL7($channel, $date, (int) $row['l7']);
        }

        return $out;
    }

    /** @var array<string, array{l1: int, l7: int}> */
    private array $lastSkuWindows = [];

    /**
     * @return array{l1: int, l7: int, source: string}
     */
    private function fromDailyJson(string $dailyTable, string $targetTable, string $l1Col, string $l7Col, string $date, string $source): array
    {
        $windows = $this->skuWindowsFromDaily($dailyTable, $date, 'json');
        $this->lastSkuWindows = $windows;
        $this->applySkuWindows($targetTable, $l1Col, $l7Col, $windows);

        return $this->sumWindows($windows, $source);
    }

    /**
     * @return array{l1: int, l7: int, source: string}
     */
    private function fromDailyColumn(string $dailyTable, string $valueCol, string $targetTable, string $l1Col, string $l7Col, string $date, string $source): array
    {
        $windows = $this->skuWindowsFromDaily($dailyTable, $date, $valueCol);
        $this->lastSkuWindows = $windows;
        $this->applySkuWindows($targetTable, $l1Col, $l7Col, $windows);

        return $this->sumWindows($windows, $source);
    }

    /**
     * @return array<string, array{l1: int, l7: int}>
     */
    private function skuWindowsFromDaily(string $table, string $date, string $mode): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $start = Carbon::parse($date, self::TZ)->subDays(8)->toDateString();
        $select = ['sku', 'record_date'];
        if ($mode === 'json') {
            $select[] = 'daily_data';
        } elseif (Schema::hasColumn($table, $mode)) {
            $select[] = $mode;
        } else {
            return [];
        }

        $bySku = [];
        foreach (DB::table($table)->where('record_date', '>=', $start)->where('record_date', '<=', $date)->orderBy('record_date')->get($select) as $row) {
            $sku = strtoupper(trim((string) $row->sku));
            if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                continue;
            }
            $day = Carbon::parse($row->record_date)->toDateString();
            $bySku[$sku][$day] = $mode === 'json'
                ? $this->extractJsonViews($row->daily_data ?? null)
                : (int) ($row->{$mode} ?? 0);
        }

        $windows = [];
        $l7Start = Carbon::parse($date, self::TZ)->subDays(6)->toDateString();
        $prev = Carbon::parse($date, self::TZ)->subDay()->toDateString();
        foreach ($bySku as $sku => $days) {
            ksort($days);
            $keys = array_keys($days);
            $deltas = [];
            for ($i = 0; $i < count($keys); $i++) {
                $dk = $keys[$i];
                $deltas[$dk] = $i === 0 ? 0 : max(0, (int) $days[$dk] - (int) $days[$keys[$i - 1]]);
            }
            $l1 = (int) ($deltas[$date] ?? 0);
            if ($l1 === 0 && isset($days[$date], $days[$prev])) {
                $l1 = max(0, (int) $days[$date] - (int) $days[$prev]);
            }
            $l7 = 0;
            foreach ($deltas as $d => $v) {
                if ($d >= $l7Start && $d <= $date) {
                    $l7 += $v;
                }
            }
            $windows[$sku] = ['l1' => $l1, 'l7' => $l7];
        }

        return $windows;
    }

    /**
     * @param  array<string, array{l1: int, l7: int}>  $windows
     */
    private function applySkuWindows(string $table, string $l1Col, string $l7Col, array $windows): void
    {
        if ($windows === [] || ! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sku')) {
            return;
        }
        $hasL1 = Schema::hasColumn($table, $l1Col);
        $hasL7 = Schema::hasColumn($table, $l7Col);
        if (! $hasL1 && ! $hasL7) {
            return;
        }

        foreach ($windows as $sku => $w) {
            $update = [];
            if ($hasL1) {
                $update[$l1Col] = $w['l1'];
            }
            if ($hasL7) {
                $update[$l7Col] = $w['l7'];
            }
            if ($update === []) {
                continue;
            }
            DB::table($table)->whereRaw('UPPER(TRIM(sku)) = ?', [$sku])->update($update);
        }
    }

    /**
     * @return array{l1: int, l7: int, source: string}|null
     */
    private function storeShopifyWindows(string $date, YesterdayViewsService $viewsApi): ?array
    {
        if (! Schema::hasColumn('shopify_skus', 'views_l1')) {
            $l1 = $viewsApi->shopifyViewsPublic($date);
            return $l1 === null ? null : ['l1' => $l1, 'l7' => 0, 'source' => 'shopifyql'];
        }

        $l1ByPath = $viewsApi->shopifyViewsByPath($date, $date);
        $l7Start = Carbon::parse($date, self::TZ)->subDays(6)->toDateString();
        $l7ByPath = $viewsApi->shopifyViewsByPath($l7Start, $date);
        if ($l1ByPath === null && $l7ByPath === null) {
            return null;
        }

        $pathToIds = $this->shopifyPathToIds();
        foreach ($pathToIds as $path => $ids) {
            $l1 = (int) (($l1ByPath ?? [])[$path] ?? 0);
            $l7 = (int) (($l7ByPath ?? [])[$path] ?? 0);
            foreach ($ids as $id) {
                ShopifySku::where('id', $id)->update([
                    'views_l1' => $l1,
                    'views_l7' => $l7,
                ]);
            }
        }
        $l1Total = array_sum($l1ByPath ?? []);
        $l7Total = array_sum($l7ByPath ?? []);

        return ['l1' => $l1Total, 'l7' => $l7Total, 'source' => 'shopifyql'];
    }

    /**
     * @return array<string, array<int, int>>
     */
    private function shopifyPathToIds(): array
    {
        $map = [];
        ShopifySku::query()
            ->whereNotNull('product_link')
            ->where('product_link', '!=', '')
            ->select('id', 'product_link')
            ->orderBy('id')
            ->chunkById(1000, function ($rows) use (&$map) {
                foreach ($rows as $row) {
                    $path = $this->normalizeProductPath((string) $row->product_link);
                    if ($path !== '') {
                        $map[$path][] = (int) $row->id;
                    }
                }
            });

        return $map;
    }

    private function normalizeProductPath(string $urlOrPath): string
    {
        $raw = trim($urlOrPath);
        if ($raw === '') {
            return '';
        }
        $path = parse_url($raw, PHP_URL_PATH);
        if ($path === null || $path === false || $path === '') {
            $path = str_starts_with($raw, '/') ? $raw : '/'.$raw;
        }
        $path = strtolower(rtrim((string) $path, '/'));
        if (preg_match('#(/products/[^/]+)#', $path, $m)) {
            return $m[1];
        }

        return '';
    }

    /**
     * @return array{l1: int, l7: int, source: string}|null
     */
    private function storeAmazonL1(string $date, YesterdayViewsService $viewsApi): ?array
    {
        $byAsin = $viewsApi->amazonSessionsByAsin($date);
        if ($byAsin === null) {
            return null;
        }

        if (Schema::hasColumn('amazon_datsheets', 'sessions_l1')) {
            foreach (array_chunk($byAsin, 200, true) as $chunk) {
                $asins = array_keys($chunk);
                AmazonDatasheet::query()->whereIn('asin', $asins)->get(['id', 'asin'])->each(function ($row) use ($chunk) {
                    $asin = (string) $row->asin;
                    if (isset($chunk[$asin])) {
                        $row->sessions_l1 = $chunk[$asin];
                        $row->save();
                    }
                });
            }
        }

        $l7 = 0;
        if (Schema::hasColumn('amazon_datsheets', 'sessions_l7')) {
            $l7 = (int) AmazonDatasheet::query()->sum('sessions_l7');
        }

        return ['l1' => array_sum($byAsin), 'l7' => $l7, 'source' => 'sp_api_l1'];
    }

    /**
     * @param  array<string, array{l1: int, l7: int}>  $windows
     * @return array{l1: int, l7: int, source: string}
     */
    private function sumWindows(array $windows, string $source): array
    {
        $l1 = 0;
        $l7 = 0;
        foreach ($windows as $w) {
            $l1 += (int) $w['l1'];
            $l7 += (int) $w['l7'];
        }

        return ['l1' => $l1, 'l7' => $l7, 'source' => $source];
    }

    private function storeChannelCalculated(string $channel, int $l1, int $l7): void
    {
        if (! Schema::hasTable('channel_master_calculated_data')) {
            return;
        }
        $update = [];
        if (Schema::hasColumn('channel_master_calculated_data', 'yesterday_views')) {
            $update['yesterday_views'] = $l1;
        }
        if (Schema::hasColumn('channel_master_calculated_data', 'l7_views')) {
            $update['l7_views'] = $l7;
        }
        if ($update === []) {
            return;
        }

        $aliases = [
            'ebay' => ['ebay'],
            'ebay2' => ['ebay2', 'ebaytwo'],
            'shopify' => ['shopify', 'shopifyb2c'],
            'walmart' => ['walmart'],
            'temu' => ['temu'],
            'amazon' => ['amazon'],
        ];
        $want = $aliases[preg_replace('/[^a-z0-9]/', '', strtolower($channel))] ?? [preg_replace('/[^a-z0-9]/', '', strtolower($channel))];

        foreach (ChannelMasterCalculatedData::query()->get(['id', 'channel']) as $row) {
            $key = preg_replace('/[^a-z0-9]/', '', strtolower((string) $row->channel));
            if (in_array($key, $want, true)) {
                ChannelMasterCalculatedData::where('id', $row->id)->update($update);
            }
        }
    }

    private function storeChannelL7(string $channel, string $date, int $l7): void
    {
        if (! Schema::hasTable('channel_yesterday_views') || ! Schema::hasColumn('channel_yesterday_views', 'l7_views')) {
            return;
        }
        DB::table('channel_yesterday_views')
            ->where('channel', $channel)
            ->whereDate('snapshot_date', $date)
            ->update(['l7_views' => $l7]);
    }

    private function extractJsonViews(mixed $dailyData): int
    {
        if ($dailyData === null || $dailyData === '') {
            return 0;
        }
        $decoded = is_string($dailyData)
            ? (json_decode($dailyData, true) ?: [])
            : (is_array($dailyData) ? $dailyData : []);

        return (int) ($decoded['views'] ?? $decoded['sessions'] ?? $decoded['page_views'] ?? 0);
    }
}
