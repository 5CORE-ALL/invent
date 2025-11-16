<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\ProductMaster;
use App\Models\Ebay3Metric;
use App\Models\AmazonDatasheet;
use App\Models\BestbuyUsaProduct;
use App\Models\MacyProduct;
use App\Models\MercariWShipSheetdata;
use App\Models\PLSProduct;
use App\Models\ReverbProduct;

class SyncShopifyAllChannelsData extends Command
{
    protected $signature = 'app:sync-shopify-all-channels-data';
    protected $description = 'Sync SKU, parent, and channel metrics JSON value into shopify_all_channels_data';

    public function handle(): int
    {
        $this->info('Starting sync into shopify_all_channels_data...');

        // Get all unique SKUs from ProductMaster and ReverbProduct
        $productMasterSkus = ProductMaster::pluck('sku', 'parent')->toArray();
        $reverbSkus = ReverbProduct::whereNotIn('sku', array_keys($productMasterSkus))->pluck('sku')->toArray();
        
        // 🆕 Also get SKUs from temu_metrics that might not be in ProductMaster/Reverb
        $temuSkus = DB::table('temu_metrics')
            ->whereNotIn('sku', array_merge(array_keys($productMasterSkus), $reverbSkus))
            ->pluck('sku')
            ->toArray();
        
        // Combine both sets of SKUs
        $allSkus = array_merge(
            array_map(function($sku, $parent) {
                return ['sku' => $sku, 'parent' => $parent];
            }, array_keys($productMasterSkus), $productMasterSkus),
            array_map(function($sku) {
                return ['sku' => $sku, 'parent' => null];
            }, $reverbSkus),
            array_map(function($sku) {
                return ['sku' => $sku, 'parent' => null];
            }, $temuSkus)
        );
        
        $this->info('Total SKUs to process: ' . count($allSkus));

        // Process in chunks
        collect($allSkus)->chunk(1000)->each(function ($products) {
            foreach ($products as $product) {
                $sku = $product['sku'];
                $parent = $product['parent'];

                    // 🟢 Shopify Orders grouped by platform (tag-based)
                    $shopifyorders = DB::connection('apicentral')
                        ->table('shopify_order_items')
                        ->selectRaw("
                            CASE
                                WHEN tags LIKE '%TikTok%' THEN 'TikTok'
                                WHEN tags LIKE '%Amazon%' THEN 'Amazon'
                                WHEN tags LIKE '%eBay%' THEN 'eBay'
                                WHEN tags LIKE '%Temu%' THEN 'Temu'
                                WHEN tags LIKE '%Walmart%' THEN 'Walmart'
                                WHEN tags LIKE '%Reverb%' THEN 'Reverb'
                                WHEN tags LIKE '%Wayfair%' THEN 'Wayfair'
                                WHEN tags LIKE '%Shein%' THEN 'Shein'
                                WHEN tags LIKE '%AliExpress%' THEN 'AliExpress'
                                WHEN tags LIKE '%Mercari%' THEN 'Mercari'
                                WHEN tags LIKE '%doba%' THEN 'doba'
                                WHEN tags LIKE '%Ebay3%' THEN 'Ebay3'
                                WHEN tags LIKE '%Ebay2%' THEN 'Ebay2'

                                WHEN tags LIKE '%Best Buy USA%' THEN 'Best Buy USA'
                                WHEN tags LIKE '%Macy%' THEN 'Macy\'s'
                                WHEN source_name LIKE '%shopify_draft_order%' THEN 'shopify_draft_order'
                                ELSE 'Other'
                            END AS platform,

                            -- Order counts
                            SUM(CASE WHEN order_date >= CURDATE() - INTERVAL 30 DAY THEN 1 ELSE 0 END) AS l30,
                            SUM(CASE WHEN order_date >= CURDATE() - INTERVAL 60 DAY THEN 1 ELSE 0 END) AS l60,
                            SUM(CASE WHEN order_date >= CURDATE() - INTERVAL 90 DAY THEN 1 ELSE 0 END) AS l90,

                            -- Quantities sold
                            SUM(CASE WHEN order_date >= CURDATE() - INTERVAL 30 DAY THEN quantity ELSE 0 END) AS qty_l30,
                            SUM(CASE WHEN order_date >= CURDATE() - INTERVAL 60 DAY THEN quantity ELSE 0 END) AS qty_l60,
                            SUM(CASE WHEN order_date >= CURDATE() - INTERVAL 90 DAY THEN quantity ELSE 0 END) AS qty_l90
                        ")
                        ->where('sku', $sku)
                        ->whereRaw("tags REGEXP 'TikTok|Amazon|eBay|Temu|Walmart|Reverb|Macy|Wayfair|Shein|AliExpress|Mercari|doba|Ebay3|Best Buy USA|Ebay2|shopify_draft_order'")
                        ->groupBy('platform')
                        ->orderByDesc('qty_l30')
                        ->get();

                    // 🧩 Other channel data (no change here)
                    $ebayOne = DB::connection('apicentral')->table('ebay_one_metrics')->where('sku', $sku)->select('ebay_l30', 'ebay_l60')->first();
                    $ebayTwo = DB::connection('apicentral')->table('ebay2_metrics')->where('sku', $sku)->select('ebay_l30', 'ebay_l60')->first();
                    $ebayThree = Ebay3Metric::query()->where('sku', $sku)->select(['ebay_l30', 'ebay_l60'])->first();
                    $amazon = AmazonDatasheet::query()->where('sku', $sku)->select(['units_ordered_l30', 'units_ordered_l60'])->first();
                    $reverb = ReverbProduct::query()->where('sku', $sku)->select(['r_l30', 'r_l60'])->first();
                    $macy = MacyProduct::query()->where('sku', $sku)->select(['m_l30', 'm_l60'])->first();
                    $bestbuy = BestbuyUsaProduct::query()->where('sku', $sku)->select(['m_l30', 'm_l60'])->first();
                    $wayfair = DB::table('wayfair_product_sheets')->where('sku', $sku)->select(['l30', 'l60'])->first();
                    $mercariWship = MercariWShipSheetdata::query()->where('sku', $sku)->select(['l30', 'l60'])->first();
                    $mercariWithoutShip = DB::table('mercari_wo_ship_sheet_data')->where('sku', $sku)->select(['l30', 'l60'])->first();
                    $pls = PLSProduct::query()->where('sku', $sku)->select(['p_l30', 'p_l60'])->first();
                    $fbMarketplace = DB::table('fb_marketplace_sheet_data')->where('sku', $sku)->select(['l30', 'l60'])->first();
                    $tikTok = DB::table('tiktok_sheet_data')->where('sku', $sku)->select(['l30', 'l60'])->first();
                    $business5core = DB::table('business_five_core_sheet_data')->where('sku', $sku)->select(['l30', 'l60'])->first();
                    $aliexpress = DB::table('aliexpress_sheet_data')->where('sku', $sku)->select(['aliexpress_l30', 'aliexpress_l60'])->first();
                    $shein = DB::table('shein_sheet_data')->where('sku', $sku)->select(['shopify_sheinl30', 'shopify_sheinl60'])->first();
                    $walmart = DB::table('walmart_metrics')->where('sku', $sku)->select(['l30', 'l60'])->first();
                    $temu = DB::table('temu_metrics')->where('sku', $sku)->select(['quantity_purchased_l30', 'quantity_purchased_l60'])->first();
                    $shopify = DB::table('shopify_skus')->where('sku', $sku)->select(['quantity', 'inv', 'image_src'])->first();

                    // 🧠 Combine all data
                    $value = [
                        'ebay_one' => [
                            'l30' => $ebayOne->ebay_l30 ?? 0,
                            'l60' => $ebayOne->ebay_l60 ?? 0,
                        ],
                        'ebay_two' => [
                            'l30' => $ebayTwo->ebay_l30 ?? 0,
                            'l60' => $ebayTwo->ebay_l60 ?? 0,
                        ],
                        'ebay_three' => [
                            'l30' => $ebayThree->ebay_l30 ?? 0,
                            'l60' => $ebayThree->ebay_l60 ?? 0,
                        ],
                        'amazon' => [
                            'l30' => $amazon->units_ordered_l30 ?? 0,
                            'l60' => $amazon->units_ordered_l60 ?? 0,
                        ],
                        'reverb' => [
                            'l30' => $reverb->r_l30 ?? 0,
                            'l60' => $reverb->r_l60 ?? 0,
                        ],
                        'macy' => [
                            'l30' => $macy->m_l30 ?? 0,
                            'l60' => $macy->m_l60 ?? 0,
                        ],
                        'bestbuy' => [
                            'l30' => $bestbuy->m_l30 ?? 0,
                            'l60' => $bestbuy->m_l60 ?? 0,
                        ],
                        'wayfair' => [
                            'l30' => $wayfair->l30 ?? 0,
                            'l60' => $wayfair->l60 ?? 0,
                        ],
                        'mercariWship' => [
                            'l30' => $mercariWship->l30 ?? 0,
                            'l60' => $mercariWship->l60 ?? 0,
                        ],
                        'mercariWithoutShip' => [
                            'l30' => $mercariWithoutShip->l30 ?? 0,
                            'l60' => $mercariWithoutShip->l60 ?? 0,
                        ],
                        'pls' => [
                            'l30' => $pls->p_l30 ?? 0,
                            'l60' => $pls->p_l60 ?? 0,
                        ],
                        'fb_marketplace' => [
                            'l30' => $fbMarketplace->l30 ?? 0,
                            'l60' => $fbMarketplace->l60 ?? 0,
                        ],
                        'tiktok' => [
                            'l30' => $tikTok->l30 ?? 0,
                            'l60' => $tikTok->l60 ?? 0,
                        ],
                        'business5core' => [
                            'l30' => $business5core->l30 ?? 0,
                            'l60' => $business5core->l60 ?? 0,
                        ],
                        'aliexpress' => [
                            'l30' => $aliexpress->aliexpress_l30 ?? 0,
                            'l60' => $aliexpress->aliexpress_l60 ?? 0,
                        ],
                        'shein' => [
                            'l30' => $shein->shopify_sheinl30 ?? 0,
                            'l60' => $shein->shopify_sheinl60 ?? 0,
                        ],
                        'walmart' => [
                            'l30' => $walmart->l30 ?? 0,
                            'l60' => $walmart->l60 ?? 0,
                        ],
                        'temu' => [
                            'l30' => $temu->quantity_purchased_l30 ?? 0,
                            'l60' => $temu->quantity_purchased_l60 ?? 0,
                        ],
                        'shopify' => [
                            'inv' => $shopify->inv ?? 0,
                            'qty' => $shopify->quantity ?? 0,
                            'img' => $shopify->image_src ?? '',
                        ],
                        // 🟩 Shopify Orders (including quantity)
                        'shopifyorders' => $shopifyorders->mapWithKeys(function ($row) {
                            return [
                                $row->platform => [
                                    'l30' => (int) $row->l30,
                                    'l60' => (int) $row->l60,
                                    'l90' => (int) $row->l90,
                                    'qty_l30' => (int) $row->qty_l30,
                                    'qty_l60' => (int) $row->qty_l60,
                                    'qty_l90' => (int) $row->qty_l90,
                                ]
                            ];
                        })->toArray(),
                    ];

                    // 🟩 Upsert into main table
                    DB::table('shopify_all_channels_data')->updateOrInsert(
                        ['sku' => $sku],
                        [
                            'parent' => $parent,
                            'value' => json_encode($value),
                            'updated_at' => now(),
                        ]
                    );
                }
            });

        $this->info('✅ Sync complete successfully.');
        return self::SUCCESS;
    }
}
