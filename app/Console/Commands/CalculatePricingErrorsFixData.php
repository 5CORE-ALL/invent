<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarketPlace\PricingErrorsFixController;
use App\Models\PricingErrorsFixCalculatedData;
use App\Services\PricingErrorsFixCvrCacheBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Pre-calculate Pricing Errors Fix from /price-increase CVR bulk data
 * (NOT amazon/ebay/… pricing tabulator pages).
 *
 *   php artisan pricing-errors:calculate-data --force
 *   php artisan pricing-errors:calculate-data --sku=ABC --channel=amazon --sprice=19.99
 */
class CalculatePricingErrorsFixData extends Command
{
    protected $signature = 'pricing-errors:calculate-data
                            {--force : Rebuild even if table already has data}
                            {--channel= : Comma-separated PEF channel keys (amazon,ebay,…)}
                            {--sku= : Patch a single SKU (modal breakdown)}
                            {--sprice= : Optional new SPRICE when patching --sku}
                            {--listed-only=1 : Store only rows with price or sprice > 0}';

    protected $description = 'Calculate Pricing Errors Fix cache from /price-increase CVR (bulk)';

    public function handle(): int
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');

        $sku = trim((string) $this->option('sku'));
        $channelOpt = trim((string) $this->option('channel'));

        if ($sku !== '') {
            return $this->patchSku($sku, $channelOpt);
        }

        return $this->fullRebuildFromCvr($channelOpt);
    }

    private function patchSku(string $sku, string $channelOpt): int
    {
        $listedOnly = filter_var($this->option('listed-only'), FILTER_VALIDATE_BOOLEAN);
        $channels = $channelOpt !== ''
            ? array_values(array_filter(array_map('strtolower', array_map('trim', explode(',', $channelOpt)))))
            : null;

        $this->info("Refreshing {$sku} from price-increase breakdown…");
        $builder = app(PricingErrorsFixCvrCacheBuilder::class);
        $result = $builder->build($channels, [$sku], $listedOnly);
        $rows = $result['rows'] ?? [];

        if ($rows === []) {
            $this->warn('No CVR rows for '.$sku);
            if (! empty($result['errors'][$sku])) {
                $this->error($result['errors'][$sku]);
            }

            return self::FAILURE;
        }

        $spriceOpt = $this->option('sprice');
        $spriceOverride = ($spriceOpt !== null && $spriceOpt !== '') ? (float) $spriceOpt : null;
        $now = now();
        $n = 0;

        foreach ($rows as $r) {
            if ($spriceOverride !== null && $spriceOverride > 0
                && ($channels === null || in_array($r['pull_key'], $channels, true) || in_array($r['marketplace'], $channels, true))) {
                $r['sprice'] = round($spriceOverride, 2);
                $ctrl = app(PricingErrorsFixController::class);
                $calc = $ctrl->publicComputeMetrics(
                    $r['price'],
                    $r['sprice'],
                    (float) $r['lp'],
                    (float) $r['ship'],
                    (float) $r['margin'],
                    (float) $r['ads_pct']
                );
                $r['sroi'] = $calc['sroi'];
                $r['sgpft'] = $calc['sgpft'];
                $r['snroi'] = $calc['snroi'];
                $r['snpft'] = $calc['snpft'];
            }

            PricingErrorsFixCalculatedData::query()->updateOrCreate(
                ['sku' => $r['sku'], 'marketplace' => $r['marketplace']],
                [
                    'pull_key' => $r['pull_key'] ?? $r['marketplace'],
                    'channel_label' => $r['channel'] ?? null,
                    'parent' => $r['parent'] ?? null,
                    'image_path' => is_string($r['image_path'] ?? null) ? substr($r['image_path'], 0, 512) : null,
                    'inv' => $r['inv'] ?? 0,
                    'ov_l30' => $r['ov_l30'] ?? 0,
                    'l30' => $r['l30'] ?? 0,
                    'dil' => $r['dil'],
                    'price' => $r['price'],
                    'groi' => $r['groi'],
                    'nroi' => $r['nroi'],
                    'gpft' => $r['gpft'],
                    'npft' => $r['npft'],
                    'sprice' => $r['sprice'],
                    'sroi' => $r['sroi'],
                    'sgpft' => $r['sgpft'],
                    'snroi' => $r['snroi'],
                    'snpft' => $r['snpft'],
                    'success' => is_scalar($r['success'] ?? null) ? (string) $r['success'] : null,
                    'lp' => $r['lp'] ?? 0,
                    'ship' => $r['ship'] ?? 0,
                    'margin' => $r['margin'] ?? 0,
                    'ads_pct' => $r['ads_pct'] ?? 0,
                    'calculated_at' => $now,
                ]
            );
            $n++;
            $this->info("  ✓ {$r['marketplace']} price={$r['price']} sprice={$r['sprice']} groi={$r['groi']} gpft={$r['gpft']}");
        }

        $this->info("Patched {$n} marketplace row(s) for {$sku}");
        PricingErrorsFixController::forgetLowGroiSkuSidebarCountCache();

        return self::SUCCESS;
    }

    private function fullRebuildFromCvr(string $channelOpt): int
    {
        if (! $this->option('force') && PricingErrorsFixCalculatedData::hasData() && $channelOpt === '') {
            $last = PricingErrorsFixCalculatedData::lastCalculatedAt();
            $this->warn("Cache already has data (last: {$last}). Use --force to rebuild.");

            return self::SUCCESS;
        }

        $channels = $channelOpt !== ''
            ? array_values(array_filter(array_map('strtolower', array_map('trim', explode(',', $channelOpt)))))
            : null;

        $listedOnly = filter_var($this->option('listed-only'), FILTER_VALIDATE_BOOLEAN);
        $this->info('Building Pricing Errors Fix from /price-increase CVR bulk (not channel pricing pages)…');
        if ($channels) {
            $this->info('Channels filter: '.implode(', ', $channels));
        }

        $builder = app(PricingErrorsFixCvrCacheBuilder::class);
        $bar = null;

        $result = $builder->build(
            $channels,
            null,
            $listedOnly,
            function (int $done, int $total, string $sku) use (&$bar) {
                if ($bar === null) {
                    $bar = $this->output->createProgressBar(max(1, $total));
                    $bar->start();
                }
                $bar->setProgress($done);
            }
        );

        if ($bar) {
            $bar->finish();
            $this->newLine();
        }

        $rows = $result['rows'] ?? [];
        $errors = $result['errors'] ?? [];
        $this->info('Built '.count($rows).' rows from CVR. Writing cache…');

        if ($rows === [] && ! empty($errors['_cvr'])) {
            $this->error($errors['_cvr']);

            return self::FAILURE;
        }

        $calculatedAt = now();
        $total = 0;

        try {
            if ($channels === null || $channels === []) {
                PricingErrorsFixCalculatedData::query()->delete();
                $this->info('Cleared old cache.');
            } else {
                $ctrl = app(PricingErrorsFixController::class);
                $reg = $ctrl->publicChannelRegistry();
                $mps = [];
                foreach ($channels as $key) {
                    $mps[] = $reg[$key]['marketplace'] ?? $key;
                }
                PricingErrorsFixCalculatedData::query()->whereIn('marketplace', array_unique($mps))->delete();
                PricingErrorsFixCalculatedData::query()->whereIn('pull_key', $channels)->delete();
            }

            foreach (array_chunk($rows, 200) as $chunk) {
                $insert = [];
                foreach ($chunk as $r) {
                    $insert[] = [
                        'sku' => $r['sku'],
                        'marketplace' => $r['marketplace'],
                        'pull_key' => $r['pull_key'] ?? $r['marketplace'],
                        'channel_label' => $r['channel'] ?? null,
                        'parent' => $r['parent'] ?? null,
                        'image_path' => is_string($r['image_path'] ?? null) ? substr($r['image_path'], 0, 512) : null,
                        'inv' => $r['inv'] ?? 0,
                        'ov_l30' => $r['ov_l30'] ?? 0,
                        'l30' => $r['l30'] ?? 0,
                        'dil' => $r['dil'],
                        'price' => $r['price'],
                        'groi' => $r['groi'],
                        'nroi' => $r['nroi'],
                        'gpft' => $r['gpft'],
                        'npft' => $r['npft'],
                        'sprice' => $r['sprice'],
                        'sroi' => $r['sroi'],
                        'sgpft' => $r['sgpft'],
                        'snroi' => $r['snroi'],
                        'snpft' => $r['snpft'],
                        'success' => is_scalar($r['success'] ?? null) ? (string) $r['success'] : null,
                        'lp' => $r['lp'] ?? 0,
                        'ship' => $r['ship'] ?? 0,
                        'margin' => $r['margin'] ?? 0,
                        'ads_pct' => $r['ads_pct'] ?? 0,
                        'calculated_at' => $calculatedAt,
                        'created_at' => $calculatedAt,
                        'updated_at' => $calculatedAt,
                    ];
                }
                // Upsert on unique (sku, marketplace) — safer than raw insert if id/AI glitches
                PricingErrorsFixCalculatedData::query()->upsert(
                    $insert,
                    ['sku', 'marketplace'],
                    [
                        'pull_key', 'channel_label', 'parent', 'image_path',
                        'inv', 'ov_l30', 'l30', 'dil', 'price', 'groi', 'nroi', 'gpft', 'npft',
                        'sprice', 'sroi', 'sgpft', 'snroi', 'snpft', 'success',
                        'lp', 'ship', 'margin', 'ads_pct', 'calculated_at', 'updated_at',
                    ]
                );
                $total += count($insert);
            }
        } catch (\Throwable $e) {
            $this->error('Write failed: '.$e->getMessage());
            Log::error('pricing-errors:calculate-data write failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Done. Stored {$total} rows (source: /price-increase CVR).");
        if ($errors) {
            $this->warn('SKU/errors: '.count($errors).' (see log)');
        }
        PricingErrorsFixController::forgetLowGroiSkuSidebarCountCache();

        return self::SUCCESS;
    }
}
