<?php

namespace App\Console\Commands;

use App\Models\EbayMetric;
use App\Support\Marketplace\AutomatedListingPage;
use Illuminate\Console\Command;

/**
 * Compare the eBay 1 page Missing L list with a reference SKU list (one SKU per line).
 * Prints, for every reference SKU the page does not call Missing L, the reason.
 */
class EbayMissingLCompare extends Command
{
    protected $signature = 'ebay:missing-l-compare {file=database/data/ebay_missing_l_expected.txt}';

    protected $description = 'Compare eBay 1 Missing L (page logic) against a reference SKU list';

    public function handle(): int
    {
        @ini_set('memory_limit', '1024M');
        $canon = fn ($s) => preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $s));
        $path = base_path($this->argument('file'));
        if (! is_file($path)) {
            $this->error('File not found: '.$path);

            return self::FAILURE;
        }
        $expected = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line !== '') {
                $expected[$canon($line)] = $line;
            }
        }

        $metric = [];
        foreach (EbayMetric::query()->get(['sku', 'item_id', 'listing_status', 'updated_at']) as $m) {
            $metric[$canon($m->sku)] = $m;
        }

        $rows = [];
        $pageMissing = [];
        foreach (AutomatedListingPage::rows('ebay') as $r) {
            $sku = trim((string) $r->sku);
            if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                continue;
            }
            $k = $canon($sku);
            $rows[$k] = $r;
            if ((float) $r->INV > 0 && $r->nr_req === 'REQ' && $r->listed === 'Pending') {
                $pageMissing[$k] = $sku;
            }
        }

        $both = array_intersect_key($pageMissing, $expected);
        $onlyPage = array_diff_key($pageMissing, $expected);
        $onlyRef = array_diff_key($expected, $pageMissing);
        $this->info('reference='.count($expected).' page Missing L='.count($pageMissing)
            .' in both='.count($both).' only page='.count($onlyPage).' only reference='.count($onlyRef));

        $this->line('');
        $this->info('Reference SKUs the page does NOT list as Missing L:');
        $groups = [];
        foreach ($onlyRef as $k => $sku) {
            $r = $rows[$k] ?? null;
            $m = $metric[$k] ?? null;
            if (! $r) {
                $reason = 'not in Product Master';
            } else {
                $why = [];
                if ((float) $r->INV <= 0) {
                    $why[] = 'INV=0';
                }
                if ($r->nr_req !== 'REQ') {
                    $why[] = 'nr_req='.$r->nr_req;
                }
                if ($r->listed !== 'Pending') {
                    $why[] = 'listed='.$r->listed;
                }
                $reason = implode(', ', $why).' | metric='.($m ? ($m->listing_status ?: 'null').' id='.$m->item_id : 'none');
            }
            $groups[$reason][] = $sku;
            $this->line(sprintf('  %-34s %s', $sku, $reason));
        }

        $this->line('');
        $this->info('Summary by reason:');
        ksort($groups);
        foreach ($groups as $reason => $skus) {
            $this->line(sprintf('  %3d  %s', count($skus), $reason));
        }

        $this->line('');
        $this->info('Page Missing L SKUs not in the reference ('.count($onlyPage).'):');
        foreach ($onlyPage as $k => $sku) {
            $m = $metric[$k] ?? null;
            $this->line(sprintf('  %-34s metric=%s', $sku, $m ? ($m->listing_status ?: 'null').' id='.$m->item_id : 'none'));
        }

        return self::SUCCESS;
    }
}
