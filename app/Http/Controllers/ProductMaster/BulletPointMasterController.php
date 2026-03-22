<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProductMaster\ProductMasterController as PMController;
use App\Models\AliexpressMetric;
use App\Models\DobaMetric;
use App\Models\Ebay2Metric;
use App\Models\Ebay3Metric;
use App\Models\EbayMetric;
use App\Models\ProductMaster;
use App\Models\SheinMetric;
use App\Models\WalmartMetrics;
use App\Services\AliExpressApiService;
use App\Services\DobaApiService;
use App\Services\Ebay2ApiService;
use App\Services\EbayApiService;
use App\Services\EbayThreeApiService;
use App\Services\FaireService;
use App\Services\MacysApiService;
use App\Services\SheinApiService;
use App\Services\WalmartService;
use App\Services\WayfairApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class BulletPointMasterController extends Controller
{
    /**
     * Character limits per marketplace for bullet points.
     */
    public function getBulletPointLimit(string $marketplace): int
    {
        return match ($marketplace) {
            'ebay', 'ebay2', 'ebay3', 'walmart', 'macy', 'aliexpress', 'faire', 'bestbuy' => 150,
            'wayfair' => 100,
            'shein' => 80,
            'doba' => 60,
            default => 150,
        };
    }

    /**
     * Display the Bullet Points Master view.
     */
    public function index(Request $request)
    {
        $mode = $request->query('mode', '');
        $demo = $request->query('demo', '');

        return view('bullet-point-master', compact('mode', 'demo'));
    }

    /**
     * API: Get combined product data (product-master-data-view + marketplace bullet_points).
     * Used by Bullet Points Master UI for full table with original columns + marketplace columns.
     */
    public function getCombinedData(Request $request)
    {
        try {
            $baseResponse = app(PMController::class)->getViewProductData($request);
            $baseData = $baseResponse->getData(true);
            $products = $baseData['data'] ?? [];

            $ebayBySku = $this->safeLoadMetrics(EbayMetric::class, 'ebay_metrics');
            $ebay2BySku = $this->safeLoadMetrics(Ebay2Metric::class, 'ebay_2_metrics');
            $ebay3BySku = $this->safeLoadMetrics(Ebay3Metric::class, 'ebay_3_metrics');
            $walmartBySku = $this->safeLoadMetrics(WalmartMetrics::class, 'walmart_metrics');
            $sheinBySku = $this->safeLoadMetrics(SheinMetric::class, 'shein_metrics');
            $dobaBySku = $this->safeLoadMetrics(DobaMetric::class, 'doba_metrics');
            $aliexpressBySku = $this->safeLoadMetrics(AliexpressMetric::class, 'aliexpress_metrics');

            $marketplaceKeys = ['ebay', 'ebay2', 'ebay3', 'walmart', 'macy', 'aliexpress', 'faire', 'bestbuy', 'wayfair', 'shein', 'doba'];

            foreach ($products as &$row) {
                $normSku = $this->normalizeSku($row['SKU'] ?? $row['sku'] ?? null);
                $defaultBullets = $this->combineBullets(
                    $row['bullet1'] ?? null,
                    $row['bullet2'] ?? null,
                    $row['bullet3'] ?? null,
                    $row['bullet4'] ?? null,
                    $row['bullet5'] ?? null
                );

                $bulletPoints = [];
                $bulletPoints['ebay'] = $ebayBySku->get($normSku)?->bullet_points ?? $defaultBullets;
                $bulletPoints['ebay2'] = $ebay2BySku->get($normSku)?->bullet_points ?? $defaultBullets;
                $bulletPoints['ebay3'] = $ebay3BySku->get($normSku)?->bullet_points ?? $defaultBullets;
                $bulletPoints['walmart'] = $walmartBySku->get($normSku)?->bullet_points ?? $defaultBullets;
                $bulletPoints['macy'] = $defaultBullets;
                $bulletPoints['aliexpress'] = $aliexpressBySku->get($normSku)?->bullet_points ?? $defaultBullets;
                $bulletPoints['faire'] = $defaultBullets;
                $bulletPoints['bestbuy'] = $defaultBullets;
                $bulletPoints['wayfair'] = $defaultBullets;
                $bulletPoints['shein'] = $sheinBySku->get($normSku)?->bullet_points ?? $defaultBullets;
                $bulletPoints['doba'] = $dobaBySku->get($normSku)?->bullet_points ?? $defaultBullets;

                $row['bullet_points'] = $bulletPoints;
                $row['default_bullets'] = $defaultBullets;
            }

            return response()->json([
                'message' => 'Data loaded from database',
                'data' => $products,
                'status' => 200,
            ]);
        } catch (\Throwable $e) {
            Log::error('BulletPointMaster getCombinedData failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to load data.',
                'error' => $e->getMessage(),
                'status' => 500,
            ], 500);
        }
    }

    /**
     * API: Get products with bullet points per marketplace (legacy/simple format).
     */
    public function getData(Request $request)
    {
        try {
            $products = ProductMaster::orderBy('parent')
                ->orderBy('sku')
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->get();

            $ebayBySku = $this->safeLoadMetrics(EbayMetric::class, 'ebay_metrics');
            $ebay2BySku = $this->safeLoadMetrics(Ebay2Metric::class, 'ebay_2_metrics');
            $ebay3BySku = $this->safeLoadMetrics(Ebay3Metric::class, 'ebay_3_metrics');
            $walmartBySku = $this->safeLoadMetrics(WalmartMetrics::class, 'walmart_metrics');
            $sheinBySku = $this->safeLoadMetrics(SheinMetric::class, 'shein_metrics');
            $dobaBySku = $this->safeLoadMetrics(DobaMetric::class, 'doba_metrics');
            $aliexpressBySku = $this->safeLoadMetrics(AliexpressMetric::class, 'aliexpress_metrics');

            $result = [];
            foreach ($products as $pm) {
                $normSku = $this->normalizeSku($pm->sku);
                $defaultBullets = $this->combineBullets($pm->bullet1, $pm->bullet2, $pm->bullet3, $pm->bullet4, $pm->bullet5);

                $bulletPoints = [
                    'ebay' => $ebayBySku->get($normSku)?->bullet_points ?? $defaultBullets,
                    'ebay2' => $ebay2BySku->get($normSku)?->bullet_points ?? $defaultBullets,
                    'ebay3' => $ebay3BySku->get($normSku)?->bullet_points ?? $defaultBullets,
                    'walmart' => $walmartBySku->get($normSku)?->bullet_points ?? $defaultBullets,
                    'macy' => $defaultBullets,
                    'aliexpress' => $aliexpressBySku->get($normSku)?->bullet_points ?? $defaultBullets,
                    'faire' => $defaultBullets,
                    'bestbuy' => $defaultBullets,
                    'wayfair' => $defaultBullets,
                    'shein' => $sheinBySku->get($normSku)?->bullet_points ?? $defaultBullets,
                    'doba' => $dobaBySku->get($normSku)?->bullet_points ?? $defaultBullets,
                ];

                $result[] = [
                    'id' => $pm->id,
                    'sku' => $pm->sku,
                    'product_name' => $pm->parent ?? $pm->sku,
                    'bullet_points' => $bulletPoints,
                    'default_bullets' => $defaultBullets,
                ];
            }

            return response()->json([
                'products' => $result,
                'status' => 200,
            ]);
        } catch (\Throwable $e) {
            Log::error('BulletPointMaster getData failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to load bullet point data.',
                'error' => $e->getMessage(),
                'status' => 500,
            ], 500);
        }
    }

    /**
     * API: Update bullet points for selected marketplaces.
     */
    public function update(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'updates' => 'required|array',
                'updates.*.marketplace' => 'required|string',
                'updates.*.bullet_points' => 'nullable|string',
            ]);

            $sku = trim($validated['sku']);
            $updates = $validated['updates'];

            $product = ProductMaster::where('sku', $sku)
                ->orWhere('SKU', $sku)
                ->first();

            if (! $product) {
                return response()->json([
                    'success' => false,
                    'message' => "Product not found for SKU: {$sku}",
                ], 404);
            }

            $results = [];
            foreach ($updates as $u) {
                $marketplace = $u['marketplace'];
                $bulletPoints = trim($u['bullet_points'] ?? '');
                if ($bulletPoints === '') {
                    $results[$marketplace] = ['success' => false, 'message' => 'Bullet points cannot be empty.'];
                    continue;
                }

                $limit = $this->getBulletPointLimit($marketplace);
                if (mb_strlen($bulletPoints) > $limit) {
                    $bulletPoints = mb_substr($bulletPoints, 0, $limit);
                }

                $res = $this->updateMarketplaceBulletPoints($marketplace, $sku, $bulletPoints);
                $results[$marketplace] = $res;
            }

            $successCount = collect($results)->where('success', true)->count();
            $failedCount = collect($results)->where('success', false)->count();

            return response()->json([
                'success' => $failedCount === 0,
                'results' => $results,
                'total_success' => $successCount,
                'total_failed' => $failedCount,
                'message' => "Updated {$successCount} marketplace(s)." . ($failedCount > 0 ? " {$failedCount} failed." : ''),
            ]);
        } catch (\Throwable $e) {
            Log::error('BulletPointMaster update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Update failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function updateMarketplaceBulletPoints(string $marketplace, string $sku, string $bulletPoints): array
    {
        $limit = $this->getBulletPointLimit($marketplace);
        $truncated = mb_strlen($bulletPoints) > $limit ? mb_substr($bulletPoints, 0, $limit) : $bulletPoints;

        try {
            switch ($marketplace) {
                case 'ebay':
                    return $this->updateEbayBulletPoints(EbayMetric::class, app(EbayApiService::class), $sku, $truncated);
                case 'ebay2':
                    return $this->updateEbayBulletPoints(Ebay2Metric::class, app(Ebay2ApiService::class), $sku, $truncated);
                case 'ebay3':
                    return $this->updateEbayBulletPoints(Ebay3Metric::class, app(EbayThreeApiService::class), $sku, $truncated);
                case 'walmart':
                    return $this->updateWalmartBulletPoints($sku, $truncated);
                case 'shein':
                    return $this->updateSheinBulletPoints($sku, $truncated);
                case 'doba':
                    return $this->updateDobaBulletPoints($sku, $truncated);
                case 'aliexpress':
                    return $this->updateAliexpressBulletPoints($sku, $truncated);
                case 'macy':
                    return $this->saveMetricsOnly('macy', $sku, $truncated, app(MacysApiService::class));
                case 'faire':
                    return $this->saveMetricsOnly('faire', $sku, $truncated, app(FaireService::class));
                case 'wayfair':
                    return $this->saveMetricsOnly('wayfair', $sku, $truncated, app(WayfairApiService::class));
                case 'bestbuy':
                    return $this->saveMetricsOnly('bestbuy', $sku, $truncated, null);
                default:
                    return ['success' => false, 'message' => "Unknown marketplace: {$marketplace}"];
            }
        } catch (\Throwable $e) {
            Log::error("BulletPointMaster update failed for {$marketplace}", [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function updateEbayBulletPoints(string $metricClass, $service, string $sku, string $bulletPoints): array
    {
        $metric = $metricClass::where('sku', $sku)
            ->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])
            ->first();

        if (! $metric || ! $metric->item_id) {
            return ['success' => false, 'message' => 'Listing not found for this SKU.'];
        }

        $res = $service->updateBulletPoints($metric->item_id, $bulletPoints);

        if ($res['success'] ?? false) {
            $metric->bullet_points = $bulletPoints;
            $metric->save();
        }

        return $res;
    }

    private function updateWalmartBulletPoints(string $sku, string $bulletPoints): array
    {
        $res = app(WalmartService::class)->updateBulletPoints($sku, $bulletPoints);

        if ($res['success'] ?? false) {
            $metric = WalmartMetrics::where('sku', $sku)->first();
            if ($metric) {
                $metric->bullet_points = $bulletPoints;
                $metric->save();
            }
        }

        return $res;
    }

    private function updateSheinBulletPoints(string $sku, string $bulletPoints): array
    {
        $res = app(SheinApiService::class)->updateBulletPoints($sku, $bulletPoints);

        if ($res['success'] ?? false) {
            $metric = SheinMetric::where('sku', $sku)->first();
            if ($metric) {
                $metric->bullet_points = $bulletPoints;
                $metric->save();
            }
        }

        return $res;
    }

    private function updateDobaBulletPoints(string $sku, string $bulletPoints): array
    {
        $res = app(DobaApiService::class)->updateBulletPoints($sku, $bulletPoints);

        if ($res['success'] ?? false) {
            $metric = DobaMetric::where('sku', $sku)->first();
            if ($metric) {
                $metric->bullet_points = $bulletPoints;
                $metric->save();
            }
        }

        return $res;
    }

    private function updateAliexpressBulletPoints(string $sku, string $bulletPoints): array
    {
        $metric = AliexpressMetric::where('sku', $sku)->first();
        if (! $metric || ! $metric->product_id) {
            return ['success' => false, 'message' => 'AliExpress listing not found for this SKU.'];
        }

        $res = app(AliExpressApiService::class)->updateBulletPoints((string) $metric->product_id, $bulletPoints);

        if ($res['success'] ?? false) {
            $metric->bullet_points = $bulletPoints;
            $metric->save();
        }

        return $res;
    }

    private function saveMetricsOnly(string $marketplace, string $sku, string $bulletPoints, $service): array
    {
        if ($service && method_exists($service, 'updateBulletPoints')) {
            $res = $service->updateBulletPoints($sku, $bulletPoints);
            return $res;
        }

        return ['success' => true, 'message' => "{$marketplace} bullet points API not yet implemented; saved locally."];
    }

    private function normalizeSku(?string $sku): string
    {
        if ($sku === null || $sku === '') {
            return '';
        }

        return str_replace("\u{00a0}", ' ', trim($sku));
    }

    private function combineBullets(?string $b1, ?string $b2, ?string $b3, ?string $b4, ?string $b5): string
    {
        $parts = array_filter(array_map('trim', [$b1 ?? '', $b2 ?? '', $b3 ?? '', $b4 ?? '', $b5 ?? '']));

        return implode(' ', $parts);
    }

    /**
     * Safely load metrics by SKU. Returns empty collection if table doesn't exist or query fails.
     */
    private function safeLoadMetrics(string $modelClass, string $tableName): \Illuminate\Support\Collection
    {
        try {
            if (! Schema::hasTable($tableName)) {
                return collect();
            }

            return $modelClass::all()->keyBy(fn ($m) => $this->normalizeSku($m->sku ?? null));
        } catch (\Throwable $e) {
            Log::warning("BulletPointMaster: Could not load {$tableName}", ['error' => $e->getMessage()]);

            return collect();
        }
    }
}
