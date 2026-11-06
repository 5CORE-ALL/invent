<?php

namespace App\Console\Commands;

use App\Services\Lqs\TopdawgLqsApiSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SyncTopdawgLqsFromApi extends Command
{
    protected $signature = 'lqs:sync-topdawg
        {--sku= : Score only this SKU}
        {--dry-run : Score but do not save}';

    protected $description = 'Score TopDawg listings (LQS 0-100) from the synced TopDawg products (title, price, MSRP, stock, TDID, state) plus pushed title/description/bullets.';

    public function handle(TopdawgLqsApiSyncService $sync): int
    {
        if (! $sync->isConfigured() || ! Schema::hasTable('lqs_marketplace_scores')) {
            $this->error('Required tables (product_master, lqs_marketplace_scores) are missing.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');

        if ($sku = trim((string) $this->option('sku'))) {
            return $this->scoreOne($sync, $sku, $dry);
        }

        // One bulk run at a time (page button + nightly schedule share this lock).
        $lock = Cache::lock(TopdawgLqsApiSyncService::LOCK_KEY, 1800);
        if (! $lock->get()) {
            $this->warn('Another TopDawg LQS sync is already running. Skipping.');

            return self::SUCCESS;
        }

        try {
            return $this->runBulk($sync, $dry);
        } finally {
            $lock->release();
        }
    }

    private function runBulk(TopdawgLqsApiSyncService $sync, bool $dry): int
    {
        $status = [
            'running' => true, 'total' => null, 'done' => 0, 'failed' => 0,
            'started_at' => now()->toDateTimeString(), 'finished_at' => null, 'message' => 'Loading TopDawg listings…',
        ];
        $this->publish($status);

        try {
            $contexts = $sync->listedContexts();
        } catch (\Throwable $e) {
            $status['running'] = false;
            $status['finished_at'] = now()->toDateTimeString();
            $status['message'] = 'Stopped: '.$e->getMessage();
            $this->publish($status);
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $total = count($contexts);
        $this->info('Scoring '.$total.' TopDawg listing(s)'.($dry ? ' (dry run)' : '').'…');
        $status['total'] = $total;
        $status['message'] = null;
        $this->publish($status);

        $ok = 0;
        $failed = 0;
        $sum = 0.0;
        foreach ($contexts as $sku => $context) {
            try {
                $score = $dry ? $sync->assess($context)['score'] : $sync->saveListing($context)['lqs'];
                $ok++;
                $sum += $score;
                $this->line(sprintf('  %-34s %5.1f', mb_substr($sku, 0, 34), $score));
            } catch (\Throwable $e) {
                $failed++;
                $this->warn('  '.$sku.': '.$e->getMessage());
            }
            if (($ok + $failed) % 25 === 0) {
                $status['done'] = $ok;
                $status['failed'] = $failed;
                $this->publish($status);
            }
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

    /** Progress the page polls (see HandlesTopdawgLqsApi::apiSyncStatus). */
    private function publish(array $status): void
    {
        $status['updated_at'] = time();
        Cache::put(TopdawgLqsApiSyncService::STATUS_KEY, $status, 86400);
    }

    private function scoreOne(TopdawgLqsApiSyncService $sync, string $sku, bool $dry): int
    {
        try {
            if ($dry) {
                $context = $sync->listedContexts([$sku])[TopdawgLqsApiSyncService::normalizeSku($sku)] ?? null;
                if ($context === null) {
                    throw new \RuntimeException('SKU '.$sku.' has not been synced from TopDawg yet.');
                }
                $res = $sync->assess($context);
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
