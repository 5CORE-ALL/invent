<?php

namespace App\Console\Commands;

use App\Models\AmazonSkuCompetitor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Fills Revenue (30d) / Units (30d) / Buy Box / Type on the Amazon LMP list
 * (amazon_sku_competitors.monthly_revenue, monthly_units_sold, buy_box_owner, seller_type_js).
 *
 * Scheduled in App\Console\Kernel. It used to be manual-only, so those columns froze at the
 * one-off run and every competitor added afterwards stayed blank.
 */
class ProcessCompetitorSalesData extends Command
{
    /** JungleScout product_database_query accepts up to 100 keywords per call. */
    private const CHUNK_SIZE = 100;

    protected $signature = 'app:process-competitor-sales-data
                            {--limit= : Limit number of unique ASINs to process for testing}
                            {--asin= : Test with specific ASIN}';

    protected $description = 'Fetch competitor ASIN sales data from JungleScout API and update amazon_sku_competitors table';

    public function handle()
    {
        $limit = $this->option('limit');
        $testAsin = $this->option('asin');

        $apiKey = config('services.junglescout.key_with_title');
        if (! $apiKey) {
            $this->error('JUNGLESCOUT_API_KEY_WITH_TITLE is not set; cannot refresh competitor sales data.');
            Log::error('Competitor sales data skipped: JUNGLESCOUT_API_KEY_WITH_TITLE missing');

            return 1;
        }

        // One entry per unique ASIN. Every SKU stores its own copy of the ASIN, so the old
        // per-row batches wasted API calls on duplicates and (with --limit) skipped ASINs.
        $query = DB::table('amazon_sku_competitors')
            ->whereNotNull('asin')
            ->where('asin', '!=', '');

        if ($testAsin) {
            $query->whereRaw('UPPER(TRIM(asin)) = ?', [strtoupper(trim($testAsin))]);
            $this->info("TESTING MODE: Processing only ASIN: $testAsin");
        }

        // Never-pulled ASINs first, then oldest data first, so a partial run still helps.
        $asins = $query
            ->selectRaw('UPPER(TRIM(asin)) as asin_key')
            ->groupBy('asin_key')
            ->orderByRaw('(MIN(sales_data_updated_at) IS NULL) DESC, MIN(sales_data_updated_at) ASC')
            ->pluck('asin_key')
            ->filter(fn ($asin) => (bool) preg_match('/^[A-Z0-9]{10}$/', (string) $asin))
            ->values();

        if ($limit && ! $testAsin) {
            $asins = $asins->take((int) $limit)->values();
            $this->info("TESTING MODE: Processing only {$asins->count()} ASINs");
        }

        $this->info('Found ' . $asins->count() . ' unique competitor ASINs in amazon_sku_competitors');

        if ($asins->isEmpty()) {
            $this->info('No competitors found to process.');

            return 0;
        }

        try {
            $chunks = $asins->chunk(self::CHUNK_SIZE);
            $processedCount = 0;
            $updatedCount = 0;
            $failedChunks = 0;
            $missing = 0;

            foreach ($chunks as $chunkIndex => $chunk) {
                $chunkAsins = $chunk->values()->all();

                $this->info('Processing chunk ' . ($chunkIndex + 1) . ' of ' . $chunks->count() . ' (' . count($chunkAsins) . ' ASINs)');

                $apiResponse = Http::withOptions(['verify' => false])
                    ->withHeaders([
                        'Authorization' => $apiKey,
                        'Content-Type' => 'application/vnd.api+json',
                        'Accept' => 'application/vnd.junglescout.v1+json',
                        'X-API-Type' => 'junglescout',
                    ])
                    ->post('https://developer.junglescout.com/api/product_database_query?marketplace=us', [
                        'data' => [
                            'type' => 'product_database_query',
                            'attributes' => [
                                'include_keywords' => $chunkAsins,
                            ],
                        ],
                    ]);

                if (! $apiResponse->ok()) {
                    $failedChunks++;
                    $this->error('JungleScout API failed with status: ' . $apiResponse->status());
                    Log::error('JungleScout API failed for chunk ' . ($chunkIndex + 1), [
                        'status' => $apiResponse->status(),
                        'response' => $apiResponse->body(),
                    ]);

                    continue;
                }

                $products = $apiResponse->json()['data'] ?? [];
                $this->info('Received ' . count($products) . ' products from JungleScout API');

                $returned = [];
                foreach ($products as $product) {
                    $asinId = $product['id'] ?? null;
                    $attributes = $product['attributes'] ?? [];

                    if (! $asinId) {
                        continue;
                    }

                    // Clean ASIN (remove 'us/' prefix if present)
                    $cleanAsin = strtoupper(trim(str_replace('us/', '', $asinId)));
                    $returned[$cleanAsin] = true;

                    // Update every copy of this ASIN (all SKUs)
                    $updatedCount += AmazonSkuCompetitor::query()
                        ->whereRaw('UPPER(TRIM(asin)) = ?', [$cleanAsin])
                        ->update([
                            'monthly_revenue' => $attributes['approximate_30_day_revenue'] ?? null,
                            'monthly_units_sold' => $attributes['approximate_30_day_units_sold'] ?? null,
                            'buy_box_owner' => $attributes['buy_box_owner'] ?? null,
                            'seller_type_js' => $attributes['seller_type'] ?? null,
                            'sales_data_updated_at' => now(),
                        ]);

                    $processedCount++;
                }

                $missing += count(array_diff($chunkAsins, array_keys($returned)));

                if ($chunkIndex < $chunks->count() - 1) {
                    sleep(1);
                }
            }

            $this->info('Processing completed.');
            $this->info("Processed: $processedCount unique ASINs");
            $this->info("Updated: $updatedCount competitor records");
            $this->info("Not returned by JungleScout: $missing ASINs");
            if ($failedChunks > 0) {
                $this->warn("Failed chunks: $failedChunks");
            }

            Log::info('Competitor sales data refresh completed', [
                'unique_asins' => $asins->count(),
                'processed' => $processedCount,
                'updated_rows' => $updatedCount,
                'not_returned' => $missing,
                'failed_chunks' => $failedChunks,
            ]);

            return $failedChunks > 0 ? 1 : 0;
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            Log::error('Competitor sales data processing error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            // Send email notification (fail silently if mail not configured)
            try {
                $adminEmail = config('services.admin.email');
                if ($adminEmail) {
                    Mail::raw('Competitor sales data processing failed: ' . $e->getMessage() . "\n\n" . $e->getTraceAsString(), function ($message) use ($adminEmail) {
                        $message->to($adminEmail)->subject('Competitor Sales Data Processing Error');
                    });
                }
            } catch (\Exception $mailException) {
                Log::warning('Could not send error email: ' . $mailException->getMessage());
            }

            return 1;
        }
    }
}
