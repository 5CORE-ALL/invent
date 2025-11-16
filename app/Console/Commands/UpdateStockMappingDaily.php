<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ProductStockMapping;
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
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class UpdateStockMappingDaily extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stock:update-mapping-daily';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update stock mapping data with ±1% tolerance matching - runs daily';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting daily stock mapping update with ±1% tolerance...');
        
        try {
            $latestRecord = ProductStockMapping::orderBy('updated_at', 'desc')->first();
            
            if (!$latestRecord) {
                $this->error('No stock mapping records found.');
                return 1;
            }

            $data = ProductStockMapping::all()->groupBy('sku')->map(function ($items) {
                return $items->first();
            });

            $skusforNR = array_values(array_filter(array_map(function ($item) {
                return $item['sku'] ?? null;
            }, $data->toArray())));

            $this->info('Processing ' . count($skusforNR) . ' SKUs...');

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
                $listingData = $model::whereIn('sku', $skusforNR)
                    ->where('value->nr_req', 'NR')
                    ->get()
                    ->unique()
                    ->keyBy('sku');
                
                $this->info("Processing {$key}...");
                
                foreach ($listingData as $sku => $listing) {
                    $sku = str_replace("\u{00A0}", ' ', $sku);
                    $sku = trim(preg_replace('/\s+/', ' ', $sku));
                    
                    if (
                        isset($data[$sku]) &&
                        Arr::get($listing->value, 'nr_req') === 'NR' &&
                        $data[$sku]->$inventoryField > 0
                    ) {
                        $data[$sku]->$inventoryField = 'NRL';
                    }
                }
            }

            // Calculate statistics with ±1% tolerance
            $datainfo = $this->calculateDataInfo($data);
            
            $this->info('Stock mapping update completed successfully!');
            $this->table(
                ['Platform', 'Matching (±1%)', 'Not Matching'],
                collect($datainfo)->map(function ($stats, $platform) {
                    return [
                        $platform,
                        $stats['matching'] ?? 0,
                        $stats['notmatching'] ?? 0
                    ];
                })
            );

            Log::info('Daily stock mapping update completed', ['stats' => $datainfo]);
            
            return 0;
        } catch (\Exception $e) {
            $this->error('Error updating stock mapping: ' . $e->getMessage());
            Log::error('Daily stock mapping update failed', ['error' => $e->getMessage()]);
            return 1;
        }
    }

    /**
     * Calculate data info with ±1% tolerance
     */
    protected function calculateDataInfo($data)
    {
        $platforms = [
            'shopify', 'amazon', 'walmart', 'reverb', 'shein', 'doba',
            'temu', 'macy', 'ebay1', 'ebay2', 'ebay3', 'bestbuy', 'tiendamia'
        ];

        $info = [];
        foreach ($platforms as $platform) {
            $info[$platform] = [
                'matching' => 0,
                'notmatching' => 0,
            ];
        }

        foreach ($data as $item) {
            $shopifyInventoryRaw = $item['inventory_shopify'] ?? 0;
            $shopifyInventory = is_numeric($shopifyInventoryRaw) ? (int)$shopifyInventoryRaw : 0;
            
            if ($shopifyInventory < 0) {
                $shopifyInventory = 0;
                $item['inventory_shopify'] = 0;
            }

            foreach ($platforms as $platform) {
                if ($platform === 'shopify') {
                    continue;
                }

                $platformInventoryRaw = $item["inventory_{$platform}"] ?? 0;
                $platformInventory = is_numeric($platformInventoryRaw) ? (int)$platformInventoryRaw : 0;

                if (in_array($platformInventoryRaw, ['Not Listed', 'NRL'], true) || $platformInventory === 0 || $shopifyInventory === 0) {
                    continue;
                }

                // Apply ±1% tolerance automatically for all platforms
                $tolerance = $shopifyInventory * 0.01;
                $difference = abs($platformInventory - $shopifyInventory);

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
