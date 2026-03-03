<?php

namespace App\Console\Commands;

use App\Models\AmazonSkuCompetitor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BackfillSkuCompetitorsRatings extends Command
{
    protected $signature = 'repricer:backfill-sku-competitors-ratings
                            {--limit=50 : Max number of unique ASINs to process}
                            {--asin= : Process only this ASIN}';
    protected $description = 'Backfill rating, reviews, old price, delivery for existing amazon_sku_competitors rows (calls SerpApi per ASIN).';

    private string $serpApiKey = '1ce23be0f3d775e0d631854b4856791aefa6e003415b28e33eb99b5a9c6a83c9';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $limit = min(max(1, $limit), 200);
        $singleAsin = $this->option('asin');

        $baseQuery = AmazonSkuCompetitor::where(function ($q) {
            $q->whereNull('rating')->orWhereNull('reviews');
        });
        if ($singleAsin) {
            $baseQuery->where('asin', trim($singleAsin));
        }
        $asins = $baseQuery->select('asin')->distinct()->orderBy('asin')->limit($limit)->pluck('asin');

        if ($asins->isEmpty()) {
            $this->info('No SKU competitor rows with missing rating/reviews found.');
            return 0;
        }

        $totalNeeding = AmazonSkuCompetitor::where(function ($q) {
            $q->whereNull('rating')->orWhereNull('reviews');
        })->select('asin')->distinct()->count();
        $this->info("Found {$totalNeeding} unique ASIN(s) needing backfill. Processing " . $asins->count() . " in this run.");
        $updated = 0;
        $noData = 0;
        $bar = $this->output->createProgressBar($asins->count());
        $bar->start();

        foreach ($asins as $asin) {
            try {
                $response = Http::timeout(25)->get('https://serpapi.com/search', [
                    'engine' => 'amazon_product',
                    'amazon_domain' => 'amazon.com',
                    'asin' => $asin,
                    'api_key' => $this->serpApiKey,
                ]);
                if (!$response->successful()) {
                    $noData++;
                    $bar->advance();
                    continue;
                }
                $data = $response->json();
                $pr = $data['product_results'] ?? null;
                if (!$pr) {
                    $noData++;
                    $bar->advance();
                    continue;
                }
                $rating = isset($pr['rating']) && is_numeric($pr['rating']) ? (float) $pr['rating'] : null;
                $reviews = isset($pr['reviews']) && is_numeric($pr['reviews']) ? (int) $pr['reviews'] : null;
                $price = isset($pr['extracted_price']) ? (float) $pr['extracted_price'] : null;
                if ($price === null && isset($pr['price']) && is_string($pr['price'])) {
                    preg_match('/[\d,.]+/', $pr['price'], $m);
                    if (!empty($m)) {
                        $price = (float) str_replace(',', '', $m[0]);
                    }
                }
                $extractedOldPrice = $this->extractOldPriceFromProductResponse($data, $pr);
                $delivery = isset($pr['delivery']) && is_array($pr['delivery'])
                    ? array_values(array_filter(array_map('strval', $pr['delivery'])))
                    : null;
                $title = $pr['title'] ?? null;
                $thumbnail = $pr['thumbnail'] ?? (isset($pr['thumbnails'][0]) ? $pr['thumbnails'][0] : null);
                $sellerName = $this->extractSellerFromTitle($title);

                $payload = array_filter([
                    'rating' => $rating,
                    'reviews' => $reviews,
                    'extracted_old_price' => $extractedOldPrice,
                    'delivery' => $delivery,
                    'seller_name' => $sellerName,
                    'product_title' => $title,
                    'image' => $thumbnail,
                    'price' => $price,
                ], fn ($v) => $v !== null && $v !== '');

                if (empty($payload)) {
                    $noData++;
                    $bar->advance();
                    continue;
                }

                $count = AmazonSkuCompetitor::where('asin', $asin)->update($payload);
                $updated += $count;
            } catch (\Throwable $e) {
                Log::warning('Backfill SKU competitor by ASIN failed', ['asin' => $asin, 'message' => $e->getMessage()]);
                $noData++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Done. Updated {$updated} row(s) for " . ($asins->count() - $noData) . " ASIN(s). " . $noData . " ASIN(s) had no data or failed.");
        return 0;
    }

    private function extractOldPriceFromProductResponse(array $data, array $pr): ?float
    {
        if (isset($pr['extracted_old_price']) && (is_numeric($pr['extracted_old_price']) || (is_string($pr['extracted_old_price']) && preg_match('/^[\d.]+$/', $pr['extracted_old_price'])))) {
            return (float) $pr['extracted_old_price'];
        }
        if (!empty($pr['old_price']) && is_string($pr['old_price']) && preg_match('/[\d,.]+/', $pr['old_price'], $m)) {
            return (float) str_replace(',', '', $m[0]);
        }
        $buyNew = $data['purchase_options']['buy_new'] ?? null;
        if (is_array($buyNew)) {
            if (isset($buyNew['extracted_old_price']) && is_numeric($buyNew['extracted_old_price'])) {
                return (float) $buyNew['extracted_old_price'];
            }
            if (!empty($buyNew['old_price']) && is_string($buyNew['old_price']) && preg_match('/[\d,.]+/', $buyNew['old_price'], $m)) {
                return (float) str_replace(',', '', $m[0]);
            }
        }
        $currentPrice = isset($pr['extracted_price']) ? (float) $pr['extracted_price'] : null;
        if ($currentPrice === null && !empty($pr['price']) && is_string($pr['price']) && preg_match('/[\d,.]+/', $pr['price'], $m)) {
            $currentPrice = (float) str_replace(',', '', $m[0]);
        }
        if ($currentPrice !== null && $currentPrice > 0 && !empty($pr['discount']) && is_string($pr['discount']) && preg_match('/-\s*(\d+)\s*%/', $pr['discount'], $m)) {
            $pct = (int) $m[1];
            if ($pct > 0 && $pct < 100) {
                return round($currentPrice / (1 - $pct / 100), 2);
            }
        }
        return null;
    }

    private function extractSellerFromTitle(?string $title): ?string
    {
        if ($title === null || trim($title) === '') {
            return null;
        }
        $title = trim($title);
        $patterns = [
            '/\s+by\s+([^\-|(]+)$/i',
            '/\s+-\s+([^\-|(]+)$/u',
            '/\s*[|]\s*([^\-|(]+)$/u',
            '/\s*\(\s*([^)]+)\)\s*$/u',
            '/Sold\s+by\s+([^\.\-|(]+)/i',
            '/from\s+([^\.\-|(]+?)(?:\s*[\.\-|]|$)/i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $title, $m)) {
                $seller = trim(preg_replace('/\s+/', ' ', $m[1]));
                if (strlen($seller) >= 2 && strlen($seller) <= 255 && !preg_match('/^\d+$/', $seller)) {
                    return $seller;
                }
            }
        }
        return null;
    }
}
