<?php

namespace App\Console\Commands;

use App\Models\LqsMarketplaceScore;
use App\Services\Lqs\TemuLqsApiSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SyncTemuLqsFromApi extends Command
{
    protected $signature = 'lqs:sync-temu
        {--channel=temu : Temu account to score: temu or temu2}
        {--sku= : Score only the listing behind this SKU}
        {--limit=300 : Max SKUs per run}
        {--stale-hours=24 : Skip SKUs scored within this many hours (0 = rescore all)}
        {--sleep-ms=300 : Pause between Temu calls}
        {--dry-run : Fetch and score but do not save}';

    protected $description = 'Score Temu / Temu 2 listings (LQS 0-100) from live Temu API data (title, photos, bullets, description, attributes, shipping).';

    public function handle(TemuLqsApiSyncService $sync): int
    {
        $slug = strtolower(trim((string) $this->option('channel')));
        if (! TemuLqsApiSyncService::supports($slug)) {
            $this->error('Unknown --channel "'.$slug.'". Use: '.implode(', ', TemuLqsApiSyncService::slugs()).'.');

            return self::FAILURE;
        }
        $label = TemuLqsApiSyncService::label($slug);

        if (! $sync->isConfigured($slug)) {
            $this->error($label.' API is not configured.');

            return self::FAILURE;
        }
        if (! Schema::hasTable(TemuLqsApiSyncService::metricsTable($slug)) || ! Schema::hasTable('lqs_marketplace_scores')) {
            $this->error('Required tables are missing.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');

        if ($sku = trim((string) $this->option('sku'))) {
            return $this->scoreOne($sync, $slug, $sku, $dry);
        }

        // One bulk run at a time (page button + nightly schedule share this lock).
        $lock = Cache::lock(TemuLqsApiSyncService::lockKey($slug), 7200);
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

    private function runBulk(TemuLqsApiSyncService $sync, string $slug, bool $dry): int
    {
        $metricModel = TemuLqsApiSyncService::metricsModel($slug);
        $limit = max(1, (int) $this->option('limit'));
        $staleHours = max(0, (int) $this->option('stale-hours'));
        $sleepUs = max(0, (int) $this->option('sleep-ms')) * 1000;

        // Latest temu_metrics row per SKU that has a goods id.
        $rows = $metricModel::query()
            ->whereNotNull('goods_id')->where('goods_id', '!=', '')
            ->whereNotNull('sku')->where('sku', '!=', '')
            ->whereRaw('UPPER(sku) NOT LIKE ?', ['%PARENT%'])
            ->orderByDesc('id')
            ->get(['id', 'sku', 'goods_id'])
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
        $scored = LqsMarketplaceScore::query()
            ->where('marketplace', $slug)
            ->whereNotNull('lqs')->where('lqs', '>', 0)
            ->pluck('sku')
            ->mapWithKeys(fn ($s) => [strtoupper(trim($s)) => true])
            ->all();

        // Never-scored SKUs first, so a capped run always makes visible progress.
        $todo = $rows
            ->reject(fn ($r) => isset($recent[strtoupper(trim($r->sku))]))
            ->sortBy(fn ($r) => isset($scored[strtoupper(trim($r->sku))]) ? 1 : 0)
            ->values()
            ->take($limit);

        // Variations share one goods_id: one API call per goods, saved for all of its SKUs.
        $byGoods = $todo->groupBy(fn ($r) => (string) $r->goods_id);

        $total = $todo->count();
        $this->info('Scoring '.$total.' of '.$rows->count().' '.TemuLqsApiSyncService::label($slug).' SKU(s) across '.$byGoods->count().' listing(s)'.($dry ? ' (dry run)' : '').'…');

        $status = [
            'running' => true, 'total' => $total, 'done' => 0, 'failed' => 0,
            'started_at' => now()->toDateTimeString(), 'finished_at' => null, 'message' => null,
        ];
        $this->publish($slug, $status);

        $ok = 0;       // SKUs scored
        $okGoods = 0;
        $failed = 0;   // SKUs failed
        $failedGoods = 0;
        $sum = 0.0;
        $aborted = false;
        foreach ($byGoods as $goodsId => $group) {
            $skus = $group->pluck('sku')->all();
            try {
                if ($dry) {
                    $score = $sync->assess((string) $goodsId, $slug)['score'];
                } else {
                    $score = $sync->syncGoods((string) $goodsId, $skus, $slug)['lqs'];
                }
                $okGoods++;
                $ok += count($skus);
                $sum += $score;
                $this->line(sprintf('  %-16s %5.1f  (%d SKU%s: %s)', $goodsId, $score, count($skus), count($skus) === 1 ? '' : 's', implode(', ', array_slice($skus, 0, 3)).(count($skus) > 3 ? ', …' : '')));
            } catch (\Throwable $e) {
                $failedGoods++;
                $failed += count($skus);
                $this->warn('  '.$goodsId.': '.$e->getMessage());
                // Stop early if Temu is throttling / auth or IP allowlist is broken.
                if ($failedGoods >= 10 && $okGoods === 0) {
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
            ?: "Scored {$ok} SKUs ({$okGoods} listings), failed {$failed}".($okGoods ? sprintf(', average %.1f.', $sum / $okGoods) : '.');
        $this->publish($slug, $status);

        $this->info('Done. '.$status['message']);

        return $aborted || ($failedGoods > 0 && $okGoods === 0) ? self::FAILURE : self::SUCCESS;
    }

    /** Progress the page polls (see HandlesTemuLqsApi::apiSyncStatus). */
    private function publish(string $slug, array $status): void
    {
        $status['updated_at'] = time();
        Cache::put(TemuLqsApiSyncService::statusKey($slug), $status, 86400);
    }

    private function scoreOne(TemuLqsApiSyncService $sync, string $slug, string $sku, bool $dry): int
    {
        try {
            if ($dry) {
                $metric = $sync->metricForSku($sku, $slug);
                $res = $sync->assess((string) ($metric->goods_id ?? ''), $slug);
                $score = $res['score'];
            } else {
                $res = $sync->syncSku($sku, $slug);
                $score = $res['lqs'];
            }
            $this->info("{$sku}: {$score}/100");
            $this->line($res['findings']."\n\n".$res['suggestions']);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
