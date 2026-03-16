<?php

namespace App\Console\Commands;

use App\Models\JungleScoutProductData;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProcessJungleScoutSheetData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:process-jungle-scout-sheet-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch ASIN data from Google Sheet and process it';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Fetch ASINs, SKUs and Parent from amazon_datsheets table instead of Google Sheet
        $data = \DB::table('amazon_datsheets as ad')
            ->join('product_master as pm', 'ad.sku', '=', 'pm.sku')
            ->whereNotNull('ad.asin')
            ->where('ad.asin', '!=', '')
            ->whereNull('pm.deleted_at')
            ->select('ad.asin as ASIN', 'ad.sku as SKU', 'pm.parent as PARENT')
            ->distinct()
            ->get()
            ->toArray();
        
        $data = array_map(function($item) {
            return (array) $item;
        }, $data);
        
        $this->info('Fetched ' . count($data) . ' ASINs from amazon_datsheets table');
        
        try {

            $chunks = array_chunk($data, 100);

            foreach ($chunks as $chunk) {
                $asins = array_column($chunk, 'ASIN');
                info('$asins', [$asins]);
                $apiResponse = Http::withOptions(['verify' => false])
                    ->withHeaders([
                        'Authorization' => config('services.junglescout.key_with_title'),
                        'Content-Type'  => 'application/vnd.api+json',
                        'Accept'        => 'application/vnd.junglescout.v1+json',
                        'X-API-Type'    => 'junglescout',
                    ])
                    ->post('https://developer.junglescout.com/api/product_database_query?marketplace=us', [
                        'data' => [
                            'type' => 'product_database_query',
                            'attributes' => [
                                'include_keywords' => $asins,
                            ],
                        ],
                    ]);

                if (!$apiResponse->ok()) {
                    throw new \Exception('JungleScout API failed with status: ' . $apiResponse->status());
                }

                $products = $apiResponse->json()['data'] ?? [];

                foreach ($products as $product) {
                    $asinId = $product['id'] ?? null;
                    $attributes = $product['attributes'] ?? [];

                    if (!$asinId) continue;

                    $cleanAsin = str_replace('us/', '', $asinId);
                    $inputRow = collect($chunk)->firstWhere('ASIN', $cleanAsin);

                    if (!$inputRow) continue;

                    $allData = [
                        'id' => $asinId,
                        'price' => $attributes['price'] ?? '',
                        'reviews' => $attributes['reviews'] ?? '',
                        'category' => $attributes['category'] ?? '',
                        'rating' => $attributes['rating'] ?? '',
                        'image_url' => $attributes['image_url'] ?? '',
                        'parent_asin' => $attributes['parent_asin'] ?? '',
                        'brand' => $attributes['brand'] ?? '',
                        'product_rank' => $attributes['product_rank'] ?? '',
                        'weight' => $attributes['weight_value'] ?? '',
                        'dimensions' => implode(' x ', [
                            $attributes['length_value'] ?? '',
                            $attributes['width_value'] ?? '',
                            $attributes['height_value'] ?? ''
                        ]),
                        'listing_quality_score' => $attributes['listing_quality_score'] ?? ''
                    ];

                    JungleScoutProductData::updateOrCreate(
                        [
                            'asin' => $inputRow['ASIN'],
                            'sku' => $inputRow['SKU'],
                            'parent' => $inputRow['PARENT'],
                        ],
                        [
                            'data' => $allData
                        ]
                    );
                }
            }

            $this->info('ASIN processing completed successfully.');
        } catch (\Exception $e) {
            Log::error('ASIN processing error: ' . $e->getMessage());

            
            Mail::raw('ASIN process failed: ' . $e->getMessage(), function ($message) {
                $adminEmail = config('services.admin.email');
                $message->to($adminEmail)->subject('ASIN Processing Error');
            });
        }        
    }
}
