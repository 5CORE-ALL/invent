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
        {sku? : Child SKU to test (leave out for the eligibility summary)}
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
        if ($sku === '') {
            return $this->summary($temu);
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

    /** Why does the page say "Not eligible"? Compare Temu's candidate list with our goods ids. */
    protected function summary(Temu2ApiService $temu): int
    {
        $promo = new TemuPromotionApiService($temu);
        $this->info('Temu 2 promotion activities');
        foreach ([TemuPromotionApiService::STATUS_ONGOING => 'ongoing', TemuPromotionApiService::STATUS_NOT_STARTED => 'not started'] as $status => $label) {
            $res = $promo->queryActivities(TemuPromotionApiService::TYPE_LIGHTNING_DEAL, $status);
            if (! $res['success']) {
                $this->error("  {$label}: ".$res['message']);
                continue;
            }
            foreach ($res['activities'] as $a) {
                $this->line(sprintf('  [%s] id=%s name=%s end=%s', $label, $a['activityId'] ?? '?', $a['activityName'] ?? '', ! empty($a['activityEndTime']) ? date('Y-m-d H:i', (int) $a['activityEndTime']) : '—'));
            }
            if ($res['activities'] === []) {
                $this->line("  [{$label}] none");
            }
        }

        $activityId = trim((string) $this->option('activity')) ?: (NewTemutwoController::promotionSetting()['activity_id'] ?? null);
        if (! $activityId) {
            $resolved = $promo->resolveOpenActivity();
            if (! $resolved['success']) {
                $this->error('No open activity: '.$resolved['message']);

                return self::FAILURE;
            }
            $activityId = (string) $resolved['activity']['activityId'];
        }
        $this->line("Using activity {$activityId} (pin another with --activity=ID)");

        $cand = $promo->candidateGoods($activityId);
        if (! $cand['success']) {
            $this->error('Candidate list failed: '.$cand['message']);

            return self::FAILURE;
        }
        $goods = $cand['goods'];
        $allowed = count(array_filter($goods, fn ($g) => ! empty($g['allowed'])));
        $this->line('Temu candidate goods: '.count($goods)." (allowed to enroll: {$allowed})");
        $this->line('  sample candidate goodsIds: '.implode(', ', array_slice(array_keys($goods), 0, 5)));

        $ours = Temu2Metric::query()->whereNotNull('goods_id')->where('goods_id', '!=', '')->pluck('goods_id')->map(fn ($g) => (string) $g)->unique()->values();
        $total = Temu2Metric::query()->count();
        $this->line("Our temu2_metrics rows: {$total}, with a goods_id: ".$ours->count());
        $this->line('  sample our goodsIds: '.$ours->take(5)->implode(', '));
        $hit = $ours->filter(fn ($g) => isset($goods[$g]))->count();
        $this->line("Our goods that are on Temu's candidate list: {$hit}");
        if ($hit === 0) {
            $this->warn(count($goods) === 0
                ? 'Temu returned NO candidate goods for this activity — nothing is enrollable (try another activity).'
                : 'No overlap — compare the sample ids above (format mismatch?) or the activity does not cover these goods.');
        }

        return self::SUCCESS;
    }
}
