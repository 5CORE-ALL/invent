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
        ];


            foreach ($marketplaces as $key => [$model, $inventoryField]) {
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
        

        $datainfo = $this->getDataInfo($data);

        $totalNotMatching = 0;
        foreach (['shopify','amazon','walmart','reverb','shein','doba','temu','macy','ebay1','ebay2','ebay3','bestbuy','tiendamia'] as $platform) {
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
}

protected function getDataInfo($data)
{
    $platforms = [
        'shopify', 'amazon', 'walmart', 'reverb', 'shein', 'doba',
        'temu', 'macy', 'ebay1', 'ebay2', 'ebay3','bestbuy','tiendamia'
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
        $shopifyInventoryRaw = $item['inventory_shopify'] ?? 0;
        $shopifyInventory = is_numeric($shopifyInventoryRaw) ? (int)$shopifyInventoryRaw : 0;
        
        // If Shopify inventory is negative, set it to 0
        if ($shopifyInventory < 0) {
            $shopifyInventory = 0;
            $item['inventory_shopify'] = 0;
        }

        foreach ($platforms as $platform) {
            if ($platform === 'shopify') {
                continue; // Skip comparison for Shopify itself
            }

            $platformInventoryRaw = $item["inventory_{$platform}"] ?? 0;
            $platformInventory = is_numeric($platformInventoryRaw) ? (int)$platformInventoryRaw : 0;

            // Skip invalid or placeholder values
            if (in_array($platformInventoryRaw, ['Not Listed', 'NRL'], true) || $platformInventory === 0 || $shopifyInventory === 0) {
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

