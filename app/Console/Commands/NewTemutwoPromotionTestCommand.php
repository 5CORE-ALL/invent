<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarketPlace\NewTemutwoController;
use App\Models\Temu2DataView;
use App\Models\Temu2Metric;
use App\Services\Support\NewTemutwoSuggestedPriceStore;
use App\Services\Temu2ApiService;
use App\Services\TemuPromotionApiService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

/**
 * Test the Temu 2 promotion for ONE SKU. Dry run by default (read-only Temu calls only).
 * --apply really enrolls the goods in the open Temu 2 promotion, even when the page switch is OFF.
 */
class NewTemutwoPromotionTestCommand extends Command
{
    protected $signature = 'newtemutwo:promotion-test
        {sku : Child SKU to test}
        {--pct= : Promotion % (the page Sum disc for this SKU)}
        {--sprice= : S PRC the promotion is taken off (default: saved S PRC, else live base)}
        {--activity= : Pin an activity id (default: first open lightning deal)}
        {--apply : Really enroll on Temu 2 (default is a dry run)}';

    protected $description = 'Test the Temu 2 promotion for one SKU (dry run unless --apply).';

    public function handle(Temu2ApiService $temu): int
    {
        $sku = trim((string) $this->argument('sku'));
        $pct = round((float) $this->option('pct'), 2);
        if (! $temu->isConfigured()) {
            $this->error('Temu 2 credentials (TEMU2_*) are not configured on this environment.');

            return self::FAILURE;
        }
        if (! ($pct > 0 && $pct < 100)) {
            $this->error('Pass --pct=<Sum disc %> between 0 and 100.');

            return self::FAILURE;
        }

        $metric = Temu2Metric::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first();
        $goodsId = trim((string) ($metric?->goods_id ?? '')) ?: (string) ($temu->getGoodsIdBySku($sku) ?? '');
        $this->line("SKU {$sku}  goodsId=".($goodsId ?: '—').'  skuId='.($metric?->sku_id ?: '—').'  live base='.($metric?->base_price ?? '—'));
        if ($goodsId === '') {
            $this->error('Temu goodsId not found for this SKU.');

            return self::FAILURE;
        }

        $promo = new TemuPromotionApiService($temu);
        $activityId = trim((string) $this->option('activity')) ?: (NewTemutwoController::promotionSetting()['activity_id'] ?? null);
        if (! $activityId) {
            $resolved = $promo->resolveOpenActivity();
            if (! $resolved['success']) {
                $this->error('No open activity: '.$resolved['message']);

                return self::FAILURE;
            }
            $activityId = (string) $resolved['activity']['activityId'];
            $this->line('Activity: '.$activityId.' '.($resolved['activity']['activityName'] ?? ''));
        } else {
            $this->line('Activity: '.$activityId);
        }

        $cand = $promo->candidateGoods($activityId);
        if (! $cand['success']) {
            $this->error('Candidate list failed: '.$cand['message']);

            return self::FAILURE;
        }
        $g = $cand['goods'][$goodsId] ?? null;
        $this->line('Eligible: '.($g && ! empty($g['allowed']) ? 'YES' : 'NO (goods not on the candidate list — Temu would reject it)'));
        if ($g && ! empty($g['skus'])) {
            $this->line('Temu recommended prices: '.json_encode($g['skus']));
        }

        $sprice = round((float) $this->option('sprice'), 2);
        if (! ($sprice > 0)) {
            $view = Temu2DataView::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first();
            $saved = is_array($view?->value) ? ($view->value[NewTemutwoSuggestedPriceStore::KEY_SPRICE] ?? null) : null;
            $sprice = is_numeric($saved) ? round((float) $saved, 2) : 0.0;
        }
        if ($sprice > 0) {
            $sR = round($sprice * (1 - $pct / 100) * 0.88, 2);
            $price = $sR < 30 ? round($sR - 2.99, 2) : $sR;
            $this->line("S PRC \${$sprice} − {$pct}% → promotion base \${$price}");
        } else {
            $this->line('No S PRC — the promotion would be live base × (1 − pct).');
        }

        $siblings = Temu2Metric::query()->where('goods_id', $goodsId)->pluck('sku', 'sku_id')->all();
        $this->line('Sibling SKUs sent together: '.json_encode($siblings));

        if (! $this->option('apply')) {
            $this->info('Dry run — nothing was sent. Add --apply to enroll.');

            return self::SUCCESS;
        }

        $request = Request::create('/new-temu2-promotion-push', 'POST', [
            'items' => [['sku' => $sku, 'pct' => $pct, 'sprice' => $sprice]],
        ]);
        $response = app(NewTemutwoController::class)->pushPromotion($request, $temu, true);
        $this->line(json_encode($response->getData(true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $response->getStatusCode() < 400 ? self::SUCCESS : self::FAILURE;
    }
}
