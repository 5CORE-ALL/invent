<?php

namespace App\Console\Commands;

use App\Services\Lqs\MiraklLqsApiSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SyncMiraklLqsFromApi extends Command
{
    protected $signature = 'lqs:sync-mirakl
        {--channel=bestbuy : bestbuy | macy | purchasingpower}
        {--sku= : Score only this SKU}
        {--dry-run : Fetch and score but do not save}';

    protected $description = 'Score Mirakl marketplace listings (Best Buy, Macy, Purchasing Power; LQS 0-100) from the live offer feed (title, description, brand, category, price/MSRP, stock, lead time) plus bullets/images on record.';

    public function handle(MiraklLqsApiSyncService $sync): int
    {
        $slug = (string) $this->option('channel');
        if (! MiraklLqsApiSyncService::supports($slug)) {
            $this->error('Unknown channel "'.$slug.'". Use: '.implode(', ', MiraklLqsApiSyncService::slugs()));

            return self::FAILURE;
        }
        $label = MiraklLqsApiSyncService::label($slug);

        if (! $sync->isConfigured($slug)) {
            $this->error($label.' MCM API is not configured (…_MCM_API_KEY / …_MCM_BASE_URL).');

            return self::FAILURE;
        }
        if (! Schema::hasTable('lqs_marketplace_scores')) {
            $this->error('Required table lqs_marketplace_scores is missing.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');

        if ($sku = trim((string) $this->option('sku'))) {
            return $this->scoreOne($sync, $slug, $sku, $dry);
        }

        // One bulk run at a time (page button + nightly schedule share this lock).
        $lock = Cache::lock(MiraklLqsApiSyncService::lockKey($slug), 1800);
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

    private function runBulk(MiraklLqsApiSyncService $sync, string $slug, bool $dry): int
    {
        $status = [
            'running' => true, 'total' => null, 'done' => 0, 'failed' => 0,
            'started_at' => now()->toDateTimeString(), 'finished_at' => null, 'message' => 'Fetching offers…',
        ];
        $this->publish($slug, $status);

        try {
            $offers = $sync->fetchAllOffers($slug, function (int $loaded, int $total) use (&$status, $slug) {
                $status['message'] = "Fetching offers… {$loaded}/{$total}";
                $this->publish($slug, $status);
            });
        } catch (\Throwable $e) {
            $status['running'] = false;
            $status['finished_at'] = now()->toDateTimeString();
            $status['message'] = 'Stopped: '.$e->getMessage();
            $this->publish($slug, $status);
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $total = count($offers);
        $this->info('Scoring '.$total.' '.MiraklLqsApiSyncService::label($slug).' offer(s)'.($dry ? ' (dry run)' : '').'…');
        $status['total'] = $total;
        $status['message'] = null;
        $this->publish($slug, $status);

        $ok = 0;
        $failed = 0;
        $sum = 0.0;
        foreach ($offers as $sku => $offer) {
            try {
                $score = $dry ? $sync->assess($offer, $slug)['score'] : $sync->saveOffer($offer, $slug)['lqs'];
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
                $this->publish($slug, $status);
            }
        }

        $status['running'] = false;
        $status['done'] = $ok;
        $status['failed'] = $failed;
        $status['finished_at'] = now()->toDateTimeString();
        $status['message'] = "Scored {$ok}, failed {$failed}".($ok ? sprintf(', average %.1f.', $sum / $ok) : '.');
        $this->publish($slug, $status);

        $this->info('Done. '.$status['message']);

        return $ok === 0 && $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** Progress the page polls (see HandlesMiraklLqsApi::apiSyncStatus). */
    private function publish(string $slug, array $status): void
    {
        $status['updated_at'] = time();
        Cache::put(MiraklLqsApiSyncService::statusKey($slug), $status, 86400);
    }

    private function scoreOne(MiraklLqsApiSyncService $sync, string $slug, string $sku, bool $dry): int
    {
        try {
            if ($dry) {
                $offer = $sync->fetchOffer($sku, $slug);
                if ($offer === null) {
                    throw new \RuntimeException('SKU '.$sku.' has no offer on '.MiraklLqsApiSyncService::label($slug).' (OF21).');
                }
                $res = $sync->assess($offer, $slug);
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
