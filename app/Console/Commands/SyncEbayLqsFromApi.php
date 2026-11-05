<?php

namespace App\Console\Commands;

use App\Models\EbayMetric;
use App\Models\LqsMarketplaceScore;
use App\Services\Lqs\EbayLqsApiSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SyncEbayLqsFromApi extends Command
{
    protected $signature = 'lqs:sync-ebay
        {--sku= : Score only this SKU}
        {--limit=300 : Max listings per run (eBay Trading API calls are rate limited)}
        {--stale-hours=24 : Skip SKUs scored within this many hours (0 = rescore all)}
        {--sleep-ms=300 : Pause between eBay calls}
        {--dry-run : Fetch and score but do not save}';

    protected $description = 'Score eBay listings (LQS 0-100) from live eBay API data (title, photos, item specifics, description, shipping/returns).';

    public function handle(EbayLqsApiSyncService $sync): int
    {
        if (! $sync->isConfigured()) {
            $this->error('eBay API is not configured.');

            return self::FAILURE;
        }
        if (! Schema::hasTable('ebay_metrics') || ! Schema::hasTable('lqs_marketplace_scores')) {
            $this->error('Required tables are missing.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');

        if ($sku = trim((string) $this->option('sku'))) {
            return $this->scoreOne($sync, $sku, $dry);
        }

        $limit = max(1, (int) $this->option('limit'));
        $staleHours = max(0, (int) $this->option('stale-hours'));
        $sleepUs = max(0, (int) $this->option('sleep-ms')) * 1000;

        // Latest ebay_metrics row per SKU that has an item id.
        $rows = EbayMetric::query()
            ->whereNotNull('item_id')->where('item_id', '!=', '')
            ->whereNotNull('sku')->where('sku', '!=', '')
            ->whereRaw('UPPER(sku) NOT LIKE ?', ['%PARENT%'])
            ->orderByDesc('id')
            ->get(['id', 'sku', 'item_id'])
            ->unique(fn ($r) => strtoupper(trim($r->sku)));

        $recent = [];
        if ($staleHours > 0) {
            $recent = LqsMarketplaceScore::query()
                ->where('marketplace', EbayLqsApiSyncService::MARKETPLACE)
                ->where('updated_at', '>=', now()->subHours($staleHours))
                ->pluck('sku')
                ->mapWithKeys(fn ($s) => [strtoupper(trim($s)) => true])
                ->all();
        }

        $todo = $rows->reject(fn ($r) => isset($recent[strtoupper(trim($r->sku))]))->take($limit);
        $this->info('Scoring '.$todo->count().' of '.$rows->count().' eBay listing(s)'.($dry ? ' (dry run)' : '').'…');

        $ok = 0;
        $failed = 0;
        $sum = 0.0;
        foreach ($todo as $row) {
            try {
                if ($dry) {
                    $res = $sync->assess((string) $row->item_id);
                    $score = $res['score'];
                } else {
                    $score = $sync->syncSku($row->sku)['lqs'];
                }
                $ok++;
                $sum += $score;
                $this->line(sprintf('  %-24s %-14s %5.1f', $row->sku, $row->item_id, $score));
            } catch (\Throwable $e) {
                $failed++;
                $this->warn('  '.$row->sku.': '.$e->getMessage());
                // Stop early if eBay is throttling / auth is broken, instead of burning the daily quota.
                if ($failed >= 10 && $ok === 0) {
                    $this->error('10 failures in a row with no successes. Aborting.');

                    return self::FAILURE;
                }
            }
            usleep($sleepUs);
        }

        $this->info("Done. Scored {$ok}, failed {$failed}".($ok ? sprintf(', average %.1f.', $sum / $ok) : '.'));

        return $failed > 0 && $ok === 0 ? self::FAILURE : self::SUCCESS;
    }

    private function scoreOne(EbayLqsApiSyncService $sync, string $sku, bool $dry): int
    {
        try {
            if ($dry) {
                $metric = $sync->metricForSku($sku);
                $res = $sync->assess((string) ($metric->item_id ?? ''));
                $score = $res['score'];
                $text = $res['findings']."\n\n".$res['suggestions'];
            } else {
                $res = $sync->syncSku($sku);
                $score = $res['lqs'];
                $text = $res['findings']."\n\n".$res['suggestions'];
            }
            $this->info("{$sku}: {$score}/100");
            $this->line($text);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
