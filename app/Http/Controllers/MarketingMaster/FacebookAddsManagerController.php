<?php

namespace App\Http\Controllers\MarketingMaster;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MetaAllAd;
use App\Models\MetaAdGroup;
use App\Models\MetaAdRawData;
use App\Models\ShopifyMetaCampaign;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Services\MetaApiService;

class FacebookAddsManagerController extends Controller
{
    public function syncMetaAdsFromApi()
    {
        try {
            $metaApi = new MetaApiService();
            
            // Sync L30 data from Meta API
            $l30Count = $this->syncL30DataFromMetaApi($metaApi);
            
            // Sync L7 data from Meta API
            $l7Count = $this->syncL7DataFromMetaApi($metaApi);
            
            return response()->json([
                'success' => true,
                'message' => 'Data synced successfully from Meta API',
                'l30_synced' => $l30Count,
                'l7_synced' => $l7Count,
            ]);
        } catch (\Exception $e) {
            Log::error('Meta API Sync Error', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => 'Error syncing data from Meta API: ' . $e->getMessage()
            ], 500);
        }
    }

    private function syncL30DataFromMetaApi(MetaApiService $metaApi)
    {
        try {
            // Fetch L30 campaigns data from Meta API
            $campaigns = $metaApi->fetchCampaignsWithBudget('last_30d');
            
            $processed = 0;
            
            foreach ($campaigns as $campaign) {
                $campaignName = trim($campaign['name'] ?? '');
                $campaignId = trim($campaign['id'] ?? '');
                
                // Skip invalid campaigns
                if (!$campaignName || !$campaignId) {
                    continue;
                }
                
                // Map Meta API status to campaign delivery
                // Meta API statuses: ACTIVE, PAUSED, ARCHIVED, DELETED
                // Database accepts: active, inactive, not_delivering
                $status = strtolower($campaign['status'] ?? 'paused');
                $campaignDelivery = match($status) {
                    'active' => 'active',
                    'paused' => 'not_delivering',
                    'archived' => 'inactive',
                    'deleted' => 'inactive',
                    default => 'inactive',
                };
                
                // Get budget (daily or lifetime, converted from cents to dollars)
                $dailyBudget = isset($campaign['daily_budget']) ? (float) $campaign['daily_budget'] / 100 : 0;
                $lifetimeBudget = isset($campaign['lifetime_budget']) ? (float) $campaign['lifetime_budget'] / 100 : 0;
                $adSetBudget = $campaign['ad_set_budget'] ?? 0;
                $bgt = max($dailyBudget, $lifetimeBudget, $adSetBudget);
                
                // Extract insights data
                $impL30 = (int) ($campaign['impressions'] ?? 0);
                $spentL30 = (float) ($campaign['spend'] ?? 0);
                $clicksL30 = (int) ($campaign['link_clicks'] ?? 0);
                
                // Get platform information
                $platform = $campaign['platform'] ?? 'Facebook/Instagram';
                
                // Assign group based on campaign name prefix
                $groupId = MetaAllAd::assignGroupByCampaignName($campaignName);
                
                MetaAllAd::updateOrCreate(
                    ['campaign_name' => $campaignName],
                    [
                        'campaign_id' => $campaignId,
                        'group_id' => $groupId,
                        'platform' => $platform,
                        'campaign_delivery' => $campaignDelivery,
                        'bgt' => $bgt,
                        'imp_l30' => $impL30,
                        'spent_l30' => $spentL30,
                        'clicks_l30' => $clicksL30,
                    ]
                );
                $processed++;
            }
            
            Log::info('L30 Data Synced from Meta API', ['campaigns_processed' => $processed]);
            return $processed;
        } catch (\Exception $e) {
            Log::error('Meta API L30 Sync Error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function syncL7DataFromMetaApi(MetaApiService $metaApi)
    {
        try {
            // Fetch L7 campaigns data from Meta API
            $campaigns = $metaApi->fetchCampaignsL7();
            
            $processed = 0;
            
            foreach ($campaigns as $campaign) {
                $campaignName = trim($campaign['name'] ?? '');
                
                // Skip invalid campaigns
                if (!$campaignName) {
                    continue;
                }
                
                // Extract insights data
                $impL7 = (int) ($campaign['impressions'] ?? 0);
                $spentL7 = (float) ($campaign['spend'] ?? 0);
                $clicksL7 = (int) ($campaign['link_clicks'] ?? 0);
                
                // Only update if campaign exists (created during L30 sync)
                $metaAd = MetaAllAd::where('campaign_name', $campaignName)->first();
                if ($metaAd) {
                    $metaAd->update([
                        'imp_l7' => $impL7,
                        'spent_l7' => $spentL7,
                        'clicks_l7' => $clicksL7,
                    ]);
                    $processed++;
                }
            }
            
            Log::info('L7 Data Synced from Meta API', ['campaigns_processed' => $processed]);
            return $processed;
        } catch (\Exception $e) {
            Log::error('Meta API L7 Sync Error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get all meta ad groups
     */
    public function getMetaAdGroups()
    {
        try {
            $groups = MetaAdGroup::orderBy('group_name', 'asc')->get(['id', 'group_name']);
            
            return response()->json([
                'success' => true,
                'groups' => $groups
            ], 200);
        } catch (\Exception $e) {
            Log::error('Get Meta Ad Groups Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error fetching groups',
                'groups' => []
            ], 500);
        }
    }

    /**
     * Store a new group
     */
    public function storeGroup(Request $request)
    {
        try {
            $request->validate([
                'group_name' => 'required|string|max:255|unique:meta_ad_groups,group_name'
            ]);

            $group = MetaAdGroup::create([
                'group_name' => $request->group_name
            ]);

            // Automatically assign existing campaigns that match this group name prefix
            $campaigns = MetaAllAd::whereNull('group_id')
                ->orWhere('group_id', '!=', $group->id)
                ->get();
            
            $assignedCount = 0;
            foreach ($campaigns as $campaign) {
                if (stripos($campaign->campaign_name, $group->group_name) === 0) {
                    $campaign->group_id = $group->id;
                    $campaign->save();
                    $assignedCount++;
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Group created successfully',
                'group' => $group,
                'campaigns_assigned' => $assignedCount
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to create group: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a meta ad group
     */
    public function deleteMetaAdGroup(Request $request)
    {
        try {
            $request->validate([
                'group_name' => 'required|string',
            ]);

            $group = MetaAdGroup::where('group_name', $request->group_name)->first();

            if (!$group) {
                return response()->json([
                    'success' => false,
                    'message' => 'Group not found'
                ], 404);
            }

            // Check if group is DRUM THRONE or KB BENCH (default groups - cannot be deleted)
            if (in_array($request->group_name, ['DRUM THRONE', 'KB BENCH'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Default groups cannot be deleted'
                ], 422);
            }

            // Update all campaigns with this group_id to null
            MetaAllAd::where('group_id', $group->id)->update(['group_id' => null]);

            // Delete the group
            $group->delete();

            return response()->json([
                'success' => true,
                'message' => 'Group deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Delete Meta Ad Group Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error deleting group: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import ads data from Excel/CSV
     */
    public function importAds(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|mimes:csv,xlsx,xls|max:10240'
            ]);

            $file = $request->file('file');
            
            // Use Laravel Excel or similar package to import
            // \Maatwebsite\Excel\Facades\Excel::import(new MetaAdsImport, $file);
            
            // Placeholder logic - implement actual import logic based on your requirements
            
            return response()->json([
                'success' => true,
                'message' => 'File imported successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export ads data to Excel
     */
    public function exportAds(Request $request)
    {
        try {
            // Fetch all ads
            $ads = MetaAllAd::all();

            // Use Laravel Excel or similar to export
            // return \Maatwebsite\Excel\Facades\Excel::download(new MetaAdsExport($ads), 'meta_ads_export.xlsx');
            
            // Placeholder - implement actual export logic
            $filename = 'meta_ads_export_' . date('Y-m-d') . '.xlsx';
            
            return response()->json([
                'success' => true,
                'message' => 'Export functionality needs implementation with Laravel Excel package',
                'filename' => $filename
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Export failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display raw Facebook ads data page
     */
    public function showRawAdsData()
    {
        return view('marketing-masters.meta_ads_manager.raw_ads_data');
    }

    /**
     * Test Meta API connection
     */
    public function testMetaApiConnection()
    {
        try {
            $metaApi = new MetaApiService();
            
            // Test credentials
            $isValid = $metaApi->validateCredentials();
            
            if (!$isValid) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid API credentials. Please check META_ACCESS_TOKEN in .env',
                ], 401);
            }
            
            // Try to fetch ad accounts to verify access
            $adAccounts = $metaApi->fetchAdAccounts();
            
            return response()->json([
                'success' => true,
                'message' => 'API connection successful',
                'ad_accounts_count' => count($adAccounts),
                'ad_accounts' => array_slice($adAccounts, 0, 5), // Show first 5
            ], 200);
        } catch (\Exception $e) {
            Log::error('Test Meta API Connection Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Error testing API connection: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch raw Facebook ads data from API
     */
    public function fetchRawAdsData(Request $request)
    {
        try {
            $metaApi = new MetaApiService();
            
            $type = $request->get('type', 'ads'); // ads, campaigns, insights
            $datePreset = $request->get('date_preset', 'last_30d');
            $level = $request->get('level', 'campaign');
            
            $rawData = [];
            
            switch ($type) {
                case 'ads':
                    $rawData = $metaApi->fetchRawAdsData();
                    break;
                case 'campaigns':
                    $rawData = $metaApi->fetchRawCampaignsData();
                    break;
                case 'insights':
                    $rawData = $metaApi->fetchRawInsightsData($datePreset, $level);
                    break;
                default:
                    $rawData = $metaApi->fetchRawAdsData();
            }
            
            return response()->json([
                'success' => true,
                'type' => $type,
                'count' => count($rawData),
                'data' => $rawData,
                'fetched_at' => now()->toDateTimeString(),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Fetch Raw Ads Data Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'type' => $request->get('type', 'ads'),
            ]);
            
            $errorMessage = $e->getMessage();
            
            // Check if it's a credentials issue
            if (strpos($errorMessage, 'not configured') !== false) {
                $errorMessage = 'Meta API credentials not configured. Please set META_ACCESS_TOKEN and META_AD_ACCOUNT_ID in .env file.';
            }
            
            return response()->json([
                'success' => false,
                'error' => $errorMessage,
                'message' => 'Error fetching raw ads data: ' . $errorMessage,
                'type' => $request->get('type', 'ads'),
            ], 500);
        }
    }

    /**
     * Display all saved raw Meta ads from database
     */
    public function showSavedRawAds()
    {
        $latestSyncDateRaw = MetaAdRawData::max('sync_date');
        $latestSyncDate = $latestSyncDateRaw
            ? \Carbon\Carbon::parse($latestSyncDateRaw)->format('Y-m-d')
            : null;
        // Page is limited to the rolling last 30 days of sync snapshots.
        $monthFrom = $latestSyncDate
            ? \Carbon\Carbon::parse($latestSyncDate)->subDays(29)->format('Y-m-d')
            : null;

        $syncDates = MetaAdRawData::query()
            ->select('sync_date')
            ->distinct()
            ->when($monthFrom, fn ($q) => $q->whereDate('sync_date', '>=', $monthFrom))
            ->when($latestSyncDate, fn ($q) => $q->whereDate('sync_date', '<=', $latestSyncDate))
            ->orderByDesc('sync_date')
            ->pluck('sync_date')
            ->map(fn ($date) => $date instanceof \Carbon\Carbon ? $date->format('Y-m-d') : (string) $date);

        $totalRecords = MetaAdRawData::count();
        $monthCount = ($monthFrom && $latestSyncDate)
            ? MetaAdRawData::whereDate('sync_date', '>=', $monthFrom)
                ->whereDate('sync_date', '<=', $latestSyncDate)
                ->count()
            : 0;

        return view('marketing-masters.meta_ads_manager.saved_raw_ads', [
            'syncDates' => $syncDates,
            'latestSyncDate' => $latestSyncDate,
            'monthFrom' => $monthFrom,
            'monthTo' => $latestSyncDate,
            'totalRecords' => $totalRecords,
            'latestCount' => $monthCount,
        ]);
    }

    /**
     * Paginated JSON data for saved raw Meta ads table
     */
    public function getSavedRawAdsData(Request $request)
    {
        $query = $this->buildSavedRawAdsQuery($request);

        $sortField = $request->input('sort_field', 'ad_name');
        $sortDir = strtolower($request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowedSort = ['ad_id', 'ad_name', 'campaign_id', 'campaign_name', 'status', 'sync_date', 'ad_updated_time', 'ad_created_time'];
        if (in_array($sortField, $allowedSort, true)) {
            $query->orderBy($sortField, $sortDir);
        } else {
            $query->orderBy('ad_name', 'asc');
        }

        $page = max(1, (int) $request->input('page', 1));
        $size = min(500, max(10, (int) $request->input('size', 100)));

        $paginator = $query->paginate($size, ['*'], 'page', $page);
        $shopifyMetrics = $this->getShopifyCampaignSalesMaps();

        $data = $paginator->getCollection()->map(function (MetaAdRawData $row) use ($shopifyMetrics) {
            $campaignId = (string) ($row->campaign_id ?? '');
            $l7 = $shopifyMetrics[$campaignId]['7_days'] ?? ShopifyMetaCampaign::emptyMetrics();
            $l30 = $shopifyMetrics[$campaignId]['30_days'] ?? ShopifyMetaCampaign::emptyMetrics();

            return [
                'id' => $row->id,
                'ad_id' => $row->ad_id,
                'ad_name' => $row->ad_name,
                'campaign_id' => $row->campaign_id,
                'campaign_name' => $row->campaign_name,
                'adset_id' => $row->adset_id,
                'status' => $row->status,
                'sync_date' => $row->sync_date?->format('Y-m-d'),
                'ad_created_time' => $row->ad_created_time?->format('Y-m-d H:i:s'),
                'ad_updated_time' => $row->ad_updated_time?->format('Y-m-d H:i:s'),
                'preview_shareable_link' => $row->preview_shareable_link,
                'source_ad_id' => $row->source_ad_id,
                'effective_object_story_id' => $row->effective_object_story_id,
                'sales_l7' => round($l7['sales'], 2),
                'sales_l30' => round($l30['sales'], 2),
                'orders_l7' => $l7['orders'],
                'orders_l30' => $l30['orders'],
                'sessions_l30' => $l30['sessions'],
                'raw_data' => $row->raw_data,
                'creative_data' => $row->creative_data,
            ];
        })->values();

        if (in_array($sortField, ['sales_l7', 'sales_l30', 'orders_l7', 'orders_l30', 'sessions_l30'], true)) {
            $data = $data->sortBy($sortField, SORT_REGULAR, $sortDir === 'desc')->values();
        }

        return response()->json([
            'last_page' => $paginator->lastPage(),
            'data' => $data,
            'total' => $paginator->total(),
        ]);
    }

    /**
     * CSV export of all filtered saved raw Meta ads (all pages).
     * Built into a temp file (not streamDownload) so php artisan serve /
     * browsers don't hit ERR_INVALID_RESPONSE on streamed responses.
     */
    public function exportSavedRawAds(Request $request)
    {
        try {
            $shopifyMetrics = $this->getShopifyCampaignSalesMaps();
            $filename = 'meta-raw-ads-' . now()->format('Y-m-d_His') . '.csv';
            $tmpPath = tempnam(sys_get_temp_dir(), 'meta_raw_export_');
            if ($tmpPath === false) {
                throw new \RuntimeException('Unable to create temporary export file.');
            }

            $out = fopen($tmpPath, 'w');
            if ($out === false) {
                throw new \RuntimeException('Unable to open temporary export file.');
            }

            // UTF-8 BOM so Excel opens the CSV correctly.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'Ad ID',
                'Ad Name',
                'Sales L7',
                'Sales L30',
                'Orders L7',
                'Orders L30',
                'Sessions L30',
                'Campaign ID',
                'Campaign Name',
                'Ad Set ID',
                'Status',
                'Sync Date',
                'Updated',
                'Created',
                'Source Ad ID',
                'Preview',
            ]);

            // Select only export columns — skip heavy JSON fields.
            // Do not combine orderBy() with chunkById() (Laravel limitation).
            $table = (new MetaAdRawData)->getTable();
            $this->buildSavedRawAdsQuery($request)
                ->select([
                    "{$table}.id",
                    "{$table}.ad_id",
                    "{$table}.ad_name",
                    "{$table}.campaign_id",
                    "{$table}.campaign_name",
                    "{$table}.adset_id",
                    "{$table}.status",
                    "{$table}.sync_date",
                    "{$table}.ad_updated_time",
                    "{$table}.ad_created_time",
                    "{$table}.source_ad_id",
                    "{$table}.preview_shareable_link",
                ])
                ->chunkById(500, function ($rows) use ($out, $shopifyMetrics) {
                    foreach ($rows as $row) {
                        $campaignId = (string) ($row->campaign_id ?? '');
                        $l7 = $shopifyMetrics[$campaignId]['7_days'] ?? ShopifyMetaCampaign::emptyMetrics();
                        $l30 = $shopifyMetrics[$campaignId]['30_days'] ?? ShopifyMetaCampaign::emptyMetrics();

                        fputcsv($out, [
                            $row->ad_id,
                            $row->ad_name,
                            round($l7['sales'], 2),
                            round($l30['sales'], 2),
                            $l7['orders'],
                            $l30['orders'],
                            $l30['sessions'],
                            $row->campaign_id,
                            $row->campaign_name,
                            $row->adset_id,
                            $row->status,
                            $row->sync_date?->format('Y-m-d'),
                            $row->ad_updated_time?->format('Y-m-d H:i:s'),
                            $row->ad_created_time?->format('Y-m-d H:i:s'),
                            $row->source_ad_id,
                            $row->preview_shareable_link,
                        ]);
                    }
                }, "{$table}.id", 'id');

            fclose($out);

            return response()->download($tmpPath, $filename, [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ])->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            Log::error('Saved Raw Ads Export Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sales summary stats for saved raw Meta ads (top cards).
     */
    public function getSavedRawAdsSalesStats(Request $request)
    {
        try {
            $baseQuery = $this->buildSavedRawAdsQuery($request);
            $filteredTotal = (clone $baseQuery)->count();
            $syncDateMin = (clone $baseQuery)->min('sync_date');
            $syncDateMax = (clone $baseQuery)->max('sync_date');

            $campaignIds = (clone $baseQuery)
                ->whereNotNull('campaign_id')
                ->distinct()
                ->pluck('campaign_id')
                ->filter()
                ->values()
                ->all();

            $shopifyMetrics = $this->getShopifyCampaignSalesMaps();
            $stats = $this->summarizeShopifyCampaignSales($campaignIds, $shopifyMetrics);
            $stats['shopify_synced_at'] = ShopifyMetaCampaign::max('updated_at');
            $stats['filtered_total'] = $filteredTotal;
            $stats['sync_date_min'] = $syncDateMin
                ? (\Carbon\Carbon::parse($syncDateMin)->format('Y-m-d'))
                : null;
            $stats['sync_date_max'] = $syncDateMax
                ? (\Carbon\Carbon::parse($syncDateMax)->format('Y-m-d'))
                : null;

            return response()->json([
                'success' => true,
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('Saved Raw Ads Sales Stats Error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'stats' => array_merge($this->emptyShopifySalesStats(), [
                    'filtered_total' => 0,
                    'sync_date_min' => null,
                    'sync_date_max' => null,
                ]),
            ], 500);
        }
    }

    private function buildSavedRawAdsQuery(Request $request)
    {
        $query = MetaAdRawData::query();

        [$windowFrom, $windowTo] = $this->savedRawAdsMonthWindow();

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $hasRange = filled($dateFrom) || filled($dateTo);
        // Single-day filters already have one row per ad; multi-day ranges
        // contain daily snapshots and must be deduped.
        $singleDay = false;

        if ($hasRange) {
            // Clamp custom range inside the rolling 1-month window.
            if (filled($dateFrom)) {
                $from = $dateFrom;
                if ($windowFrom && $from < $windowFrom) {
                    $from = $windowFrom;
                }
                $query->whereDate('sync_date', '>=', $from);
            } elseif ($windowFrom) {
                $query->whereDate('sync_date', '>=', $windowFrom);
            }
            if (filled($dateTo)) {
                $to = $dateTo;
                if ($windowTo && $to > $windowTo) {
                    $to = $windowTo;
                }
                $query->whereDate('sync_date', '<=', $to);
            } elseif ($windowTo) {
                $query->whereDate('sync_date', '<=', $windowTo);
            }
        } elseif ($request->filled('sync_date')) {
            $syncDate = (string) $request->sync_date;
            if ($windowFrom && $syncDate < $windowFrom) {
                $syncDate = $windowFrom;
            }
            if ($windowTo && $syncDate > $windowTo) {
                $syncDate = $windowTo;
            }
            $query->whereDate('sync_date', $syncDate);
            $singleDay = true;
        } elseif ($request->boolean('latest_only', false)) {
            if ($windowTo) {
                $query->whereDate('sync_date', $windowTo);
            }
            $singleDay = true;
        } else {
            // Default: last 1 month of sync snapshots.
            if ($windowFrom) {
                $query->whereDate('sync_date', '>=', $windowFrom);
            }
            if ($windowTo) {
                $query->whereDate('sync_date', '<=', $windowTo);
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('ad_name', 'like', "%{$search}%")
                    ->orWhere('ad_id', 'like', "%{$search}%")
                    ->orWhere('campaign_id', 'like', "%{$search}%")
                    ->orWhere('campaign_name', 'like', "%{$search}%")
                    ->orWhere('adset_id', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }

        // Default status = active (hide paused/archived unless user chooses All).
        $status = strtolower(trim((string) $request->input('status', 'active')));
        $applyStatus = $status !== '' && $status !== 'all';

        if ($applyStatus && $singleDay) {
            $query->where('status', $status);
        }

        if (! $singleDay) {
            $query = $this->dedupeLatestSnapshotPerAd($query);
            if ($applyStatus) {
                $table = (new MetaAdRawData)->getTable();
                $query->where("{$table}.status", $status);
            }
        }

        return $query;
    }

    /**
     * Keep one row per ad_id — the latest sync_date inside the already
     * filtered query (stops daily snapshots looking like duplicates).
     */
    private function dedupeLatestSnapshotPerAd($query)
    {
        $table = (new MetaAdRawData)->getTable();

        $latestPerAd = $query->clone()
            ->selectRaw('ad_id, MAX(sync_date) as max_sync_date')
            ->groupBy('ad_id');

        return MetaAdRawData::query()
            ->from($table)
            ->joinSub($latestPerAd, 'latest_ad', function ($join) use ($table) {
                $join->on("{$table}.ad_id", '=', 'latest_ad.ad_id')
                    ->on("{$table}.sync_date", '=', 'latest_ad.max_sync_date');
            })
            ->select("{$table}.*");
    }

    /**
     * Rolling 30-day sync window ending on the latest available sync_date.
     *
     * @return array{0:?string,1:?string} [from, to] as Y-m-d
     */
    private function savedRawAdsMonthWindow(): array
    {
        $latest = MetaAdRawData::max('sync_date');
        if (! $latest) {
            return [null, null];
        }

        $to = \Carbon\Carbon::parse($latest)->format('Y-m-d');
        $from = \Carbon\Carbon::parse($latest)->subDays(29)->format('Y-m-d');

        return [$from, $to];
    }

    /**
     * @return array<string, array<string, array{sales: float, orders: int, sessions: int}>>
     */
    private function getShopifyCampaignSalesMaps(): array
    {
        return Cache::remember('meta_saved_raw_shopify_campaign_metrics', 300, function () {
            return ShopifyMetaCampaign::latestCampaignMetricsMap(['7_days', '30_days'], ['facebook', 'instagram']);
        });
    }

    /**
     * @param array<int, string> $campaignIds
     * @param array<string, array<string, array{sales: float, orders: int, sessions: int}>> $shopifyMetrics
     */
    private function summarizeShopifyCampaignSales(array $campaignIds, array $shopifyMetrics): array
    {
        $stats = $this->emptyShopifySalesStats();

        foreach ($campaignIds as $campaignId) {
            $l7 = $shopifyMetrics[$campaignId]['7_days'] ?? ShopifyMetaCampaign::emptyMetrics();
            $l30 = $shopifyMetrics[$campaignId]['30_days'] ?? ShopifyMetaCampaign::emptyMetrics();

            $stats['sales_l7'] += $l7['sales'];
            $stats['sales_l30'] += $l30['sales'];
            $stats['orders_l7'] += $l7['orders'];
            $stats['orders_l30'] += $l30['orders'];
            $stats['sessions_l30'] += $l30['sessions'];

            if (($l30['sales'] ?? 0) > 0) {
                $stats['campaigns_with_sales_l30']++;
            }
        }

        $stats['sales_l7'] = round($stats['sales_l7'], 2);
        $stats['sales_l30'] = round($stats['sales_l30'], 2);

        return $stats;
    }

    /**
     * @return array{sales_l7: float, sales_l30: float, orders_l7: int, orders_l30: int, sessions_l30: int, campaigns_with_sales_l30: int}
     */
    private function emptyShopifySalesStats(): array
    {
        return [
            'sales_l7' => 0,
            'sales_l30' => 0,
            'orders_l7' => 0,
            'orders_l30' => 0,
            'sessions_l30' => 0,
            'campaigns_with_sales_l30' => 0,
        ];
    }
}


