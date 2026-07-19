<?php

namespace App\Console\Commands;

use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Services\GoogleAdsSbidService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pause ENABLED Shopping campaigns when INV ≤ 0; enable PAUSED ones when INV > 0.
 * Inventory matches /google/shopping/google-shopping: PARENT … = sum of child Shopify inv.
 */
class SyncGoogleShoppingStatusByInventory extends Command
{
    protected $signature = 'google-shopping:sync-status-by-inventory
        {--mode=both : pause (INV≤0), enable (INV>0), or both}
        {--dry-run : Show actions without calling Google Ads}';

    protected $description = 'Auto pause/enable Google Shopping campaigns by Shopify inventory (parent total for PARENT campaigns)';

    public function __construct(protected GoogleAdsSbidService $sbidService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $mode = strtolower(trim((string) $this->option('mode')));
        if (! in_array($mode, ['pause', 'enable', 'both'], true)) {
            $this->error('--mode must be pause, enable, or both.');

            return 1;
        }

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('DRY RUN — no Google Ads changes will be made.');
        }

        $customerId = config('services.google_ads.login_customer_id');
        if (empty($customerId)) {
            $this->error('GOOGLE_ADS_LOGIN_CUSTOMER_ID is not configured.');

            return 1;
        }

        $invResolver = $this->buildInventoryResolver();
        $campaigns = $this->loadShoppingCampaigns();
        $this->info('Loaded '.$campaigns->count().' Shopping campaign(s) (excl. SEARCH / YT).');

        $stats = [
            'checked' => 0,
            'paused' => 0,
            'enabled' => 0,
            'skipped' => 0,
            'errors' => 0,
            'removed' => 0,
        ];

        foreach ($campaigns as $campaign) {
            $stats['checked']++;
            $name = (string) ($campaign->campaign_name ?? '');
            $status = strtoupper(trim((string) ($campaign->campaign_status ?? '')));
            $campaignId = (string) ($campaign->campaign_id ?? '');
            if ($campaignId === '' || $status === 'REMOVED') {
                $stats['skipped']++;

                continue;
            }

            $inv = (int) ($invResolver($name) ?? 0);
            $doPause = ($mode === 'pause' || $mode === 'both') && $status === 'ENABLED' && $inv <= 0;
            $doEnable = ($mode === 'enable' || $mode === 'both') && $status === 'PAUSED' && $inv > 0;

            if (! $doPause && ! $doEnable) {
                $stats['skipped']++;

                continue;
            }

            $action = $doPause ? 'PAUSED' : 'ENABLED';
            $label = $doPause ? 'Pause' : 'Enable';
            $resource = "customers/{$customerId}/campaigns/{$campaignId}";

            if ($dryRun) {
                $this->line("[DRY RUN] Would {$label}: {$name} (ID {$campaignId}) INV={$inv}");
                if ($doPause) {
                    $stats['paused']++;
                } else {
                    $stats['enabled']++;
                }

                continue;
            }

            try {
                if ($doPause) {
                    $this->sbidService->pauseCampaign($customerId, $resource);
                } else {
                    $this->sbidService->enableCampaign($customerId, $resource);
                }
                DB::table('google_ads_campaigns')
                    ->where('campaign_id', $campaignId)
                    ->update(['campaign_status' => $action]);
                $this->info("{$label}d: {$name} (ID {$campaignId}) INV={$inv}");
                if ($doPause) {
                    $stats['paused']++;
                } else {
                    $stats['enabled']++;
                }
            } catch (\Throwable $e) {
                if ($this->isRemovedResourceError($e->getMessage())) {
                    $stats['removed']++;
                    try {
                        DB::table('google_ads_campaigns')
                            ->where('campaign_id', $campaignId)
                            ->update(['campaign_status' => 'REMOVED']);
                    } catch (\Throwable) {
                    }
                    $this->warn("Already removed: {$name} (ID {$campaignId})");
                } else {
                    $stats['errors']++;
                    $this->error("Failed to {$label} {$campaignId} ({$name}): ".$e->getMessage());
                    Log::error('google-shopping:sync-status-by-inventory failed', [
                        'campaign_id' => $campaignId,
                        'campaign_name' => $name,
                        'action' => $action,
                        'inv' => $inv,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->newLine();
        $this->info(str_repeat('=', 60));
        $this->info('Summary (mode='.$mode.($dryRun ? ', dry-run' : '').')');
        $this->info('  Checked:  '.$stats['checked']);
        $this->info('  Paused:   '.$stats['paused'].'  (ENABLED + INV≤0)');
        $this->info('  Enabled:  '.$stats['enabled'].'  (PAUSED + INV>0)');
        $this->info('  Skipped:  '.$stats['skipped']);
        $this->info('  Removed:  '.$stats['removed']);
        $this->info('  Errors:   '.$stats['errors']);
        $this->info(str_repeat('=', 60));

        return $stats['errors'] > 0 ? 1 : 0;
    }

    /**
     * Latest row per campaign_id for Shopping-style names (exclude SEARCH / YT), same scope as the grid.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function loadShoppingCampaigns()
    {
        $latest = DB::table('google_ads_campaigns')
            ->whereNotNull('campaign_id')
            ->whereNotNull('date')
            ->whereRaw('UPPER(campaign_name) NOT LIKE ?', ['% SEARCH%'])
            ->whereRaw('UPPER(campaign_name) NOT LIKE ?', ['% YT'])
            ->selectRaw('campaign_id, MAX(`date`) as max_d')
            ->groupBy('campaign_id');

        return DB::table('google_ads_campaigns as g')
            ->joinSub($latest, 'cLatest', function ($join) {
                $join->on('g.campaign_id', '=', 'cLatest.campaign_id')
                    ->on('g.date', '=', 'cLatest.max_d');
            })
            ->whereRaw('UPPER(g.campaign_name) NOT LIKE ?', ['% SEARCH%'])
            ->whereRaw('UPPER(g.campaign_name) NOT LIKE ?', ['% YT'])
            ->select('g.campaign_id', 'g.campaign_name', 'g.campaign_status')
            ->orderBy('g.campaign_name')
            ->get();
    }

    /**
     * Same INV rules as GoogleShoppingCampaignsController::buildInventoryResolver().
     *
     * @return \Closure(string): ?int
     */
    private function buildInventoryResolver(): \Closure
    {
        $allPm = ProductMaster::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->get(['sku', 'parent']);

        $childSkus = [];
        foreach ($allPm as $pm) {
            $s = trim((string) ($pm->sku ?? ''));
            if ($s === '' || str_starts_with(strtoupper($s), 'PARENT')) {
                continue;
            }
            $childSkus[] = $s;
        }
        $shopifyByPmSku = ShopifySku::mapByProductSkus(array_values(array_unique($childSkus)));

        $inventoryByParent = [];
        $skuToParentKey = [];
        foreach ($allPm as $pm) {
            $s = trim((string) ($pm->sku ?? ''));
            if ($s === '' || str_starts_with(strtoupper($s), 'PARENT')) {
                continue;
            }
            $pKey = preg_replace('/\s+/', ' ', strtoupper(trim((string) ($pm->parent ?? ''))));
            if ($pKey === '') {
                continue;
            }
            $normSku = preg_replace('/\s+/', ' ', strtoupper(rtrim($s, '.')));
            $skuToParentKey[$normSku] = $pKey;
            $rec = $shopifyByPmSku->get($s);
            $inventoryByParent[$pKey] = ($inventoryByParent[$pKey] ?? 0) + (float) ($rec?->inv ?? 0);
        }

        $parentSkuToFamilyKey = [];
        foreach ($allPm as $pm) {
            $s = trim((string) ($pm->sku ?? ''));
            if ($s === '' || ! str_starts_with(strtoupper($s), 'PARENT')) {
                continue;
            }
            $normSku = preg_replace('/\s+/', ' ', strtoupper(rtrim($s, '.')));
            $parentCol = trim((string) ($pm->parent ?? ''));
            if ($parentCol !== '') {
                $parentSkuToFamilyKey[$normSku] = preg_replace('/\s+/', ' ', strtoupper($parentCol));
            } else {
                $rest = trim(preg_replace('/^PARENT\s+/i', '', $s) ?? '');
                $parentSkuToFamilyKey[$normSku] = $rest === ''
                    ? $normSku
                    : preg_replace('/\s+/', ' ', strtoupper(rtrim($rest, '.')));
            }
        }

        $childInvBySku = [];
        foreach ($shopifyByPmSku as $sku => $rec) {
            $normSku = preg_replace('/\s+/', ' ', strtoupper(rtrim((string) $sku, '.')));
            if ($normSku !== '') {
                $childInvBySku[$normSku] = (int) round((float) ($rec->inv ?? 0));
            }
        }

        $memo = [];

        return static function (string $campaignName) use (
            $inventoryByParent,
            $parentSkuToFamilyKey,
            $skuToParentKey,
            $childInvBySku,
            &$memo
        ): ?int {
            $norm = preg_replace('/\s+/', ' ', strtoupper(rtrim(trim($campaignName), '.')));
            if ($norm === '') {
                return null;
            }
            if (array_key_exists($norm, $memo)) {
                return $memo[$norm];
            }

            if (str_starts_with($norm, 'PARENT ')) {
                $fam = $parentSkuToFamilyKey[$norm]
                    ?? preg_replace('/\s+/', ' ', trim(substr($norm, strlen('PARENT '))));
                $out = isset($inventoryByParent[$fam]) ? (int) round($inventoryByParent[$fam]) : 0;
                $memo[$norm] = $out;

                return $out;
            }

            if (isset($childInvBySku[$norm])) {
                $memo[$norm] = $childInvBySku[$norm];

                return $memo[$norm];
            }
            if (isset($skuToParentKey[$norm])) {
                $fam = $skuToParentKey[$norm];
                $out = isset($inventoryByParent[$fam]) ? (int) round($inventoryByParent[$fam]) : 0;
                $memo[$norm] = $out;

                return $out;
            }

            $memo[$norm] = null;

            return null;
        };
    }

    private function isRemovedResourceError(string $message): bool
    {
        return stripos($message, 'OPERATION_NOT_PERMITTED_FOR_REMOVED_RESOURCE') !== false
            || stripos($message, 'removed resources') !== false
            || stripos($message, 'removed resource') !== false
            || stripos($message, 'operation is not allowed for removed') !== false;
    }
}
