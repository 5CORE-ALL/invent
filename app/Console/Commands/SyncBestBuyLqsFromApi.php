<?php

namespace App\Console\Commands;

use App\Services\Lqs\BestBuyLqsApiSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SyncBestBuyLqsFromApi extends Command
{
    protected $signature = 'lqs:sync-bestbuy
        {--sku= : Score only this SKU}
        {--dry-run : Fetch and score but do not save}';

    protected $description = 'Score Best Buy listings (LQS 0-100) from the live Mirakl offer feed (title, description, brand, category, price/MSRP, stock, lead time) plus bullets/images on record.';

    public function handle(BestBuyLqsApiSyncService $sync): int
    {
        if (! $sync->isConfigured()) {
            $this->error('Best Buy MCM API is not configured (BESTBUY_MCM_API_KEY / BESTBUY_MCM_BASE_URL).');

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
        $lock = Cache::lock(BestBuyLqsApiSyncService::LOCK_KEY, 1800);
        if (! $lock->get()) {
            $this->warn('Another Best Buy LQS sync is already running. Skipping.');

            return self::SUCCESS;
        }

        try {
            return $this->runBulk($sync, $dry);
        } finally {
            $lock->release();
        }
    }

    private function runBulk(BestBuyLqsApiSyncService $sync, bool $dry): int
    {
        $status = [
            'running' => true, 'total' => null, 'done' => 0, 'failed' => 0,
            'started_at' => now()->toDateTimeString(), 'finished_at' => null, 'message' => 'Fetching offers…',
        ];
        $this->publish($status);

        try {
            $offers = $sync->fetchAllOffers(function (int $loaded, int $total) use (&$status) {
                $status['message'] = "Fetching offers… {$loaded}/{$total}";
                $this->publish($status);
            });
        } catch (\Throwable $e) {
            $status['running'] = false;
            $status['finished_at'] = now()->toDateTimeString();
            $status['message'] = 'Stopped: '.$e->getMessage();
            $this->publish($status);
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $total = count($offers);
        $this->info('Scoring '.$total.' Best Buy offer(s)'.($dry ? ' (dry run)' : '').'…');
        $status['total'] = $total;
        $status['message'] = null;
        $this->publish($status);

        $ok = 0;
        $failed = 0;
        $sum = 0.0;
        foreach ($offers as $sku => $offer) {
            try {
                $score = $dry ? $sync->assess($offer)['score'] : $sync->saveOffer($offer)['lqs'];
                $ok++;
                $sum += $score;
                $this->line(sprintf('  %-34s %5.1f', mb_substr((string) ($offer['shop_sku'] ?? $sku), 0, 34), $score));
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

    /** Progress the page polls (see HandlesBestBuyLqsApi::apiSyncStatus). */
    private function publish(array $status): void
    {
        $status['updated_at'] = time();
        Cache::put(BestBuyLqsApiSyncService::STATUS_KEY, $status, 86400);
    }

    private function scoreOne(BestBuyLqsApiSyncService $sync, string $sku, bool $dry): int
    {
        try {
            if ($dry) {
                $offer = $sync->fetchOffer($sku);
                if ($offer === null) {
                    throw new \RuntimeException('SKU '.$sku.' has no offer on Best Buy (OF21).');
                }
                $res = $sync->assess($offer);
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
