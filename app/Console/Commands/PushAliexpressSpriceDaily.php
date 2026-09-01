<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarketPlace\AliexpressController;
use App\Models\AliexpressDataView;
use App\Models\AliexpressMetric;
use App\Models\AliexpressPricingPrice;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Services\AliExpressApiService;
use App\Support\AliexpressPushGuard;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class PushAliexpressSpriceDaily extends Command
{
    protected $signature = 'aliexpress:push-sprice-daily
                            {--dry-run : List SKUs that would be pushed, do not call AliExpress}
                            {--force : Push SGROI < 40% even if the stop button is ON}';

    protected $description = 'Daily cron: push AliExpress Sprice to live listings (skips SGROI < 40% when that button is ON)';

    public function handle(AliExpressApiService $api, AliexpressController $controller): int
    {
        if (empty(config('services.aliexpress.access_token'))) {
            $this->error('ALIEXPRESS_ACCESS_TOKEN is missing.');

            return self::FAILURE;
        }
        if (! Schema::hasTable('aliexpress_metric') || ! Schema::hasTable('aliexpress_data_views')) {
            $this->error('AliExpress pricing tables are missing.');

            return self::FAILURE;
        }

        $guardOn = AliexpressPushGuard::stopLowSgroiEnabled() && ! $this->option('force');
        $this->info('Stop cron push SGROI < '.AliexpressPushGuard::minSgroi().'%: '.($guardOn ? 'ON' : 'OFF'));

        $collected = $this->collect($guardOn);
        $updates = $collected['updates'];
        $skippedLow = $collected['skipped_low'];
        $matched = $collected['matched'];

        $this->line('Already live (Sprice = Price): '.$matched);
        $this->line('Skipped SGROI < '.AliexpressPushGuard::minSgroi().'%: '.$skippedLow);
        $this->info('To push: '.count($updates));

        if ($updates === []) {
            $this->info('Nothing to push.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->table(
                ['SKU', 'Sprice', 'Live', 'SGROI'],
                array_map(static fn (array $u) => [
                    $u['sku'],
                    number_format($u['price'], 2),
                    number_format($u['live'], 2),
                    (string) $u['sgroi'],
                ], array_slice($updates, 0, 40))
            );
            if (count($updates) > 40) {
                $this->line('…and '.(count($updates) - 40).' more');
            }

            return self::SUCCESS;
        }

        $pushed = 0;
        $failed = 0;
        foreach (array_chunk($updates, 40) as $chunk) {
            $req = Request::create('/aliexpress/pricing-push-price', 'POST', [
                'updates' => array_map(static fn (array $u) => [
                    'sku' => $u['sku'],
                    'price' => $u['price'],
                ], $chunk),
            ]);
            $res = $controller->pushPricingPrice($req, $api);
            $payload = $res->getData(true);
            $pushed += (int) ($payload['pushed'] ?? 0);
            $failed += (int) ($payload['failed'] ?? 0);
            $this->line(($payload['message'] ?? 'chunk done').' (ok '.$pushed.', fail '.$failed.')');
        }

        $this->info("Done. Pushed {$pushed}, failed {$failed}.");

        return $failed && ! $pushed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{updates: list<array{sku: string, price: float, live: float, sgroi: int}>, skipped_low: int, matched: int}
     */
    private function collect(bool $guardOn): array
    {
        $margin = $this->marginRate();
        $liveByNorm = [];
        if (Schema::hasTable('aliexpress_pricing_prices')) {
            foreach (AliexpressPricingPrice::query()->get(['sku', 'price']) as $row) {
                $norm = $this->normSku((string) $row->sku);
                if ($norm === '') {
                    continue;
                }
                $liveByNorm[$norm] = round((float) $row->price, 2);
            }
        }

        $listed = [];
        AliexpressMetric::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->whereNotNull('product_id')
            ->where('product_id', '!=', '')
            ->get(['sku', 'product_id'])
            ->each(function (AliexpressMetric $row) use (&$listed) {
                $norm = $this->normSku((string) $row->sku);
                if ($norm !== '' && ! isset($listed[$norm])) {
                    $listed[$norm] = true;
                }
            });

        $updates = [];
        $skippedLow = 0;
        $matched = 0;

        AliexpressDataView::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy('id')
            ->chunkById(400, function ($rows) use ($margin, $liveByNorm, $listed, $guardOn, &$updates, &$skippedLow, &$matched) {
                $skus = [];
                foreach ($rows as $row) {
                    $sku = trim((string) $row->sku);
                    if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                        continue;
                    }
                    $skus[] = $sku;
                }
                $pmByNorm = $this->productMasters($skus);

                foreach ($rows as $row) {
                    $sku = trim((string) $row->sku);
                    if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                        continue;
                    }
                    $norm = $this->normSku($sku);
                    if ($norm === '' || empty($listed[$norm])) {
                        continue;
                    }
                    $val = is_array($row->value)
                        ? $row->value
                        : (json_decode((string) ($row->value ?? ''), true) ?: []);
                    $sprice = round((float) ($val['SPRICE'] ?? 0), 2);
                    if (! ($sprice > 0)) {
                        continue;
                    }
                    $live = $liveByNorm[$norm] ?? 0;
                    if ($live > 0 && abs($sprice - $live) < 0.005) {
                        $matched++;
                        continue;
                    }

                    $pm = $pmByNorm[$norm] ?? ['lp' => 0.0, 'ship' => 0.0];
                    $sgroi = $this->sgroi($sprice, $margin, (float) $pm['lp'], (float) $pm['ship']);
                    if ($guardOn && $sgroi < AliexpressPushGuard::minSgroi()) {
                        $skippedLow++;
                        continue;
                    }

                    $updates[] = [
                        'sku' => $sku,
                        'price' => $sprice,
                        'live' => $live,
                        'sgroi' => $sgroi,
                    ];
                }
            });

        return [
            'updates' => $updates,
            'skipped_low' => $skippedLow,
            'matched' => $matched,
        ];
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, array{lp: float, ship: float}>
     */
    private function productMasters(array $skus): array
    {
        if ($skus === []) {
            return [];
        }
        $map = [];
        $norms = array_values(array_unique(array_map(fn ($s) => $this->normSku($s), $skus)));
        ProductMaster::query()
            ->whereIn(\Illuminate\Support\Facades\DB::raw('UPPER(TRIM(sku))'), $norms)
            ->get(['sku', 'Values'])
            ->each(function (ProductMaster $pm) use (&$map) {
                $norm = $this->normSku((string) $pm->sku);
                if ($norm === '') {
                    return;
                }
                $values = is_array($pm->Values)
                    ? $pm->Values
                    : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                $lp = isset($values['lp']) ? (float) $values['lp'] : 0;
                $ship = isset($values['ae_ship']) ? (float) $values['ae_ship']
                    : (isset($values['ship']) ? (float) $values['ship'] : 0);
                $map[$norm] = ['lp' => $lp, 'ship' => $ship];
            });

        return $map;
    }

    private function sgroi(float $sprice, float $margin, float $lp, float $ship): int
    {
        if (! ($lp > 0) || ! ($sprice > 0)) {
            return 0;
        }

        return (int) round((($sprice * $margin - $lp - $ship) / $lp) * 100);
    }

    private function marginRate(): float
    {
        $mp = MarketplacePercentage::query()
            ->whereRaw('LOWER(TRIM(marketplace)) = ?', ['aliexpress'])
            ->orderBy('id')
            ->first();
        $pct = ($mp !== null && is_numeric($mp->percentage ?? null) && (float) $mp->percentage > 0)
            ? (float) $mp->percentage
            : 89.0;

        return $pct / 100;
    }

    private function normSku(string $sku): string
    {
        return strtoupper(trim($sku));
    }
}
