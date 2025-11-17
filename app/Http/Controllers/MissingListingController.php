<?php

namespace App\Http\Controllers;


use Illuminate\Support\Arr;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;
use App\Models\AutoStockBalance;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// use App\Http\Controllers\ShopifyApiInventoryController;
use App\Models\ShopifySku;
use App\Models\Inventory;
use App\Models\ShopifyInventory;

use App\Models\AmazonDataView;
use App\Models\AmazonListingStatus;
use App\Models\WalmartListingStatus;
use App\Models\ReverbListingStatus;
use App\Models\ProductStockMapping;
use App\Models\SheinListingStatus;
use App\Models\DobaListingStatus;
use App\Models\TemuListingStatus;
use App\Models\MacysListingStatus;
use App\Models\EbayListingStatus;
use App\Models\EbayTwoListingStatus;
use App\Models\EbayThreeListingStatus;
use App\Models\BestbuyUSAListingStatus;
use App\Models\TiendamiaListingStatus;


use App\Services\ShopifyApiService;
use App\Services\AmazonSpApiService;
use App\Services\WalmartApiService;
use App\Services\EbayApiService;
use App\Services\Ebay2ApiService;
use App\Services\Ebay3ApiService;
use App\Services\ReverbApiService;
use App\Services\TemuApiService;
use App\Services\SheinApiService;
use App\Services\DobaApiService;
use App\Services\WayfairApiService;
use App\Services\MacysApiService;
use App\Services\BestBuyApiService;
use App\Services\TiendamiaApiService;
use App\Services\AliExpressApiService;

use GuzzleHttp\Client;

use Aws\Signature\SignatureV4;
use Aws\Credentials\Credentials;
use Illuminate\Support\Facades\DB;

class MissingListingController extends Controller
{

    protected $shopifyDomain;
    protected $shopifyApiKey;
    protected $shopifyPassword;


    /**
     * Display a listing of the resource.
     */
    public function index()
    {
       return view('missing-listing.view-missing-listing');
    }

     
   public function getShopifyMissingInventoryStock(Request $request) {    
    
        ini_set('max_execution_time', 300);
            
            // Check if data is older than 1 day
            $latestRecord = ProductStockMapping::orderBy('updated_at', 'desc')->first();    
            // if ($latestRecord && $latestRecord->updated_at > now()->subDay()) {
        if ($latestRecord) {
            // Return cached data from DB
            // $data = ProductStockMapping::all()->keyBy('sku')->unique()->groupby('sku');
            $data = ProductStockMapping::all()
            ->groupBy('sku')
            ->map(function ($items) {
                return $items->first(); // or customize how you want to handle duplicates
            });

        $skusforNR = array_values(array_filter(array_map(function ($item) {
            return $item['sku'] ?? null;
        }, $data->toArray())));
        // dd(implode(',',$skusforNR));

        $marketplaces = [
            'amazon'  => [AmazonListingStatus::class,  'inventory_amazon'],
            'walmart' => [WalmartListingStatus::class, 'inventory_walmart'],
            'reverb'  => [ReverbListingStatus::class,  'inventory_reverb'],
            'shein'   => [SheinListingStatus::class,   'inventory_shein'],
            'doba'    => [DobaListingStatus::class,    'inventory_doba'],
            'temu'    => [TemuListingStatus::class,    'inventory_temu'],
            'macy'    => [MacysListingStatus::class,   'inventory_macy'],
            'ebay1'   => [EbayListingStatus::class,    'inventory_ebay1'],
            'ebay2'   => [EbayTwoListingStatus::class, 'inventory_ebay2'],
            'ebay3'   => [EbayThreeListingStatus::class,'inventory_ebay3'],
            'bestbuy' => [BestbuyUSAListingStatus::class,'inventory_bestbuy'],
            'tiendamia' => [TiendamiaListingStatus::class,'inventory_tiendamia'],
            
        ];


        //  dd($data['FR 10 185 AL 4OHMS']['inventory_macy']);

        foreach ($marketplaces as $key => [$model, $inventoryField]) {
            // $listingData = $model::whereIn('sku', $skusforNR)->where('value->nr_req', 'NR')->get()->unique()->keyBy('sku');
            $listingData = $model::whereIn('sku', $skusforNR)->where('value->nr_req', 'NR')->get()->unique()->keyBy('sku');
                    
            foreach ($listingData as $sku => $listing) {
                $sku = str_replace("\u{00A0}", ' ', $sku);
                    // Trim and normalize spacing
                    $sku = trim(preg_replace('/\s+/', ' ', $sku));
                    // dd($sku);
                if (
                    isset($data[$sku]) &&
                    Arr::get($listing->value, 'nr_req') === 'NR'
                    && 
                    $data[$sku]->$inventoryField>0 
                    // && $data[$sku]->$inventoryField!="Not Listed"
                ) {

                    $data[$sku]->$inventoryField = 'NRL';
                    // if($data[$sku]->$inventoryField != 'Not Listed'){
                    // }
                }        
            }

        }

        // dd($data->where('inventory_ebay2','NRL')->count());
        // dd($test);

        $datainfo = $this->getDataInfo($data);
        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => $data,
            'datainfo' => $datainfo,
            'status' => 200
        ]);
    }

        
        $freshData=$this->fetchFreshDataU();   
        $datainfo=$this->getDataInfo($freshData);

        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => $freshData,
            'datainfo'=>$datainfo,
            'status' => 200
        ]);
        
    }





protected function fetchFreshData(){
    ini_set('max_execution_time', 300);

    $result=(new AliExpressApiService())->getInventory();
    dd($result);
    // $result=(new BestBuyApiService())->getInventory();
    
    // // Fetch fresh data from APIs
    // $delete=ProductStockMapping::truncate();
    // $shopifyInventoryData = (new ShopifyApiService())->getinventory();        
    // $parentskuList=$this->filterParentSKU($shopifyInventoryData);
    // $amazonInventoryData = (new AmazonSpApiService())->getinventory();
    // $walmartInventory=(new WalmartApiService())->getinventory();
    // $reverbInventory=(new ReverbApiService())->getInventory();
    // $sheinInventory = (new SheinApiService())->listAllProducts();
    // $dobaInventory = (new DobaApiService())->getinventory();
    // $temuInventory = (new TemuApiService())->getInventory();
    // $macyInventory = (new MacysApiService())->getInventory();
    // $ebay1Inventory = (new EbayApiService())->getEbayInventory();
    // $ebay2Inventory = (new Ebay2ApiService())->getEbayInventory();
    // $ebay3Inventory = (new Ebay3ApiService())->getEbayInventory();
    // $data = ProductStockMapping::all();
    // return $data;
}

protected function fetchFreshDataU($type = null)
{
    ini_set('max_execution_time', 3600);
    ini_set('memory_limit', '512M');
    $progress = [];
    
    // Define all sources including Shopify
    $sources = [
        'shopify' => fn() => (new ShopifyApiService())->getinventory(),
        'amazon'  => fn() => (new AmazonSpApiService())->getinventory(),
        'walmart' => fn() => (new WalmartApiService())->getinventory(),
        'reverb'  => fn() => (new ReverbApiService())->getInventory(),
        'shein'   => fn() => (new SheinApiService())->listAllProducts(),
        'doba'    => fn() => (new DobaApiService())->getinventory(),
        'temu'    => fn() => (new TemuApiService())->getInventory(),
        'macy'    => fn() => (new MacysApiService())->getInventory(),
        'ebay1'   => fn() => (new EbayApiService())->getEbayInventory(),
        'ebay2'   => fn() => (new Ebay2ApiService())->getEbayInventory(),
        'ebay3'   => fn() => (new Ebay3ApiService())->getEbayInventory(),
        'bestbuy' => fn() => (new BestBuyApiService())->getInventory(),
        'tiendamia' => fn() => (new TiendamiaApiService())->getInventory(),
    ];
   
    
    // if (!$type) {
    if ($type=='all') {
        // Fetch all sources with breaks
        return $this->fetchAllSourcesWithBreaks();
    } else {
        // Fetch single source
        return $this->fetchSingleSource($sources, $type, $progress);
    }
}

/**
 * Fetch all sources with automatic breaks and timeout prevention
 */
protected function fetchAllSourcesWithBreaks()
{
     $jobs = [
        'shopify' => \App\Jobs\missing_listing\ShopifyInventoryFetchJob::class,
        'amazon'  => \App\Jobs\missing_listing\AmazonInventoryFetchJob::class,
        'walmart' => \App\Jobs\missing_listing\WalmartInventoryFetchJob::class,
        'reverb'  => \App\Jobs\missing_listing\ReverbInventoryFetchJob::class,
        'shein'   => \App\Jobs\missing_listing\SheinInventoryFetchJob::class,
        'doba'    => \App\Jobs\missing_listing\DobaInventoryFetchJob::class,
        'temu'    => \App\Jobs\missing_listing\TemuInventoryFetchJob::class,
        'macy'    => \App\Jobs\missing_listing\MacysInventoryFetchJob::class,
        'ebay1'   => \App\Jobs\missing_listing\Ebay1InventoryFetchJob::class,
        'ebay2'   => \App\Jobs\missing_listing\Ebay2InventoryFetchJob::class,
        'ebay3'   => \App\Jobs\missing_listing\Ebay3InventoryFetchJob::class,
        'bestbuy' => \App\Jobs\missing_listing\BestbuyInventoryFetchJob::class,
        'tiendamia' => \App\Jobs\missing_listing\TiendamiaInventoryFetchJob::class,
    ];

    $startTime = time();
    $maxExecutionTime = 3000; // 50 minutes max
    $breakDuration = 2; // 2 seconds pause between sources
     $startTime = time();
    $batchId = uniqid('inv_fetch_');
    $maxWaitTime = 600; // 10 minutes maximum wait time
    $checkInterval = 5; // Check status every 5 seconds
  $breakDuration = 0.3;   
  try {
        // ProductStockMapping::truncate();

        // // First dispatch Shopify job and wait for it to complete
        // \Log::info('Starting Shopify inventory fetch');
        // $shopifyJob = new $jobs['shopify']($batchId);
        // dispatch($shopifyJob);

        // // // Wait for Shopify job to complete
        // $shopifyData = $this->waitForJobCompletion('shopify', $batchId, $maxWaitTime, $checkInterval);
        // if (!$shopifyData) {
        //     throw new \Exception('Shopify inventory fetch failed or timed out');
        // }

        // $parentskuList = $this->filterParentSKU($shopifyData);
        
        // // // Clean up Shopify data from cache
        // cache()->forget("inventory_fetch_{$batchId}_shopify");

        // // Optional short break after Shopify
        // if ($breakDuration > 0) {
        //     usleep((int)($breakDuration * 1_000_000)); // convert seconds to microseconds
        // }

        // === Step 2: Dispatch jobs for all other platforms ===
        $pendingPlatforms = [];
        foreach ($jobs as $platform => $jobClass) {
            if ($platform === 'shopify') {
                continue; // Already done
            }

            \Log::info("Dispatching job for: $platform");
            try {
                $job = new $jobClass($batchId);
                dispatch($job);
                $pendingPlatforms[] = $platform;
            } catch (\Exception $e) {
                \Log::error("Error dispatching job for $platform: " . $e->getMessage());
                $progress[$platform . '_error'] = $e->getMessage();
            }
        }

        // === Step 3: Wait for all jobs to complete ===
        $results = [];
        foreach ($pendingPlatforms as $platform) {
            try {
                $result = $this->waitForJobCompletion($platform, $batchId, $maxWaitTime, $checkInterval);
                if ($result) {
                    $results[$platform] = $result;
                    $progress[$platform] = [
                        'status' => 'success',
                        'count' => is_array($result) ? count($result) : 0
                    ];
                } else {
                    $progress[$platform] = [
                        'status' => 'timeout',
                        'error' => 'Job timed out'
                    ];
                }
                
                // Clean up cache
                cache()->forget("inventory_fetch_{$batchId}_{$platform}");
                
            } catch (\Exception $e) {
                \Log::error("Error processing results for $platform: " . $e->getMessage());
                $progress[$platform] = [
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }

        \Log::info('Inventory sync completed. Final progress:', $progress);

        return [
            'status' => true,
            'data' => ProductStockMapping::all(),
            'progress' => $progress,
            'execution_time' => time() - $startTime
        ];

    } catch (\Exception $e) {
        \Log::error('Critical error in fetchAllSourcesWithBreaks: ' . $e->getMessage());
        return [
            'status' => false,
            'msg' => 'Error: ' . $e->getMessage(),
            // 'progress' => $progress
        ];
    }
}


/**
 * Fetch single source with optimizations
 */
protected function fetchSingleSource($sources, $type, &$progress)
{
    ini_set('max_execution_time', 1800);
    
    if (!array_key_exists($type, $sources)) {
        return ['status' => false, 'msg' => "Invalid type: $type"];
    }
    
    try {
        \Log::info("Fetching single source: $type");
        
        $startTime = time();
        $result = $this->safeFetch($sources[$type], $type, $progress);
        $executionTime = time() - $startTime;
        
        // Optional: handle Shopify-specific logic if needed
        if ($type === 'shopify') {
            $parentskuList = $this->filterParentSKU($result);
        }
        
        // Clear memory
        gc_collect_cycles();
        
        return [
            'status' => true,
            'msg' => 'success',
            'progress' => $progress,
            'data' => $result,
            'execution_time' => $executionTime
        ];
        
    } catch (\Exception $e) {
        \Log::error("Error fetching single source $type: " . $e->getMessage());
        return [
            'status' => false,
            'msg' => 'Error: ' . $e->getMessage(),
            'progress' => $progress
        ];
    }
}


/**
 * Optimized safeFetch with batching support
 */
protected function waitForJobCompletion($platform, $batchId, $maxWaitTime, $checkInterval)
{
    $startTime = time();
    while (time() - $startTime < $maxWaitTime) {
        $result = cache()->get("inventory_fetch_{$batchId}_{$platform}");
        
        if ($result) {
            if ($result['status'] === 'completed') {
                return $result['data'];
            } elseif ($result['status'] === 'failed') {
                throw new \Exception("Job failed for {$platform}: " . ($result['error'] ?? 'Unknown error'));
            }
        }
        
        sleep($checkInterval);
    }
    
    return null;
}


/**
 * Optimized safeFetch with batching support
 */
protected function safeFetch($fetcher, $platform, &$progress)
{
    $startTime = microtime(true);
    
    try {
        $result = $fetcher();
        
        $executionTime = round(microtime(true) - $startTime, 2);
        $progress[$platform] = [
            'status' => 'success',
            'count' => is_array($result) ? count($result) : 0,
            'time' => $executionTime . 's'
        ];
        
        \Log::info("$platform fetch completed in {$executionTime}s");
        
        return $result;
        
    } catch (\Exception $e) {
        $executionTime = round(microtime(true) - $startTime, 2);
        $progress[$platform] = [
            'status' => 'error',
            'error' => $e->getMessage(),
            'time' => $executionTime . 's'
        ];
        
        \Log::error("$platform fetch failed: " . $e->getMessage());
        
        return [];
    }
}


protected function getDataInfo($data)
{
    $channels = ['shopify', 'amazon', 'walmart', 'reverb', 'shein', 'doba', 
                 'temu', 'macy', 'ebay1', 'ebay2', 'ebay3', 'bestbuy', 'tiendamia'];
    
    $info = array_fill_keys($channels, [
        'listed' => 0, 
        'notlisted' => 0, 
        'notlistedwzi' => 0, 
        'notlistedwnzi' => 0, 
        'nrl' => 0
    ]);

    foreach ($data as $item) {
        if (!is_array($item)) {
            continue;
        }
        
        $shopifyInv = isset($item['inventory_shopify']) && is_numeric($item['inventory_shopify']) ? (float)$item['inventory_shopify'] : 0;
        
        foreach ($channels as $channel) {
            $inv = isset($item["inventory_{$channel}"]) ? $item["inventory_{$channel}"] : '';
            
            if ($channel === 'shopify') {
                $info[$channel][$inv === 'Not Listed' ? 'notlisted' : ($inv === 'NRL' ? 'nrl' : 'listed')]++;
            } else {
                if ($inv === 'Not Listed') {
                    $info[$channel]['notlisted']++;
                    $info[$channel][$shopifyInv === 0 ? 'notlistedwzi' : 'notlistedwnzi']++;
                } elseif ($inv === 'NRL') {
                    $info[$channel]['nrl']++;
                } else {
                    $info[$channel]['listed']++;
                }
            }
        }
    }

    return $info;
}

protected function getDataInfo1($data){
    
    $info = [
        'shopify' => [
            'listed' => 0,
            'notlisted' => 0,
            'notlistedwzi' => 0,
            'nrl'=>0,
        ],
        'amazon' => [
            'listed' => 0,
            'notlisted' => 0,
            'notlistedwzi' => 0,
            'nrl'=>0,
        ],
         'walmart' => [
            'listed' => 0,
            'notlisted' => 0,
            'notlistedwzi' => 0,
            'nrl'=>0,
        ],
        'reverb' => [
            'listed' => 0,
            'notlisted' => 0,
            'notlistedwzi' => 0,
            'nrl'=>0,
        ],
        'shein' => [
            'listed' => 0,
            'notlisted' => 0,
            'notlistedwzi' => 0,
            'nrl'=>0,
        ],
        'doba' => [
            'listed' => 0,
            'notlisted' => 0,
            'notlistedwzi' => 0,
            'nrl'=>0,
        ],
        'temu' => [
            'listed' => 0,
            'notlisted' => 0,
            'notlistedwzi' => 0,
            'nrl'=>0,
        ],

        'macy' => [
            'listed' => 0,
            'notlisted' => 0,
            'notlistedwzi' => 0,
            'nrl'=>0,
        ],
        'ebay1' => [
            'listed' => 0,
            'notlisted' => 0,
            'notlistedwzi' => 0,
            'nrl'=>0,
        ],
        'ebay2' => [
            'listed' => 0,
            'notlisted' => 0,
            'notlistedwzi' => 0,
            'nrl'=>0,
        ],
        'ebay3' => [
            'listed' => 0,
            'notlisted' => 0,
            'notlistedwzi' => 0,
            'nrl'=>0,
        ],
          'bestbuy' => [
            'listed' => 0,
            'notlisted' => 0,
            'notlistedwzi' => 0,
            'nrl'=>0,
        ],
          'tiendamia' => [
            'listed' => 0,
            'notlisted' => 0,
            'notlistedwzi' => 0,
            'nrl'=>0,
        ],
    ];

    foreach ($data as $item) {
        if($item['inventory_shopify']=='Not Listed'){ $info['shopify']['notlisted']++;}
        else if($item['inventory_shopify']=='NRL'){ $info['shopify']['nrl']++;}
        else{ $info['shopify']['listed']++;  }

        if($item['inventory_amazon']=='Not Listed'){  $info['amazon']['notlisted']++; }
        else if($item['inventory_amazon']=='NRL'){ $info['amazon']['nrl']++;}
        else if($item['inventory_amazon']=='Not Listed' && $item['inventory_shopify']>0) {  $info['amazon']['notlistedwzi']++; }
        else {  $info['amazon']['listed']++; }

        if($item['inventory_walmart']=='Not Listed'){  $info['walmart']['notlisted']++; }
        else if($item['inventory_walmart']=='NRL'){ $info['walmart']['nrl']++;}
        else if($item['inventory_walmart']=='Not Listed' && $item['inventory_shopify']>0) {  $info['walmart']['notlistedwzi']++; }
        else {  $info['walmart']['listed']++; }

        if($item['inventory_reverb']=='Not Listed'){  $info['reverb']['notlisted']++; }
        else if($item['inventory_reverb']=='NRL'){ $info['reverb']['nrl']++;}
        else if($item['inventory_reverb']=='Not Listed' && $item['inventory_shopify']>0) {  $info['reverb']['notlistedwzi']++; }
        else {  $info['reverb']['listed']++; }        

        if($item['inventory_shein']=='Not Listed'){  $info['shein']['notlisted']++; }
        else if($item['inventory_shein']=='NRL'){ $info['shein']['nrl']++;}
        else if($item['inventory_shein']=='Not Listed' && $item['inventory_shopify']>0) {  $info['shein']['notlistedwzi']++; }  
        else {  $info['shein']['listed']++; }

        if($item['inventory_doba']=='Not Listed'){  $info['doba']['notlisted']++; }
        else if($item['inventory_doba']=='NRL'){ $info['doba']['nrl']++;}
        else if($item['inventory_doba']=='Not Listed' && $item['inventory_shopify']>0) {  $info['doba']['notlistedwzi']++; }
        else {  $info['doba']['listed']++; }

        if($item['inventory_temu']=='Not Listed'){  $info['temu']['notlisted']++; }
        else if($item['inventory_temu']=='NRL'){ $info['temu']['nrl']++;}
        else if($item['inventory_temu']=='Not Listed' && $item['inventory_shopify']>0) {  $info['temu']['notlistedwzi']++; }    
        else {  $info['temu']['listed']++; }
        
        if($item['inventory_macy']=='Not Listed'){  $info['macy']['notlisted']++; }
        else if($item['inventory_macy']=='NRL'){ $info['macy']['nrl']++;}
        else if($item['inventory_macy']=='Not Listed' && $item['inventory_shopify']>0) {  $info['macy']['notlistedwzi']++; }    
        else {  $info['macy']['listed']++; }
        
        if($item['inventory_ebay1']=='Not Listed'){  $info['ebay1']['notlisted']++; }
        else if($item['inventory_ebay1']=='NRL'){ $info['ebay1']['nrl']++;}
        else if($item['inventory_ebay1']=='Not Listed' && $item['inventory_shopify']>0) {  $info['ebay1']['notlistedwzi']++; }
        else {  $info['ebay1']['listed']++; }

        if($item['inventory_ebay2']=='Not Listed'){  $info['ebay2']['notlisted']++; }
        else if($item['inventory_ebay2']=='NRL'){ $info['ebay2']['nrl']++;}
        else if($item['inventory_ebay2']=='Not Listed' && $item['inventory_shopify']>0) {  $info['ebay2']['notlistedwzi']++; }
        else {  $info['ebay2']['listed']++; }

        if($item['inventory_ebay3']=='Not Listed'){  $info['ebay3']['notlisted']++; }
        else if($item['inventory_ebay3']=='NRL'){ $info['ebay3']['nrl']++;}
        else if($item['inventory_ebay3']=='Not Listed' && $item['inventory_shopify']>0) {  $info['ebay3']['notlistedwzi']++; }
        else {  $info['ebay3']['listed']++; }

         if($item['inventory_bestbuy']=='Not Listed'){  $info['bestbuy']['notlisted']++; }
        else if($item['inventory_bestbuy']=='NRL'){ $info['bestbuy']['nrl']++;}
        else if($item['inventory_bestbuy']=='Not Listed' && $item['inventory_shopify']>0) {  $info['bestbuy']['notlistedwzi']++; }  
        else {  $info['bestbuy']['listed']++; }

         if($item['inventory_tiendamia']=='Not Listed'){  $info['tiendamia']['notlisted']++; }
        else if($item['inventory_tiendamia']=='NRL'){ $info['tiendamia']['nrl']++;}
        else if($item['inventory_tiendamia']=='Not Listed' && $item['inventory_shopify']>0) {  $info['tiendamia']['notlistedwzi']++; }  
        else {  $info['tiendamia']['listed']++; }

        // $shopifyQty = $item['inventory_shopify'] ?? null;
    
        // $amazonQty = $item['inventory_amazon'] ?? null;
        // $walmartQty = $item['inventory_walmart'] ?? null;
        // $reverbQty = $item['inventory_reverb'] ?? null;
        // $sheinQty = $item['inventory_shein'] ?? null;
        // $dobaQty = $item['inventory_doba'] ?? null;
        // $temuQty = $item['inventory_temu'] ?? null;
        // $macyQty = $item['inventory_macy'] ?? null;
        // $ebay1Qty = $item['inventory_ebay1'] ?? null;
        // $ebay2Qty = $item['inventory_ebay2'] ?? null;
        // $ebay3Qty = $item['inventory_ebay3'] ?? null;

        // $isShopifyListed = is_numeric($shopifyQty);
        // $isAmazonListed = is_numeric($amazonQty);
        // $isWalmartListed = is_numeric($walmartQty);
        // $isReverbListed = is_numeric($reverbQty);
        // $isSheinListed = is_numeric($sheinQty);
        // $isDobaListed = is_numeric($dobaQty);
        // $isTemuListed = is_numeric($temuQty);
        // $isMacyListed = is_numeric($macyQty);
        // $isEbay1Listed = is_numeric($ebay1Qty);
        // $isEbay2Listed = is_numeric($ebay2Qty);
        // $isEbay3Listed = is_numeric($ebay3Qty);

        // // Channel-specific listing status
        // if($isShopifyListed && $isShopifyListed=='Not Listed'  && $isShopifyListed==null){ $info['shopify']['notlisted']++; } else{$info['shopify']['listed']++;}
        // if($isAmazonListed && $isAmazonListed=='Not Listed'  && $isAmazonListed==null){ $info['amazon']['notlisted']++; } else{$info['amazon']['listed']++;}
        // if($isWalmartListed && $isWalmartListed=='Not Listed'  && $isWalmartListed==null){ $info['walmart']['notlisted']++; } else{$info['walmart']['listed']++;}
        // if($isReverbListed && $isReverbListed=='Not Listed'  && $isReverbListed==null){ $info['reverb']['notlisted']++; } else{$info['reverb']['listed']++;}
        // if($isSheinListed && $isSheinListed=='Not Listed'  && $isSheinListed==null){ $info['shein']['notlisted']++; } else{$info['shein']['listed']++;}
        // if($isDobaListed && $isDobaListed=='Not Listed'  && $isDobaListed==null){ $info['doba']['notlisted']++; } else{$info['doba']['listed']++;}
        // if($isTemuListed && $isTemuListed=='Not Listed'  && $isTemuListed==null){ $info['temu']['notlisted']++; } else{$info['temu']['listed']++;}
        // if($isMacyListed && $isMacyListed=='Not Listed'  && $isMacyListed==null){ $info['macy']['notlisted']++; } else{$info['macy']['listed']++;}
        // if($isEbay1Listed && $isEbay1Listed=='Not Listed'  && $isEbay1Listed==null){ $info['ebay1']['notlisted']++; } else{$info['ebay1']['listed']++;}
        // if($isEbay2Listed && $isEbay2Listed=='Not Listed'  && $isEbay2Listed==null){ $info['ebay2']['notlisted']++; } else{$info['ebay2']['listed']++;}
        // if($isEbay3Listed && $isEbay3Listed=='Not Listed'  && $isEbay3Listed==null){ $info['ebay3']['notlisted']++; } else{$info['ebay3']['listed']++;}

        // $info['shopify'][$isShopifyListed='Not Listed'  ? 'listed' : 'notlisted']++;
        // $info['amazon'][$isAmazonListed ? 'listed' : 'notlisted']++;
        // $info['walmart'][$isWalmartListed ? 'listed' : 'notlisted']++;
        // $info['reverb'][$isReverbListed ? 'listed' : 'notlisted']++;
        // $info['shein'][$isSheinListed ? 'listed' : 'notlisted']++;
        // $info['doba'][$isDobaListed ? 'listed' : 'notlisted']++;
        // $info['temu'][$isTemuListed ? 'listed' : 'notlisted']++;
        // $info['macy'][$isMacyListed ? 'listed' : 'notlisted']++;
        // $info['ebay1'][$isEbay1Listed ? 'listed' : 'notlisted']++;
        // $info['ebay2'][$isEbay2Listed ? 'listed' : 'notlisted']++;
        // $info['ebay3'][$isEbay3Listed ? 'listed' : 'notlisted']++;        
    }

    return $info;
}


protected function filterParentSKU(array $data): array
{
    // Extract SKUs from input array
    $filteredSkus = array_values(array_filter(array_map(function ($item) {
        return $item['sku'] ?? null;
    }, $data)));

    // Query ProductMaster for matching SKUs
    $parentRecords = ProductMaster::whereIn('sku', $filteredSkus)->get();

    // Return associative array: [sku => parent]
    return $parentRecords->pluck('parent', 'sku')->toArray();
}


    

    protected function getAllInventoryDataebay(){
        return $result = (new EbayApiService())->getEbayInventory();
    }
    
    public function WalmartInventoryData(){
        return $result = (new WalmartService())->getAllInventoryData();
    }

    public function getReverbInventoryData(){
        return $result = (new ReverbApiService())->getInventory();
    }

    protected function updateNotRequired(Request $request)
    {
       $not_required = $request->input('notrequired');
           foreach ($not_required as $entry) {
        [$sku, $id] = explode('___', $entry);

        ProductStockMapping::where('sku', $sku)
            ->where('id', $id)
            ->update(['not_required' => 1]); // or true, or any value you need
    }
        return response()->json(['status' => 'success']);
    }


    public function refetchLiveData(){
        $freshData=$this->fetchFreshData();   
        if($freshData){
            return response()->json(['status' => 'success']);
        }
    }

    public function refetchLiveDataU(Request $request){        
        $freshData=$this->fetchFreshDataU($request->source);   
        return $freshData;
        if($freshData){
            return response()->json(['status' => 'success']);
        }
    }

 
     public function getAccessTokenV1()
    {
        $res = Http::withoutVerifying()->asForm()->post('https://api.amazon.com/auth/o2/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => env('SPAPI_REFRESH_TOKEN'),
            'client_id' => env('SPAPI_CLIENT_ID'),
            'client_secret' => env('SPAPI_CLIENT_SECRET'),
        ]);

        return $res['access_token'] ?? null;
    }


    public function getAmazonProductAndOffers($asin)
    {
        $marketplaceId = env('SPAPI_MARKETPLACE_ID'); // e.g. ATVPDKIKX0DER
        $accessToken   = $this->getAccessTokenV1(); // your function to get SP-API access token

        // Pricing/Offers Info (all sellers)
        $offersUrl = "https://sellingpartnerapi-na.amazon.com/products/pricing/v0/items/{$asin}/offers"
            . "?MarketplaceId={$marketplaceId}&ItemCondition=New";


        sleep(2);


        $offersRes = Http::withoutVerifying()->withHeaders([
            'Authorization' => "Bearer {$accessToken}",
            'x-amz-access-token' => $accessToken,
        ])->get($offersUrl)->json();

        dd($accessToken, $offersRes);


        $payload = $offersRes['payload'] ?? null;

        if (!$payload || empty($payload['Offers'])) {
            return [
                'asin'     => $asin,
                'price'    => null,
                'shipping' => null,
                'seller'   => null,
                'image'    => null,
            ];
        }

        $firstOffer = $payload['Offers'][0]; // take first seller (often BuyBox winner)

        return [
            'asin'     => $asin,
            'price'    => $firstOffer['ListingPrice']['Amount'] ?? null,
            'shipping' => $firstOffer['Shipping']['Amount'] ?? null,
            'seller'   => $firstOffer['SellerId'] ?? null,
            // For image, Pricing API doesn’t return it → keeping null placeholder
            'image'    => null,
        ];
    }

    public function shopifyMissingInventoryListings() {
        $productMasters = DB::table('product_master')
            ->whereRaw('LOWER(sku) NOT LIKE ?', ['parent%'])
            ->orderBy('id', 'asc')
            ->get();

        $masterSKUs = $productMasters
            ->pluck('sku')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $shopifyInventory = ShopifyInventory::whereIn('sku', $masterSKUs)
            ->get()
            ->keyBy('sku');

        $marketplaces = [
            'amazon'    => [AmazonListingStatus::class],
            'walmart'   => [WalmartListingStatus::class],
            'reverb'    => [ReverbListingStatus::class],
            'shein'     => [SheinListingStatus::class],
            'doba'      => [DobaListingStatus::class],
            'temu'      => [TemuListingStatus::class],
            'macy'      => [MacysListingStatus::class],
            'ebay1'     => [EbayListingStatus::class],
            'ebay2'     => [EbayTwoListingStatus::class],
            'ebay3'     => [EbayThreeListingStatus::class],
            'bestbuy'   => [BestbuyUSAListingStatus::class],
            'tiendamia' => [TiendamiaListingStatus::class],
        ];

        $result = [];

        foreach ($productMasters as $pm) {

            $sku = $pm->sku;

            $row = [];
            $row['parent'] = $pm->parent;
            $row['sku']    = $pm->sku;
            $row['listing_status'] = [];

            if (isset($shopifyInventory[$sku])) {
                $row['listing_status']['shopify'] = "Listed";
            } else {
                $row['listing_status']['shopify'] = "Not Listed";
            }

            foreach ($marketplaces as $marketplaceName => $models) {

                $status = "Not Listed";   
                $foundListing = null;

                foreach ($models as $modelClass) {
                    $listing = $modelClass::whereRaw('LOWER(sku) = ?', [strtolower($sku)])
                        ->first();

                    if ($listing) {
                        $foundListing = $listing;
                        break;
                    }
                }

                if ($foundListing) {

                    $value = $foundListing->value;

                    if (is_string($value)) {
                        $value = json_decode($value, true);
                    }

                    $value = is_array($value) ? $value : [];

                    $listed = $value['listed'] ?? null;
                    $nr_req = $value['nr_req'] ?? null;

                    if ($listed === "Listed" && $nr_req === "NRL") {
                        $status = "NRL";
                    } else {
                        $status = "Listed";
                    }
                }

                $row['listing_status'][$marketplaceName] = $status;
            }

            $result[] = (object) $row;
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data'    => $result,
            'status'  => 200,
        ]);
    }
}