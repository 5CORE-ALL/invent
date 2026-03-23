<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProductMaster\ProductMasterController as PMController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class BulletPointMasterController extends Controller
{
    public function index(Request $request)
    {
        $mode = $request->query('mode', '');
        $demo = $request->query('demo', '');

        return view('bullet-point-master', compact('mode', 'demo'));
    }

    public function getData(Request $request)
    {
        try {
            $baseResponse = app(PMController::class)->getViewProductData($request);
            $baseData = $baseResponse->getData(true);
            $products = $baseData['data'] ?? [];

            $marketTables = $this->marketplaceTableMap();
            $metricsByMarketplace = [];
            foreach ($marketTables as $marketplace => $table) {
                $metricsByMarketplace[$marketplace] = $this->loadMetricsBySku($table);
            }

            foreach ($products as &$row) {
                $sku = $this->normalizeSku($row['SKU'] ?? null);
                $defaultBullets = $this->combineBullets(
                    $row['bullet1'] ?? null,
                    $row['bullet2'] ?? null,
                    $row['bullet3'] ?? null,
                    $row['bullet4'] ?? null,
                    $row['bullet5'] ?? null
                );

                $bp = [];
                foreach (array_keys($marketTables) as $mp) {
                    $bp[$mp] = $metricsByMarketplace[$mp][$sku] ?? $defaultBullets;
                }

                $row['bullet_points'] = $bp;
                $row['default_bullets'] = $defaultBullets;
            }

            return response()->json([
                'message' => 'Data loaded from database',
                'data' => $products,
                'status' => 200,
            ]);
        } catch (\Throwable $e) {
            Log::error('BulletPointMaster getData failed', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Failed to load bullet points data.',
                'error' => $e->getMessage(),
                'status' => 500,
            ], 500);
        }
    }

    /**
     * Backward-compatible endpoint used by the current blade.
     */
    public function getCombinedData(Request $request)
    {
        return $this->getData($request);
    }

    public function update(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'updates' => 'required|array|min:1',
                'updates.*.marketplace' => 'required|string',
                'updates.*.bullet_points' => 'nullable|string',
            ]);

            $sku = $this->normalizeSku($validated['sku']);
            $results = [];

            foreach ($validated['updates'] as $u) {
                $marketplace = strtolower(trim($u['marketplace']));
                $text = trim((string) ($u['bullet_points'] ?? ''));
                if ($text === '') {
                    $results[$marketplace] = ['success' => false, 'message' => 'Bullet points cannot be empty'];
                    continue;
                }

                $limit = $this->getBulletPointLimit($marketplace);
                $text = mb_substr($text, 0, $limit);

                $tableSaved = $this->saveToMarketplaceTable($marketplace, $sku, $text);
                $serviceResult = $this->callMarketplaceService($marketplace, $sku, $text);

                $success = $tableSaved || ($serviceResult['success'] ?? false);
                $results[$marketplace] = [
                    'success' => $success,
                    'message' => $success
                        ? ($serviceResult['message'] ?? 'Updated')
                        : ($serviceResult['message'] ?? 'Unable to update this marketplace'),
                ];
            }

            $totalSuccess = collect($results)->where('success', true)->count();
            $totalFailed = collect($results)->where('success', false)->count();

            return response()->json([
                'success' => $totalFailed === 0,
                'results' => $results,
                'total_success' => $totalSuccess,
                'total_failed' => $totalFailed,
                'message' => "Updated {$totalSuccess} marketplace(s)." . ($totalFailed > 0 ? " {$totalFailed} failed." : ''),
            ]);
        } catch (\Throwable $e) {
            Log::error('BulletPointMaster update failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateBulk(Request $request)
    {
        try {
            $validated = $request->validate([
                'items' => 'required|array|min:1',
                'items.*.sku' => 'required|string',
                'items.*.updates' => 'required|array|min:1',
                'items.*.updates.*.marketplace' => 'required|string',
                'items.*.updates.*.bullet_points' => 'nullable|string',
            ]);

            $items = [];
            $totalSuccess = 0;
            $totalFailed = 0;

            foreach ($validated['items'] as $item) {
                $res = $this->update(new Request([
                    'sku' => $item['sku'],
                    'updates' => $item['updates'],
                ]));
                $payload = $res->getData(true);
                $items[] = ['sku' => $item['sku'], 'results' => $payload['results'] ?? []];
                $totalSuccess += $payload['total_success'] ?? 0;
                $totalFailed += $payload['total_failed'] ?? 0;
            }

            return response()->json([
                'success' => $totalFailed === 0,
                'items' => $items,
                'total_success' => $totalSuccess,
                'total_failed' => $totalFailed,
                'message' => "Bulk update finished: {$totalSuccess} success, {$totalFailed} failed.",
            ]);
        } catch (\Throwable $e) {
            Log::error('BulletPointMaster updateBulk failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function generateBulletPoints(Request $request)
    {
        try {
            $validated = $request->validate([
                'product_name' => 'required|string',
                'current_text' => 'nullable|string',
                'product_id' => 'nullable|string',
            ]);

            $productId = (string) ($validated['product_id'] ?? $request->input('sku', ''));
            Log::info('AI Generation Started', ['product_id' => $productId]);

            $prompt = "Generate 5 engaging, benefit-focused bullet points for this product: {$validated['product_name']}. " .
                "Each bullet point should be concise, highlight key features and benefits, and be under 200 characters. " .
                "Focus on what makes this product valuable to customers. Return plain text list only.";
            Log::info('AI Prompt:', ['prompt' => $prompt]);

            $apiKey = config('services.anthropic.key') ?: env('ANTHROPIC_API_KEY');
            if (! $apiKey) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anthropic API key is not configured.',
                ], 422);
            }

            $url = 'https://api.anthropic.com/v1/messages';
            $params = [
                'model' => 'claude-3-5-sonnet-20241022',
                'max_tokens' => 600,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ];
            Log::info('AI API Request', ['url' => $url, 'params' => $params]);

            $resp = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(90)->post($url, $params);
            Log::info('AI API Response', ['response' => $resp->json()]);

            if (! $resp->successful()) {
                return response()->json(['success' => false, 'message' => 'AI request failed', 'error' => $resp->body()], 500);
            }

            $text = data_get($resp->json(), 'content.0.text', '');
            $bullets = $this->parseBulletsFromText($text);

            if (count($bullets) < 5) {
                $bullets = array_pad($bullets, 5, '');
            }

            return response()->json([
                'success' => true,
                'bullets' => array_slice($bullets, 0, 5),
            ]);
        } catch (\Throwable $e) {
            Log::error('AI Generation Failed', ['error' => $e->getMessage()]);
            Log::error('BulletPointMaster generateBulletPoints failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function generate(Request $request)
    {
        return $this->generateBulletPoints($request);
    }

    public function getBulletPointLimit(string $marketplace): int
    {
        return match ($marketplace) {
            'wayfair', 'shopify_main', 'shopify_pls' => 100,
            'shein' => 80,
            'doba' => 60,
            default => 150,
        };
    }

    private function marketplaceTableMap(): array
    {
        return [
            'shopify_main' => 'shopify_metrics',
            'shopify_pls' => 'shopify_pls_metrics',
            'ebay' => 'ebay_metrics',
            'ebay2' => 'ebay_2_metrics',
            'ebay3' => 'ebay_3_metrics',
            'temu' => 'temu_metrics',
            'amazon' => 'amazon_metrics',
            'reverb' => 'reverb_metrics',
            'walmart' => 'walmart_metrics',
            'macy' => 'macy_metrics',
            'aliexpress' => 'aliexpress_metrics',
            'faire' => 'faire_metrics',
            'bestbuy' => 'bestbuy_metrics',
            'wayfair' => 'wayfair_metrics',
            'shein' => 'shein_metrics',
            'doba' => 'doba_metrics',
        ];
    }

    private function loadMetricsBySku(string $table): array
    {
        try {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sku')) {
                return [];
            }

            $hasBp = Schema::hasColumn($table, 'bullet_points');
            if (! $hasBp) {
                return [];
            }

            return DB::table($table)
                ->select('sku', 'bullet_points')
                ->whereNotNull('sku')
                ->get()
                ->mapWithKeys(function ($row) {
                    return [$this->normalizeSku($row->sku) => (string) ($row->bullet_points ?? '')];
                })
                ->toArray();
        } catch (\Throwable $e) {
            Log::warning("Unable to load table {$table}", ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function saveToMarketplaceTable(string $marketplace, string $sku, string $text): bool
    {
        $table = $this->marketplaceTableMap()[$marketplace] ?? null;
        if (! $table) {
            return false;
        }

        try {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sku') || ! Schema::hasColumn($table, 'bullet_points')) {
                return false;
            }

            $existing = DB::table($table)->where('sku', $sku)->first();
            if ($existing) {
                DB::table($table)->where('sku', $sku)->update(['bullet_points' => $text, 'updated_at' => now()]);
            } else {
                DB::table($table)->insert(['sku' => $sku, 'bullet_points' => $text, 'created_at' => now(), 'updated_at' => now()]);
            }
            return true;
        } catch (\Throwable $e) {
            Log::warning("Unable to save bullet points to {$table}", ['sku' => $sku, 'error' => $e->getMessage()]);
            return false;
        }
    }

    private function callMarketplaceService(string $marketplace, string $sku, string $text): array
    {
        $serviceMap = [
            'ebay' => \App\Services\EbayApiService::class,
            'ebay2' => \App\Services\Ebay2ApiService::class,
            'ebay3' => \App\Services\EbayThreeApiService::class,
            'walmart' => \App\Services\WalmartService::class,
            'macy' => \App\Services\MacysApiService::class,
            'aliexpress' => \App\Services\AliExpressApiService::class,
            'faire' => \App\Services\FaireService::class,
            'temu' => \App\Services\TemuApiService::class,
            'doba' => \App\Services\DobaApiService::class,
            'wayfair' => \App\Services\WayfairApiService::class,
            'shein' => \App\Services\SheinApiService::class,
            'reverb' => \App\Services\ReverbApiService::class,
            'shopify_main' => \App\Services\ShopifyApiService::class,
            'shopify_pls' => \App\Services\ShopifyPLSApiService::class,
        ];

        try {
            $serviceClass = $serviceMap[$marketplace] ?? null;
            if (! $serviceClass || ! class_exists($serviceClass)) {
                return ['success' => false, 'message' => 'Service not available'];
            }

            $service = app($serviceClass);
            if (! method_exists($service, 'updateBulletPoints')) {
                return ['success' => false, 'message' => 'Service does not support bullet point update'];
            }

            $result = $service->updateBulletPoints($sku, $text);
            if (is_array($result)) {
                return $result + ['success' => false, 'message' => 'Unknown service response'];
            }
            if (is_bool($result)) {
                return ['success' => $result, 'message' => $result ? 'Updated' : 'Failed'];
            }
            return ['success' => false, 'message' => 'Unexpected service response'];
        } catch (\Throwable $e) {
            Log::warning('Marketplace service update failed', [
                'marketplace' => $marketplace,
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function parseBulletsFromText(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($text));
        $bullets = [];
        foreach ($lines as $line) {
            $line = trim(preg_replace('/^[-*\d\.\)\s]+/', '', $line));
            if ($line !== '') {
                $bullets[] = mb_substr($line, 0, 200);
            }
        }

        if (count($bullets) === 0 && trim($text) !== '') {
            $parts = preg_split('/\s*[;\|]\s*/', trim($text));
            foreach ($parts as $p) {
                if (trim($p) !== '') {
                    $bullets[] = mb_substr(trim($p), 0, 200);
                }
            }
        }

        return array_values(array_unique(array_slice($bullets, 0, 5)));
    }

    private function normalizeSku(?string $sku): string
    {
        if (! $sku) {
            return '';
        }

        return str_replace("\u{00a0}", ' ', trim((string) $sku));
    }

    private function combineBullets(?string $b1, ?string $b2, ?string $b3, ?string $b4, ?string $b5): string
    {
        $parts = array_filter(array_map('trim', [$b1 ?? '', $b2 ?? '', $b3 ?? '', $b4 ?? '', $b5 ?? '']));
        return implode(' ', $parts);
    }
}
