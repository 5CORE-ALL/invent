<?php

namespace App\Http\Controllers\MarketingMaster;

use App\Http\Controllers\Controller;
use App\Models\MetaAdAccount;
use App\Models\MetaCampaign;
use App\Models\MetaAdSet;
use App\Models\MetaAd;
use App\Models\MetaInsightDaily;
use App\Models\MetaSyncRun;
use App\Models\MetaActionLog;
use App\Models\MetaAutomationRule;
use App\Models\MetaCampaignGroup;
use App\Models\MetaCampaignAdType;
use App\Models\ProductMaster;
use App\Services\MetaAdsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MetaAdsManagerController extends Controller
{
    protected $metaAdsService;

    public function __construct(MetaAdsService $metaAdsService)
    {
        $this->metaAdsService = $metaAdsService;
    }

    /**
     * Dashboard view
     */
    public function dashboard(Request $request)
    {
        $userId = Auth::id();
        $dateStart = $request->get('date_start', Carbon::now()->subDays(30)->format('Y-m-d'));
        $dateEnd = $request->get('date_end', Carbon::now()->format('Y-m-d'));
        $adAccountId = $request->get('ad_account_id');

        // Calculate previous period for comparison
        $daysDiff = Carbon::parse($dateStart)->diffInDays(Carbon::parse($dateEnd));
        $prevDateStart = Carbon::parse($dateStart)->subDays($daysDiff + 1)->format('Y-m-d');
        $prevDateEnd = Carbon::parse($dateStart)->subDay()->format('Y-m-d');

        // Get KPIs for current period
        $currentKPIs = $this->getKPIs($userId, $dateStart, $dateEnd, $adAccountId);
        
        // If no data for current user, try to get all data (fallback for server)
        $hasData = array_sum($currentKPIs) > 0;
        if (!$hasData && $userId) {
            Log::info('Meta Ads Dashboard: No data for user, trying fallback', [
                'user_id' => $userId,
                'date_start' => $dateStart,
                'date_end' => $dateEnd,
            ]);
            // Try without user filter as fallback
            $currentKPIs = $this->getKPIs(null, $dateStart, $dateEnd, $adAccountId);
            Log::info('Meta Ads Dashboard: Fallback data', [
                'has_data' => array_sum($currentKPIs) > 0,
                'kpis' => $currentKPIs,
            ]);
        }
        
        // Get KPIs for previous period
        $previousKPIs = $this->getKPIs($userId, $prevDateStart, $prevDateEnd, $adAccountId);
        
        // If no previous data, try without user filter
        $hasPrevData = array_sum($previousKPIs) > 0;
        if (!$hasPrevData && $userId) {
            $previousKPIs = $this->getKPIs(null, $prevDateStart, $prevDateEnd, $adAccountId);
        }

        // Calculate changes
        $changes = [];
        foreach ($currentKPIs as $key => $value) {
            $prevValue = $previousKPIs[$key] ?? 0;
            $change = $prevValue > 0 ? (($value - $prevValue) / $prevValue) * 100 : 0;
            $changes[$key] = round($change, 2);
        }

        // Get ad accounts - show user's accounts first, then all if none found
        $adAccounts = MetaAdAccount::when($userId, fn($q) => $q->where('user_id', $userId))->get();
        if ($adAccounts->isEmpty() && $userId) {
            // Fallback: show all accounts if user has none
            $adAccounts = MetaAdAccount::all();
        }

        return view('marketing-masters.meta_ads_manager.dashboard', [
            'currentKPIs' => $currentKPIs,
            'previousKPIs' => $previousKPIs,
            'changes' => $changes,
            'adAccounts' => $adAccounts,
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
            'selectedAdAccountId' => $adAccountId,
        ]);
    }

    /**
     * Get KPIs for a date range
     */
    protected function getKPIs($userId, $dateStart, $dateEnd, $adAccountId = null)
    {
        $query = MetaInsightDaily::whereBetween('date_start', [$dateStart, $dateEnd]);
        
        // Filter by user_id if provided, otherwise get all
        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        if ($adAccountId) {
            $account = MetaAdAccount::find($adAccountId);
            if ($account) {
                // Filter by ad account through relationships
                $campaignIds = MetaCampaign::where('ad_account_id', $account->id)->pluck('id');
                $adsetIds = MetaAdSet::whereIn('campaign_id', $campaignIds)->pluck('id');
                $adIds = MetaAd::whereIn('adset_id', $adsetIds)->pluck('id');

                $query->where(function($q) use ($campaignIds, $adsetIds, $adIds) {
                    $q->where(function($q2) use ($campaignIds) {
                        $q2->where('entity_type', 'campaign')
                           ->whereIn('entity_id', $campaignIds);
                    })->orWhere(function($q2) use ($adsetIds) {
                        $q2->where('entity_type', 'adset')
                           ->whereIn('entity_id', $adsetIds);
                    })->orWhere(function($q2) use ($adIds) {
                        $q2->where('entity_type', 'ad')
                           ->whereIn('entity_id', $adIds);
                    });
                });
            }
        }

        $insights = $query->get();

        return [
            'spend' => $insights->sum('spend'),
            'impressions' => $insights->sum('impressions'),
            'clicks' => $insights->sum('clicks'),
            'ctr' => $insights->sum('clicks') > 0 ? ($insights->sum('impressions') > 0 ? ($insights->sum('clicks') / $insights->sum('impressions')) * 100 : 0) : 0,
            'cpc' => $insights->sum('clicks') > 0 ? ($insights->sum('spend') / $insights->sum('clicks')) : 0,
            'cpm' => $insights->sum('impressions') > 0 ? ($insights->sum('spend') / $insights->sum('impressions')) * 1000 : 0,
            'purchases' => $insights->sum('purchases'),
            'purchase_roas' => $insights->sum('spend') > 0 ? ($insights->sum('action_values') / $insights->sum('spend')) : 0,
            'cpa' => $insights->sum('purchases') > 0 ? ($insights->sum('spend') / $insights->sum('purchases')) : 0,
        ];
    }

    /**
     * Accounts list view
     */
    public function accounts()
    {
        return view('marketing-masters.meta_ads_manager.accounts');
    }

    /**
     * Accounts data (JSON for DataTables)
     */
    public function accountsData(Request $request)
    {
        try {
            $userId = Auth::id();
            
            Log::info('MetaAdsManagerController: accountsData called', [
                'user_id' => $userId,
                'auth_check' => Auth::check(),
            ]);
            
            // For now, show all accounts to authenticated users
            // TODO: Implement proper access control based on business rules
            // Option 1: Show user's accounts + shared accounts (null user_id)
            // Option 2: Show all accounts if user is admin
            // Option 3: Show all accounts to all authenticated users (current implementation)
            
            if ($userId) {
                // Show user's accounts OR shared accounts OR all accounts for now
                // You can restrict this later based on your access control requirements
                $accounts = MetaAdAccount::where(function($query) use ($userId) {
                    $query->where('user_id', $userId)
                          ->orWhereNull('user_id');
                })
                ->orderBy('name', 'asc')
                ->get();
                
                // If user has no accounts and no shared accounts, show all accounts
                // This is a temporary fix until proper access control is implemented
                if ($accounts->count() === 0) {
                    $accounts = MetaAdAccount::orderBy('name', 'asc')->get();
                }
            } else {
                // If no user, show only shared accounts
                $accounts = MetaAdAccount::whereNull('user_id')
                    ->orderBy('name', 'asc')
                    ->get();
            }

            Log::info('MetaAdsManagerController: accountsData query result', [
                'accounts_found' => $accounts->count(),
            ]);

            $data = [];
            foreach ($accounts as $account) {
                $data[] = [
                    'id' => $account->id,
                    'meta_id' => $account->meta_id,
                    'name' => $account->name ?? 'N/A',
                    'account_status' => $account->account_status ?? 'N/A',
                    'currency' => $account->currency ?? 'N/A',
                    'timezone' => $account->timezone_name ?? 'N/A',
                    'campaigns_count' => $account->campaigns()->count(),
                    'synced_at' => $account->synced_at ? $account->synced_at->format('Y-m-d H:i:s') : null,
                ];
            }

            Log::info('MetaAdsManagerController: accountsData response', [
                'user_id' => $userId,
                'accounts_count' => count($data),
            ]);

            return response()->json(['data' => $data]);
        } catch (\Exception $e) {
            Log::error('MetaAdsManagerController: accountsData error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['data' => [], 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Campaigns list view
     */
    public function campaigns(Request $request)
    {
        $adAccountId = $request->get('ad_account_id');
        $adAccounts = MetaAdAccount::when(Auth::id(), fn($q) => $q->where('user_id', Auth::id()))->get();
        
        // Get predefined groups and custom groups
        $predefinedGroups = $this->getPredefinedGroups();
        $customGroups = MetaCampaignGroup::orderBy('name')->pluck('name')->toArray();
        $allGroups = array_merge($predefinedGroups, $customGroups);
        
        // Get unique parent values from productmaster table that start with 'PARENT'
        $parents = ProductMaster::whereNotNull('parent')
            ->where('parent', '!=', '')
            ->distinct()
            ->orderBy('parent')
            ->pluck('parent')
            ->toArray();
        
        // Get predefined ad types and custom ad types
        $predefinedAdTypes = $this->getPredefinedAdTypes();
        $customAdTypes = MetaCampaignAdType::orderBy('name')->pluck('name')->toArray();
        $allAdTypes = array_merge($predefinedAdTypes, $customAdTypes);

        return view('marketing-masters.meta_ads_manager.campaigns', [
            'adAccounts' => $adAccounts,
            'selectedAdAccountId' => $adAccountId,
            'groups' => $allGroups,
            'parents' => $parents,
            'adTypes' => $allAdTypes,
        ]);
    }
    
    /**
     * Get predefined groups list
     */
    private function getPredefinedGroups()
    {
        return [
            'DRUM THRONE',
            'KEYBOARD & PIANO BENCHES',
            'DYNAMIC MICROPHONES',
            'MIC STAND',
            'FLOOR GUITAR STANDS',
            'SPEAKER STANDS',
            'ALL MICROPHONES',
            'INSTRUMENT MICS',
            'WIRELESS MICS',
            'ALL STANDS',
            'STOOLS AND BENCHES',
            'GUITAR ACCESSORIES',
            'MIXERS',
        ];
    }
    
    /**
     * Get predefined ad types list
     */
    private function getPredefinedAdTypes()
    {
        return [
            'IN GRP CAR IMG',
            'IN GRP VID',
            'IN PAR CAR IMG',
            'IN PAR VID',
            'FB GRP CAR IMG',
            'FB GRP VID',
            'FB PAR CAR IMG',
            'FB PAR VID',
        ];
    }

    /**
     * Campaigns data (JSON for DataTables)
     */
    public function campaignsData(Request $request)
    {
        $userId = Auth::id();
        $adAccountId = $request->get('ad_account_id');

        $query = MetaCampaign::when($userId, fn($q) => $q->where('user_id', $userId))
            ->when($adAccountId, function($q) use ($adAccountId) {
                $account = MetaAdAccount::find($adAccountId);
                if ($account) {
                    $q->where('ad_account_id', $account->id);
                }
            })
            ->with('adAccount')
            ->orderBy('name', 'asc');

        $campaigns = $query->get();

        $data = [];
        foreach ($campaigns as $campaign) {
            // Get latest insights
            $insight = $campaign->insights()->latest('date_start')->first();
            
            $data[] = [
                'id' => $campaign->id,
                'meta_id' => $campaign->meta_id,
                'ad_type' => $campaign->ad_type,
                'group' => $campaign->group,
                'parent' => $campaign->parent,
                'name' => $campaign->name,
                'status' => $campaign->status,
                'effective_status' => $campaign->effective_status,
                'objective' => $campaign->objective,
                'daily_budget' => $campaign->daily_budget,
                'lifetime_budget' => $campaign->lifetime_budget,
                'adsets_count' => $campaign->adsets()->count(),
                'ads_count' => $campaign->ads()->count(),
                'spend' => $insight?->spend ?? 0,
                'impressions' => $insight?->impressions ?? 0,
                'clicks' => $insight?->clicks ?? 0,
                'ctr' => $insight?->ctr ?? 0,
                'cpc' => $insight?->cpc ?? 0,
            ];
        }

        return response()->json(['data' => $data]);
    }

    /**
     * AdSets list view
     */
    public function adsets(Request $request)
    {
        $campaignId = $request->get('campaign_id');
        $campaigns = MetaCampaign::when(Auth::id(), fn($q) => $q->where('user_id', Auth::id()))->get();

        return view('marketing-masters.meta_ads_manager.adsets', [
            'campaigns' => $campaigns,
            'selectedCampaignId' => $campaignId,
        ]);
    }

    /**
     * AdSets data (JSON for DataTables)
     */
    public function adsetsData(Request $request)
    {
        $userId = Auth::id();
        $campaignId = $request->get('campaign_id');

        $query = MetaAdSet::when($userId, fn($q) => $q->where('user_id', $userId))
            ->when($campaignId, fn($q) => $q->where('campaign_id', $campaignId))
            ->with('campaign')
            ->orderBy('name', 'asc');

        $adsets = $query->get();

        $data = [];
        foreach ($adsets as $adset) {
            $insight = $adset->insights()->latest('date_start')->first();
            
            $data[] = [
                'id' => $adset->id,
                'meta_id' => $adset->meta_id,
                'name' => $adset->name,
                'status' => $adset->status,
                'effective_status' => $adset->effective_status,
                'campaign_name' => $adset->campaign?->name,
                'daily_budget' => $adset->daily_budget,
                'lifetime_budget' => $adset->lifetime_budget,
                'ads_count' => $adset->ads()->count(),
                'spend' => $insight?->spend ?? 0,
                'impressions' => $insight?->impressions ?? 0,
                'clicks' => $insight?->clicks ?? 0,
            ];
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Ads list view
     */
    public function ads(Request $request)
    {
        $adsetId = $request->get('adset_id');
        $adsets = MetaAdSet::when(Auth::id(), fn($q) => $q->where('user_id', Auth::id()))
            ->orderBy('name', 'asc')
            ->get();

        return view('marketing-masters.meta_ads_manager.ads', [
            'adsets' => $adsets,
            'selectedAdsetId' => $adsetId,
        ]);
    }

    /**
     * Ads data (JSON for DataTables)
     */
    public function adsData(Request $request)
    {
        $userId = Auth::id();
        $adsetId = $request->get('adset_id');

        $query = MetaAd::when($userId, fn($q) => $q->where('user_id', $userId))
            ->when($adsetId, fn($q) => $q->where('adset_id', $adsetId))
            ->with(['adset', 'campaign'])
            ->orderBy('name', 'asc');

        $ads = $query->get();

        $data = [];
        foreach ($ads as $ad) {
            $insight = $ad->insights()->latest('date_start')->first();
            
            $data[] = [
                'id' => $ad->id,
                'meta_id' => $ad->meta_id,
                'name' => $ad->name,
                'status' => $ad->status,
                'effective_status' => $ad->effective_status,
                'campaign_name' => $ad->campaign?->name,
                'adset_name' => $ad->adset?->name,
                'preview_link' => $ad->preview_shareable_link,
                'spend' => $insight?->spend ?? 0,
                'impressions' => $insight?->impressions ?? 0,
                'clicks' => $insight?->clicks ?? 0,
                'ctr' => $insight?->ctr ?? 0,
                'cpc' => $insight?->cpc ?? 0,
            ];
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Update entity status (pause/resume)
     */
    public function updateStatus(Request $request)
    {
        $request->validate([
            'entity_type' => 'required|in:campaign,adset,ad',
            'entity_meta_id' => 'required|string',
            'status' => 'required|in:PAUSED,ACTIVE',
        ]);

        try {
            $this->metaAdsService->updateStatus(
                $request->entity_type,
                $request->entity_meta_id,
                $request->status,
                Auth::id()
            );

            return response()->json([
                'success' => true,
                'message' => ucfirst($request->entity_type) . ' status updated successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('MetaAdsManagerController: Failed to update status', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update entity budget
     */
    public function updateBudget(Request $request)
    {
        $request->validate([
            'entity_type' => 'required|in:campaign,adset',
            'entity_meta_id' => 'required|string',
            'daily_budget' => 'nullable|numeric|min:0',
            'lifetime_budget' => 'nullable|numeric|min:0',
        ]);

        try {
            $budgetData = array_filter([
                'daily_budget' => $request->daily_budget ? (int)($request->daily_budget * 100) : null, // Convert to cents
                'lifetime_budget' => $request->lifetime_budget ? (int)($request->lifetime_budget * 100) : null,
            ]);

            if (empty($budgetData)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Either daily_budget or lifetime_budget must be provided',
                ], 400);
            }

            $this->metaAdsService->updateBudget(
                $request->entity_type,
                $request->entity_meta_id,
                $budgetData,
                Auth::id()
            );

            return response()->json([
                'success' => true,
                'message' => ucfirst($request->entity_type) . ' budget updated successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('MetaAdsManagerController: Failed to update budget', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update budget: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk update entities
     */
    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'entity_type' => 'required|in:campaign,adset,ad',
            'updates' => 'required|array',
            'updates.*.id' => 'required|string',
        ]);

        try {
            $results = $this->metaAdsService->bulkUpdate(
                $request->entity_type,
                $request->updates,
                Auth::id()
            );

            return response()->json([
                'success' => true,
                'message' => 'Bulk update completed',
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('MetaAdsManagerController: Failed bulk update', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed bulk update: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Automation rules view
     */
    public function automation()
    {
        $rules = MetaAutomationRule::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();

        return view('marketing-masters.meta_ads_manager.automation', [
            'rules' => $rules,
        ]);
    }

    /**
     * Show create rule form
     */
    public function createRule()
    {
        $accounts = MetaAdAccount::where('user_id', Auth::id())
            ->orWhereNull('user_id')
            ->orderBy('name', 'asc')
            ->get();

        return view('marketing-masters.meta_ads_manager.create_rule', [
            'accounts' => $accounts,
        ]);
    }

    /**
     * Store new automation rule
     */
    public function storeRule(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'entity_type' => 'required|in:campaign,adset,ad',
            'schedule' => 'nullable|string|max:100',
            'conditions' => 'required|array',
            'conditions.*.field' => 'required|string',
            'conditions.*.operator' => 'required|string',
            'conditions.*.value' => 'required',
            'conditions.*.type' => 'nullable|string',
            'conditions.*.aggregation' => 'nullable|string',
            'actions' => 'required|array|min:1',
            'actions.*.type' => 'required|string',
            'actions.*.value' => 'nullable',
        ]);

        $rule = MetaAutomationRule::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'entity_type' => $validated['entity_type'],
            'conditions' => $validated['conditions'],
            'actions' => $validated['actions'],
            'is_active' => $request->has('is_active') && $request->input('is_active') == '1',
            'schedule' => $validated['schedule'] ?? 'daily',
            'dry_run_mode' => $request->has('dry_run_mode') && $request->input('dry_run_mode') == '1',
        ]);

        return redirect()->route('meta.ads.manager.automation')
            ->with('success', 'Automation rule created successfully!');
    }

    /**
     * Show edit rule form
     */
    public function editRule($id)
    {
        $rule = MetaAutomationRule::where('user_id', Auth::id())
            ->findOrFail($id);

        $accounts = MetaAdAccount::where('user_id', Auth::id())
            ->orWhereNull('user_id')
            ->orderBy('name', 'asc')
            ->get();

        return view('marketing-masters.meta_ads_manager.edit_rule', [
            'rule' => $rule,
            'accounts' => $accounts,
        ]);
    }

    /**
     * Update automation rule
     */
    public function updateRule(Request $request, $id)
    {
        $rule = MetaAutomationRule::where('user_id', Auth::id())
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'entity_type' => 'required|in:campaign,adset,ad',
            'schedule' => 'nullable|string|max:100',
            'conditions' => 'required|array',
            'conditions.*.field' => 'required|string',
            'conditions.*.operator' => 'required|string',
            'conditions.*.value' => 'required',
            'conditions.*.type' => 'nullable|string',
            'conditions.*.aggregation' => 'nullable|string',
            'actions' => 'required|array|min:1',
            'actions.*.type' => 'required|string',
            'actions.*.value' => 'nullable',
        ]);

        $rule->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'entity_type' => $validated['entity_type'],
            'conditions' => $validated['conditions'],
            'actions' => $validated['actions'],
            'is_active' => $request->has('is_active') && $request->input('is_active') == '1',
            'schedule' => $validated['schedule'] ?? 'daily',
            'dry_run_mode' => $request->has('dry_run_mode') && $request->input('dry_run_mode') == '1',
        ]);

        return redirect()->route('meta.ads.manager.automation')
            ->with('success', 'Automation rule updated successfully!');
    }

    /**
     * Delete automation rule
     */
    public function deleteRule($id)
    {
        $rule = MetaAutomationRule::where('user_id', Auth::id())
            ->findOrFail($id);

        $rule->delete();

        return redirect()->route('meta.ads.manager.automation')
            ->with('success', 'Automation rule deleted successfully!');
    }

    /**
     * Logs view
     */
    public function logs(Request $request)
    {
        $type = $request->get('type', 'actions'); // actions or sync

        if ($type === 'sync') {
            $logs = MetaSyncRun::where('user_id', Auth::id())
                ->orderBy('started_at', 'desc')
                ->paginate(20);
        } else {
            // Show user's logs, or all logs if user has none (fallback for server)
            $logs = MetaActionLog::where('user_id', Auth::id())
                ->orderBy('created_at', 'desc')
                ->paginate(20);
            
            // Fallback: if no logs for user, show all logs
            if ($logs->isEmpty() && Auth::id()) {
                $logs = MetaActionLog::orderBy('created_at', 'desc')
                    ->paginate(20);
            }
        }

        return view('marketing-masters.meta_ads_manager.logs', [
            'logs' => $logs,
            'type' => $type,
        ]);
    }

    /**
     * Export data to CSV
     */
    public function export(Request $request)
    {
        $type = $request->get('type', 'campaigns');
        $userId = Auth::id();

        // This is a placeholder - implement CSV export based on type
        return response()->json([
            'message' => 'Export functionality to be implemented',
        ]);
    }
    
    /**
     * Store a new campaign group
     */
    public function storeGroup(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:meta_campaign_groups,name',
        ]);

        $group = MetaCampaignGroup::create([
            'user_id' => Auth::id(),
            'name' => $request->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Group created successfully',
            'group' => $group->name,
        ]);
    }
    
    /**
     * Store a new campaign ad type
     */
    public function storeAdType(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:meta_campaign_ad_types,name',
        ]);

        $adType = MetaCampaignAdType::create([
            'user_id' => Auth::id(),
            'name' => $request->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ad Type created successfully',
            'ad_type' => $adType->name,
        ]);
    }
    
    /**
     * Update campaign group
     */
    public function updateCampaignGroup(Request $request, $campaignId)
    {
        $request->validate([
            'group' => 'nullable|string|max:255',
        ]);

        $campaign = MetaCampaign::findOrFail($campaignId);
        $campaign->group = $request->group;
        $campaign->save();

        return response()->json([
            'success' => true,
            'message' => 'Campaign group updated successfully',
        ]);
    }
    
    /**
     * Update campaign parent
     */
    public function updateCampaignParent(Request $request, $campaignId)
    {
        $request->validate([
            'parent' => 'nullable|string|max:255',
        ]);

        $campaign = MetaCampaign::findOrFail($campaignId);
        $campaign->parent = $request->parent;
        $campaign->save();

        return response()->json([
            'success' => true,
            'message' => 'Campaign parent updated successfully',
        ]);
    }
    
    /**
     * Update campaign ad type
     */
    public function updateCampaignAdType(Request $request, $campaignId)
    {
        $request->validate([
            'ad_type' => 'nullable|string|max:255',
        ]);

        $campaign = MetaCampaign::findOrFail($campaignId);
        $campaign->ad_type = $request->ad_type;
        $campaign->save();

        return response()->json([
            'success' => true,
            'message' => 'Campaign ad type updated successfully',
        ]);
    }
}
