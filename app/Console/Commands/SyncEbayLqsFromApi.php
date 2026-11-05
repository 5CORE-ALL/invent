<?php

namespace App\Console\Commands;

use App\Models\LqsMarketplaceScore;
use App\Services\Lqs\EbayLqsApiSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SyncEbayLqsFromApi extends Command
{
    protected $signature = 'lqs:sync-ebay
        {--channel=ebay : eBay account to score: ebay, ebay2 or ebay3}
        {--sku= : Score only this SKU}
        {--limit=300 : Max listings per run (eBay Trading API calls are rate limited)}
        {--stale-hours=24 : Skip SKUs scored within this many hours (0 = rescore all)}
        {--sleep-ms=300 : Pause between eBay calls}
        {--dry-run : Fetch and score but do not save}';

    protected $description = 'Score eBay / eBay 2 / eBay 3 listings (LQS 0-100) from live eBay API data (title, photos, item specifics, description, shipping/returns).';

    public function handle(EbayLqsApiSyncService $sync): int
    {
        $slug = strtolower(trim((string) $this->option('channel')));
        if (! EbayLqsApiSyncService::supports($slug)) {
            $this->error('Unknown --channel "'.$slug.'". Use: '.implode(', ', EbayLqsApiSyncService::slugs()).'.');

            return self::FAILURE;
        }
        $label = EbayLqsApiSyncService::label($slug);

        if (! $sync->isConfigured($slug)) {
            $this->error($label.' API is not configured.');

            return self::FAILURE;
        }
        if (! Schema::hasTable(EbayLqsApiSyncService::metricsTable($slug)) || ! Schema::hasTable('lqs_marketplace_scores')) {
            $this->error('Required tables are missing.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');

        if ($sku = trim((string) $this->option('sku'))) {
            return $this->scoreOne($sync, $slug, $sku, $dry);
        }

        // One bulk run at a time (page button + nightly schedule share this lock).
        $lock = Cache::lock(EbayLqsApiSyncService::lockKey($slug), 7200);
        if (! $lock->get()) {
            $this->warn('Another '.$label.' LQS sync is already running. Skipping.');

            return self::SUCCESS;
        }

        try {
            return $this->runBulk($sync, $slug, $dry);
        } finally {
            $lock->release();
        }
    }

    private function runBulk(EbayLqsApiSyncService $sync, string $slug, bool $dry): int
    {
        $metricModel = EbayLqsApiSyncService::metricsModel($slug);
        $limit = max(1, (int) $this->option('limit'));
        $staleHours = max(0, (int) $this->option('stale-hours'));
        $sleepUs = max(0, (int) $this->option('sleep-ms')) * 1000;

        // Latest ebay_metrics row per SKU that has an item id.
        $rows = $metricModel::query()
            ->whereNotNull('item_id')->where('item_id', '!=', '')
            ->whereNotNull('sku')->where('sku', '!=', '')
            ->whereRaw('UPPER(sku) NOT LIKE ?', ['%PARENT%'])
            ->orderByDesc('id')
            ->get(['id', 'sku', 'item_id'])
            ->unique(fn ($r) => strtoupper(trim($r->sku)));

        $recent = [];
        if ($staleHours > 0) {
            $recent = LqsMarketplaceScore::query()
                ->where('marketplace', $slug)
                ->where('updated_at', '>=', now()->subHours($staleHours))
                ->pluck('sku')
                ->mapWithKeys(fn ($s) => [strtoupper(trim($s)) => true])
                ->all();
        }

        // SKUs that have never been scored come first, so a capped run always makes visible progress.
        $scored = LqsMarketplaceScore::query()
            ->where('marketplace', $slug)
            ->whereNotNull('lqs')->where('lqs', '>', 0)
            ->pluck('sku')
            ->mapWithKeys(fn ($s) => [strtoupper(trim($s)) => true])
            ->all();

        $todo = $rows
            ->reject(fn ($r) => isset($recent[strtoupper(trim($r->sku))]))
            ->sortBy(fn ($r) => isset($scored[strtoupper(trim($r->sku))]) ? 1 : 0)
            ->values()
            ->take($limit);

        $total = $todo->count();
        $this->info('Scoring '.$total.' of '.$rows->count().' '.EbayLqsApiSyncService::label($slug).' listing(s)'.($dry ? ' (dry run)' : '').'…');

        $status = [
            'running' => true,
            'total' => $total,
            'done' => 0,
            'failed' => 0,
            'started_at' => now()->toDateTimeString(),
            'finished_at' => null,
            'message' => null,
        ];
        $this->publish($slug, $status);

        $ok = 0;
        $failed = 0;
        $sum = 0.0;
        $aborted = false;
        foreach ($todo as $row) {
            try {
                if ($dry) {
                    $res = $sync->assess((string) $row->item_id, $slug);
                    $score = $res['score'];
                } else {
                    $score = $sync->syncSku($row->sku, $slug)['lqs'];
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
                    $status['message'] = 'Stopped: 10 failures in a row. Last error: '.$e->getMessage();
                    $aborted = true;
                }
            }

            $status['done'] = $ok;
            $status['failed'] = $failed;
            $this->publish($slug, $status);

            if ($aborted) {
                break;
            }
            usleep($sleepUs);
        }

        $status['running'] = false;
        $status['finished_at'] = now()->toDateTimeString();
        $status['message'] = $status['message']
            ?: "Scored {$ok}, failed {$failed}".($ok ? sprintf(', average %.1f.', $sum / $ok) : '.');
        $this->publish($slug, $status);

        $this->info('Done. '.$status['message']);

        return $aborted || ($failed > 0 && $ok === 0) ? self::FAILURE : self::SUCCESS;
    }

    /** Progress the page polls (see HandlesEbayLqsApi::apiSyncStatus). */
    private function publish(string $slug, array $status): void
    {
        $status['updated_at'] = time();
        Cache::put(EbayLqsApiSyncService::statusKey($slug), $status, 86400);
    }

    private function scoreOne(EbayLqsApiSyncService $sync, string $slug, string $sku, bool $dry): int
    {
        try {
            if ($dry) {
                $metric = $sync->metricForSku($sku, $slug);
                $res = $sync->assess((string) ($metric->item_id ?? ''), $slug);
                $score = $res['score'];
                $text = $res['findings']."\n\n".$res['suggestions'];
            } else {
                $res = $sync->syncSku($sku, $slug);
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
