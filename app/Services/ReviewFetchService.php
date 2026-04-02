<?php

namespace App\Services;

use App\Models\SkuReview;
use App\Models\ProductMaster;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ReviewFetchService
{
    /**
     * Fetch Amazon reviews via SP-API or stored data.
     * NOTE: This is a scaffold — replace with real SP-API calls when credentials are available.
     * Reviews are NEVER fetched on page load; always dispatched via FetchReviewsJob.
     */
    public function fetchAmazonReviews(string $sku, int $maxPages = 5): int
    {
        $saved = 0;

        try {
            // Validate SKU exists in product master
            $product = ProductMaster::where('sku', $sku)->first();
            if (!$product) {
                Log::warning("ReviewFetchService: SKU not found in product_master", ['sku' => $sku]);
                return 0;
            }

            // TODO: Replace with real Amazon SP-API call
            // $api = new AmazonSpApiService();
            // $reviews = $api->getProductReviews($sku, $maxPages);
            $reviews = $this->getMockAmazonReviews($sku);

            foreach ($reviews as $review) {
                $saved += $this->storeReview($review, $product, 'amazon');
            }

            Log::info("ReviewFetchService: Amazon fetch complete", ['sku' => $sku, 'saved' => $saved]);
        } catch (\Exception $e) {
            Log::error("ReviewFetchService: Amazon fetch failed", [
                'sku'   => $sku,
                'error' => $e->getMessage(),
            ]);
        }

        return $saved;
    }

    /**
     * Fetch eBay reviews via Finding API or stored data.
     * NOTE: This is a scaffold — replace with real eBay API calls when credentials are available.
     */
    public function fetchEbayReviews(string $sku, int $maxPages = 5): int
    {
        $saved = 0;

        try {
            $product = ProductMaster::where('sku', $sku)->first();
            if (!$product) {
                Log::warning("ReviewFetchService: SKU not found in product_master", ['sku' => $sku]);
                return 0;
            }

            // TODO: Replace with real eBay Feedback API call
            $reviews = $this->getMockEbayReviews($sku);

            foreach ($reviews as $review) {
                $saved += $this->storeReview($review, $product, 'ebay');
            }

            Log::info("ReviewFetchService: eBay fetch complete", ['sku' => $sku, 'saved' => $saved]);
        } catch (\Exception $e) {
            Log::error("ReviewFetchService: eBay fetch failed", [
                'sku'   => $sku,
                'error' => $e->getMessage(),
            ]);
        }

        return $saved;
    }

    /**
     * Process CSV data already parsed into array rows.
     */
    public function processCsvRows(array $rows): array
    {
        $stats = ['saved' => 0, 'skipped' => 0, 'errors' => 0];

        foreach ($rows as $row) {
            try {
                $sku = trim($row['sku'] ?? '');
                if (!$sku) {
                    $stats['errors']++;
                    continue;
                }

                $product = ProductMaster::where('sku', $sku)->first();
                if (!$product) {
                    $stats['errors']++;
                    continue;
                }

                $reviewId = trim($row['review_id'] ?? '') ?: null;
                $marketplace = strtolower(trim($row['marketplace'] ?? 'csv'));

                // Duplicate check
                if ($reviewId) {
                    $exists = SkuReview::where('marketplace', $marketplace)
                        ->where('review_id', $reviewId)
                        ->exists();

                    if ($exists) {
                        $stats['skipped']++;
                        continue;
                    }
                }

                SkuReview::create([
                    'sku'           => $sku,
                    'product_id'    => $product->id,
                    'marketplace'   => $marketplace,
                    'review_id'     => $reviewId,
                    'rating'        => intval($row['rating'] ?? 0) ?: null,
                    'review_title'  => $row['review_title'] ?? null,
                    'review_text'   => $row['review_text'] ?? null,
                    'reviewer_name' => $row['reviewer_name'] ?? null,
                    'review_date'   => $this->parseDate($row['review_date'] ?? null),
                    'source_type'   => 'csv',
                    'supplier_id'   => $product->supplier_id ?? null,
                ]);

                $stats['saved']++;
            } catch (\Exception $e) {
                Log::error("ReviewFetchService: CSV row error", ['error' => $e->getMessage()]);
                $stats['errors']++;
            }
        }

        return $stats;
    }

    private function storeReview(array $data, ProductMaster $product, string $marketplace): int
    {
        $reviewId = $data['review_id'] ?? null;

        if ($reviewId) {
            $exists = SkuReview::where('marketplace', $marketplace)
                ->where('review_id', $reviewId)
                ->exists();

            if ($exists) {
                return 0;
            }
        }

        SkuReview::create([
            'sku'           => $product->sku,
            'product_id'    => $product->id,
            'marketplace'   => $marketplace,
            'review_id'     => $reviewId,
            'rating'        => $data['rating'] ?? null,
            'review_title'  => $data['title'] ?? null,
            'review_text'   => $data['body'] ?? null,
            'reviewer_name' => $data['reviewer'] ?? null,
            'review_date'   => $data['date'] ?? null,
            'source_type'   => 'api',
            'supplier_id'   => $product->supplier_id ?? null,
        ]);

        return 1;
    }

    private function parseDate(?string $dateStr): ?string
    {
        if (!$dateStr) {
            return null;
        }
        try {
            return Carbon::parse($dateStr)->toDateString();
        } catch (\Exception $e) {
            return null;
        }
    }

    // --- Mock data for development (remove when real APIs are connected) ---

    private function getMockAmazonReviews(string $sku): array
    {
        return [
            [
                'review_id' => 'AMZ_' . $sku . '_' . uniqid(),
                'rating'    => rand(1, 5),
                'title'     => 'Product review',
                'body'      => 'Sample amazon review text for testing.',
                'reviewer'  => 'Amazon Customer',
                'date'      => Carbon::now()->subDays(rand(1, 30))->toDateString(),
            ],
        ];
    }

    private function getMockEbayReviews(string $sku): array
    {
        return [
            [
                'review_id' => 'EBAY_' . $sku . '_' . uniqid(),
                'rating'    => rand(1, 5),
                'title'     => 'eBay feedback',
                'body'      => 'Sample ebay feedback text for testing.',
                'reviewer'  => 'eBay Buyer',
                'date'      => Carbon::now()->subDays(rand(1, 30))->toDateString(),
            ],
        ];
    }
}
