<?php

namespace App\Console\Commands;

use App\Models\ProductStockMapping;
use App\Models\ReverbListingStatus;
use App\Services\ReverbListingService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RelistReverbProducts extends Command
{
    protected $signature = 'reverb:relist-products
                            {--sku= : Single SKU to relist}
                            {--days=30 : Consider products ended in the last X days}
                            {--auto : Attempt auto relist (set inventory and push to Reverb)}';

    protected $description = 'List or relist ended Reverb products (ended in last X days). Use --auto to push inventory.';

    public function handle(): int
    {
        $sku = $this->option('sku');
        $days = (int) $this->option('days');
        $auto = $this->option('auto');
        if ($days < 1) {
            $days = 30;
        }

        if ($sku !== null && trim($sku) !== '') {
            return $this->relistSingleSku(trim($sku), $auto);
        }

        $cutoff = Carbon::now()->subDays($days);
        $ended = ReverbListingStatus::where('updated_at', '>=', $cutoff)->get()->filter(function ($row) {
            $v = $row->value;
            return is_array($v) && ($v['state'] ?? '') === 'ended';
        });
        if ($ended->isEmpty()) {
            $this->info("No ended listings found in last {$days} days (based on ReverbListingStatus.updated_at).");
            $this->line('Run reverb:sync-listing-statuses first to refresh statuses, or check --days value.');
            return self::SUCCESS;
        }

        $rows = [];
        foreach ($ended as $row) {
            $value = $row->value ?? [];
            $listingId = is_array($value) ? ($value['listing_id'] ?? null) : null;
            $rows[] = [$row->sku, $listingId, $row->updated_at->toDateTimeString()];
        }
        $this->info("Ended listings (last {$days} days): " . count($rows));
        $this->table(['SKU', 'Listing ID', 'Last updated'], $rows);

        if ($auto) {
            $this->warn('Auto relist: Reverb has no public "relist" API. We can set inventory to 1 and update the listing.');
            $this->line('If the listing was ended (not out of stock), you may need to relist manually in Reverb dashboard.');
            if (!$this->confirm('Update inventory to 1 for these ended SKUs (where Shopify has stock)?', false)) {
                return self::SUCCESS;
            }
            $this->autoRelistEnded($ended);
        } else {
            $this->line('Use --auto to attempt inventory update for relisting.');
        }

        return self::SUCCESS;
    }

    protected function relistSingleSku(string $sku, bool $auto): int
    {
        $status = ReverbListingStatus::where('sku', $sku)->first();
        if (!$status) {
            $this->warn("SKU '{$sku}' not found in ReverbListingStatus. Run reverb:check-listings --sku={$sku} to check API.");
            return self::FAILURE;
        }
        $value = $status->value ?? [];
        $state = is_array($value) ? ($value['state'] ?? 'unknown') : 'unknown';
        $listingId = is_array($value) ? ($value['listing_id'] ?? null) : null;

        $this->table(['SKU', 'State', 'Listing ID'], [[$sku, $state, $listingId ?? '']]);

        if ($state !== 'ended') {
            $this->info("SKU is not in 'ended' state (current: {$state}). Nothing to relist.");
            return self::SUCCESS;
        }

        if (!$listingId) {
            $this->warn('No listing_id stored. Run reverb:sync-listing-statuses then try again.');
            return self::FAILURE;
        }

        if (!$auto) {
            $this->line('To attempt relist, run with --auto');
            return self::SUCCESS;
        }

        $stock = ProductStockMapping::where('sku', $sku)->first();
        $qty = $stock ? (int) ($stock->inventory_shopify ?? 0) : 0;
        if ($qty < 1) {
            $this->warn("Shopify inventory is 0 for SKU {$sku}. Set inventory to 1 anyway? (Reverb may require stock to relist)");
            $qty = 1;
        }

        $listingService = app(ReverbListingService::class);
        if ($listingService->updateListingInventory((string) $listingId, $qty)) {
            $this->info("Updated Reverb listing {$listingId} inventory to {$qty}. Check Reverb dashboard to confirm relist.");
            Log::channel('reverb_sync')->info('Reverb relist: updated inventory for ended SKU', [
                'sku' => $sku,
                'listing_id' => $listingId,
                'inventory' => $qty,
            ]);
        } else {
            $this->error('Failed to update listing inventory. Check logs.');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * For each ended SKU with listing_id, set inventory to 1 (or Shopify qty) and push to Reverb.
     */
    protected function autoRelistEnded($ended): void
    {
        $listingService = app(ReverbListingService::class);
        $updated = 0;
        foreach ($ended as $row) {
            $value = $row->value ?? [];
            $listingId = is_array($value) ? ($value['listing_id'] ?? null) : null;
            if (!$listingId) {
                continue;
            }
            $stock = ProductStockMapping::where('sku', $row->sku)->first();
            $qty = $stock ? max(1, (int) ($stock->inventory_shopify ?? 0)) : 1;
            if ($listingService->updateListingInventory((string) $listingId, $qty)) {
                $updated++;
                $this->line("  Updated {$row->sku} (listing {$listingId}) => inventory {$qty}");
            }
        }
        $this->info("Updated {$updated} listing(s). Check Reverb dashboard for relist status.");
    }
}
