<?php

namespace App\Console\Commands;

use App\Models\GoogleAdsCampaign;
use App\Services\GoogleAdsSbidService;
use App\Support\GoogleShoppingBgtSkuMetrics;
use App\Support\GoogleShoppingCampaignsRawRule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * BGT Rules "Pause ads if price below $X" / "Pause ads if Dil above X%": pauses every ENABLED Shopping campaign whose price
 * (same Shopify price as the grid's Price column) is below the saved threshold. Runs hourly so a
 * low price pauses the ad without waiting for the daily SBGT push.
 */
class PauseLowPriceShoppingCampaignsCommand extends Command
{
    protected $signature = 'google:pause-low-price-shopping
                            {--dry-run : List the campaigns that would be paused without pausing them}';

    protected $description = 'Pause ENABLED Google Shopping campaigns whose price is below, or Dil% above, the BGT Rules "pause ads" amounts';

    public function handle(GoogleAdsSbidService $sbidService): int
    {
        $cfg = GoogleShoppingCampaignsRawRule::resolvedRule()['sbgt'] ?? [];
        $below = (float) ($cfg['pause_price_below'] ?? 20.0);
        $dilAbove = (float) ($cfg['pause_dil_above'] ?? 50.0);
        if ($below <= 0 && $dilAbove <= 0) {
            $this->info('Both rules are off (price below = 0, Dil above = 0). Nothing to do.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $customerId = config('services.google_ads.login_customer_id');

        $maxDate = DB::table('google_ads_campaigns')->where('advertising_channel_type', 'SHOPPING')->max('date');
        if (! $maxDate) {
            $this->warn('No Shopping campaign rows found.');

            return self::SUCCESS;
        }

        // Latest stored row per campaign (the grid's status), SHOPPING only, same name scope as the grid.
        $latest = DB::table('google_ads_campaigns')
            ->where('advertising_channel_type', 'SHOPPING')
            ->whereRaw("UPPER(campaign_name) NOT LIKE '% SEARCH%'")
            ->whereRaw("UPPER(campaign_name) NOT LIKE '% YT'")
            ->where('date', '>=', \Carbon\Carbon::parse($maxDate)->subDays(10)->toDateString())
            ->orderByDesc('date')
            ->get(['campaign_id', 'campaign_name', 'campaign_status']);

        $seen = [];
        $enabled = [];
        foreach ($latest as $row) {
            $cid = (string) $row->campaign_id;
            if (isset($seen[$cid])) {
                continue;
            }
            $seen[$cid] = true;
            if ($row->campaign_status === 'ENABLED') {
                $enabled[$cid] = (string) $row->campaign_name;
            }
        }

        $resolver = GoogleShoppingBgtSkuMetrics::resolver();
        $paused = 0;
        $failed = 0;
        foreach ($enabled as $cid => $name) {
            $m = $resolver($name);
            $price = $m['price'] ?? null;
            $dil = $m['dil'] ?? null;
            $reasons = [];
            if ($below > 0 && is_numeric($price) && (float) $price > 0 && (float) $price < $below) {
                $reasons[] = 'price $'.round((float) $price, 2).' < $'.$below;
            }
            if ($dilAbove > 0 && is_numeric($dil) && (float) $dil > $dilAbove) {
                $reasons[] = 'Dil '.round((float) $dil, 1).'% > '.$dilAbove.'%';
            }
            if ($reasons === []) {
                continue;
            }
            $label = "{$cid} ({$name}) ".implode(', ', $reasons);
            if ($dryRun) {
                $this->info("[DRY RUN] Would pause {$label}");
                $paused++;

                continue;
            }
            try {
                $sbidService->pauseCampaign($customerId, "customers/{$customerId}/campaigns/{$cid}");
                GoogleAdsCampaign::where('campaign_id', $cid)->update(['campaign_status' => 'PAUSED']);
                $paused++;
                $this->info("Paused {$label}");
            } catch (\Throwable $e) {
                $failed++;
                $this->error("Failed to pause {$label}: ".$e->getMessage());
            }
        }

        if ($paused > 0 && ! $dryRun) {
            foreach (['shopping', 'serp', 'youtube'] as $channel) {
                $k = 'gads_raw_grid_ver:'.$channel;
                if (! Cache::add($k, 2, now()->addYear())) {
                    Cache::increment($k);
                }
            }
        }

        $this->info(($dryRun ? 'Would pause ' : 'Paused ')."{$paused} campaign(s) (price below \${$below} or Dil above {$dilAbove}%); {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
