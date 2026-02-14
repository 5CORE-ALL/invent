<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\GoogleAdsSbidService;

class GoogleShoppingBulkEnableUpdateCommand extends Command
{
    protected $signature = 'google:shopping-bulk-enable-update
                            {--file= : Path to CSV/TSV with columns Campaign, SBGT, SBID}
                            {--delimiter=tab : Delimiter: tab or comma}
                            {--skip-lines=0 : Skip this many lines at start (e.g. 2 when row 3 is header)}
                            {--dry-run : Only show what would be done}';

    protected $description = 'Enable selected SHOPPING campaigns and set BGT (from SBGT) and SBID from a file';

    protected $sbidService;

    public function __construct(GoogleAdsSbidService $sbidService)
    {
        parent::__construct();
        $this->sbidService = $sbidService;
    }

    public function handle()
    {
        $file = $this->option('file');
        if (empty($file) || !is_readable($file)) {
            $this->error('--file=path is required and must be readable.');
            return 1;
        }

        $delimiter = ($this->option('delimiter') === 'comma') ? ',' : "\t";
        $skipLines = max(0, (int) $this->option('skip-lines'));
        $dryRun = $this->option('dry-run');
        if ($dryRun) {
            $this->warn('⚠️  DRY RUN - No changes will be made');
        }

        $raw = file($file) ?: [];
        if ($skipLines > 0) {
            $raw = array_slice($raw, $skipLines);
        }
        $lines = array_values(array_filter(array_map('trim', $raw)));
        if (empty($lines)) {
            $this->error('File is empty or has no lines after --skip-lines.');
            return 1;
        }

        $header = str_getcsv(array_shift($lines), $delimiter);
        $header = array_map(function ($h) { return strtolower(trim($h)); }, $header);
        $ic = array_search('campaign', $header);
        $is = array_search('sbgt', $header);
        $ib = array_search('sbid', $header);
        if ($ic === false || $is === false || $ib === false) {
            $this->error('File must have columns: Campaign, SBGT, SBID (case-insensitive).');
            return 1;
        }

        $customerId = config('services.google_ads.login_customer_id');
        if (empty($customerId)) {
            $this->error('GOOGLE_ADS_LOGIN_CUSTOMER_ID is not set.');
            return 1;
        }

        $campaigns = DB::table('google_ads_campaigns')
            ->select('campaign_id', 'campaign_name', 'budget_id')
            ->where('advertising_channel_type', 'SHOPPING')
            ->orderByDesc('date')
            ->get()
            ->unique('campaign_id')
            ->keyBy('campaign_id');

        $totalDataRows = count($lines);
        $this->info("Total data rows in file: {$totalDataRows}");

        $ok = 0; $fail = 0; $skip = 0;
        $seenCampaignIds = [];
        $duplicates = 0;

        foreach ($lines as $i => $line) {
            $row = str_getcsv($line, $delimiter);
            $name = trim(rtrim(trim($row[$ic] ?? ''), '.'));
            if ($name === '') {
                $skip++;
                continue;
            }
            $sbgt = (int) ($row[$is] ?? 0);
            $sbid = (float) ($row[$ib] ?? 0);
            if ($sbgt < 1 || $sbgt > 5) {
                $this->warn("Row " . ($skipLines + 2 + $i) . " [{$name}]: SBGT must be 1–5, got {$sbgt}; skipped.");
                $skip++;
                continue;
            }
            if ($sbid <= 0) {
                $this->warn("Row " . ($skipLines + 2 + $i) . " [{$name}]: SBID must be > 0, got {$sbid}; skipped.");
                $skip++;
                continue;
            }

            $rec = $this->findCampaign($campaigns, $name);
            if (!$rec) {
                $this->warn("Row " . ($skipLines + 2 + $i) . " [{$name}]: No SHOPPING campaign matched; skipped.");
                $skip++;
                continue;
            }

            $cid = $rec->campaign_id;
            $budgetId = $rec->budget_id;
            $bgtDollars = $sbgt; // 1→1, 2→2, … 5→5

            if ($dryRun) {
                $this->line("[DRY RUN] Would: Enable {$name} (campaign_id={$cid}), BGT=\${$bgtDollars}, SBID={$sbid}");
                if (isset($seenCampaignIds[$cid])) { $duplicates++; } else { $seenCampaignIds[$cid] = true; }
                $ok++;
                continue;
            }

            try {
                $campaignResource = "customers/{$customerId}/campaigns/{$cid}";
                $this->sbidService->enableCampaign($customerId, $campaignResource);
                DB::table('google_ads_campaigns')->where('campaign_id', $cid)->update(['campaign_status' => 'ENABLED']);
            } catch (\Exception $e) {
                $this->error("Enable [{$name}]: " . $e->getMessage());
                Log::error("Bulk enable: " . $e->getMessage(), ['campaign_id' => $cid]);
                $fail++;
                continue;
            }

            if (!empty($budgetId)) {
                try {
                    $budgetResource = "customers/{$customerId}/campaignBudgets/{$budgetId}";
                    $this->sbidService->updateCampaignBudget($customerId, $budgetResource, (float) $bgtDollars);
                } catch (\Exception $e) {
                    $this->error("BGT [{$name}]: " . $e->getMessage());
                    Log::error("Bulk BGT: " . $e->getMessage(), ['campaign_id' => $cid]);
                }
            } else {
                $this->warn("BGT [{$name}]: no budget_id; BGT not updated.");
            }

            try {
                $this->sbidService->updateCampaignSbids($customerId, $cid, $sbid);
            } catch (\Exception $e) {
                $this->error("SBID [{$name}]: " . $e->getMessage());
                Log::error("Bulk SBID: " . $e->getMessage(), ['campaign_id' => $cid]);
            }

            $this->info("OK: {$name} – enabled, BGT=\${$bgtDollars}, SBID={$sbid}");
            if (isset($seenCampaignIds[$cid])) { $duplicates++; } else { $seenCampaignIds[$cid] = true; }
            $ok++;
        }

        $doneMsg = sprintf('Done. Total: %d, OK: %d, Failed: %d, Skipped: %d.', $totalDataRows, $ok, $fail, $skip);
        if ($duplicates > 0) {
            $doneMsg .= sprintf(' Unique campaigns: %d. Duplicate rows in file: %d (same campaign in multiple rows).', $ok - $duplicates, $duplicates);
        }
        $this->info($doneMsg);
        return 0;
    }

    protected function findCampaign($campaigns, $searchName)
    {
        $search = strtoupper(trim(rtrim(trim($searchName), '.')));
        foreach ($campaigns as $c) {
            $cn = $c->campaign_name ?? '';
            $cn = strtoupper(trim(rtrim(trim($cn), '.')));
            $parts = array_map('trim', explode(',', $cn));
            if ($cn === $search || in_array($search, $parts)) {
                return $c;
            }
        }
        return null;
    }
}
