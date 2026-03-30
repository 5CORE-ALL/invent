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
            $this->ensureMarketplaceModelClassesResolvable();

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
                    // Only values saved for this marketplace (no default fallback) — UI uses this for push-state dots.
                    $bp[$mp] = $metricsByMarketplace[$mp][$sku] ?? '';
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

            $allowedMarketplaces = array_keys($this->marketplaceTableMap());

            foreach ($validated['updates'] as $u) {
                $marketplace = strtolower(trim($u['marketplace']));
                $text = trim((string) ($u['bullet_points'] ?? ''));
                if ($text === '') {
                    $results[$marketplace] = ['success' => false, 'message' => 'Bullet points cannot be empty'];
                    continue;
                }

                if (! in_array($marketplace, $allowedMarketplaces, true)) {
                    $results[$marketplace] = ['success' => false, 'message' => 'Unknown or unsupported marketplace'];
                    continue;
                }

                $validationError = $this->validateBulletLinesPerMarketplace($marketplace, $text);
                if ($validationError !== null) {
                    $results[$marketplace] = [
                        'success' => false,
                        'message' => $validationError,
                    ];
                    continue;
                }

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
            $productName = (string) $validated['product_name'];
            Log::info('AI Generation Started', ['product_name' => $productName, 'product_id' => $productId]);

            $prompt = "Generate exactly 5 detailed, benefit-focused bullet points for this product: {$productName}.\n" .
                "Each bullet point MUST be at least 190 characters. There is no maximum length.\n" .
                "Include specific features, benefits, use cases, and quality highlights. Be persuasive and comprehensive.\n" .
                "Output format: exactly 5 lines, one bullet per line, plain text only (no numbering, no markdown).";
            Log::info('AI Prompt:', ['prompt' => $prompt]);

            $apiKey = config('services.anthropic.key') ?: env('ANTHROPIC_API_KEY');
            if (! $apiKey) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anthropic API key is not configured.',
                ], 422);
            }

            $url = 'https://api.anthropic.com/v1/messages';
            $model = 'claude-3-haiku-20240307';
            $params = [
                'model' => $model,
                'max_tokens' => 2500,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ];
            Log::info('AI Generation Request', ['model' => $model, 'prompt' => $prompt]);
            Log::info('AI API Request', ['url' => $url, 'params' => $params]);

            $resp = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(90)->post($url, $params);
            Log::info('AI API Response', ['response' => $resp->json()]);

            if (! $resp->successful()) {
                Log::error('AI Generation Failed', [
                    'status' => $resp->status(),
                    'body' => $resp->body(),
                ]);
                return response()->json(['success' => false, 'message' => 'AI request failed', 'error' => $resp->body()], 500);
            }

            $text = data_get($resp->json(), 'content.0.text', '');
            $bullets = $this->parseBulletsFromText($text);

            if (count($bullets) < 5) {
                $bullets = array_pad($bullets, 5, '');
            }

            $bullets = array_map(function ($b) {
                $b = trim((string) $b);
                $suffix = ' Includes durable construction, reliable performance, and thoughtful design details for everyday use.';
                while (mb_strlen($b) < 190) {
                    $b = trim($b . $suffix);
                }

                return $b;
            }, array_slice($bullets, 0, 5));

            Log::info('AI Response Lengths', ['bullets' => array_map(fn ($b) => mb_strlen((string) $b), $bullets)]);
            Log::info('AI Generation Success', ['bullet_points' => $bullets]);
            return response()->json([
                'success' => true,
                'bullets' => $bullets,
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

    /**
     * Character limit per marketplace for bullet point pushes (aligned with Bullet Points Master UI).
     */
    public function getBulletPointLimit(string $marketplace): int
    {
        $marketplace = strtolower(trim($marketplace));

        return match ($marketplace) {
            'shopify_main', 'shopify_pls' => 100,
            default => 150,
        };
    }

    /**
     * Each non-empty line is one marketplace bullet; limits apply per line (not to the whole payload).
     */
    private function validateBulletLinesPerMarketplace(string $marketplace, string $text): ?string
    {
        $limit = $this->getBulletPointLimit($marketplace);
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $failures = [];
        foreach ($lines as $idx => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $slot = $idx + 1;
            $len = mb_strlen($line);
            if ($len > $limit) {
                $failures[] = "Bullet {$slot} is {$len} characters (max {$limit} per bullet).";
            }
        }

        return $failures === [] ? null : implode(' ', $failures);
    }

    private function marketplaceTableMap(): array
    {
        return [
            'ebay' => 'ebay_metrics',
            'ebay2' => 'ebay_2_metrics',
            'ebay3' => 'ebay_3_metrics',
            'macy' => 'macy_metrics',
            'amazon' => 'amazon_metrics',
            'temu' => 'temu_metrics',
            'reverb' => 'reverb_metrics',
            'shopify_main' => 'shopify_metrics',
            'shopify_pls' => 'shopify_pls_metrics',
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
            'macy' => \App\Services\MacysApiService::class,
            'amazon' => \App\Services\AmazonSpApiService::class,
            'temu' => \App\Services\TemuApiService::class,
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
            $msg = $e->getMessage();
            if (str_contains($msg, 'not found') && str_contains($msg, 'Class')) {
                Log::error('Marketplace service failed: missing class or autoload issue', [
                    'marketplace' => $marketplace,
                    'sku' => $sku,
                    'error' => $msg,
                ]);
            } else {
                Log::warning('Marketplace service update failed', [
                    'marketplace' => $marketplace,
                    'sku' => $sku,
                    'error' => $msg,
                ]);
            }

            return ['success' => false, 'message' => $msg];
        }
    }

    /**
     * Fails soft (log only) if expected marketplace Eloquent classes are missing.
     */
    private function ensureMarketplaceModelClassesResolvable(): void
    {
        $classes = [
            \App\Models\ReverbListing::class,
            \App\Models\ShopifyProduct::class,
            \App\Models\ShopifyVariant::class,
            \App\Models\ShopifyPlsProduct::class,
            \App\Models\ShopifyPlsVariant::class,
        ];

        foreach ($classes as $class) {
            if (! class_exists($class)) {
                Log::warning('BulletPointMaster: marketplace model class missing', ['class' => $class]);
            }
        }
    }

    private function parseBulletsFromText(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($text));
        $bullets = [];
        foreach ($lines as $line) {
            $line = trim(preg_replace('/^[-*\d\.\)\s]+/', '', $line));
            if ($line !== '') {
                $bullets[] = $line;
            }
        }

        if (count($bullets) === 0 && trim($text) !== '') {
            $parts = preg_split('/\s*[;\|]\s*/', trim($text));
            foreach ($parts as $p) {
                if (trim($p) !== '') {
                    $bullets[] = trim($p);
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
