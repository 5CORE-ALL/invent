<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncAmazonPrices extends Command
{
    protected $signature = 'sync:amazon-prices';
    protected $description = 'One-time sync of prices from repricer.lmpa_data to 5coreinventory.amazon_datsheets';

    public function handle(): int
    {
        try {
            // Subquery to get lowest non-zero price per SKU
            $subQuery = DB::table('5core_repricer.lmpa_data')
                ->select('sku', DB::raw('MIN(price) as price'))
                ->where('price', '>', 0)
                ->groupBy('sku');

            $updated = DB::table('5coreinventory.amazon_datsheets as a')
                ->joinSub($subQuery, 'l', function ($join) {
                    $join->on('a.sku', '=', 'l.sku');
                })
                ->where(function ($q) {
                    $q->whereColumn('a.price_lmpa', '<>', 'l.price')
                      ->orWhere(function ($sub) {
                          $sub->whereNull('a.price_lmpa')
                              ->whereNotNull('l.price');
                      })
                      ->orWhere(function ($sub) {
                          $sub->whereNotNull('a.price_lmpa')
                              ->whereNull('l.price');
                      });
                })
                ->update([
                    'a.price_lmpa' => DB::raw('l.price'),
                    'a.updated_at' => now(),
                ]);

            if ($updated) {
                $this->info("✅ {$updated} rows updated successfully.");
            } else {
                $this->warn("⚠️ No rows updated, prices already in sync.");
            }

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error("❌ Error syncing prices: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
