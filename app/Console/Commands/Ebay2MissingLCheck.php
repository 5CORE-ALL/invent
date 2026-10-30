<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingEbayTwoController;
use App\Models\Ebay2Metric;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

/**
 * Explain the Missing L number on /listing-ebaytwo: where every SKU lands and why.
 */
class Ebay2MissingLCheck extends Command
{
    protected $signature = 'ebay2:missing-l-check {--samples=15 : SKUs to print per group}';

    protected $description = 'Break down the eBay 2 Missing L count (INV, NR, listed by metric / by saved link, stale metrics)';

    public function handle(): int
    {
        @ini_set('memory_limit', '1024M');
        $samples = max(0, (int) $this->option('samples'));
        $canon = static fn ($s) => preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $s));

        $metric = [];
        $statusCounts = [];
        $latest = null;
        $recent = 0;
        foreach (Ebay2Metric::query()->get(['sku', 'item_id', 'listing_status', 'updated_at']) as $m) {
            $metric[$canon($m->sku)] = $m;
            if (trim((string) $m->item_id) !== '') {
                $status = trim((string) $m->listing_status) !== '' ? strtolower(trim((string) $m->listing_status)) : '(blank)';
                $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
            }
            if ($m->updated_at && ($latest === null || $m->updated_at->gt($latest))) {
                $latest = $m->updated_at;
            }
            if ($m->updated_at && $m->updated_at->gt(now()->subDays(3))) {
                $recent++;
            }
        }

        $payload = app(ListingEbayTwoController::class)->getViewListingEbayTwoData(Request::create('/x'))->getData(true);
        $rows = $payload['data'] ?? [];

        $g = [
            'sku_total' => 0, 'inv_zero' => 0, 'inv_pos' => 0, 'nr' => 0, 'req' => 0,
            'listed_metric' => 0, 'listed_link_only' => 0, 'listed_other' => 0, 'missing' => 0,
            'zero_inv_req_unlisted' => 0,
        ];
        $linkOnly = [];
        $missingSkus = [];
        $zeroInv = [];
        foreach ($rows as $r) {
            $sku = trim((string) ($r['sku'] ?? ''));
            if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                continue;
            }
            $g['sku_total']++;
            $inv = (float) ($r['INV'] ?? 0);
            $nr = ($r['nr_req'] ?? 'REQ') === 'NR';
            $listed = ($r['listed'] ?? '') === 'Listed';
            if ($inv <= 0) {
                $g['inv_zero']++;
                if (! $nr && ! $listed) {
                    $g['zero_inv_req_unlisted']++;
                    $zeroInv[] = $sku;
                }

                continue;
            }
            $g['inv_pos']++;
            if ($nr) {
                $g['nr']++;

                continue;
            }
            $g['req']++;
            if (! $listed) {
                $g['missing']++;
                $missingSkus[] = $sku;

                continue;
            }
            $m = $metric[$canon($sku)] ?? null;
            $live = $m && trim((string) $m->item_id) !== ''
                && ! in_array(strtolower(trim((string) $m->listing_status)), ['inactive', 'ended', 'unsold', 'completed', 'sold', 'missing', 'not_listed'], true);
            if ($live) {
                $g['listed_metric']++;
            } elseif (! $m) {
                $g['listed_link_only']++;
                $linkOnly[] = $sku.'  '.($r['eBay_item_id'] ?? '');
            } else {
                $g['listed_other']++;
            }
        }

        $this->info('eBay 2 Missing L breakdown');
        $this->line('  CP Master SKUs (no PARENT)      : '.$g['sku_total']);
        $this->line('  Shopify INV = 0 (never counted) : '.$g['inv_zero'].'   of which REQ and not on eBay 2: '.$g['zero_inv_req_unlisted']);
        $this->line('  Shopify INV > 0                 : '.$g['inv_pos']);
        $this->line('    NR (not required)             : '.$g['nr']);
        $this->line('    REQ                           : '.$g['req']);
        $this->line('      Listed by live metric item  : '.$g['listed_metric']);
        $this->line('      Listed ONLY by a saved link : '.$g['listed_link_only'].'   (no row in ebay_2_metrics)');
        $this->line('      Listed, other               : '.$g['listed_other']);
        $this->line('      MISSING L                   : '.$g['missing'].'   <- the number on the page');
        $this->line('');
        $this->info('ebay_2_metrics rows with an item id, by listing_status:');
        arsort($statusCounts);
        foreach ($statusCounts as $status => $n) {
            $this->line(sprintf('  %5d  %s', $n, $status));
        }
        $this->line('  last metric update: '.($latest ? $latest->toDateTimeString() : 'never').' | rows updated in last 3 days: '.$recent.' of '.count($metric));

        if ($samples > 0 && $linkOnly !== []) {
            $this->line('');
            $this->info('Listed only because a buyer/seller link is saved (first '.$samples.'):');
            foreach (array_slice($linkOnly, 0, $samples) as $line) {
                $this->line('  '.$line);
            }
        }
        if ($samples > 0 && $zeroInv !== []) {
            $this->line('');
            $this->info('REQ, not on eBay 2, but Shopify INV is 0 (first '.$samples.'):');
            foreach (array_slice($zeroInv, 0, $samples) as $sku) {
                $this->line('  '.$sku);
            }
        }

        return self::SUCCESS;
    }
}
