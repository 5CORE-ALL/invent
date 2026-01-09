<?php

namespace App\Console\Commands;

use App\Models\MetaAdAccount;
use App\Models\MetaCampaign;
use App\Models\MetaAdSet;
use App\Models\MetaAd;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MetaAdsImportRawCommand extends Command
{
    protected $signature = 'meta-ads:import-raw 
                            {path : Path to JSON file or directory}
                            {--type= : Entity type (accounts, campaigns, adsets, ads, insights). Auto-detect if not provided}
                            {--user-id= : User ID to associate with imported data}';

    protected $description = 'Import raw JSON data from Meta API exports';

    public function handle()
    {
        $path = $this->argument('path');
        $type = $this->option('type');
        $userId = $this->option('user-id') ? (int) $this->option('user-id') : null;

        if (!File::exists($path)) {
            $this->error("File or directory not found: {$path}");
            return 1;
        }

        $this->info("Importing from: {$path}");

        if (File::isDirectory($path)) {
            $files = File::files($path);
            foreach ($files as $file) {
                $this->importFile($file->getPathname(), $type, $userId);
            }
        } else {
            $this->importFile($path, $type, $userId);
        }

        $this->info('✓ Import completed!');
        return 0;
    }

    protected function importFile(string $filePath, ?string $type, ?int $userId)
    {
        $this->line("Processing: " . basename($filePath));

        $content = File::get($filePath);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error("Invalid JSON in file: {$filePath}");
            return;
        }

        // Auto-detect type if not provided
        if (!$type) {
            $type = $this->detectType($data);
            $this->line("Auto-detected type: {$type}");
        }

        switch ($type) {
            case 'accounts':
                $this->importAccounts($data, $userId);
                break;
            case 'campaigns':
                $this->importCampaigns($data, $userId);
                break;
            case 'adsets':
                $this->importAdSets($data, $userId);
                break;
            case 'ads':
                $this->importAds($data, $userId);
                break;
            case 'insights':
                $this->importInsights($data, $userId);
                break;
            default:
                $this->error("Unknown type: {$type}");
        }
    }

    protected function detectType(array $data): string
    {
        // Check if it's an array of items
        if (isset($data['data']) && is_array($data['data'])) {
            $firstItem = $data['data'][0] ?? [];
        } else {
            $firstItem = $data[0] ?? [];
        }

        // Detect by fields
        if (isset($firstItem['account_id'])) {
            return 'accounts';
        } elseif (isset($firstItem['campaign_id']) && !isset($firstItem['adset_id'])) {
            return 'campaigns';
        } elseif (isset($firstItem['adset_id']) && !isset($firstItem['id']) || isset($firstItem['optimization_goal'])) {
            return 'adsets';
        } elseif (isset($firstItem['date_start']) || isset($firstItem['date_preset'])) {
            return 'insights';
        } else {
            return 'ads';
        }
    }

    protected function importAccounts(array $data, ?int $userId)
    {
        $items = $data['data'] ?? $data;
        $imported = 0;

        foreach ($items as $account) {
            $metaId = $account['id'] ?? null;
            if (!$metaId) continue;

            $metaUpdatedTime = null;
            if (isset($account['updated_time'])) {
                try {
                    $metaUpdatedTime = Carbon::parse($account['updated_time']);
                } catch (\Exception $e) {
                    // Invalid date
                }
            }

            MetaAdAccount::updateOrCreate(
                ['meta_id' => $metaId],
                [
                    'user_id' => $userId,
                    'account_id' => $account['account_id'] ?? null,
                    'name' => $account['name'] ?? null,
                    'account_status' => $account['account_status'] ?? null,
                    'currency' => $account['currency'] ?? null,
                    'timezone_name' => $account['timezone_name'] ?? null,
                    'meta_updated_time' => $metaUpdatedTime,
                    'synced_at' => now(),
                    'raw_json' => $account,
                ]
            );
            $imported++;
        }

        $this->line("  Imported {$imported} accounts");
    }

    protected function importCampaigns(array $data, ?int $userId)
    {
        $items = $data['data'] ?? $data;
        $imported = 0;

        foreach ($items as $campaign) {
            $metaId = $campaign['id'] ?? null;
            if (!$metaId) continue;

            $metaUpdatedTime = null;
            if (isset($campaign['updated_time'])) {
                try {
                    $metaUpdatedTime = Carbon::parse($campaign['updated_time']);
                } catch (\Exception $e) {
                    // Invalid date
                }
            }

            $startTime = null;
            $stopTime = null;
            if (isset($campaign['start_time'])) {
                try {
                    $startTime = Carbon::parse($campaign['start_time']);
                } catch (\Exception $e) {
                    // Invalid date
                }
            }
            if (isset($campaign['stop_time'])) {
                try {
                    $stopTime = Carbon::parse($campaign['stop_time']);
                } catch (\Exception $e) {
                    // Invalid date
                }
            }

            MetaCampaign::updateOrCreate(
                [
                    'user_id' => $userId,
                    'meta_id' => $metaId,
                ],
                [
                    'name' => $campaign['name'] ?? null,
                    'status' => $campaign['status'] ?? null,
                    'effective_status' => $campaign['effective_status'] ?? null,
                    'objective' => $campaign['objective'] ?? null,
                    'daily_budget' => isset($campaign['daily_budget']) ? ($campaign['daily_budget'] / 100) : null,
                    'lifetime_budget' => isset($campaign['lifetime_budget']) ? ($campaign['lifetime_budget'] / 100) : null,
                    'budget_remaining' => isset($campaign['budget_remaining']) ? ($campaign['budget_remaining'] / 100) : null,
                    'start_time' => $startTime,
                    'stop_time' => $stopTime,
                    'buying_type' => $campaign['buying_type'] ?? null,
                    'bid_strategy' => $campaign['bid_strategy'] ?? null,
                    'special_ad_categories' => $campaign['special_ad_categories'] ?? null,
                    'meta_updated_time' => $metaUpdatedTime,
                    'synced_at' => now(),
                    'raw_json' => $campaign,
                ]
            );
            $imported++;
        }

        $this->line("  Imported {$imported} campaigns");
    }

    protected function importAdSets(array $data, ?int $userId)
    {
        $items = $data['data'] ?? $data;
        $imported = 0;

        foreach ($items as $adset) {
            $metaId = $adset['id'] ?? null;
            if (!$metaId) continue;

            // Find campaign
            $campaign = null;
            if (isset($adset['campaign_id'])) {
                $campaign = MetaCampaign::where('meta_id', $adset['campaign_id'])->first();
            }

            $metaUpdatedTime = null;
            if (isset($adset['updated_time'])) {
                try {
                    $metaUpdatedTime = Carbon::parse($adset['updated_time']);
                } catch (\Exception $e) {
                    // Invalid date
                }
            }

            MetaAdSet::updateOrCreate(
                [
                    'user_id' => $userId,
                    'meta_id' => $metaId,
                ],
                [
                    'ad_account_id' => $campaign?->ad_account_id,
                    'campaign_id' => $campaign?->id,
                    'name' => $adset['name'] ?? null,
                    'status' => $adset['status'] ?? null,
                    'effective_status' => $adset['effective_status'] ?? null,
                    'optimization_goal' => $adset['optimization_goal'] ?? null,
                    'daily_budget' => isset($adset['daily_budget']) ? ($adset['daily_budget'] / 100) : null,
                    'lifetime_budget' => isset($adset['lifetime_budget']) ? ($adset['lifetime_budget'] / 100) : null,
                    'budget_remaining' => isset($adset['budget_remaining']) ? ($adset['budget_remaining'] / 100) : null,
                    'start_time' => isset($adset['start_time']) ? Carbon::parse($adset['start_time']) : null,
                    'end_time' => isset($adset['end_time']) ? Carbon::parse($adset['end_time']) : null,
                    'billing_event' => $adset['billing_event'] ?? null,
                    'bid_amount' => $adset['bid_amount'] ?? null,
                    'targeting' => $adset['targeting'] ?? null,
                    'meta_updated_time' => $metaUpdatedTime,
                    'synced_at' => now(),
                    'raw_json' => $adset,
                ]
            );
            $imported++;
        }

        $this->line("  Imported {$imported} ad sets");
    }

    protected function importAds(array $data, ?int $userId)
    {
        $items = $data['data'] ?? $data;
        $imported = 0;

        foreach ($items as $ad) {
            $metaId = $ad['id'] ?? null;
            if (!$metaId) continue;

            // Find adset
            $adset = null;
            if (isset($ad['adset_id'])) {
                $adset = MetaAdSet::where('meta_id', $ad['adset_id'])->first();
            }

            $metaUpdatedTime = null;
            if (isset($ad['updated_time'])) {
                try {
                    $metaUpdatedTime = Carbon::parse($ad['updated_time']);
                } catch (\Exception $e) {
                    // Invalid date
                }
            }

            MetaAd::updateOrCreate(
                [
                    'user_id' => $userId,
                    'meta_id' => $metaId,
                ],
                [
                    'ad_account_id' => $adset?->ad_account_id,
                    'campaign_id' => $adset?->campaign_id,
                    'adset_id' => $adset?->id,
                    'name' => $ad['name'] ?? null,
                    'status' => $ad['status'] ?? null,
                    'effective_status' => $ad['effective_status'] ?? null,
                    'creative_id' => $ad['creative']['id'] ?? $ad['creative_id'] ?? null,
                    'preview_shareable_link' => $ad['preview_shareable_link'] ?? null,
                    'meta_updated_time' => $metaUpdatedTime,
                    'synced_at' => now(),
                    'raw_json' => $ad,
                ]
            );
            $imported++;
        }

        $this->line("  Imported {$imported} ads");
    }

    protected function importInsights(array $data, ?int $userId)
    {
        $this->warn('Insights import not yet implemented. Use sync command instead.');
    }
}
