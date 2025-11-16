<?php

namespace App\Http\Controllers\MarketingMaster;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ApiController;

use App\Models\MarketplacePercentage;
use App\Models\ZeroVisibilityMaster;
use App\Models\ProductMaster;
use App\Models\SheinDataView;
use App\Models\ShopifySku;
use App\Models\ChannelDailyCount;
use App\Models\ZeroVisibilityDailyTotal;
use App\Models\ChannelAction;
use App\Models\SkuAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\FacadesLog;

class ZeroVisibilityMasterController extends Controller
{

    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }


    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $productSKUs = ProductMaster::where('sku', 'NOT LIKE', '%PARENT%')
            ->pluck('sku')
            ->toArray();

        $zeroInvCount = ShopifySku::whereIn('sku', $productSKUs)
            ->where('inv', '<=', 0)
            ->count();


        $totalSkuCount = count($productSKUs);

        $channels = ZeroVisibilityMaster::all();

        // Mapping for special channel/controller names
        $controllerMap = [
            'ebay'          => 'EbayZeroController',
            'ebaythree'     => 'Ebay3ZeroController',
            'ebay3'         => 'Ebay3ZeroController',
            'ebaytwo'       => 'Ebay2ZeroController',
            'ebay2'         => 'Ebay2ZeroController',
            'ebayvariation' => 'EbayVariationZeroController',
            'tiktokshop'    => 'TiktokShopZeroController',
            'doba'          => 'DobaZeroController',
            'walmart'       => 'WalmartZeroController',
            'shein'         => 'SheinZeroController',
            'bestbuyusa'    => 'BestbuyUSAZeroController',
            'aliexpress'    => 'AliexpressZeroController',
            'tiendamia'     => 'TiendamiaZeroController',
            'pls'           => 'PLSZeroController',
            'mercariwship'  => 'MercariWShipZeroController',
            'mercariwoship' => 'MercariWoShipZeroController',
            'instagramshop' => 'InstagramShopZeroController',
            'fbshop'        => 'FBShopZeroController',
            'fbmarketplace' => 'FBMarketplaceZeroController',
            'faire'         => 'FaireZeroController',
            'business5core' => 'Business5CoreZeroController',

        ];

        foreach ($channels as $channel) {
            $livePending = null;
            $zeroView = null;

            // Check if channel has special mapping
            $key = strtolower(str_replace([' ', '&', '-', '/'], '', trim($channel->channel_name)));
            if (isset($controllerMap[$key])) {
                $controllerName = $controllerMap[$key];
                if ($controllerName === 'EbayZeroController') {
                    $controllerClass = "App\\Http\\Controllers\\MarketPlace\\{$controllerName}";
                } else {
                    $controllerClass = "App\\Http\\Controllers\\MarketPlace\\ZeroViewMarketPlace\\{$controllerName}";
                }
            } else {
                // Build controller class name dynamically (e.g., "Amazon" => AmazonZeroController)
                $baseName = str_replace([' ', '&', '-', '/'], '', ucwords(strtolower(trim($channel->channel_name))));
                $controllerClass = "App\\Http\\Controllers\\MarketPlace\\{$baseName}ZeroController";
            }

            if (class_exists($controllerClass)) {
                $controller = app($controllerClass);
                if (method_exists($controller, 'getLivePendingAndZeroViewCounts')) {
                    $counts = $controller->getLivePendingAndZeroViewCounts();
                    $livePending = $counts['live_pending'] ?? null;
                    $zeroView = $counts['zero_view'] ?? null;
                }
            }

            $channel->live_pending = $livePending;
            $channel->zero_view = $zeroView;
        }

        return view('marketing-masters.zero-visibility-master', compact('totalSkuCount', 'zeroInvCount', 'channels'));
    }



    public function Zeroviewmasters(Request $request)
    {
        $productSKUs = ProductMaster::where('sku', 'NOT LIKE', '%PARENT%')
            ->pluck('sku')
            ->toArray();

        $zeroInvCount = ShopifySku::whereIn('sku', $productSKUs)
            ->where('inv', '<=', 0)
            ->count();


        $totalSkuCount = count($productSKUs);

        $channels = MarketplacePercentage::pluck('marketplace')->toArray();

        // Mapping for special channel/controller names
        $controllerMap = [
            'ebay'          => 'EbayZeroController',
            'ebaythree'     => 'Ebay3ZeroController',
            'ebay3'         => 'Ebay3ZeroController',
            'ebaytwo'       => 'Ebay2ZeroController',
            'ebay2'         => 'Ebay2ZeroController',
            'ebayvariation' => 'EbayVariationZeroController',
            'tiktokshop'    => 'TiktokShopZeroController',
            'doba'          => 'DobaZeroController',
            'walmart'       => 'WalmartZeroController',
            'shein'         => 'SheinZeroController',
            'bestbuyusa'    => 'BestbuyUSAZeroController',
            'aliexpress'    => 'AliexpressZeroController',
            'tiendamia'     => 'TiendamiaZeroController',
            'pls'           => 'PLSZeroController',
            'mercariwship'  => 'MercariWShipZeroController',
            'mercariwoship' => 'MercariWoShipZeroController',
            'instagramshop' => 'InstagramShopZeroController',
            'fbshop'        => 'FBShopZeroController',
            'fbmarketplace' => 'FBMarketplaceZeroController',
            'faire'         => 'FaireZeroController',
            'business5core' => 'Business5CoreZeroController',
            // Add more mappings as needed
        ];

        $livePendingData = [];
        $listedNotPendingData = []; // For Amazon's Listed but not Pending count
        $pendingCountData = []; // For Amazon's Pending count

        foreach ($channels as $channelName) {
            $livePending = null;
            $zeroView = null;
            $listedNotPending = null;
            $pendingCount = null;

            // Check if channel has special mapping
            $key = strtolower(str_replace([' ', '&', '-', '/'], '', trim($channelName)));
            if (isset($controllerMap[$key])) {
                $controllerName = $controllerMap[$key];
                if ($controllerName === 'EbayZeroController') {
                    $controllerClass = "App\\Http\\Controllers\\MarketPlace\\{$controllerName}";
                } else {
                    $controllerClass = "App\\Http\\Controllers\\MarketPlace\\ZeroViewMarketPlace\\{$controllerName}";
                }
            } else {
                // Build controller class name dynamically (e.g., "Amazon" => AmazonZeroController)
                $baseName = str_replace([' ', '&', '-', '/'], '', ucwords(strtolower(trim($channelName))));
                $controllerClass = "App\\Http\\Controllers\\MarketPlace\\{$baseName}ZeroController";
            }

            if (class_exists($controllerClass)) {
                try {
                    $controller = app($controllerClass);
                    if (method_exists($controller, 'getLivePendingAndZeroViewCounts')) {
                        $counts = $controller->getLivePendingAndZeroViewCounts();
                        $livePending = $counts['live_pending'] ?? null;
                        $zeroView = $counts['zero_view'] ?? null;
                        $listedNotPending = $counts['listed_not_pending'] ?? null;
                        $pendingCount = $counts['pending_count'] ?? null;
                        
                        // Debug logging
                        Log::info("Channel: {$channelName}, Live Pending: {$livePending}, Zero View: {$zeroView}, Listed Not Pending: {$listedNotPending}, Pending: {$pendingCount}");
                    } else {
                        Log::warning("Method getLivePendingAndZeroViewCounts not found for controller: {$controllerClass}");
                    }
                } catch (\Exception $e) {
                    Log::error("Error fetching data for channel {$channelName}: " . $e->getMessage());
                }
            } else {
                Log::warning("Controller class not found: {$controllerClass}");
            }

            // Use 0 as fallback if livePending is null
            $livePendingData[$channelName] = $livePending ?? 0;
            
            // Store listed_not_pending and pending_count for Amazon
            if ($listedNotPending !== null) {
                $listedNotPendingData[$channelName] = $listedNotPending;
            }
            if ($pendingCount !== null) {
                $pendingCountData[$channelName] = $pendingCount;
            }
        }
            // Save today's counts for each channel (existing per-channel JSON approach kept if table exists)
            $today = now()->toDateString();
            $yesterday = now()->subDay()->toDateString();
            $todayUpdates = 0;
            $channelUpdateData = [];

            if (Schema::hasTable('channel_daily_counts')) {
                foreach ($livePendingData as $channel => $count) {
                    try {
                        $record = ChannelDailyCount::firstOrNew(['channel_name' => $channel]);
                        // Decode counts safely — handle string/int/null
                        $countsRaw = $record->counts;

                        if (is_string($countsRaw)) {
                            $decoded = json_decode($countsRaw, true);
                            $counts = is_array($decoded) ? $decoded : [];
                        } elseif (is_array($countsRaw)) {
                            $counts = $countsRaw;
                        } else {
                            $counts = [];
                        }

                        $todayCount = $count ?? 0;

                        // Debug logging
                        Log::info("Saving channel data - Channel: {$channel}, Today Count: {$todayCount}, Existing Counts: " . json_encode($counts));

                        // Find the most recent previous date (strictly before today) in stored counts
                        $previousDate = null;
                        $previousValue = null;

                        if (!empty($counts)) {
                            $dates = array_keys($counts);
                            // Keep only dates strictly less than today
                            $priorDates = array_filter($dates, function ($d) use ($today) {
                                return $d < $today;
                            });

                            if (!empty($priorDates)) {
                                // Sort prior dates descending to get the latest available
                                rsort($priorDates);
                                $previousDate = $priorDates[0];
                                $previousValue = $counts[$previousDate] ?? 0;
                            }
                        }

                        if ($previousDate !== null) {
                            // Compute diff against the most recent prior value
                            $difference = $todayCount - $previousValue;
                            $updatedFlag = ($difference != 0);
                            $todayUpdates += $difference;
                        } else {
                            // No prior data available -> don't mark as updated and diff is 0
                            $difference = 0;
                            $updatedFlag = false;
                        }

                        // Store update status for each channel
                        $channelUpdateData[$channel] = [
                            'updated' => $updatedFlag,
                            'diff' => $difference
                        ];

                        // Save today's value (ensures future comparisons)
                        $counts[$today] = $todayCount;
                        $record->counts = $counts;
                        $record->save();

                        // Debug logging for final save
                        Log::info("Final save - Channel: {$channel}, Updated Counts: " . json_encode($counts));
                    } catch (\Exception $e) {
                        Log::error('Error saving ChannelDailyCount for ' . $channel . ': ' . $e->getMessage());
                    }
                }
            } else {
                Log::info('Table channel_daily_counts not found. Skipping per-channel daily saves.');
                // initialize channelUpdateData with defaults so later code can read it
                foreach ($livePendingData as $channel => $count) {
                    $channelUpdateData[$channel] = ['updated' => false, 'diff' => 0];
                }
            }

        // (No aggregated save here - we store zero-visibility totals from getMergedChannelData instead)

        // Get action data for all channels
        $actionData = ChannelAction::whereIn('channel_name', $channels)
            ->pluck('action', 'channel_name')
            ->toArray();
        
        $correctionData = ChannelAction::whereIn('channel_name', $channels)
            ->pluck('correction', 'channel_name')
            ->toArray();

        $data = array_map(function ($channelName) use ($livePendingData, $channelUpdateData, $actionData, $correctionData, $listedNotPendingData, $pendingCountData) {
            return [
                'Channel ' => $channelName,
                'R&A' => false, // placeholder
                'Live Pending' => $livePendingData[$channelName] ?? 0,
                'Updated Today' => $channelUpdateData[$channelName]['updated'] ?? false,
                'Diff' => $channelUpdateData[$channelName]['diff'] ?? 0,
                'Listed Not Pending' => $listedNotPendingData[$channelName] ?? null, // Only for Amazon
                'Pending Count' => $pendingCountData[$channelName] ?? null, // Only for Amazon
                'Action' => $actionData[$channelName] ?? '',
                'Correction action' => $correctionData[$channelName] ?? '',
            ];
        }, $channels);

        // If the caller expects JSON (fetch from sidebar or AJAX), return the data as JSON
        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json(['data' => $data, 'totalSkuCount' => $totalSkuCount, 'zeroInvCount' => $zeroInvCount, 'todayUpdates' => $todayUpdates]);
        }

        return view('marketing-masters.live-pending-masters', compact('data', 'totalSkuCount', 'zeroInvCount', 'todayUpdates'));
    }


    public function update(Request $request, string $id)
    {
        $row = ZeroVisibilityMaster::findOrFail($request->id);
        $row->update($request->except('id'));
        return response()->json(['status' => true]);
    }


    public function getMergedChannelData(Request $request)
    {
        // Get all channels from DB
        $channels = MarketplacePercentage::pluck('marketplace')->toArray();

        $data = [];


        // Mapping for special channel/controller names
        $controllerMap = [
            'ebay'          => 'EbayZeroController',
            'ebaythree'     => 'Ebay3ZeroController',
            'ebaytwo'       => 'Ebay2ZeroController',
            'ebay2'         => 'Ebay2ZeroController',
            'ebayvariation' => 'EbayVariationZeroController',
            'tiktokshop'    => 'TiktokShopZeroController',
            'doba'          => 'DobaZeroController',
            'walmart'       => 'WalmartZeroController',
            'shein'         => 'SheinZeroController',
            'bestbuyusa'    => 'BestbuyUSAZeroController',
            'aliexpress'    => 'AliexpressZeroController',
            'tiendamia'     => 'TiendamiaZeroController',
            'pls'           => 'PLSZeroController',
            'mercariwship'  => 'MercariWShipZeroController',
            'mercariwoship' => 'MercariWoShipZeroController',
            'instagramshop' => 'InstagramShopZeroController',
            'fbshop'        => 'FBShopZeroController',
            'fbmarketplace' => 'FBMarketplaceZeroController',
            'faire'         => 'FaireZeroController',
            'business5core' => 'Business5CoreZeroController',
            // Add more mappings as needed
        ];

        foreach ($channels as $channel) {
            $livePending = 0;
            $zeroViews = 0;

            $key = strtolower(str_replace([' ', '-', '&', '/'], '', trim($channel)));
             if ($key === 'amazonfba') {
                $controllerClass = "App\\Http\\Controllers\\FbaDataController";
            } else if (isset($controllerMap[$key])) {
                $controllerName = $controllerMap[$key];
                if ($controllerName === 'EbayZeroController') {
                    $controllerClass = "App\\Http\\Controllers\\MarketPlace\\{$controllerName}";
                } else {
                    $controllerClass = "App\\Http\\Controllers\\MarketPlace\\ZeroViewMarketPlace\\{$controllerName}";
                }
            } else {
                $controllerClass = "App\\Http\\Controllers\\MarketPlace\\" . ucfirst($channel) . "ZeroController";
            }

            if (class_exists($controllerClass)) {
                $controller = app($controllerClass);

                if ($key === 'amazonfba' && method_exists($controller, 'getFbaListedLiveAndViewsData')) {
                    $counts = $controller->getFbaListedLiveAndViewsData();
                    $livePending = $counts['live_pending'] ?? 0;
                    $zeroViews = $counts['zero_view'] ?? 0;
                }
                
                // Try getLivePendingAndZeroViewCounts (preferred, returns both counts)
                if (method_exists($controller, 'getLivePendingAndZeroViewCounts')) {
                    $counts = $controller->getLivePendingAndZeroViewCounts();
                    $livePending = $counts['live_pending'] ?? 0;
                    $zeroViews = $counts['zero_view'] ?? 0;
                }
                // Fallback: try getZeroViewCount (returns only zero view count)
                else if (method_exists($controller, 'getZeroViewCount')) {
                    $zeroViews = $controller->getZeroViewCount();
                }
                // Fallback: try getZeroViewCounts (plural, some controllers use this)
                else if (method_exists($controller, 'getZeroViewCounts')) {
                    $zeroViews = $controller->getZeroViewCounts();
                }
            }

            $data[] = [
                'Channel ' => $channel,          // keep space to match your DataTable
                'R&A' => false,                  // placeholder
                'Live Pending' => $livePending,
                'Zero Visibility SKU Count' => $zeroViews,
            ];
        }

        // Also persist aggregated Zero Visibility total (sum of 'Zero Visibility SKU Count') for today
        try {
            $zeroTotal = 0;
            foreach ($data as $row) {
                $val = $row['Zero Visibility SKU Count'] ?? 0;
                if (is_numeric($val)) {
                    $num = (int)$val;
                } else {
                    $clean = preg_replace('/[^0-9.-]/', '', (string)$val);
                    $num = ($clean === '' || !is_numeric($clean)) ? 0 : (int)$clean;
                }
                $zeroTotal += $num;
            }
            $today = now()->toDateString();
            ZeroVisibilityDailyTotal::updateOrCreate(
                ['date' => $today],
                ['total' => (int)$zeroTotal]
            );
        } catch (\Exception $e) {
            Log::error('Failed to save zero visibility daily total from getMergedChannelData: ' . $e->getMessage());
        }

        return response()->json(['data' => $data]);
    }


    public function getChannelChartData(Request $request)
    {
        $channelName = $request->input('channel');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $record = ChannelDailyCount::where('channel_name', $channelName)->first();

        if (!$record || !$record->counts) {
            return response()->json(['dates' => [], 'counts' => []]);
        }

        $counts = $record->counts;
        ksort($counts); // Sort by date

        // Filter by date range
        if ($startDate && $endDate) {
            $counts = array_filter($counts, function ($date) use ($startDate, $endDate) {
                return $date >= $startDate && $date <= $endDate;
            }, ARRAY_FILTER_USE_KEY);
        } else {
            // Default: Show last 7 days
            $today = now()->toDateString();
            $sevenDaysAgo = now()->subDays(6)->toDateString();

            $counts = array_filter($counts, function ($date) use ($sevenDaysAgo, $today) {
                return $date >= $sevenDaysAgo && $date <= $today;
            }, ARRAY_FILTER_USE_KEY);
        }

        $dates = array_keys($counts);
        $values = array_values($counts);

        return response()->json([
            'dates' => $dates,
            'counts' => $values
        ]);
    }

    public function getAllChannelsChartData(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // Get all channels from DB
        $channels = MarketplacePercentage::pluck('marketplace')->toArray();
        
        // Default: Show last 7 days
        $today = now()->toDateString();
        $sevenDaysAgo = now()->subDays(6)->toDateString();
        
        if ($startDate && $endDate) {
            $filterStartDate = $startDate;
            $filterEndDate = $endDate;
        } else {
            $filterStartDate = $sevenDaysAgo;
            $filterEndDate = $today;
        }

        $allChannelsData = [];
        $allDates = [];

        foreach ($channels as $channel) {
            $record = ChannelDailyCount::where('channel_name', $channel)->first();
            
            if ($record && $record->counts) {
                $counts = $record->counts;
                ksort($counts); // Sort by date

                // Filter by date range
                $filteredCounts = array_filter($counts, function ($date) use ($filterStartDate, $filterEndDate) {
                    return $date >= $filterStartDate && $date <= $filterEndDate;
                }, ARRAY_FILTER_USE_KEY);

                if (!empty($filteredCounts)) {
                    $allChannelsData[$channel] = $filteredCounts;
                    $allDates = array_merge($allDates, array_keys($filteredCounts));
                }
            }
        }

        // Get unique dates and sort them
        $allDates = array_unique($allDates);
        sort($allDates);

        // Calculate differences for each channel
        $datasets = [];
        $colors = [
            '#FF0000', '#00FF00', '#FF6B00', '#00BFFF', '#FF1493',
            '#32CD32', '#FF4500', '#00CED1', '#FF69B4', '#00FA9A',
            '#DC143C', '#00FF7F', '#FF8C00', '#20B2AA', '#FF6347',
            '#00FF00', '#FF0000', '#00BFFF', '#FF1493', '#32CD32'
        ];

        $colorIndex = 0;
        foreach ($allChannelsData as $channel => $counts) {
            $differences = [];
            $previousValue = null;
            
            foreach ($allDates as $date) {
                $currentValue = $counts[$date] ?? 0;
                
                if ($previousValue === null) {
                    // First day - no difference
                    $differences[] = 0;
                } else {
                    // Calculate difference from previous day
                    $difference = $currentValue - $previousValue;
                    $differences[] = $difference;
                }
                
                $previousValue = $currentValue;
            }

            $datasets[] = [
                'label' => $channel,
                'data' => $differences,
                'borderColor' => $colors[$colorIndex % count($colors)],
                'backgroundColor' => $colors[$colorIndex % count($colors)] . '30',
                'tension' => 0.1,
                'fill' => false,
                'pointRadius' => 6,
                'pointHoverRadius' => 8,
                'borderWidth' => 3,
                'pointBorderWidth' => 3,
                'pointBackgroundColor' => $colors[$colorIndex % count($colors)],
                'pointBorderColor' => '#fff'
            ];
            $colorIndex++;
        }

        return response()->json([
            'dates' => $allDates,
            'datasets' => $datasets
        ]);
    }

    /**
     * Return aggregated daily totals from the persisted table (date -> total).
     */
    public function getDailyTotals(Request $request)
    {
        $days = (int) $request->input('days', 7);
        $end = $request->input('end_date') ?: now()->toDateString();
        $start = $request->input('start_date') ?: now()->subDays($days - 1)->toDateString();

        $rows = ZeroVisibilityDailyTotal::whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->get(['date', 'total']);

        $dates = $rows->pluck('date')->map(function ($d) { return $d->toDateString(); })->toArray();
        $totals = $rows->pluck('total')->map(fn($v) => (int)$v)->toArray();

        return response()->json(['dates' => $dates, 'totals' => $totals]);
    }

    public function saveChannelAction(Request $request)
    {
        try {
            $channel = $request->input('channel');
            $action = $request->input('action');
            $correction = $request->input('correction');

            // Save to channel_actions table
            ChannelAction::updateOrCreate(
                ['channel_name' => $channel],
                [
                    'action' => $action,
                    'correction' => $correction,
                    'updated_at' => now()
                ]
            );

            Log::info("Action data saved - Channel: {$channel}, Action: {$action}, Correction: {$correction}");

            return response()->json([
                'status' => true,
                'message' => 'Action data saved successfully',
                'data' => [
                    'channel' => $channel,
                    'action' => $action,
                    'correction' => $correction
                ]
            ]);
        } catch (\Exception $e) {
            Log::error("Error saving action data: " . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Error saving action data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function testChannelData()
    {
        try {
            // Get all channels from DB
            $channels = MarketplacePercentage::pluck('marketplace')->toArray();
            
            $livePendingData = [];
            
            // Test with sample data for debugging
            foreach ($channels as $channel) {
                $livePendingData[$channel] = rand(10, 100); // Random test data
            }
            
            // Save test data
            $today = now()->toDateString();
            
            foreach ($livePendingData as $channel => $count) {
                $record = ChannelDailyCount::firstOrNew(['channel_name' => $channel]);
                $counts = $record->counts ?? [];
                $counts[$today] = $count;
                $record->counts = $counts;
                $record->save();
                
                Log::info("Test save - Channel: {$channel}, Count: {$count}, Date: {$today}");
            }
            
            return response()->json([
                'status' => true,
                'message' => 'Test data saved successfully',
                'data' => $livePendingData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}
