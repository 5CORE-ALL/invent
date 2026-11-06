<?php

namespace App\Console\Commands;

use App\Services\Lqs\FaireLqsApiSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SyncFaireLqsFromApi extends Command
{
    protected $signature = 'lqs:sync-faire
        {--sku= : Score only this SKU}
        {--limit=0 : Only score the first N Faire products}
        {--dry-run : Fetch and score but do not save}';

    protected $description = 'Score Faire listings (LQS 0-100) from the live Faire product API (name, description, images, variants: price, stock, GTIN, size, state).';

    public function handle(FaireLqsApiSyncService $sync): int
    {
        if (! $sync->isConfigured()) {
            $this->error('Faire API is not configured (FAIRE_ACCESS_TOKEN) or faire_metric / product_master is missing.');

            return self::FAILURE;
        }
        if (! Schema::hasTable('lqs_marketplace_scores')) {
            $this->error('Required table lqs_marketplace_scores is missing.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');

        if ($sku = trim((string) $this->option('sku'))) {
            return $this->scoreOne($sync, $sku, $dry);
        }

        // One bulk run at a time (page button + nightly schedule share this lock).
        $lock = Cache::lock(FaireLqsApiSyncService::LOCK_KEY, 3600);
        if (! $lock->get()) {
            $this->warn('Another Faire LQS sync is already running. Skipping.');

            return self::SUCCESS;
        }

        try {
            return $this->runBulk($sync, $dry, (int) $this->option('limit'));
        } finally {
            $lock->release();
        }
    }

    private function runBulk(FaireLqsApiSyncService $sync, bool $dry, int $limit): int
    {
        $status = [
            'running' => true, 'total' => null, 'done' => 0, 'failed' => 0,
            'started_at' => now()->toDateTimeString(), 'finished_at' => null, 'message' => 'Loading Faire listings…',
        ];
        $this->publish($status);

        $groups = $sync->productGroups();
        if ($limit > 0) {
            $groups = array_slice($groups, 0, $limit, true);
        }
        $total = array_sum(array_map('count', $groups));
        $status['total'] = $total;
        $status['message'] = null;
        $this->publish($status);
        $this->info("Scoring {$total} SKU(s) in ".count($groups).' Faire product(s)'.($dry ? ' (dry run)' : '').'…');

        $ok = 0;
        $failed = 0;
        $sum = 0.0;
        $n = 0;
        foreach ($groups as $productId => $skus) {
            if ($n++ > 0) {
                usleep(150_000); // be gentle with Faire's rate limit
            }
            try {
                $contexts = $sync->contextsFromProduct($sync->fetchProduct($productId), $skus);
            } catch (\Throwable $e) {
                $failed += count($skus);
                $this->warn('  '.$productId.': '.$e->getMessage());
                $this->tick($status, $ok, $failed);

                continue;
            }

            foreach ($skus as $key => $sku) {
                if (! isset($contexts[$key])) {
                    $failed++;
                    $this->warn('  '.$sku.': not a variant of Faire product '.$productId);

                    continue;
                }
                try {
                    $score = $dry ? $sync->assess($contexts[$key])['score'] : $sync->saveListing($contexts[$key])['lqs'];
                    $ok++;
                    $sum += $score;
                    $this->line(sprintf('  %-34s %5.1f', mb_substr($key, 0, 34), $score));
                } catch (\Throwable $e) {
                    $failed++;
                    $this->warn('  '.$sku.': '.$e->getMessage());
                }
            }
            $this->tick($status, $ok, $failed);
        }

        $status['running'] = false;
        $status['done'] = $ok;
        $status['failed'] = $failed;
        $status['finished_at'] = now()->toDateTimeString();
        $status['message'] = "Scored {$ok}, failed {$failed}".($ok ? sprintf(', average %.1f.', $sum / $ok) : '.');
        $this->publish($status);

        $this->info('Done. '.$status['message']);

        return $ok === 0 && $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function tick(array &$status, int $ok, int $failed): void
    {
        $status['done'] = $ok;
        $status['failed'] = $failed;
        $this->publish($status);
    }

    /** Progress the page polls (see HandlesFaireLqsApi::apiSyncStatus). */
    private function publish(array $status): void
    {
        $status['updated_at'] = time();
        Cache::put(FaireLqsApiSyncService::STATUS_KEY, $status, 86400);
    }

    private function scoreOne(FaireLqsApiSyncService $sync, string $sku, bool $dry): int
    {
        try {
            if ($dry) {
                $res = $sync->assess($sync->contextForSku($sku));
                $score = $res['score'];
            } else {
                $res = $sync->syncSku($sku);
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
