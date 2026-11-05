<?php

namespace App\Console\Commands;

use App\Services\Lqs\DobaLqsApiSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SyncDobaLqsFromApi extends Command
{
    protected $signature = 'lqs:sync-doba
        {--sku= : Score only this SKU}
        {--dry-run : Fetch and score but do not save}
        {--raw : With --sku, print the field names of the live Doba product/SKU}';

    protected $description = 'Score Doba listings (LQS 0-100) from the live goods/detail catalog (title, description, highlights, images, pricing/stock).';

    public function handle(DobaLqsApiSyncService $sync): int
    {
        if (! $sync->isConfigured()) {
            $this->error('Doba API is not configured (DOBA_APP_KEY / DOBA_PRIVATE_KEY).');

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
        $lock = Cache::lock(DobaLqsApiSyncService::LOCK_KEY, 1800);
        if (! $lock->get()) {
            $this->warn('Another Doba LQS sync is already running. Skipping.');

            return self::SUCCESS;
        }

        try {
            return $this->runBulk($sync, $dry);
        } finally {
            $lock->release();
        }
    }

    private function runBulk(DobaLqsApiSyncService $sync, bool $dry): int
    {
        $status = [
            'running' => true, 'total' => count($sync->catalogSkus()) ?: null, 'done' => 0, 'failed' => 0,
            'started_at' => now()->toDateTimeString(), 'finished_at' => null, 'message' => 'Fetching products…',
        ];
        $this->publish($status);
        $this->info('Scoring Doba catalog'.($dry ? ' (dry run)' : '').'…');

        $ok = 0;
        $failed = 0;
        $sum = 0.0;
        $seen = [];

        try {
            $products = $sync->eachProduct(function (array $product) use ($sync, $dry, &$ok, &$failed, &$sum, &$seen, &$status) {
                foreach ((array) ($product['skus'] ?? []) as $sku) {
                    if (! is_array($sku)) {
                        continue;
                    }
                    $key = DobaLqsApiSyncService::normalizeSku((string) ($sku['skuCode'] ?? ''));
                    if ($key === '' || isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    try {
                        $score = $dry ? $sync->assess($product, $sku)['score'] : $sync->saveSku($product, $sku)['lqs'];
                        $ok++;
                        $sum += $score;
                        $this->line(sprintf('  %-34s %5.1f', mb_substr($key, 0, 34), $score));
                    } catch (\Throwable $e) {
                        $failed++;
                        $this->warn('  '.$key.': '.$e->getMessage());
                    }
                }
                $status['done'] = $ok;
                $status['failed'] = $failed;
                $status['message'] = null;
                $this->publish($status);
            });
        } catch (\Throwable $e) {
            $status['running'] = false;
            $status['done'] = $ok;
            $status['failed'] = $failed;
            $status['finished_at'] = now()->toDateTimeString();
            $status['message'] = 'Stopped: '.$e->getMessage();
            $this->publish($status);
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $status['running'] = false;
        $status['done'] = $ok;
        $status['failed'] = $failed;
        $status['finished_at'] = now()->toDateTimeString();
        $status['message'] = "Scored {$ok} SKU(s) from {$products} product(s), failed {$failed}".($ok ? sprintf(', average %.1f.', $sum / $ok) : '.');
        $this->publish($status);

        $this->info('Done. '.$status['message']);

        return $ok === 0 ? self::FAILURE : self::SUCCESS;
    }

    /** Progress the page polls (see HandlesDobaLqsApi::apiSyncStatus). */
    private function publish(array $status): void
    {
        $status['updated_at'] = time();
        Cache::put(DobaLqsApiSyncService::STATUS_KEY, $status, 86400);
    }

    private function scoreOne(DobaLqsApiSyncService $sync, string $skuCode, bool $dry): int
    {
        try {
            $found = $sync->fetchSku($skuCode);
            if ($found === null) {
                throw new \RuntimeException('SKU '.$skuCode.' was not found in the live Doba catalog.');
            }
            [$product, $sku] = $found;

            if ($this->option('raw')) {
                $this->line('product keys: '.json_encode(array_keys($product)));
                $this->line('sku keys: '.json_encode(array_keys($sku)));
                $this->line('stock keys: '.json_encode(array_keys((array) ($sku['stocks'][0] ?? []))));
                $brief = array_map(fn ($v) => is_string($v) ? mb_substr($v, 0, 120) : (is_array($v) ? '['.count($v).' item(s)]' : $v), $product);
                $this->line('product (shortened): '.mb_substr(json_encode($brief), 0, 2500));
            }

            $res = $dry ? $sync->assess($product, $sku) : $sync->saveSku($product, $sku);
            $score = $dry ? $res['score'] : $res['lqs'];
            $this->info("{$skuCode}: {$score}/100");
            $this->line($res['findings']."\n\n".$res['suggestions']);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
