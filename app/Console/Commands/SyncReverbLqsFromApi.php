<?php

namespace App\Console\Commands;

use App\Services\Lqs\ReverbLqsApiSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SyncReverbLqsFromApi extends Command
{
    protected $signature = 'lqs:sync-reverb
        {--sku= : Score only this SKU}
        {--dry-run : Fetch and score but do not save}
        {--raw : With --sku, print the field names of the live Reverb listing}';

    protected $description = 'Score Reverb listings (LQS 0-100) from the live seller API (title, photos, description, item details, shipping/offers/UPC, listing health).';

    public function handle(ReverbLqsApiSyncService $sync): int
    {
        if (! $sync->isConfigured()) {
            $this->error('Reverb is not configured (REVERB_CLIENT_ID / REVERB_CLIENT_SECRET or REVERB_TOKEN).');

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
        $lock = Cache::lock(ReverbLqsApiSyncService::LOCK_KEY, 1800);
        if (! $lock->get()) {
            $this->warn('Another Reverb LQS sync is already running. Skipping.');

            return self::SUCCESS;
        }

        try {
            return $this->runBulk($sync, $dry);
        } finally {
            $lock->release();
        }
    }

    private function runBulk(ReverbLqsApiSyncService $sync, bool $dry): int
    {
        $status = [
            'running' => true, 'total' => null, 'done' => 0, 'failed' => 0,
            'started_at' => now()->toDateTimeString(), 'finished_at' => null, 'message' => 'Fetching listings…',
        ];
        $this->publish($status);

        try {
            $listings = $sync->fetchAllListings(function (int $page, int $loaded) use (&$status) {
                $status['message'] = "Fetching listings… {$loaded} so far";
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

        $total = count($listings);
        $this->info('Scoring '.$total.' Reverb listing(s)'.($dry ? ' (dry run)' : '').'…');
        $status['total'] = $total;
        $status['message'] = null;
        $this->publish($status);

        $ok = 0;
        $failed = 0;
        $sum = 0.0;
        foreach ($listings as $sku => $listing) {
            try {
                $score = $dry ? $sync->assess($listing)['score'] : $sync->saveListing($listing)['lqs'];
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

    /** Progress the page polls (see HandlesReverbLqsApi::apiSyncStatus). */
    private function publish(array $status): void
    {
        $status['updated_at'] = time();
        Cache::put(ReverbLqsApiSyncService::STATUS_KEY, $status, 86400);
    }

    private function scoreOne(ReverbLqsApiSyncService $sync, string $sku, bool $dry): int
    {
        try {
            $listing = $sync->fetchListing($sku);
            if ($listing === null) {
                throw new \RuntimeException('SKU '.$sku.' was not found among your Reverb listings.');
            }
            if ($this->option('raw')) {
                $this->line('listing keys: '.json_encode(array_keys($listing)));
                $brief = array_map(fn ($v) => is_string($v) ? mb_substr($v, 0, 100) : (is_array($v) ? '['.count($v).' item(s): '.mb_substr(json_encode(array_slice($v, 0, 2)), 0, 160).']' : $v), $listing);
                unset($brief['_links']);
                $this->line('listing (shortened): '.mb_substr(json_encode($brief), 0, 3000));
            }

            $res = $dry ? $sync->assess($listing) : $sync->saveListing($listing);
            $score = $dry ? $res['score'] : $res['lqs'];
            $this->info("{$sku}: {$score}/100");
            $this->line($res['findings']."\n\n".$res['suggestions']);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
