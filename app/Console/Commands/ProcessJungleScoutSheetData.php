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
    protected $description = 'Fetch ASIN data from amazon_datsheets and process it with JungleScout API (includes competitor sales data)';

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

        $saved = 0;
        $skipped = 0;
        $alreadyFresh = 0;

        $byAsin = [];
        foreach ($data as $row) {
            $asinKey = strtoupper(trim((string) ($row['ASIN'] ?? '')));
            if ($asinKey === '' || isset($byAsin[$asinKey])) {
                continue;
            }
            $byAsin[$asinKey] = $row;
        }

        $freshCutoff = now()->subDays(30);
        $priority = [];
        foreach (JungleScoutProductData::query()->get(['asin', 'updated_at', 'data']) as $existing) {
            $asinKey = strtoupper(trim((string) $existing->asin));
            if ($asinKey === '' || isset($priority[$asinKey])) {
                continue;
            }
            $payload = is_array($existing->data) ? $existing->data : [];
            $lqs = $payload['listing_quality_score'] ?? null;
            $hasLqs = is_numeric($lqs) && (string) $lqs !== '';
            $isFresh = $existing->updated_at && $existing->updated_at->gte($freshCutoff);
            if ($hasLqs && $isFresh) {
                $priority[$asinKey] = 2;
            } elseif ($hasLqs) {
                $priority[$asinKey] = 1;
            } else {
                $priority[$asinKey] = 0;
            }
        }

        $queue = array_values($byAsin);
        usort($queue, function (array $a, array $b) use ($priority): int {
            $aKey = strtoupper(trim((string) ($a['ASIN'] ?? '')));
            $bKey = strtoupper(trim((string) ($b['ASIN'] ?? '')));

            return ($priority[$aKey] ?? 0) <=> ($priority[$bKey] ?? 0);
        });

        $this->info('Unique ASINs: ' . count($queue) . '. Missing and unscored ASINs are fetched first.');

        try {
            // Query one ASIN at a time so JungleScout returns the exact product.
            // page[size]=1 often returned a different keyword hit and the ASIN was
            // skipped forever. Ask for a few rows and keep only the exact ASIN.
            foreach ($queue as $inputRow) {
                $asin = strtoupper(trim((string) $inputRow['ASIN']));
                if (($priority[$asin] ?? 0) === 2) {
                    $alreadyFresh++;
                    continue;
                }

                try {
                    $apiResponse = Http::withOptions(['verify' => false])
                        ->withHeaders([
                            'Authorization' => config('services.junglescout.key_with_title'),
                            'Content-Type'  => 'application/vnd.api+json',
                            'Accept'        => 'application/vnd.junglescout.v1+json',
                            'X-API-Type'    => 'junglescout',
                        ])
                        ->timeout(40)
                        ->post('https://developer.junglescout.com/api/product_database_query?marketplace=us&page[size]=10', [
                            'data' => [
                                'type' => 'product_database_query',
                                'attributes' => [
                                    'include_keywords' => [$asin],
                                ],
                            ],
                        ]);

                    if ($apiResponse->status() === 401 || $apiResponse->status() === 403) {
                        $detail = (string) ($apiResponse->json('errors.0.detail') ?? $apiResponse->body());
                        Log::error("JungleScout API stopped at ASIN {$asin}: " . $apiResponse->status() . ' ' . $detail);
                        $this->error('Jungle Scout stopped the pull (' . $apiResponse->status() . '). ' . $detail);
                        break;
                    }

                    if (!$apiResponse->ok()) {
                        Log::warning("JungleScout API failed for ASIN {$asin}: " . $apiResponse->status());
                        $skipped++;
                        usleep(500000);
                        continue;
                    }

                    $products = $apiResponse->json()['data'] ?? [];
                    $matchedProduct = false;

                    foreach ($products as $product) {
                        $asinId = $product['id'] ?? null;
                        if (!$asinId) continue;

                        $cleanAsin = str_replace('us/', '', $asinId);
                        if (strtoupper($cleanAsin) !== strtoupper($asin)) continue;

                        $attributes = $product['attributes'] ?? [];

                        $allData = [
                            'id'                            => $asinId,
                            'price'                         => $attributes['price'] ?? '',
                            'reviews'                       => $attributes['reviews'] ?? '',
                            'category'                      => $attributes['category'] ?? '',
                            'rating'                        => $attributes['rating'] ?? '',
                            'image_url'                     => $attributes['image_url'] ?? '',
                            'parent_asin'                   => $attributes['parent_asin'] ?? '',
                            'brand'                         => $attributes['brand'] ?? '',
                            'product_rank'                  => $attributes['product_rank'] ?? '',
                            'weight'                        => $attributes['weight_value'] ?? '',
                            'dimensions'                    => implode(' x ', [
                                $attributes['length_value'] ?? '',
                                $attributes['width_value']  ?? '',
                                $attributes['height_value'] ?? '',
                            ]),
                            'listing_quality_score'         => $attributes['listing_quality_score'] ?? '',
                            'approximate_30_day_revenue'    => $attributes['approximate_30_day_revenue'] ?? null,
                            'approximate_30_day_units_sold' => $attributes['approximate_30_day_units_sold'] ?? null,
                            'number_of_sellers'             => $attributes['number_of_sellers'] ?? null,
                            'buy_box_owner'                 => $attributes['buy_box_owner'] ?? null,
                            'buy_box_owner_seller_id'       => $attributes['buy_box_owner_seller_id'] ?? null,
                            'seller_type'                   => $attributes['seller_type'] ?? null,
                            'is_variant'                    => $attributes['is_variant'] ?? null,
                            'is_parent'                     => $attributes['is_parent'] ?? null,
                            'variants'                      => $attributes['variants'] ?? null,
                            'date_first_available'          => $attributes['date_first_available'] ?? null,
                        ];

                        JungleScoutProductData::updateOrCreate(
                            [
                                'asin'   => $inputRow['ASIN'],
                                'sku'    => $inputRow['SKU'],
                                'parent' => $inputRow['PARENT'],
                            ],
                            ['data' => $allData]
                        );

                        $saved++;
                        $matchedProduct = true;
                        break; // Only need the matched product
                    }

                    if (! $matchedProduct) {
                        $skipped++;
                    }

                } catch (\Exception $e) {
                    Log::warning("JungleScout error for ASIN {$asin}: " . $e->getMessage());
                    $skipped++;
                }

                // 200ms pause between requests to respect API rate limits
                usleep(200000);
            }

            $this->info("ASIN processing completed. Saved: {$saved}, Skipped/Not found: {$skipped}, Already fresh: {$alreadyFresh}.");
        } catch (\Exception $e) {
            Log::error('ASIN processing error: ' . $e->getMessage());

            
            Mail::raw('ASIN process failed: ' . $e->getMessage(), function ($message) {
                $adminEmail = config('services.admin.email');
                $message->to($adminEmail)->subject('ASIN Processing Error');
            });
        }        
    }
}
