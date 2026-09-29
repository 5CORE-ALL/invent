<?php

namespace App\Console\Commands;

use App\Models\ListingManagerProductSnapshot;
use App\Support\Marketplace\ListingManagerProductSnapshots;
use Illuminate\Console\Command;

class RefreshListingManagerProductSnapshots extends Command
{
    protected $signature = 'listing-manager:snapshot-refresh
        {--limit=120 : Max SKUs to (re)build in this run}
        {--sku= : Only this SKU}
        {--stale-hours= : Rebuild snapshots older than this many hours (default '.ListingManagerProductSnapshots::SCHEDULED_REFRESH_HOURS.')}
        {--missing-only : Only build SKUs that have no snapshot yet}
        {--sleep-ms=400 : Pause between SKUs (marketplace API rate limits)}';

    protected $description = 'Listing Manager: store the product-modal payload per SKU so /listing-manager opens from the database';

    public function handle(): int
    {
        if (! ListingManagerProductSnapshot::tableReady()) {
            $this->error('listing_manager_product_snapshots table is missing. Run php artisan migrate first.');

            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $sleepUs = max(0, (int) $this->option('sleep-ms')) * 1000;
        $onlySku = trim((string) $this->option('sku'));
        $staleHours = (int) ($this->option('stale-hours') ?: ListingManagerProductSnapshots::SCHEDULED_REFRESH_HOURS);
        $missingOnly = (bool) $this->option('missing-only');

        if ($onlySku !== '') {
            $skus = [$onlySku];
        } else {
            $skus = $this->pickSkus($limit, $staleHours, $missingOnly);
        }

        if ($skus === []) {
            $this->info('Nothing to do — every Listing Manager SKU has a fresh snapshot.');

            return self::SUCCESS;
        }

        $this->info('Building '.count($skus).' Listing Manager snapshot(s)…');
        $ok = 0;
        $failed = 0;
        foreach ($skus as $i => $sku) {
            $started = microtime(true);
            $payload = ListingManagerProductSnapshots::refresh($sku);
            $ms = (int) round((microtime(true) - $started) * 1000);
            if ($payload === null) {
                $failed++;
                $this->warn(sprintf('[%d/%d] %s: FAILED (%d ms)', $i + 1, count($skus), $sku, $ms));
            } else {
                $ok++;
                $this->line(sprintf('[%d/%d] %s: stored (%d ms)', $i + 1, count($skus), $sku, $ms));
            }
            if ($sleepUs > 0 && $i < count($skus) - 1) {
                usleep($sleepUs);
            }
        }

        $this->info("Done. stored={$ok} failed={$failed}");

        return self::SUCCESS;
    }

    /**
     * Missing snapshots first, then the oldest ones beyond the stale window.
     *
     * @return list<string>
     */
    private function pickSkus(int $limit, int $staleHours, bool $missingOnly): array
    {
        $candidates = ListingManagerProductSnapshots::candidateSkus();
        if ($candidates === []) {
            return [];
        }

        $existing = ListingManagerProductSnapshot::query()
            ->whereIn('sku', $candidates)
            ->get(['sku', 'built_at', 'last_error', 'updated_at'])
            ->keyBy('sku');

        $missing = [];
        $stale = [];
        $cutoff = now()->subHours(max(1, $staleHours));
        foreach ($candidates as $sku) {
            $row = $existing->get($sku);
            if (! $row || $row->built_at === null) {
                // Failed builds are retried after the stale window, not on every run.
                if ($row && $row->last_error && $row->updated_at && $row->updated_at->gt($cutoff)) {
                    continue;
                }
                $missing[] = $sku;
            } elseif (! $missingOnly && $row->built_at->lt($cutoff)) {
                $stale[$sku] = $row->built_at->getTimestamp();
            }
        }

        asort($stale);
        $picked = array_merge($missing, array_keys($stale));

        return array_slice(array_values($picked), 0, $limit);
    }
}
