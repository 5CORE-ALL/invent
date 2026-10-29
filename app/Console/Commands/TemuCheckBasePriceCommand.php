<?php

namespace App\Console\Commands;

use App\Models\TemuDataView;
use App\Models\TemuMetric;
use App\Services\Support\NewTemuoneSuggestedPriceStore;
use App\Services\TemuApiService;
use App\Services\TemuShopifySalesService;
use Illuminate\Console\Command;

/**
 * Read-only: compare, for one SKU, the base price Temu holds (live API) with what /new-temuone has stored and pushed.
 * Nothing is written to Temu or to the database.
 */
class TemuCheckBasePriceCommand extends Command
{
    protected $signature = 'temu:check-base-price {sku : Seller SKU, e.g. "CS 06 2W 2PAIR"}';

    protected $description = 'Compare Temu live base price (API) with temu_metrics.base_price and the S Base Prc pushed from /new-temuone (read-only).';

    public function handle(TemuApiService $temu): int
    {
        $sku = trim((string) $this->argument('sku'));
        $metrics = TemuMetric::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->get();
        if ($metrics->isEmpty()) {
            $this->error("No temu_metrics row for SKU {$sku}.");

            return self::FAILURE;
        }
        if (! $temu->isConfigured()) {
            $this->error('Temu credentials are not configured on this environment (run this on the server).');

            return self::FAILURE;
        }

        foreach ($metrics as $m) {
            $goodsId = trim((string) $m->goods_id);
            $skuId = trim((string) $m->sku_id);
            $this->info("SKU {$m->sku}   goodsId={$goodsId}   skuId={$skuId}");

            $siblings = TemuMetric::query()->where('sku_id', $skuId)->where('id', '!=', $m->id)->pluck('sku')->all();
            if ($siblings !== []) {
                $this->warn('  Same Temu skuId is also used by: '.implode(', ', $siblings).'  (a price pull writes the same value to all of them)');
            }

            $live = null;
            if ($goodsId !== '' && $skuId !== '') {
                $prices = $temu->querySkuSupplierPrices([['goodsId' => $goodsId, 'skuIdList' => [$skuId]]]);
                $live = $prices[(string) (int) $skuId] ?? null;
            }

            $stored = $m->base_price !== null ? round((float) $m->base_price, 2) : null;
            $view = TemuDataView::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim((string) $m->sku))])->first();
            $saved = is_array($view?->value) ? $view->value : [];
            $pushed = is_numeric($saved[NewTemuoneSuggestedPriceStore::KEY_PUSHED_BASE] ?? null) ? round((float) $saved[NewTemuoneSuggestedPriceStore::KEY_PUSHED_BASE], 2) : null;
            $sBase = is_numeric($saved[NewTemuoneSuggestedPriceStore::KEY_S_BASE] ?? null) ? round((float) $saved[NewTemuoneSuggestedPriceStore::KEY_S_BASE], 2) : null;
            $sprice = is_numeric($saved[NewTemuoneSuggestedPriceStore::KEY_SPRICE] ?? null) ? round((float) $saved[NewTemuoneSuggestedPriceStore::KEY_SPRICE], 2) : null;

            $this->table(['What', 'Value'], [
                ['Temu LIVE base price (API supplier price)', $live === null ? 'not returned' : '$'.number_format($live, 2)],
                ['App stored base price (temu_metrics.base_price)', $stored === null ? 'blank' : '$'.number_format($stored, 2)],
                ['App stored base updated at', (string) $m->updated_at],
                ['App S PRC saved (NTO_SPRICE)', $sprice === null ? '-' : '$'.number_format($sprice, 2)],
                ['App S Base Prc saved (NTO_S_BASE)', $sBase === null ? '-' : '$'.number_format($sBase, 2)],
                ['Last pushed base (NTO_PUSHED_BASE)', $pushed === null ? '-' : '$'.number_format($pushed, 2)],
                ['Last pushed at', (string) ($saved[NewTemuoneSuggestedPriceStore::KEY_PUSHED_AT] ?? '-')],
                ['Page Price from live base', $live > 0 ? '$'.number_format(TemuShopifySalesService::newTemuonePriceFromBase((float) $live), 2) : '-'],
                ['Page Price from stored base', $stored > 0 ? '$'.number_format(TemuShopifySalesService::newTemuonePriceFromBase((float) $stored), 2) : '-'],
            ]);

            if ($live !== null && $stored !== null && abs($live - $stored) >= 0.01) {
                $this->warn(sprintf('  Live $%.2f differs from the stored $%.2f: the app has not pulled the latest price (run: php artisan app:fetch-temu-metrics --only=price).', $live, $stored));
            }
            if ($pushed !== null && $live !== null && abs($pushed - $live) >= 0.01) {
                $this->warn(sprintf('  Pushed $%.2f is not live yet (Temu shows "being processed" for a few days): live stays $%.2f until Temu applies it.', $pushed, $live));
            }
            $this->newLine();
        }

        return self::SUCCESS;
    }
}
