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
use App\Models\ProductStockMapping;

use App\Models\AmazonDataView;
use App\Models\AmazonListingStatus;
use App\Models\WalmartListingStatus;
use App\Models\ReverbListingStatus;
use App\Models\SheinListingStatus;
use App\Models\DobaListingStatus;
use App\Models\TemuListingStatus;
use App\Models\MacysListingStatus;
use App\Models\EbayListingStatus;
use App\Models\EbayTwoListingStatus;
use App\Models\EbayThreeListingStatus;
use App\Models\BestbuyUSAListingStatus;
use App\Models\TiendamiaListingStatus;
use App\Models\PlsListingStatus;
use App\Models\Business5CoreListingStatus;
use App\Models\Business5CoreDataView;
use App\Models\PLSProduct;


class StockMappingController extends Controller
{

    protected $shopifyDomain;
    protected $shopifyApiKey;
    protected $shopifyPassword;

    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
        $this->shopifyApiKey = config('services.shopify.api_key');
        $this->shopifyPassword = config('services.shopify.password');
        $this->shopifyStoreUrl = str_replace(['https://', 'http://'],'',config('services.shopify.store_url'));
        $this->shopifyStoreUrlName = env('SHOPIFY_STORE');
        $this->shopifyAccessToken = env('SHOPIFY_PASSWORD');
    }


    /**
     * Display a listing of the resource.
     */
    public function index()
    {
       return view('stock_mapping.view-stock-mapping');
    }

     
   public function getShopifyAmazonInventoryStock(Request $request)
{
        try {
            ini_set('max_execution_time', 300);
            $latestRecord = ProductStockMapping::orderBy('updated_at', 'desc')->first();    
            if ($latestRecord) {
            $data = ProductStockMapping::all()->groupBy('sku')->map(function ($items) {return $items->first(); });
            $skusforNR = array_values(array_filter(array_map(function ($item) { return $item['sku'] ?? null; }, $data->toArray())));
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
            'pls' => [PLSProduct::class, 'inventory_pls'],
            'business5core' => [Business5CoreDataView::class, 'inventory_business5core'],
        ];


            foreach ($marketplaces as $key => [$model, $inventoryField]) {
                // Fetch all listings for these SKUs
                $allListings = $model::whereIn('sku', $skusforNR)->get();
                
                foreach ($allListings as $listing) {
                    $sku = $listing->sku ?? '';
                    $sku = str_replace("\u{00A0}", ' ', $sku);
                    $sku = trim(preg_replace('/\s+/', ' ', $sku));
                    
                    if (isset($data[$sku])) {
                        $inventory = null;
                        
                        // Handle different data sources
                        if ($key === 'pls') {
                            // For PLS products, get price (which might represent stock or availability)
                            // Use price field as inventory indicator (0 = not available, >0 = available)
                            $inventory = $listing->price ?? null;
                            
                            // If price is empty/null, try to calculate from p_l30 or other fields
                            if ($inventory === null || $inventory === '') {
                                $inventory = !empty($listing->p_l30) ? 1 : 0;
                            }
                        } elseif ($key === 'business5core') {
                            // For Business5Core data view, get inventory from value JSON
                            $inventory = Arr::get($listing->value ?? [], 'inventory', null);
                            
                            // If inventory not found, try other common keys
                            if ($inventory === null) {
                                $inventory = Arr::get($listing->value ?? [], 'stock', null);
                            }
                            
                            // If still empty, check if there's any data in value
                            if ($inventory === null && isset($listing->value)) {
                                $inventory = !empty($listing->value) ? 1 : 0;
                            }
                        } else {
                            // For other marketplace statuses, get from value JSON
                            $inventory = Arr::get($listing->value ?? [], 'inventory', null);
                        }
                        
                        // If inventory value exists, update it in the mapping
                        if ($inventory !== null && $inventory !== '') {
                            // Store the actual inventory value
                            $data[$sku]->$inventoryField = (int)$inventory;
                            
                            // Also save to database
                            $data[$sku]->save();
                        }
                    }
                }
            }
        

        $datainfo = $this->getDataInfo($data);

        $totalNotMatching = 0;
        foreach (['shopify','amazon','walmart','reverb','shein','doba','temu','macy','ebay1','ebay2','ebay3','bestbuy','tiendamia','pls','business5core'] as $platform) {
            $totalNotMatching += $datainfo[$platform]['notmatching'] ?? 0;
        }
        // dd($datainfo);
    return response()->json([
        'message' => 'Data fetched successfully',
        'data' => $data,
        'datainfo' => $datainfo,
        'totalNotMatching' => $totalNotMatching,
        'status' => 200
    ]);


        }
        } catch (\Exception $e) {
            \Log::error('Stock mapping error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Data fetched successfully',
                'data' => [],
                'datainfo' => [],
                'totalNotMatching' => 0,
                'status' => 200
            ]);
        }
}

protected function getDataInfo($data)
{
    $platforms = [
        'shopify', 'amazon', 'walmart', 'reverb', 'shein', 'doba',
        'temu', 'macy', 'ebay1', 'ebay2', 'ebay3','bestbuy','tiendamia','pls','business5core'
    ];

    // Initialize info array
    $info = [];
    foreach ($platforms as $platform) {
        $info[$platform] = [
            'matching' => 0,
            'notmatching' => 0,
        ];
    }

    // Process each item
    foreach ($data as $item) {
        // Handle both array and object access
        $shopifyInventoryRaw = is_array($item) ? ($item['inventory_shopify'] ?? 0) : ($item->inventory_shopify ?? 0);
        $shopifyInventory = is_numeric($shopifyInventoryRaw) ? (int)$shopifyInventoryRaw : 0;
        
        // If Shopify inventory is negative, set it to 0
        if ($shopifyInventory < 0) {
            $shopifyInventory = 0;
        }

        foreach ($platforms as $platform) {
            if ($platform === 'shopify') {
                continue; // Skip comparison for Shopify itself
            }

            $fieldName = "inventory_{$platform}";
            $platformInventoryRaw = is_array($item) ? ($item[$fieldName] ?? 0) : ($item->$fieldName ?? 0);
            $platformInventory = is_numeric($platformInventoryRaw) ? (int)$platformInventoryRaw : 0;

            // Skip only if value is "Not Listed" or "NRL"
            if (in_array($platformInventoryRaw, ['Not Listed', 'NRL'], true)) {
                continue;
            }

            // Only count if at least one inventory is greater than 0
            // If both are 0, we can skip (no actual inventory to match)
            if ($platformInventory === 0 && $shopifyInventory === 0) {
                continue;
            }

            // Calculate ±1% tolerance (applies to all platforms automatically)
            $tolerance = $shopifyInventory * 0.01;
            $difference = abs($platformInventory - $shopifyInventory);
            
            // Match if exact or within ±1% tolerance
            if ($platformInventory === $shopifyInventory || $difference <= $tolerance) {
                $info[$platform]['matching']++;
            } else {
                $info[$platform]['notmatching']++;
            }
        }
    }

    return $info;
}
    
}

