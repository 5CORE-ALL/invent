<?php

namespace App\Console\Commands;

use App\Models\ProductStockMapping;
use App\Models\ReverbProduct;
use App\Services\ReverbApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckReverbListings extends Command
{
    private ?string $reverbBearerToken = null;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reverb:check-listings
                            {--sku= : Check specific SKU and show full listing details}
                            {--save : Save full JSON report to storage/logs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnose Reverb sync: test API, fetch all listings with pagination, group by status, compare with DB';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->reverbBearerToken = ReverbApiService::getReverbBearerToken();
        if (! $this->reverbBearerToken) {
            $this->error('Reverb API token not configured (REVERB_CLIENT_ID + REVERB_CLIENT_SECRET or REVERB_TOKEN).');

            return self::FAILURE;
        }

        $sku = $this->option('sku');
        if ($sku !== null && trim($sku) !== '') {
            return $this->checkSingleSku(trim($sku)) ? self::SUCCESS : self::FAILURE;
        }

        $this->info('1. Testing Reverb API connection...');
        if (!$this->testConnection()) {
            return self::FAILURE;
        }
        $this->info('   API connection OK.');

        $this->info('2. Fetching ALL listings (state=all, per_page=100)...');
        $listings = $this->fetchAllListings();
        $this->info('   Total listings from API: ' . count($listings));

        $byStatus = $this->groupByStatus($listings);
        $this->info('3. Listings by status:');
        $this->printStatusTable($byStatus);

        $apiSkus = $this->extractSkus($listings);
        $this->info('4. Comparing SKUs with database...');
        $this->compareWithDatabase($apiSkus, $listings);

        if ($this->option('save')) {
            $path = $this->saveReport($listings, $byStatus, $apiSkus);
            $this->info('5. Full report saved to: ' . $path);
        } else {
            $this->info('5. Run with --save to write reverb_listings_report_*.json to storage/logs.');
        }

        return self::SUCCESS;
    }

    /**
     * Check a single SKU: find in API and show full listing details.
     */
    protected function checkSingleSku(string $sku): bool
    {
        $this->info("Checking SKU: {$sku}");
        $this->info('Fetching all listings to find SKU (this may take a moment)...');
        $listings = $this->fetchAllListings();
        $normalized = strtolower(trim($sku));
        $found = null;
        foreach ($listings as $item) {
            $itemSku = isset($item['sku']) ? trim((string) $item['sku']) : '';
            if (strcasecmp($itemSku, $normalized) === 0 || $itemSku === $sku) {
                $found = $item;
                break;
            }
        }
        if ($found === null) {
            $this->warn("SKU '{$sku}' not found in Reverb API listings.");
            $this->line('It may be ended, or never listed. Run without --sku to see full report.');
            return true;
        }
        $this->info('Listing found. Full details:');
        $this->line(json_encode($found, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $state = $this->normalizeState($found);
        $this->line('');
        $this->table(
            ['Field', 'Value'],
            [
                ['SKU', $found['sku'] ?? ''],
                ['State', $state],
                ['Listing ID', $found['id'] ?? ''],
                ['Title', isset($found['title']) ? substr($found['title'], 0, 80) . (strlen($found['title'] ?? '') > 80 ? '...' : '') : ''],
                ['Inventory', $found['inventory'] ?? ''],
                ['Price (amount)', $found['price']['amount'] ?? ($found['price'] ?? '')],
            ]
        );
        return true;
    }

    protected function testConnection(): bool
    {
        try {
            $response = Http::withoutVerifying()
                ->timeout(15)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$this->reverbBearerToken,
                    'Accept' => 'application/hal+json',
                    'Accept-Version' => '3.0',
                ])
                ->get('https://api.reverb.com/api/my/listings?per_page=1');

            if ($response->failed()) {
                $this->error('   API returned HTTP ' . $response->status() . ': ' . substr($response->body(), 0, 200));
                Log::channel('reverb_daily')->error('CheckReverbListings connection test failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            $this->error('   Connection failed: ' . $e->getMessage());
            Log::channel('reverb_daily')->error('CheckReverbListings connection exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Fetch all listings with pagination (per_page=100, state=all).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function fetchAllListings(): array
    {
        $listings = [];
        $url = 'https://api.reverb.com/api/my/listings?state=all&per_page=100';
        $page = 0;

        try {
            do {
                $page++;
                $this->line("   Page {$page}...");
                $response = Http::withoutVerifying()
                    ->timeout(60)
                    ->withHeaders([
                    'Authorization' => 'Bearer '.$this->reverbBearerToken,
                    'Accept' => 'application/hal+json',
                    'Accept-Version' => '3.0',
                    ])
                    ->get($url);

                if ($response->failed()) {
                    $this->error('   Failed to fetch page ' . $page . ': HTTP ' . $response->status());
                    Log::channel('reverb_sync')->error('CheckReverbListings fetch page failed', [
                        'page' => $page,
                        'url' => $url,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    break;
                }

                $data = $response->json();
                $chunk = $data['listings'] ?? [];
                $listings = array_merge($listings, $chunk);
                $nextHref = $data['_links']['next']['href'] ?? null;
                $url = $nextHref ? trim($nextHref) : null;

                if ($url) {
                    usleep(200000); // 0.2s between pages to avoid rate limit
                }
            } while ($url);

            return $listings;
        } catch (\Throwable $e) {
            $this->error('   Exception: ' . $e->getMessage());
            Log::channel('reverb_sync')->error('CheckReverbListings fetch exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $listings;
        }
    }

    /**
     * Normalize state/status from a listing.
     */
    protected function normalizeState(array $item): string
    {
        $state = $item['state'] ?? $item['status'] ?? null;
        if (is_array($state)) {
            $state = $state['slug'] ?? $state['name'] ?? $state['title'] ?? 'unknown';
        }
        if ($state === null && isset($item['_embedded']['state'])) {
            $emb = $item['_embedded']['state'];
            $state = is_array($emb) ? ($emb['slug'] ?? $emb['name'] ?? 'unknown') : (string) $emb;
        }
        return $state ? strtolower(trim((string) $state)) : 'unknown';
    }

    /**
     * Group listings by status (live, ended, draft, out_of_stock, suspended, ordered).
     *
     * @param array<int, array<string, mixed>> $listings
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function groupByStatus(array $listings): array
    {
        $groups = [
            'live' => [],
            'ended' => [],
            'draft' => [],
            'out_of_stock' => [],
            'suspended' => [],
            'ordered' => [],
            'other' => [],
        ];
        foreach ($listings as $item) {
            $state = $this->normalizeState($item);
            if (array_key_exists($state, $groups)) {
                $groups[$state][] = $item;
            } else {
                $groups['other'][] = $item;
            }
        }
        return $groups;
    }

    protected function printStatusTable(array $byStatus): void
    {
        $rows = [];
        foreach ($byStatus as $status => $items) {
            $rows[] = [$status, count($items)];
        }
        $this->table(['Status', 'Count'], $rows);
    }

    /**
     * @param array<int, array<string, mixed>> $listings
     * @return array<string, true>
     */
    protected function extractSkus(array $listings): array
    {
        $skus = [];
        foreach ($listings as $item) {
            $sku = isset($item['sku']) ? trim((string) $item['sku']) : null;
            if ($sku !== null && $sku !== '') {
                $skus[$sku] = true;
            }
        }
        return $skus;
    }

    /**
     * @param array<string, true> $apiSkus
     * @param array<int, array<string, mixed>> $listings
     */
    protected function compareWithDatabase(array $apiSkus, array $listings): void
    {
        $apiSkuList = array_keys($apiSkus);
        $dbStockSkuList = ProductStockMapping::pluck('sku')->map(fn ($s) => trim((string) $s))->filter()->unique()->values()->all();
        $dbReverbSkuList = ReverbProduct::pluck('sku')->map(fn ($s) => trim((string) $s))->filter()->unique()->values()->all();

        $inApiNotInStock = array_diff($apiSkuList, $dbStockSkuList);
        $inStockNotInApi = array_diff($dbStockSkuList, $apiSkuList);
        $inApiNotInReverb = array_diff($apiSkuList, $dbReverbSkuList);
        $inReverbNotInApi = array_diff($dbReverbSkuList, $apiSkuList);

        $this->line('   ProductStockMapping: ' . count($dbStockSkuList) . ' SKUs');
        $this->line('   ReverbProduct: ' . count($dbReverbSkuList) . ' SKUs');
        $this->line('   API: ' . count($apiSkuList) . ' SKUs');

        $this->line('');
        $this->line('   In API but not in ProductStockMapping: ' . count($inApiNotInStock));
        $this->showSample(array_values($inApiNotInStock), 10, '   Sample (API only): ');

        $this->line('   In ProductStockMapping but not in API: ' . count($inStockNotInApi));
        $this->showSample(array_values($inStockNotInApi), 10, '   Sample (DB only): ');

        $this->line('   In API but not in ReverbProduct: ' . count($inApiNotInReverb));
        $this->showSample(array_values($inApiNotInReverb), 10, '   Sample (API, not ReverbProduct): ');

        $this->line('   In ReverbProduct but not in API: ' . count($inReverbNotInApi));
        $this->showSample(array_values($inReverbNotInApi), 10, '   Sample (ReverbProduct, not in API): ');

        if (count($inStockNotInApi) > 0) {
            $sample = array_slice(array_values($inStockNotInApi), 0, 5);
            $this->line('   First few "in DB not in API" SKUs (not in API response; may be ended/other): ' . implode(', ', $sample));
        }
    }

    /**
     * @param array<int, string> $items
     */
    protected function showSample(array $items, int $max, string $prefix): void
    {
        $sample = array_slice(array_values($items), 0, $max);
        if (count($sample) > 0) {
            $this->line($prefix . implode(', ', $sample));
        }
    }

    /**
     * @param array<int, array<string, mixed>> $listings
     * @param array<string, array<int, array<string, mixed>>> $byStatus
     * @param array<string, true> $apiSkus
     */
    protected function saveReport(array $listings, array $byStatus, array $apiSkus): string
    {
        $dir = storage_path('logs/reverb');
        if (!File::isDirectory($dir)) {
            File::ensureDirectoryExists($dir);
        }

        $apiSkuList = array_keys($apiSkus);
        $dbStockSkuList = ProductStockMapping::pluck('sku')->map(fn ($s) => trim((string) $s))->filter()->unique()->values()->all();
        $dbReverbSkuList = ReverbProduct::pluck('sku')->map(fn ($s) => trim((string) $s))->filter()->unique()->values()->all();

        $report = [
            'generated_at' => now()->toIso8601String(),
            'total_listings' => count($listings),
            'by_status' => array_map('count', $byStatus),
            'api_sku_count' => count($apiSkuList),
            'product_stock_mapping_sku_count' => count($dbStockSkuList),
            'reverb_product_sku_count' => count($dbReverbSkuList),
            'in_api_not_in_stock_mapping' => array_values(array_diff($apiSkuList, $dbStockSkuList)),
            'in_stock_mapping_not_in_api' => array_values(array_diff($dbStockSkuList, $apiSkuList)),
            'in_api_not_in_reverb_product' => array_values(array_diff($apiSkuList, $dbReverbSkuList)),
            'in_reverb_product_not_in_api' => array_values(array_diff($dbReverbSkuList, $apiSkuList)),
            'listings_summary' => array_map(function ($item) {
                return [
                    'id' => $item['id'] ?? null,
                    'sku' => $item['sku'] ?? null,
                    'title' => isset($item['title']) ? substr($item['title'], 0, 80) : null,
                    'state' => $this->normalizeState($item),
                    'inventory' => $item['inventory'] ?? null,
                ];
            }, $listings),
        ];

        $filename = 'reverb_listings_report_' . now()->format('Y-m-d_His') . '.json';
        $path = $dir . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        Log::channel('reverb_sync')->info('CheckReverbListings report saved', ['path' => $path]);
        return $path;
    }
}
