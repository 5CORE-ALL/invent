<?php

namespace App\Console\Commands;

use App\Models\ShopifySku;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Prints SKUs + Ohio-synced columns from shopify_skus for side-by-side checks in Shopify Admin
 * (same location as sync: name contains "Ohio").
 */
class ShopifySpotCheckSkuListCommand extends Command
{
    protected $signature = 'shopify:spot-check-sku-list
                            {--count=30 : Max rows to print}
                            {--skus= : Comma-separated SKUs only (optional)}
                            {--random : Random sample instead of most recently updated}';

    protected $description = 'List SKUs with AVL / ON_HAND / COMMITTED from shopify_skus to match Shopify Admin (Ohio)';

    public function handle(): int
    {
        $count = max(1, min(500, (int) $this->option('count')));
        $skusOpt = trim((string) $this->option('skus'));

        $q = ShopifySku::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '');

        if ($skusOpt !== '') {
            $list = array_values(array_filter(array_map('trim', explode(',', $skusOpt))));
            if ($list === []) {
                $this->error('Use --skus="A,B,C" with at least one SKU.');

                return 1;
            }
            $q->whereIn('sku', $list);
        }

        if ($this->option('random')) {
            $q->inRandomOrder();
        } else {
            $q->orderByDesc('updated_at');
        }

        $hasUnavail = Schema::hasColumn('shopify_skus', 'unavailable');
        $hasIncoming = Schema::hasColumn('shopify_skus', 'incoming');

        $select = ['sku', 'available_to_sell', 'committed', 'on_hand', 'inv', 'updated_at'];
        if ($hasUnavail) {
            $select[] = 'unavailable';
        }
        if ($hasIncoming) {
            $select[] = 'incoming';
        }

        $rows = $q->limit($count)->get($select);

        if ($rows->isEmpty()) {
            $this->warn('No shopify_skus rows matched.');

            return 0;
        }

        $this->line('Compare in Shopify Admin: Product → variant → Inventory → location whose name contains <fg=yellow>Ohio</>.');
        $this->line('Columns: <fg=cyan>AVL</> ≈ Available, <fg=cyan>ON</> ≈ On hand, <fg=cyan>CMT</> ≈ Committed at that location.');
        $this->newLine();

        $headers = ['sku', 'AVL', 'ON', 'CMT'];
        if ($hasUnavail) {
            $headers[] = 'UNAV';
        }
        if ($hasIncoming) {
            $headers[] = 'IN';
        }
        $headers[] = 'inv';
        $headers[] = 'updated_at';

        $table = $rows->map(function ($r) use ($hasUnavail, $hasIncoming) {
            $row = [
                $r->sku,
                (int) ($r->available_to_sell ?? 0),
                (int) ($r->on_hand ?? 0),
                (int) ($r->committed ?? 0),
            ];
            if ($hasUnavail) {
                $row[] = (int) ($r->unavailable ?? 0);
            }
            if ($hasIncoming) {
                $row[] = (int) ($r->incoming ?? 0);
            }
            $row[] = (int) ($r->inv ?? 0);
            $row[] = $r->updated_at ? $r->updated_at->format('Y-m-d H:i') : '';

            return $row;
        })->toArray();

        $this->table($headers, $table);

        $this->newLine();
        $this->line('<fg=gray>Quick sync a few SKUs only:</> php artisan shopify:sync-live-inventory --probe="SKU1,SKU2" --samples=2');

        return 0;
    }
}
