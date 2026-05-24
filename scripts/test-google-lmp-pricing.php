<?php

/**
 * Manual test: compare LMP-style live pricing from SerpApi
 * - Amazon (amazon_product)
 * - eBay (ebay_product)
 * - Google Shopping (google_shopping search)
 *
 * Run: php scripts/test-google-lmp-pricing.php
 * Optional: php scripts/test-google-lmp-pricing.php "Rockville 15 inch subwoofer"
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AmazonSkuCompetitor;
use App\Models\EbaySkuCompetitor;
use App\Services\AmazonLivePriceFetcher;
use App\Services\EbayLivePriceFetcher;
use Illuminate\Support\Facades\Http;

$searchQuery = $argv[1] ?? 'Rockville RVP15W8 15 inch subwoofer';
$testSku = 'FR 15 140 MS GTR';

function line(string $title, mixed $value = null): void
{
    echo str_repeat('-', 72) . PHP_EOL;
    echo $title . PHP_EOL;
    if ($value !== null) {
        if (is_array($value)) {
            echo json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        } else {
            echo (string) $value . PHP_EOL;
        }
    }
}

function serpApiKey(): ?string
{
    $key = config('services.serpapi.key');

    return $key ?: '1ce23be0f3d775e0d631854b4856791aefa6e003415b28e33eb99b5a9c6a83c9';
}

function fetchGoogleShoppingResults(string $query, int $limit = 5): array
{
    $apiKey = serpApiKey();
    if (!$apiKey) {
        return ['error' => 'SerpApi key missing'];
    }

    $response = Http::timeout(30)->get('https://serpapi.com/search', [
        'engine' => 'google_shopping',
        'q' => $query,
        'google_domain' => 'google.com',
        'gl' => 'us',
        'hl' => 'en',
        'api_key' => $apiKey,
    ]);

    if (!$response->successful()) {
        return ['error' => 'HTTP ' . $response->status(), 'body' => $response->body()];
    }

    $data = $response->json();
    if (!empty($data['error'])) {
        return ['error' => $data['error']];
    }

    $results = [];
    foreach (array_slice($data['shopping_results'] ?? [], 0, $limit) as $item) {
        $price = null;
        if (isset($item['extracted_price'])) {
            $price = (float) $item['extracted_price'];
        } elseif (!empty($item['price']) && preg_match('/[\d,.]+/', (string) $item['price'], $m)) {
            $price = (float) str_replace(',', '', $m[0]);
        }

        $results[] = [
            'title' => $item['title'] ?? null,
            'price' => $price,
            'source' => $item['source'] ?? null,
            'link' => $item['link'] ?? ($item['product_link'] ?? null),
            'thumbnail' => $item['thumbnail'] ?? null,
            'rating' => $item['rating'] ?? null,
            'reviews' => $item['reviews'] ?? null,
            'product_id' => $item['product_id'] ?? null,
        ];
    }

    usort($results, fn ($a, $b) => ($a['price'] ?? PHP_FLOAT_MAX) <=> ($b['price'] ?? PHP_FLOAT_MAX));

    return [
        'query' => $query,
        'count' => count($results),
        'lowest' => $results[0] ?? null,
        'results' => $results,
    ];
}

echo PHP_EOL . '=== Marketplace LMP Pricing Test ===' . PHP_EOL;
echo 'Search query: ' . $searchQuery . PHP_EOL;
echo 'Sample SKU: ' . $testSku . PHP_EOL;

// Amazon (existing LMP)
line('1) AMAZON — stored LMP rows for SKU');
$amazonRows = AmazonSkuCompetitor::getCompetitorsForSku($testSku, 'amazon');
if ($amazonRows->isEmpty()) {
    echo "No amazon_sku_competitors rows for {$testSku}" . PHP_EOL;
} else {
    echo 'Lowest stored: $' . number_format((float) $amazonRows->first()->price, 2) . PHP_EOL;
    echo 'Competitors: ' . $amazonRows->count() . PHP_EOL;
}

line('1b) AMAZON — live SerpApi fetch (amazon_product)');
$amazonFetcher = app(AmazonLivePriceFetcher::class);
$sampleAsin = $amazonRows->first()->asin ?? 'B00HWLR8EQ';
$amazonLive = $amazonFetcher->fetchByAsin($sampleAsin, 'amazon');
echo json_encode($amazonLive, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

// eBay (existing LMP)
line('2) EBAY — stored LMP rows for SKU');
$ebayRows = \App\Models\EbaySkuCompetitor::resolveLookupKeys($testSku);
$ebayCompetitors = collect();
foreach ($ebayRows as $lookupSku) {
    $found = \App\Models\EbaySkuCompetitor::getCompetitorsForSku($lookupSku, 'ebay');
    if ($found->isNotEmpty()) {
        $ebayCompetitors = $found;
        break;
    }
}
if ($ebayCompetitors->isEmpty()) {
    echo "No ebay_sku_competitors rows for {$testSku}" . PHP_EOL;
} else {
    $lowest = $ebayCompetitors->first();
    $total = (float) ($lowest->total_price ?? $lowest->price);
    echo 'Lowest stored total: $' . number_format($total, 2) . PHP_EOL;
    echo 'Competitors: ' . $ebayCompetitors->count() . PHP_EOL;
}

line('2b) EBAY — live SerpApi fetch (ebay_product)');
$ebayFetcher = app(EbayLivePriceFetcher::class);
$sampleListing = $ebayCompetitors->first()->item_id ?? '173950776753';
$ebayLive = $ebayFetcher->fetchByListingId((string) $sampleListing);
echo json_encode($ebayLive, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

// Google (NOT in app yet — test only)
line('3) GOOGLE — SerpApi google_shopping (test only, not saved to DB)');
$google = fetchGoogleShoppingResults($searchQuery);
echo json_encode($google, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

line('SUMMARY');
echo "Amazon LMP in app: YES (amazon_sku_competitors + amazon:update-sku-prices)\n";
echo "eBay LMP in app:   YES (ebay_sku_competitors + ebay:update-sku-prices)\n";
echo "Google LMP in app: NO  (this test only checks if SerpApi returns shopping prices)\n";
if (!empty($google['lowest']['price'])) {
    echo 'Google Shopping lowest from test query: $' . number_format((float) $google['lowest']['price'], 2) . PHP_EOL;
    echo 'Seller/source: ' . ($google['lowest']['source'] ?? 'N/A') . PHP_EOL;
} else {
    echo "Google Shopping: no price returned for this query (check SerpApi response above).\n";
}

echo PHP_EOL;
