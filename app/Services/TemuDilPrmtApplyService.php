<?php

namespace App\Services;

use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Dil vs PRMT% for Temu 1 / Temu 2 / Temu 3 — the "Save & Apply" on /promotions.
 *
 * Temu pages price from Std Prc vs Dil, so PRMT% is NOT used for Temu prices and nothing is pushed to Temu.
 * This only keeps each SKU's saved PRMT% (PEF_PRMT_PCT in the channel's data_view) in line with the shared
 * Dil vs PRMT slabs, the same numbers the eBay / Amazon rows use.
 *
 *  Dil   = SKU-wise OV L30 ÷ INV × 100 (Shopify qty ÷ inv, as /new-temuone reads it)
 *  PRMT% = shared slab for that Dil (INV = 0 → 0)
 *
 * Universe = every non-PARENT product-master SKU (the same set the Promotions badge counts).
 */
class TemuDilPrmtApplyService
{
    public const CHANNELS = ['temu', 'temu2', 'temu3'];

    public function __construct(
        private readonly PefDilPrmtAutoApplyService $rules,
        private readonly ChannelPromoPricingService $promo,
    ) {}

    /**
     * Dil of every SKU with INV > 0 (one entry per SKU). Read-only; feeds the Promotions "Count" column.
     *
     * @return list<float>
     */
    public function skuDils(): array
    {
        $out = [];
        foreach ($this->skuRows() as $row) {
            if ($row['inv'] > 0) {
                $out[] = $row['dil'];
            }
        }

        return $out;
    }

    /**
     * @return array{skus:int,saved:int,unchanged:int,errors:list<string>}
     */
    public function run(string $channel, bool $dryRun = false, ?callable $logger = null): array
    {
        $channel = strtolower(trim($channel));
        if (! in_array($channel, self::CHANNELS, true)) {
            throw new \InvalidArgumentException('channel must be one of: '.implode(', ', self::CHANNELS));
        }
        $log = $logger ?? static function (string $msg): void {};
        $stats = ['skus' => 0, 'saved' => 0, 'unchanged' => 0, 'errors' => []];

        $lock = Cache::lock('temu-dil-prmt-apply-run-'.$channel, 1800);
        if (! $lock->get()) {
            $log('Temu Dil→PRMT apply already running for '.$channel.' — skipped.');
            $stats['errors'][] = 'lock: already running';

            return $stats;
        }

        try {
            $rules = $this->rules->loadRules();
            $rows = $this->skuRows();
            $stats['skus'] = count($rows);

            $current = [];
            foreach (array_chunk(array_column($rows, 'sku'), 400) as $chunk) {
                foreach ($this->promo->mapForSkus($channel, $chunk) as $key => $mapped) {
                    $current[$key] = is_numeric($mapped['prmt_pct'] ?? null) ? (float) $mapped['prmt_pct'] : 0.0;
                }
            }

            foreach ($rows as $row) {
                $prmt = $row['inv'] > 0 ? $this->rules->prmtForDil($row['dil'], $rules) : 0.0;
                $have = $current[strtoupper(trim($row['sku']))] ?? 0.0;
                if (abs($have - $prmt) < 0.005) {
                    $stats['unchanged']++;

                    continue;
                }
                if ($dryRun) {
                    $stats['saved']++;
                    $log(sprintf('  DRY %s prmt %s → %s (Dil %.2f%%)', $row['sku'], $have, $prmt, $row['dil']));

                    continue;
                }
                try {
                    $this->promo->upsert($channel, $row['sku'], ['prmt_pct' => $prmt]);
                    $stats['saved']++;
                } catch (\Throwable $e) {
                    $stats['errors'][] = $row['sku'].': '.$e->getMessage();
                    Log::warning('Temu Dil→PRMT save failed', ['channel' => $channel, 'sku' => $row['sku'], 'error' => $e->getMessage()]);
                }
            }

            $log(sprintf('%s: %d SKUs — PRMT saved on %d, unchanged %d, errors %d.',
                $channel, $stats['skus'], $stats['saved'], $stats['unchanged'], count($stats['errors'])));

            return $stats;
        } finally {
            $lock->release();
        }
    }

    /**
     * @return list<array{sku:string,inv:float,dil:float}>
     */
    private function skuRows(): array
    {
        $skus = [];
        ProductMaster::query()
            ->whereRaw("UPPER(TRIM(sku)) NOT LIKE 'PARENT%'")
            ->select('sku')
            ->orderBy('sku')
            ->get()
            ->each(function ($pm) use (&$skus) {
                $sku = trim((string) $pm->sku);
                if ($sku !== '') {
                    $skus[strtoupper($sku)] = $sku;
                }
            });
        $skus = array_values($skus);
        if ($skus === []) {
            return [];
        }

        $shopify = ShopifySku::mapByProductSkus($skus);
        $out = [];
        foreach ($skus as $sku) {
            $row = $shopify->get($sku);
            $inv = (float) ($row->inv ?? 0);
            $l30 = (float) ($row->quantity ?? 0);
            $out[] = [
                'sku' => $sku,
                'inv' => $inv,
                'dil' => $inv > 0 ? round(($l30 / $inv) * 100, 2) : 0.0,
            ];
        }

        return $out;
    }
}
