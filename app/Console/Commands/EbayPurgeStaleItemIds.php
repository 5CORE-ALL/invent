<?php

namespace App\Console\Commands;

use App\Support\Marketplace\ChannelListingRegistry;
use App\Support\Marketplace\ListingChannelCounts;
use App\Support\Marketplace\ListingManagerEbayTradingPublisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * A SKU that holds an eBay item id although eBay no longer has that listing (deleted, ended, or the SKU was
 * dropped from its variation listing) must not count as Listed. This asks eBay (GetItem) about each suspicious
 * item id and clears the dead ones, so the SKU shows in Missing L and is listed again as a new product.
 */
class EbayPurgeStaleItemIds extends Command
{
    protected $signature = 'ebay:purge-stale-item-ids
        {--store=2 : eBay store 1, 2, 3 or all}
        {--sku=* : Only these SKUs (checked even when they have stock)}
        {--zero-only : Only check rows whose eBay quantity is 0 (default: every live item id)}
        {--limit=1500 : Maximum rows to check per store (an item id shared by many SKUs is asked once)}
        {--dry-run : Report what would be cleared without changing anything}';

    protected $description = 'Clear eBay item ids that eBay no longer has (GetItem), so the SKU shows in Missing L and lists as new';

    public function handle(): int
    {
        $storeOpt = strtolower(trim((string) $this->option('store')));
        $stores = $storeOpt === 'all' ? [1, 2, 3] : [(int) $storeOpt];
        $stores = array_values(array_filter($stores, static fn ($s) => in_array($s, [1, 2, 3], true)));
        if ($stores === []) {
            $this->error('Use --store=1, 2, 3 or all.');

            return 1;
        }

        foreach ($stores as $store) {
            $this->purgeStore($store);
        }

        return 0;
    }

    private function purgeStore(int $store): void
    {
        $slug = [1 => 'ebay', 2 => 'ebaytwo', 3 => 'ebaythree'][$store];
        $cfg = ChannelListingRegistry::get($slug);
        $model = (string) ($cfg['listed']['model'] ?? '');
        if ($model === '' || ! class_exists($model)) {
            $this->error("eBay {$store}: metric model not found.");

            return;
        }
        $table = (new $model)->getTable();
        $dry = (bool) $this->option('dry-run');
        $skuFilter = array_values(array_filter(array_map(
            static fn ($s) => strtoupper(trim((string) $s)),
            (array) $this->option('sku')
        )));
        $notLive = array_map('strtoupper', ChannelListingRegistry::EBAY_NOT_LIVE_STATUSES);
        $hasStatus = Schema::hasColumn($table, 'listing_status');
        $hasReason = Schema::hasColumn($table, 'inactive_reason');
        $hasLink = Schema::hasColumn($table, 'ebay_link');
        $hasStock = Schema::hasColumn($table, 'ebay_stock');

        $query = $model::query()
            ->whereNotNull('sku')->where('sku', '!=', '')
            ->whereNotNull('item_id')->where('item_id', '!=', '')
            ->where('item_id', 'NOT LIKE', 'UNSOLD-%')
            ->whereColumn('item_id', '!=', 'sku');
        if ($skuFilter !== []) {
            $query->where(function ($q) use ($skuFilter) {
                foreach ($skuFilter as $sku) {
                    $q->orWhereRaw('UPPER(TRIM(sku)) = ?', [$sku]);
                }
            });
        } elseif ($this->option('zero-only') && $hasStock) {
            $query->whereNotNull('ebay_stock')->where('ebay_stock', '<=', 0);
        }

        $rows = $query->get()->filter(function ($row) use ($hasStatus, $notLive) {
            if (stripos((string) $row->sku, 'PARENT') !== false) {
                return false;
            }
            $status = $hasStatus ? strtoupper(trim((string) ($row->listing_status ?? ''))) : '';

            return ! in_array($status, $notLive, true);
        })->values();

        $limit = max(1, (int) $this->option('limit'));
        $this->info("eBay {$store}: ".$rows->count().' item id(s) to verify'.($rows->count() > $limit ? " (checking first {$limit})" : '').($dry ? ' [dry run]' : '').'.');

        $cleared = 0;
        $live = 0;
        $errors = 0;
        $consecutiveErrors = 0;
        foreach ($rows->take($limit) as $row) {
            $sku = trim((string) $row->sku);
            $itemId = trim((string) $row->item_id);
            $res = ListingManagerEbayTradingPublisher::itemState($slug, $itemId, $sku);
            $state = $res['state'];

            if ($state === 'error') {
                $errors++;
                $consecutiveErrors++;
                $this->warn("  {$sku} ({$itemId}): could not verify - {$res['message']}");
                if ($consecutiveErrors >= 5) {
                    $this->error('  Five failures in a row (rate limit or credentials); stopping. Run again later.');
                    break;
                }

                continue;
            }
            $consecutiveErrors = 0;

            if ($state === 'live') {
                $live++;
                $this->line("  {$sku} ({$itemId}): still live on eBay, qty ".($res['quantity'] ?? '?').' - kept');

                continue;
            }

            // not_found / ended / sku_absent: this item id no longer holds the SKU.
            $this->line("  {$sku} ({$itemId}): {$state} - ".($dry ? 'would clear' : 'cleared').' ('.$res['message'].')');
            $cleared++;
            if ($dry) {
                continue;
            }
            $payload = ['item_id' => $table === 'ebay_2_metrics' ? '' : null];
            if ($hasStatus) {
                $payload['listing_status'] = 'MISSING';
            }
            if ($hasReason) {
                $payload['inactive_reason'] = 'eBay no longer has this listing ('.$state.')';
            }
            if ($hasLink) {
                $payload['ebay_link'] = null;
            }
            $model::query()->whereKey($row->getKey())->update($payload);
            Log::info("ebay:purge-stale-item-ids eBay {$store}: cleared item id", ['sku' => $sku, 'item_id' => $itemId, 'state' => $state]);
            usleep(150000);
        }

        $this->info("eBay {$store}: cleared {$cleared}, still live {$live}, could not verify {$errors}.");

        if (! $dry && $cleared > 0) {
            try {
                ListingChannelCounts::refreshChannelOnMissingListingPage($slug);
            } catch (\Throwable $e) {
                $this->warn('Missing Listing refresh skipped: '.$e->getMessage());
            }
        }
    }
}
