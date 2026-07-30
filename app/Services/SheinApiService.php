<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use SimpleXMLElement;
use ZipArchive;
use Illuminate\Support\Str;
use App\Models\ProductStockMapping;
use App\Models\SheinDailyData;
use App\Models\SheinDailyDataL60;
use App\Models\SheinMetric;
use App\Models\SheinPricingPrice;
use App\Services\Support\SavesMarketplaceVideoMetrics;
use App\Services\Support\SavesMarketplaceImageMetrics;
use App\Services\Support\VideoMasterMarketplaceMethods;
use Illuminate\Support\Facades\DB;

class SheinApiService
{
    use SavesMarketplaceVideoMetrics;
    use SavesMarketplaceImageMetrics;
    use VideoMasterMarketplaceMethods;

    protected $appId;

    protected $appSecret;

    protected $baseUrl = 'https://openapi.sheincorp.com'; // or sandbox: openapi-test01.sheincorp.cn

    /** Counters set during listAllProducts() DB persistence */
    protected int $lastMetricCreated = 0;

    protected int $lastMetricUpdated = 0;

    public function __construct()
    {
        $this->appId = config('services.shein.app_id');
        $this->appSecret = config('services.shein.app_secret');
        $this->baseUrl = rtrim((string) (config('services.shein.base_url') ?: 'https://openapi.sheincorp.com'), '/');
    }

    /**
     * SHEIN seller Open API does not use a long-lived OAuth bearer for these calls.
     * Each request is signed with open key + secret (see generateSheinSignature).
     */
    public function getAccessToken(): ?string
    {
        return null;
    }

    /**
     * Update product title on SHEIN (seller backend).
     *
     * Endpoint (documented in SHEIN Open Platform — Product Management): POST
     * `{base_url}/open-api/openapi-business-backend/product/update`
     * Body: `skuCode` (seller SKU), `productName` (new title); optional `spuCode` when required by listing.
     *
     * @param  string  $sku  SHEIN seller SKU / skuCode (not your internal PM SKU unless they match)
     * @param  string|null  $spuCode  Optional SPU from listing (some accounts require it)
     * @return array{success: bool, message: string, title?: string}
     */
    public function updateTitle(string $sku, string $title, ?string $spuCode = null): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return ['success' => false, 'message' => 'SKU (seller skuCode) is required.'];
        }

        $openKeyId = config('services.shein.open_key_id');
        $secretKey = config('services.shein.secret_key');
        if (empty($openKeyId) || empty($secretKey)) {
            Log::error('Shein updateTitle: missing SHEIN_OPEN_KEY_ID or SHEIN_SECRET_KEY');

            return ['success' => false, 'message' => 'Configure SHEIN_OPEN_KEY_ID and SHEIN_SECRET_KEY in .env.'];
        }

        $maxLen = (int) config('services.shein.title_max_length', 80);
        if ($maxLen < 1) {
            $maxLen = 80;
        }

        $normalized = mb_substr(trim($title), 0, $maxLen);
        if ($normalized === '') {
            return ['success' => false, 'message' => 'Title is empty after trimming.'];
        }

        $endpoint = (string) config(
            'services.shein.product_update_path',
            '/open-api/openapi-business-backend/product/update'
        );
        $url = $this->baseUrl.$endpoint;

        $payload = [
            'skuCode' => $sku,
            'productName' => $normalized,
        ];

        if ($spuCode !== null && $spuCode !== '') {
            $payload['spuCode'] = $spuCode;
        } else {
            $metric = $this->safeSheinMetricFindBySku($sku);
            if ($metric && ! empty($metric->spu_name)) {
                $payload['spuCode'] = $metric->spu_name;
            }
        }

        try {
            Log::info('Shein updateTitle request', [
                'sku' => $sku,
                'title_length' => mb_strlen($normalized),
                'endpoint' => $endpoint,
            ]);

            $response = Http::withoutVerifying()
                ->timeout(45)
                ->withHeaders($this->buildSheinAuthHeaders($endpoint))
                ->post($url, $payload);

            $body = $response->body();
            $json = is_array($response->json()) ? $response->json() : null;

            if (! $response->successful()) {
                Log::error('Shein updateTitle HTTP failure', [
                    'status' => $response->status(),
                    'body' => mb_substr($body, 0, 2000),
                ]);

                return [
                    'success' => false,
                    'message' => 'HTTP '.$response->status().': '.mb_substr($body, 0, 400),
                ];
            }

            if ($this->sheinResponseIndicatesSuccess($json)) {
                $this->safeSheinMetricUpdateTitle($sku, $normalized);

                Log::info('Shein updateTitle success', ['sku' => $sku]);

                return [
                    'success' => true,
                    'message' => 'Title updated.',
                    'title' => $normalized,
                ];
            }

            $message = $this->sheinExtractErrorMessage($json);
            Log::error('Shein updateTitle API error', ['response' => $json]);

            return ['success' => false, 'message' => $message];
        } catch (\Throwable $e) {
            Log::error('Shein updateTitle exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function sheinResponseIndicatesSuccess(?array $json): bool
    {
        if ($json === null) {
            return false;
        }

        $code = $json['code'] ?? $json['errorCode'] ?? $json['status'] ?? null;

        if (isset($json['info']) && is_array($json['info'])) {
            $info = $json['info'];
            if (array_key_exists('code', $info)) {
                $code = $info['code'];
            }
        }

        if ($code === 0 || $code === 200 || $code === '0' || $code === '200') {
            return true;
        }

        if (! empty($json['success']) || (! empty($json['info']['success']))) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function sheinExtractErrorMessage(?array $json): string
    {
        if ($json === null) {
            return 'Invalid or empty JSON response.';
        }

        $parts = [];
        foreach (['message', 'msg', 'errorMsg', 'sub_msg'] as $k) {
            if (! empty($json[$k])) {
                $parts[] = (string) $json[$k];
            }
        }
        if (isset($json['info']) && is_array($json['info'])) {
            foreach (['message', 'msg'] as $k) {
                if (! empty($json['info'][$k])) {
                    $parts[] = (string) $json['info'][$k];
                }
            }
        }

        return $parts !== [] ? implode(' — ', $parts) : json_encode($json);
    }

    public function metricsTableExists(): bool
    {
        try {
            return Schema::hasTable('shein_metrics');
        } catch (\Throwable $e) {
            Log::warning('shein_metrics table check failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Persist one getStock()/full-detail row into shein_metrics.
     * Renames old hash sellerSku rows to the resolved match SKU and keys by shein_sku_code.
     *
     * @param  array<string, mixed>  $item
     */
    public function persistSheinMetricRow(array $item): ?SheinMetric
    {
        if (! $this->metricsTableExists()) {
            return null;
        }

        $resolvedSku = trim((string) ($item['sku'] ?? ''));
        if ($resolvedSku === '') {
            Log::warning('Missing SKU in Shein inventory data', $item);

            return null;
        }

        $sheinCode = trim((string) ($item['shein_sku_code'] ?? ''));
        $sellerSkuRaw = trim((string) ($item['seller_sku'] ?? ''));
        $productNumber = trim((string) ($item['product_number'] ?? ''));
        $skuSource = (string) ($item['sku_source'] ?? '');

        $metricData = [
            'sku' => $resolvedSku,
            'inventory' => (int) ($item['quantity'] ?? $item['inventory'] ?? 0),
            'price' => $item['price'] ?? null,
            'retail_price' => $item['retail_price'] ?? null,
            'views' => $item['views'] ?? 0,
            'rating' => $item['rating'] ?? null,
            'review_count' => $item['review_count'] ?? 0,
            'last_synced_at' => now(),
        ];

        if (Schema::hasColumn('shein_metrics', 'shein_sku_code') && $sheinCode !== '') {
            $metricData['shein_sku_code'] = $sheinCode;
        }
        if (Schema::hasColumn('shein_metrics', 'sku_source') && $skuSource !== '') {
            $metricData['sku_source'] = $skuSource;
        }
        foreach (['raw_data', 'product_name', 'spu_name', 'status', 'description', 'image_url', 'category'] as $key) {
            if (array_key_exists($key, $item)) {
                $metricData[$key] = $item[$key];
            }
        }

        try {
            $metric = $this->findExistingSheinMetricForPersist($sheinCode, $sellerSkuRaw, $resolvedSku);

            if ($metric) {
                $oldSku = (string) $metric->sku;
                // Avoid unique(sku) collisions when another row already owns the resolved SKU.
                if ($oldSku !== $resolvedSku) {
                    $conflict = SheinMetric::query()
                        ->where('sku', $resolvedSku)
                        ->where('id', '!=', $metric->id)
                        ->first();
                    if ($conflict) {
                        $conflictCode = trim((string) ($conflict->shein_sku_code ?? data_get($conflict->raw_data, 'skuCode', '')));
                        if ($sheinCode !== '' && $conflictCode === $sheinCode) {
                            $conflict->delete();
                        } elseif ($sheinCode !== '') {
                            $metricData['sku'] = $resolvedSku.' ['.$sheinCode.']';
                        }
                    }
                }

                $metric->fill($metricData);
                $metric->save();
            } else {
                try {
                    $metric = SheinMetric::updateOrCreate(
                        $sheinCode !== '' && Schema::hasColumn('shein_metrics', 'shein_sku_code')
                            ? ['shein_sku_code' => $sheinCode]
                            : ['sku' => $resolvedSku],
                        $metricData
                    );
                } catch (\Throwable $e) {
                    // Fallback if unique(sku) still conflicts.
                    if ($sheinCode !== '') {
                        $metricData['sku'] = $resolvedSku.' ['.$sheinCode.']';
                        $metric = SheinMetric::updateOrCreate(
                            Schema::hasColumn('shein_metrics', 'shein_sku_code')
                                ? ['shein_sku_code' => $sheinCode]
                                : ['sku' => $metricData['sku']],
                            $metricData
                        );
                    } else {
                        throw $e;
                    }
                }
            }

            // Remove stale hash rows for this listing after rename/create.
            $staleSkus = array_values(array_unique(array_filter([
                $sellerSkuRaw !== $metric->sku ? $sellerSkuRaw : null,
                $productNumber !== '' && $productNumber !== $metric->sku && $this->isUsableSheinSellerSku($productNumber) === false
                    ? $productNumber
                    : null,
            ])));
            if ($staleSkus !== []) {
                $q = SheinMetric::query()
                    ->whereIn('sku', $staleSkus)
                    ->where('id', '!=', $metric->id);
                if ($sheinCode !== '') {
                    $q->where(function ($inner) use ($sheinCode) {
                        $inner->where('shein_sku_code', $sheinCode)
                            ->orWhere('raw_data->skuCode', $sheinCode)
                            ->orWhereNull('shein_sku_code');
                    });
                }
                $q->delete();
            }

            // Keep shein_pricing_prices in sync when hash SKUs are remapped.
            $this->remapSheinPricingSku($sellerSkuRaw, (string) $metric->sku, $item);

            return $metric->fresh() ?? $metric;
        } catch (\Throwable $e) {
            Log::error('Shein persistSheinMetricRow failed', [
                'sku' => $resolvedSku,
                'shein_sku_code' => $sheinCode,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Find an existing metrics row for a Shein listing (prefer shein skuCode, then hash sellerSku).
     */
    private function findExistingSheinMetricForPersist(string $sheinCode, string $sellerSkuRaw, string $resolvedSku): ?SheinMetric
    {
        if ($sheinCode !== '' && Schema::hasColumn('shein_metrics', 'shein_sku_code')) {
            $byCode = SheinMetric::query()->where('shein_sku_code', $sheinCode)->first();
            if ($byCode) {
                return $byCode;
            }
        }

        if ($sheinCode !== '') {
            $byRaw = SheinMetric::query()->where('raw_data->skuCode', $sheinCode)->first();
            if ($byRaw) {
                return $byRaw;
            }
        }

        if ($sellerSkuRaw !== '' && $sellerSkuRaw !== $resolvedSku) {
            $bySeller = SheinMetric::query()->where('sku', $sellerSkuRaw)->first();
            if ($bySeller) {
                return $bySeller;
            }
        }

        return SheinMetric::query()->where('sku', $resolvedSku)->first();
    }

    /**
     * When a hash sellerSku is resolved, rewrite shein_pricing_prices.sku to the match SKU.
     *
     * @param  array<string, mixed>  $item
     */
    private function remapSheinPricingSku(string $fromSku, string $toSku, array $item): void
    {
        if ($fromSku === '' || $toSku === '' || $fromSku === $toSku) {
            return;
        }
        // Only remap unusable hash sellerSku → readable match SKU
        if ($this->isUsableSheinSellerSku($fromSku) || ! $this->isUsableSheinSellerSku($toSku)) {
            return;
        }

        try {
            if (! Schema::hasTable('shein_pricing_prices')) {
                return;
            }

            $from = \App\Models\SheinPricingPrice::query()->where('sku', $fromSku)->first();
            if (! $from) {
                return;
            }

            $to = \App\Models\SheinPricingPrice::query()->where('sku', $toSku)->first();
            if ($to) {
                $to->update([
                    'price' => $item['retail_price'] ?? $item['price'] ?? $to->price,
                    'special_offer_price' => $item['price'] ?? $to->special_offer_price,
                    'shein_stock' => $item['quantity'] ?? $to->shein_stock,
                ]);
                $from->delete();
            } else {
                $from->update(['sku' => $toSku]);
            }
        } catch (\Throwable $e) {
            Log::warning('Shein pricing SKU remap failed', [
                'from' => $fromSku,
                'to' => $toSku,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return \App\Models\SheinMetric|null
     */
    private function safeSheinMetricFindBySku(string $sku)
    {
        if (! $this->metricsTableExists()) {
            return null;
        }

        try {
            return SheinMetric::query()->where('sku', $sku)->first();
        } catch (\Throwable $e) {
            Log::warning('SheinMetric find failed (table or connection issue)', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Resolve seller SKU for Shein API from metrics (SKU or spu_name / alternate keys).
     */
    private function resolveSheinSellerSku(string $identifier): string
    {
        $id = trim($identifier);
        if ($id === '' || ! $this->metricsTableExists()) {
            return $id;
        }

        try {
            $m = SheinMetric::query()
                ->where('sku', $id)
                ->orWhere('sku', strtoupper($id))
                ->orWhere('sku', strtolower($id))
                ->first();
            if (! $m && Schema::hasColumn('shein_metrics', 'spu_name')) {
                $m = SheinMetric::query()->where('spu_name', $id)->first();
            }

            return ($m && $m->sku) ? trim((string) $m->sku) : $id;
        } catch (\Throwable $e) {
            return $id;
        }
    }

    private function safeSheinMetricUpdateTitle(string $sku, string $normalizedTitle): void
    {
        if (! $this->metricsTableExists()) {
            Log::info('Shein updateTitle: shein_metrics missing; API update succeeded but local row not updated.');

            return;
        }

        try {
            SheinMetric::query()->where('sku', $sku)->update([
                'product_name' => $normalizedTitle,
                'last_synced_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Shein updateTitle: could not update shein_metrics (non-fatal)', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
        }
    }

    function generateSheinSignature($path, $timestamp, $randomKey)
    {
        $openKeyId = config('services.shein.open_key_id');
        $secretKey = config('services.shein.secret_key');

        $value = $openKeyId . "&" . $timestamp . "&" . $path;

        $key = $secretKey . $randomKey;

        $hmacResult = hash_hmac('sha256', $value, $key, false); // false means return hexadecimal

        $base64Signature = base64_encode($hmacResult);

        $finalSignature = $randomKey . $base64Signature;

        return $finalSignature;
    }

    /**
     * Signed headers for SHEIN Open Platform (includes x-lt-randomKey required by auth policy).
     *
     * @return array<string, string>
     */
    protected function buildSheinAuthHeaders(string $endpoint): array
    {
        $timestamp = (int) round(microtime(true) * 1000);
        $random = Str::random(5);
        $signature = $this->generateSheinSignature($endpoint, $timestamp, $random);

        return [
            'Language' => 'en-us',
            'x-lt-openKeyId' => (string) config('services.shein.open_key_id'),
            'x-lt-timestamp' => (string) $timestamp,
            'x-lt-signature' => $signature,
            'Content-Type' => 'application/json',
        ];
    }


    /**
     * Fetch product by SPU name
     */
    public function fetchBySpu(string $spuName)
    {
        $endpoint = "/open-api/openapi-business-backend/product/full-detail";
        $timestamp = round(microtime(true) * 1000);
        $random = Str::random(5);
        $signature = $this->generateSheinSignature($endpoint, $timestamp, $random);
        $url = $this->baseUrl . $endpoint;

        $payload = [
            "skuCodes" => [$spuName]
        ];

        $response = Http::withoutVerifying()->withHeaders([
            "Language" => "en-us",
            "x-lt-openKeyId" => config('services.shein.open_key_id'),
            "x-lt-timestamp" => $timestamp,
            "x-lt-signature" => $signature,
            "Content-Type" => "application/json",
        ])->post($url, $payload);

        if (!$response->successful()) {
            Log::error("Shein API Error: " . $response->body());
            throw new \Exception("Shein API Error: " . $response->body());
        }

        $data = $response->json();
        return $data["info"] ?? [];
    }

    public function listAllProducts()
    {
        $this->lastMetricCreated = 0;
        $this->lastMetricUpdated = 0;

        $endpoint = '/open-api/openapi-business-backend/product/query';
        $pageSize = 400;
        $allProducts = [];

        // Loop max 1000 pages (safe upper bound)
        for ($pageNum = 1; $pageNum <= 1000; $pageNum++) {

            $timestamp = round(microtime(true) * 1000);
            $random    = Str::random(5);
            $signature = $this->generateSheinSignature($endpoint, $timestamp, $random);

            $url = $this->baseUrl . $endpoint;

            $payload = [
                "pageNum"         => $pageNum,
                "pageSize"        => $pageSize,
                "insertTimeEnd"   => "",
                "insertTimeStart" => "",
                "updateTimeEnd"   => "",
                "updateTimeStart" => "",
            ];
            $request= Http::withoutVerifying()->withHeaders([
                "Language"       => "en-us",
                "x-lt-openKeyId" => config('services.shein.open_key_id'),
                "x-lt-timestamp" => $timestamp,
                "x-lt-signature" => $signature,
                "Content-Type"   => "application/json",
            ]);
            if (config('filesystems.default') === 'local') {$request = $request->withoutVerifying();}
            $response =$request->post($url, $payload);

            if (!$response->successful()) {
                throw new \Exception("Shein API Error: " . $response->body());
            }

            $data = $response->json();
            $products = $data["info"]["data"] ?? [];
            // dd($products);
            // If no products returned → stop looping
            if (empty($products)) {
                break;
            }

            $allProducts = array_merge($allProducts, $products);
        }
        
        // Flatten every skuCode from every row (do not keep only skuCodeList[0]).
        $sheinSkuCodes = [];
        foreach ($allProducts as $item) {
            foreach (($item['skuCodeList'] ?? []) as $code) {
                $code = trim((string) $code);
                if ($code !== '') {
                    $sheinSkuCodes[] = $code;
                }
            }
        }
        $sheinSkuCodes = array_values(array_unique($sheinSkuCodes));

        $result = $this->getStock($sheinSkuCodes);

        if (! $this->metricsTableExists()) {
            Log::warning('shein_metrics table missing; skipping DB persistence. Run migrations to create shein_metrics.');
        } else {
            try {
                foreach ($result as $item) {
                    $metric = $this->persistSheinMetricRow($item);
                    if (! $metric) {
                        continue;
                    }

                    if ($metric->wasRecentlyCreated) {
                        $this->lastMetricCreated++;
                    } else {
                        $this->lastMetricUpdated++;
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Shein listAllProducts: shein_metrics persistence failed (API data still returned)', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Shein Data Sync Complete', [
            'total_items' => count($result),
            'created_records' => $this->lastMetricCreated,
            'updated_records' => $this->lastMetricUpdated,
        ]);

        return $result;
    }


    /**
     * True when a Shein seller-facing SKU can match Shopify / product_master
     * (non-empty and not an auto-generated 32-char hex id).
     */
    public function isUsableSheinSellerSku(?string $sku): bool
    {
        $sku = trim((string) $sku);
        if ($sku === '') {
            return false;
        }

        return ! (bool) preg_match('/^[a-f0-9]{32}$/i', $sku);
    }

    /**
     * Pull readable seller SKU from full-detail attributeLists (产品型号 / product model).
     * Many hash-SKU listings still store the real SKU here.
     *
     * @param  array<string, mixed>  $item
     */
    public function extractSheinModelSku(array $item): ?string
    {
        foreach (($item['attributeLists'] ?? []) as $attr) {
            if (! is_array($attr)) {
                continue;
            }

            $name = strtolower(trim((string) (
                $attr['attributeMulti']['attributeMulti']
                ?? $attr['attributeName']
                ?? ''
            )));

            // 产品型号 = product model number on Shein seller backend
            if ($name !== '产品型号' && ! str_contains($name, '型号') && ! str_contains($name, 'model')) {
                continue;
            }

            $value = $attr['attributeAdditionList'][0]['additionValue']
                ?? $attr['attributeValueMulti']['attributeValueMulti']
                ?? $attr['attributeValue']
                ?? null;

            $value = trim((string) $value);
            if ($value !== '' && $this->isUsableSheinSellerSku($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Resolve the SKU used to match Shopify from a product/full-detail row.
     *
     * Prefer sellerSku; if empty/hex fall back to productNumber, then attributeLists 产品型号.
     *
     * @param  array<string, mixed>  $item
     * @return array{sku: ?string, source: string, usable: bool, seller_sku: string, product_number: string, model_sku: ?string, shein_sku_code: string}
     */
    public function resolveSheinMatchSku(array $item): array
    {
        $sellerSku = trim((string) ($item['sellerSku'] ?? ''));
        $productNumber = trim((string) ($item['productNumber'] ?? ''));
        $sheinSkuCode = trim((string) ($item['skuCode'] ?? ''));
        $modelSku = $this->extractSheinModelSku($item);

        if ($this->isUsableSheinSellerSku($sellerSku)) {
            return [
                'sku' => $sellerSku,
                'source' => 'sellerSku',
                'usable' => true,
                'seller_sku' => $sellerSku,
                'product_number' => $productNumber,
                'model_sku' => $modelSku,
                'shein_sku_code' => $sheinSkuCode,
            ];
        }

        if ($this->isUsableSheinSellerSku($productNumber)) {
            return [
                'sku' => $productNumber,
                'source' => 'productNumber',
                'usable' => true,
                'seller_sku' => $sellerSku,
                'product_number' => $productNumber,
                'model_sku' => $modelSku,
                'shein_sku_code' => $sheinSkuCode,
            ];
        }

        if ($modelSku !== null) {
            return [
                'sku' => $modelSku,
                'source' => 'attributeModel',
                'usable' => true,
                'seller_sku' => $sellerSku,
                'product_number' => $productNumber,
                'model_sku' => $modelSku,
                'shein_sku_code' => $sheinSkuCode,
            ];
        }

        // Last resort: keep whatever Shein stored (hash) so the row is not dropped.
        $fallback = $sellerSku !== '' ? $sellerSku : ($productNumber !== '' ? $productNumber : $sheinSkuCode);

        return [
            'sku' => $fallback !== '' ? $fallback : null,
            'source' => $fallback !== '' ? 'unmapped_hash' : 'missing',
            'usable' => false,
            'seller_sku' => $sellerSku,
            'product_number' => $productNumber,
            'model_sku' => $modelSku,
            'shein_sku_code' => $sheinSkuCode,
        ];
    }

    public function getStock(array $skuCodes)
    {
        $endpoint = '/open-api/openapi-business-backend/product/full-detail';
        $chunkSize = 100;
        $allStock = [];

        // Split SKU codes into chunks of 100
        $chunks = array_chunk(array_values(array_filter(array_map('strval', $skuCodes))), $chunkSize);

        foreach ($chunks as $chunk) {
            $timestamp = round(microtime(true) * 1000);
            $random = Str::random(5);
            $signature = $this->generateSheinSignature($endpoint, $timestamp, $random);
            $url = $this->baseUrl.$endpoint;

            $payload = [
                'skuCodes' => $chunk,
            ];

            $response = Http::withoutVerifying()->withHeaders([
                'Language' => 'en-us',
                'x-lt-openKeyId' => config('services.shein.open_key_id'),
                'x-lt-timestamp' => $timestamp,
                'x-lt-signature' => $signature,
                'Content-Type' => 'application/json',
            ])->post($url, $payload);

            if (! $response->successful()) {
                Log::error('Shein API Error: '.$response->body());
                throw new \Exception('Shein API Error: '.$response->body());
            }

            $data = $response->json();

            if (isset($data['info']) && is_array($data['info'])) {
                foreach ($data['info'] as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $resolved = $this->resolveSheinMatchSku($item);
                    $matchSku = $resolved['sku'];
                    if ($matchSku === null || $matchSku === '') {
                        Log::warning('Shein getStock: no sellerSku/productNumber/skuCode on row', [
                            'item_keys' => array_keys($item),
                        ]);

                        continue;
                    }

                    $quantity = $item['goodsInventory']['inventoryQuantity'] ?? null;

                    // Extract price data from currentPrices array (actual API structure)
                    $price = null;
                    $retailPrice = null;
                    $costPrice = null;

                    if (isset($item['currentPrices']) && is_array($item['currentPrices']) && count($item['currentPrices']) > 0) {
                        $priceData = $item['currentPrices'][0];
                        $price = $priceData['salePrice'] ?? $priceData['specialPrice'] ?? null;
                        $retailPrice = $priceData['shopPrice'] ?? $priceData['suggestedRetailPrice'] ?? null;
                    }

                    // Extract views/visits data
                    $views = $item['viewCount'] ?? $item['visits'] ?? $item['pageViews'] ?? null;

                    // Extract rating data
                    $rating = $item['rating'] ?? $item['averageRating'] ?? $item['starRating'] ?? null;
                    $reviewCount = $item['reviewCount'] ?? $item['ratingCount'] ?? null;

                    // Extract additional product info (matching actual API structure)
                    $productName = $item['productName'] ?? null;
                    $spuName = $item['spuName'] ?? null;
                    $categoryName = $item['categoryName'] ?? null;
                    $description = $item['productDesc'] ?? null;

                    // Extract main image from imageList
                    $imageUrl = null;
                    if (isset($item['imageList']) && is_array($item['imageList'])) {
                        foreach ($item['imageList'] as $image) {
                            if (isset($image['imageType']) && $image['imageType'] === 'MAIN') {
                                $imageUrl = $image['imageUrl'] ?? null;
                                break;
                            }
                        }
                        // If no MAIN image, get first image
                        if (! $imageUrl && count($item['imageList']) > 0) {
                            $imageUrl = $item['imageList'][0]['imageUrl'] ?? null;
                        }
                    }

                    // Extract status from shelfDetails
                    $status = null;
                    if (isset($item['shelfDetails']) && is_array($item['shelfDetails']) && count($item['shelfDetails']) > 0) {
                        $shelfDetail = $item['shelfDetails'][0];
                        $isOnShelf = $shelfDetail['isOnShelf'] ?? false;
                        $status = $isOnShelf ? 'active' : 'inactive';
                    }

                    // Determine status from inventory if not set
                    if (! $status) {
                        if ($quantity === 0) {
                            $status = 'out_of_stock';
                        } elseif ($quantity > 0 && $quantity < 10) {
                            $status = 'low_stock';
                        } else {
                            $status = 'active';
                        }
                    }

                    $stockData = [
                        'sku' => $matchSku,
                        'sku_source' => $resolved['source'],
                        'sku_usable' => $resolved['usable'],
                        'seller_sku' => $resolved['seller_sku'],
                        'product_number' => $resolved['product_number'],
                        'model_sku' => $resolved['model_sku'],
                        'shein_sku_code' => $resolved['shein_sku_code'],
                        'quantity' => $quantity !== null ? (int) $quantity : 0,
                    ];

                    // Add price if available
                    if ($price !== null) {
                        $stockData['price'] = (float) $price;
                    }

                    if ($retailPrice !== null) {
                        $stockData['retail_price'] = (float) $retailPrice;
                    }

                    if ($costPrice !== null) {
                        $stockData['cost_price'] = (float) $costPrice;
                    }

                    // Add views if available
                    if ($views !== null) {
                        $stockData['views'] = (int) $views;
                    }

                    // Add rating if available
                    if ($rating !== null) {
                        $stockData['rating'] = (float) $rating;
                    }

                    if ($reviewCount !== null) {
                        $stockData['review_count'] = (int) $reviewCount;
                    }

                    // Add product info
                    if ($productName !== null) {
                        $stockData['product_name'] = $productName;
                    }

                    if ($spuName !== null) {
                        $stockData['spu_name'] = $spuName;
                    }

                    if ($status !== null) {
                        $stockData['status'] = $status;
                    }

                    if ($description !== null) {
                        $stockData['description'] = $description;
                    }

                    if ($imageUrl !== null) {
                        $stockData['image_url'] = $imageUrl;
                    }

                    if ($categoryName !== null) {
                        $stockData['category'] = $categoryName;
                    }

                    // Store raw API data
                    $stockData['raw_data'] = $item;

                    $allStock[] = $stockData;
                }
            }
        }

        Log::info('Shein Stock API - Chunks processed: '.count($chunks).', Products: '.count($allStock));

        return $allStock;
    }

    public function getStock1(array $spus)
{
    $endpoint = "/open-api/stock/stock-query";
    $chunkSize = 10;
    $allStock = [];

    // Split SPUs into chunks of 100
    $chunks = array_chunk($spus, $chunkSize);

    foreach ($chunks as $chunk) {
        $timestamp = round(microtime(true) * 1000);
        $random = Str::random(5);
        $signature = $this->generateSheinSignature($endpoint, $timestamp, $random);
        $url = $this->baseUrl . $endpoint;

        $payload = [
            "languageList" => ["en"],
            "skuCodeList" => [],       // must be empty
            "skcNameList" => [],       // must be empty
            "spuNameList" => $chunk,   // only this populated
            "warehouseType" => "3",    // required: 1, 2, or 3
        ];

        $response = Http::withoutVerifying()->withHeaders([
            "Language" => "en-us",
            "x-lt-openKeyId" => config('services.shein.open_key_id'),
            "x-lt-timestamp" => $timestamp,
            "x-lt-signature" => $signature,
            "Content-Type" => "application/json",
        ])->post($url, $payload);

        if (!$response->successful()) {
            throw new \Exception("Shein API Error: " . $response->body());
        }

        $data = $response->json();
        // dd($data['info']);
        if (isset($data["info"]["data"]) && is_array($data["info"]["data"])) {
            $allStock = array_merge($allStock, $data["info"]["data"]);
        }
    }

    return $allStock;
}

    /**
     * Fetch detailed product information by SKU
     * Includes: Price, Views, Rating, Inventory
     */
    public function getProductDetails(string $sku)
    {
        $endpoint = "/open-api/openapi-business-backend/product/full-detail";
        $timestamp = round(microtime(true) * 1000);
        $random = Str::random(5);
        $signature = $this->generateSheinSignature($endpoint, $timestamp, $random);
        $url = $this->baseUrl . $endpoint;

        $payload = [
            "skuCodes" => [$sku]
        ];

        $response = Http::withoutVerifying()->withHeaders([
            "Language" => "en-us",
            "x-lt-openKeyId" => config('services.shein.open_key_id'),
            "x-lt-timestamp" => $timestamp,
            "x-lt-signature" => $signature,
            "Content-Type" => "application/json",
        ])->post($url, $payload);

        if (!$response->successful()) {
            Log::error("Shein Product Details API Error for SKU: {$sku}", ['response' => $response->body()]);
            throw new \Exception("Shein API Error: " . $response->body());
        }

        $data = $response->json();

        if (isset($data["info"]) && is_array($data["info"]) && count($data["info"]) > 0) {
            $item = $data["info"][0];
            
            // Extract price from currentPrices array
            $price = null;
            $retailPrice = null;
            if (isset($item['currentPrices']) && is_array($item['currentPrices']) && count($item['currentPrices']) > 0) {
                $priceData = $item['currentPrices'][0];
                $price = $priceData['salePrice'] ?? $priceData['specialPrice'] ?? null;
                $retailPrice = $priceData['shopPrice'] ?? $priceData['suggestedRetailPrice'] ?? null;
            }
            
            // Extract main image from imageList
            $imageUrl = null;
            if (isset($item['imageList']) && is_array($item['imageList'])) {
                foreach ($item['imageList'] as $image) {
                    if (isset($image['imageType']) && $image['imageType'] === 'MAIN') {
                        $imageUrl = $image['imageUrl'] ?? null;
                        break;
                    }
                }
                if (!$imageUrl && count($item['imageList']) > 0) {
                    $imageUrl = $item['imageList'][0]['imageUrl'] ?? null;
                }
            }
            
            // Extract status from shelfDetails
            $status = null;
            $quantity = $item['goodsInventory']['inventoryQuantity'] ?? 0;
            if (isset($item['shelfDetails']) && is_array($item['shelfDetails']) && count($item['shelfDetails']) > 0) {
                $shelfDetail = $item['shelfDetails'][0];
                $isOnShelf = $shelfDetail['isOnShelf'] ?? false;
                $status = $isOnShelf ? 'active' : 'inactive';
            }
            if (!$status) {
                $status = $quantity === 0 ? 'out_of_stock' : ($quantity < 10 ? 'low_stock' : 'active');
            }
            
            $resolved = $this->resolveSheinMatchSku($item);

            $productDetails = [
                'sku' => $resolved['sku'] ?? $sku,
                'sku_source' => $resolved['source'],
                'sku_usable' => $resolved['usable'],
                'seller_sku' => $resolved['seller_sku'],
                'product_number' => $resolved['product_number'],
                'model_sku' => $resolved['model_sku'],
                'shein_sku_code' => $resolved['shein_sku_code'],
                'product_name' => $item['productName'] ?? null,
                'spu_name' => $item['spuName'] ?? null,
                'quantity' => $quantity,
                'price' => $price,
                'retail_price' => $retailPrice,
                'cost_price' => null,
                'views' => $item['viewCount'] ?? $item['visits'] ?? $item['pageViews'] ?? 0,
                'rating' => $item['rating'] ?? $item['averageRating'] ?? $item['starRating'] ?? null,
                'review_count' => $item['reviewCount'] ?? $item['ratingCount'] ?? 0,
                'status' => $status,
                'description' => $item['productDesc'] ?? null,
                'image_url' => $imageUrl,
                'category' => $item['categoryName'] ?? null,
                'raw_data' => $item, // Store full response for debugging
            ];

            if ($this->metricsTableExists()) {
                try {
                    $this->persistSheinMetricRow($productDetails);
                } catch (\Throwable $e) {
                    Log::warning('Shein getProductDetails: could not save to shein_metrics', [
                        'sku' => $sku,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Shein Product Details Fetched', ['sku' => $sku, 'details' => $productDetails]);
            
            return $productDetails;
        }

        Log::warning('No product details found for SKU: ' . $sku);
        return null;
    }

    /**
     * POST signed JSON to Shein Open API.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function sheinApiPost(string $endpoint, array $payload): array
    {
        $url = $this->baseUrl.$endpoint;
        $response = Http::withoutVerifying()
            ->timeout(60)
            ->withHeaders($this->buildSheinAuthHeaders($endpoint))
            ->post($url, $payload);

        $json = is_array($response->json()) ? $response->json() : null;
        if (! $response->successful()) {
            throw new \RuntimeException('Shein API HTTP '.$response->status().': '.mb_substr($response->body(), 0, 500));
        }
        if (! is_array($json)) {
            throw new \RuntimeException('Shein API invalid JSON: '.mb_substr($response->body(), 0, 500));
        }

        $code = $json['code'] ?? null;
        if (! ($code === 0 || $code === '0' || $code === 200 || $code === '200')) {
            throw new \RuntimeException('Shein API error: '.($json['msg'] ?? $json['message'] ?? json_encode($json)));
        }

        return $json;
    }

    /**
     * Order list — POST /open-api/order/order-list
     * start/end must be within 48 hours (Shein limit). Timezone: Asia/Shanghai.
     *
     * @return array{count:int, orderList: array<int, array<string, mixed>>, raw: array<string, mixed>}
     */
    public function getOrderList(string $startTime, string $endTime, int $queryType = 1, int $page = 1, int $pageSize = 30): array
    {
        $endpoint = '/open-api/order/order-list';
        $pageSize = max(1, min(30, $pageSize));
        $json = $this->sheinApiPost($endpoint, [
            'queryType' => $queryType,
            'startTime' => $startTime,
            'endTime' => $endTime,
            'page' => max(1, $page),
            'pageSize' => $pageSize,
        ]);

        $info = is_array($json['info'] ?? null) ? $json['info'] : [];

        return [
            'count' => (int) ($info['count'] ?? 0),
            'orderList' => is_array($info['orderList'] ?? null) ? $info['orderList'] : [],
            'raw' => $json,
        ];
    }

    /**
     * Order detail — POST /open-api/order/order-detail (max 30 orderNos per call).
     *
     * @param  array<int, string>  $orderNos
     * @return array{orders: array<int, array<string, mixed>>, raw: array<string, mixed>}
     */
    public function getOrderDetails(array $orderNos): array
    {
        $orderNos = array_values(array_filter(array_map(static fn ($n) => trim((string) $n), $orderNos)));
        if ($orderNos === []) {
            return ['orders' => [], 'raw' => []];
        }

        $endpoint = '/open-api/order/order-detail';
        $all = [];
        $lastRaw = [];
        foreach (array_chunk($orderNos, 30) as $chunk) {
            $json = $this->sheinApiPost($endpoint, ['orderNoList' => $chunk]);
            $lastRaw = $json;
            $info = $json['info'] ?? [];
            if (isset($info[0]) && is_array($info[0])) {
                $all = array_merge($all, $info);
            } elseif (isset($info['orderList']) && is_array($info['orderList'])) {
                $all = array_merge($all, $info['orderList']);
            } elseif (is_array($info) && isset($info['orderNo'])) {
                $all[] = $info;
            }
        }

        return ['orders' => $all, 'raw' => $lastRaw];
    }

    /**
     * Fetch orders raw data from Shein API (list + detail).
     * Walks backward in <=47h windows (Shein 48h limit).
     *
     * @return array{
     *   success: bool,
     *   message: string,
     *   order_count: int,
     *   detail_count: int,
     *   windows: int,
     *   order_list: array<int, array<string, mixed>>,
     *   order_details: array<int, array<string, mixed>>,
     *   saved_path: ?string
     * }
     */
    public function fetchOrdersRaw(int $days = 2, int $queryType = 1, bool $includeDetails = true, bool $saveToStorage = true): array
    {
        $days = max(1, min(30, $days));
        $tz = new \DateTimeZone('Asia/Shanghai');
        $end = new \DateTimeImmutable('now', $tz);
        $overallStart = $end->modify('-'.$days.' days');

        $orderList = [];
        $seen = [];
        $windows = 0;

        // Walk forward from overallStart in ~47h chunks up to now
        $cursor = $overallStart;
        while ($cursor < $end) {
            $windows++;
            $windowEnd = $cursor->modify('+47 hours');
            if ($windowEnd > $end) {
                $windowEnd = $end;
            }

            $page = 1;
            do {
                $result = $this->getOrderList(
                    $cursor->format('Y-m-d H:i:s'),
                    $windowEnd->format('Y-m-d H:i:s'),
                    $queryType,
                    $page,
                    30
                );
                foreach ($result['orderList'] as $row) {
                    $no = (string) ($row['orderNo'] ?? '');
                    if ($no === '' || isset($seen[$no])) {
                        continue;
                    }
                    $seen[$no] = true;
                    $orderList[] = $row;
                }
                $fetched = count($result['orderList']);
                $page++;
            } while ($fetched >= 30 && $page <= 200);

            $cursor = $windowEnd;
        }

        $orderDetails = [];
        if ($includeDetails && $orderList !== []) {
            $nos = array_values(array_map(static fn ($r) => (string) ($r['orderNo'] ?? ''), $orderList));
            $nos = array_values(array_filter($nos));
            $details = $this->getOrderDetails($nos);
            $orderDetails = $details['orders'];
        }

        $payload = [
            'fetched_at' => now()->toIso8601String(),
            'days' => $days,
            'queryType' => $queryType,
            'order_list' => $orderList,
            'order_details' => $orderDetails,
        ];

        $savedPath = null;
        if ($saveToStorage) {
            $dir = storage_path('app/shein');
            if (! is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $savedPath = $dir.'/orders_raw_'.now()->format('Ymd_His').'.json';
            file_put_contents($savedPath, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            // Keep a stable latest pointer
            file_put_contents($dir.'/orders_raw_latest.json', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            $savedPath = 'storage/app/shein/'.basename($savedPath);
        }

        Log::info('Shein orders raw fetched', [
            'order_count' => count($orderList),
            'detail_count' => count($orderDetails),
            'days' => $days,
        ]);

        return [
            'success' => true,
            'message' => 'Shein orders raw data fetched from API.',
            'order_count' => count($orderList),
            'detail_count' => count($orderDetails),
            'windows' => $windows,
            'order_list' => $orderList,
            'order_details' => $orderDetails,
            'saved_path' => $savedPath,
        ];
    }

    /**
     * Sync price / stock from Shein API into shein_pricing_prices (replaces sheet upload).
     *
     * @return array{success: bool, message: string, updated: int, skipped: int}
     */
    public function syncPricingPricesFromApi(): array
    {
        $products = $this->listAllProducts();

        return $this->syncPricingPricesFromApiUsingProducts($products);
    }

    /**
     * Sync orders from Shein API into shein_daily_data (L30) or shein_daily_data_l60.
     * Replaces Seller Hub sheet upload.
     *
     * @param  'l30'|'l60'  $target
     * @return array{success: bool, message: string, imported: int, order_count: int, days: int}
     */
    public function syncOrdersToDailyData(int $days = 30, string $target = 'l30'): array
    {
        $target = strtolower($target) === 'l60' ? 'l60' : 'l30';
        $days = $target === 'l60' ? max(1, min(60, $days ?: 60)) : max(1, min(30, $days ?: 30));

        $modelClass = $target === 'l60' ? SheinDailyDataL60::class : SheinDailyData::class;
        $table = (new $modelClass)->getTable();
        if (! Schema::hasTable($table)) {
            return [
                'success' => false,
                'message' => "{$table} table missing — run migrations.",
                'imported' => 0,
                'order_count' => 0,
                'days' => $days,
            ];
        }

        // Pull both new + updated orders so we don't miss modifications in-window.
        $rawNew = $this->fetchOrdersRaw($days, 1, true, true);
        $rawUpd = $this->fetchOrdersRaw($days, 2, true, false);
        $detailsByNo = [];
        foreach (array_merge($rawNew['order_details'] ?? [], $rawUpd['order_details'] ?? []) as $od) {
            $no = (string) ($od['orderNo'] ?? '');
            if ($no !== '') {
                $detailsByNo[$no] = $od;
            }
        }

        $rows = [];
        foreach ($detailsByNo as $order) {
            $mapped = $this->mapSheinApiOrderToDailyRows($order);
            foreach ($mapped as $row) {
                $rows[] = $row;
            }
        }

        DB::transaction(function () use ($modelClass, $rows) {
            $modelClass::query()->delete();
            foreach (array_chunk($rows, 200) as $chunk) {
                foreach ($chunk as $row) {
                    $modelClass::create($row);
                }
            }
        });

        return [
            'success' => true,
            'message' => 'Synced '.count($rows)." order line(s) from Shein API into {$table} ({$days}d).",
            'imported' => count($rows),
            'order_count' => count($detailsByNo),
            'days' => $days,
        ];
    }

    /**
     * Map one order-detail payload into shein_daily_data row(s) (one per goods line).
     *
     * @param  array<string, mixed>  $order
     * @return array<int, array<string, mixed>>
     */
    public function mapSheinApiOrderToDailyRows(array $order): array
    {
        $orderNo = trim((string) ($order['orderNo'] ?? ''));
        if ($orderNo === '') {
            return [];
        }

        $statusCode = $order['orderStatus'] ?? null;
        $statusMap = [
            1 => 'Pending',
            2 => 'To Be Shipped',
            3 => 'To Be Shipped by SHEIN',
            4 => 'Shipped',
            5 => 'Received',
            6 => 'Refund',
            7 => 'To Be Collected by SHEIN',
        ];
        $status = $statusMap[(int) $statusCode] ?? (string) ($statusCode ?? '');

        $orderTime = $order['orderTime'] ?? $order['paymentTime'] ?? null;
        $processedOn = null;
        if (is_string($orderTime) && $orderTime !== '') {
            try {
                $processedOn = \Carbon\Carbon::parse($orderTime)->format('Y-m-d H:i:s');
            } catch (\Throwable $e) {
                $processedOn = null;
            }
        }

        $currency = (string) ($order['saleCurrency'] ?? $order['orderCurrency'] ?? 'USD');
        $goodsList = is_array($order['orderGoodsInfoList'] ?? null) ? $order['orderGoodsInfoList'] : [];
        if ($goodsList === []) {
            return [[
                'order_type' => (string) ($order['orderType'] ?? null),
                'order_number' => $orderNo,
                'order_status' => $status,
                'seller_sku' => null,
                'shein_sku' => null,
                'skc' => null,
                'product_name' => null,
                'product_price' => null,
                'estimated_merchandise_revenue' => null,
                'commission' => isset($order['totalCommission']) ? (float) $order['totalCommission'] : null,
                'quantity' => 1,
                'seller_currency' => $currency,
                'order_processed_on' => $processedOn,
            ]];
        }

        $out = [];
        foreach ($goodsList as $goods) {
            if (! is_array($goods)) {
                continue;
            }
            $sellerSku = $this->resolveOrderGoodsSellerSku($goods);
            $price = isset($goods['sellerCurrencyPrice'])
                ? (float) $goods['sellerCurrencyPrice']
                : (isset($goods['orderCurrencyPrice']) ? (float) $goods['orderCurrencyPrice'] : 0.0);
            $qty = 1; // Shein goods lines are typically one unit each
            $out[] = [
                'order_type' => (string) ($order['orderType'] ?? null),
                'order_number' => $orderNo,
                'order_status' => $status,
                'seller_sku' => $sellerSku !== '' ? $sellerSku : null,
                'shein_sku' => trim((string) ($goods['skuCode'] ?? '')) ?: null,
                'skc' => trim((string) ($goods['skc'] ?? '')) ?: null,
                'item_id' => isset($goods['goodsId']) ? (string) $goods['goodsId'] : null,
                'product_name' => trim((string) ($goods['goodsTitle'] ?? '')) ?: null,
                'product_price' => $price,
                'estimated_merchandise_revenue' => $price * $qty,
                'commission' => isset($goods['commission']) ? (float) $goods['commission'] : null,
                'quantity' => $qty,
                'seller_currency' => (string) ($goods['saleCurrency'] ?? $currency),
                'order_processed_on' => $processedOn,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $goods
     */
    private function resolveOrderGoodsSellerSku(array $goods): string
    {
        $seller = trim((string) ($goods['sellerSku'] ?? ''));
        if ($this->isUsableSheinSellerSku($seller)) {
            return $seller;
        }

        $goodsSn = trim((string) ($goods['goodsSn'] ?? ''));
        if ($this->isUsableSheinSellerSku($goodsSn)) {
            return $goodsSn;
        }

        $code = trim((string) ($goods['skuCode'] ?? ''));
        if ($code !== '' && $this->metricsTableExists()) {
            try {
                $metric = SheinMetric::query()->where('shein_sku_code', $code)->first();
                if ($metric && $this->isUsableSheinSellerSku((string) $metric->sku)) {
                    return trim((string) $metric->sku);
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return $seller !== '' ? $seller : $goodsSn;
    }

    /**
     * Sync all product data to database
     * Updates: Price, Views, Rating, Inventory (+ pricing prices table)
     */
    public function syncAllProductData(): array
    {
        Log::info('Starting Shein Product Data Sync...');

        try {
            $result = $this->listAllProducts();
            $tableOk = $this->metricsTableExists();
            $pricing = $this->syncPricingPricesFromApiUsingProducts($result);

            return [
                'success' => true,
                'total_products' => count($result),
                'message' => $tableOk
                    ? 'Shein product data synced successfully.'
                    : 'Shein API data fetched; shein_metrics table missing — run migrations to persist rows.',
                'db_persisted' => $tableOk,
                'db_created' => $this->lastMetricCreated,
                'db_updated' => $this->lastMetricUpdated,
                'pricing_updated' => $pricing['updated'] ?? 0,
            ];
        } catch (\Throwable $e) {
            Log::error('Shein Sync Failed: '.$e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @return array{success: bool, message: string, updated: int, skipped: int}
     */
    private function syncPricingPricesFromApiUsingProducts(array $products): array
    {
        if (! Schema::hasTable('shein_pricing_prices')) {
            return ['success' => false, 'message' => 'shein_pricing_prices missing', 'updated' => 0, 'skipped' => 0];
        }

        $updated = 0;
        $skipped = 0;
        foreach ($products as $item) {
            $sku = trim((string) ($item['sku'] ?? ''));
            if ($sku === '') {
                $skipped++;

                continue;
            }
            $sale = isset($item['price']) ? (float) $item['price'] : 0.0;
            $retail = isset($item['retail_price']) ? (float) $item['retail_price'] : 0.0;
            $stock = (int) ($item['quantity'] ?? 0);
            SheinPricingPrice::updateOrCreate(
                ['sku' => $sku],
                [
                    'price' => max(0, $retail > 0 ? $retail : $sale),
                    'original_price' => max(0, $retail),
                    'special_offer_price' => max(0, $sale > 0 ? $sale : $retail),
                    'shein_stock' => max(0, $stock),
                ]
            );
            $updated++;
        }

        return [
            'success' => true,
            'message' => "Synced {$updated} SKU price/stock row(s) from Shein API.",
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    /**
     * Update product description / selling points via Shein product update API (full text, no truncation).
     *
     * @return array{success: bool, message: string}
     */
    public function updateBulletPoints(string $identifier, string $bulletPoints): array
    {
        $bulletPoints = trim($bulletPoints);
        if (trim($identifier) === '' || $bulletPoints === '') {
            return ['success' => false, 'message' => 'SKU (or spu) and bullet points are required.'];
        }

        $sku = $this->resolveSheinSellerSku($identifier);

        $openKeyId = config('services.shein.open_key_id');
        $secretKey = config('services.shein.secret_key');
        if (empty($openKeyId) || empty($secretKey)) {
            return ['success' => false, 'message' => 'Configure SHEIN_OPEN_KEY_ID and SHEIN_SECRET_KEY in .env.'];
        }

        $endpoint = (string) config(
            'services.shein.product_update_path',
            '/open-api/openapi-business-backend/product/update'
        );
        $url = $this->baseUrl.$endpoint;

        $metric = $this->safeSheinMetricFindBySku($sku);
        $productName = $metric && ! empty($metric->product_name) ? $metric->product_name : $sku;

        $payload = [
            'skuCode' => $sku,
            'productName' => $productName,
            'productDesc' => $bulletPoints,
        ];

        if ($metric && ! empty($metric->spu_name)) {
            $payload['spuCode'] = $metric->spu_name;
        }

        try {
            $response = Http::withoutVerifying()
                ->timeout(60)
                ->withHeaders($this->buildSheinAuthHeaders($endpoint))
                ->post($url, $payload);

            $json = is_array($response->json()) ? $response->json() : null;

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'message' => 'HTTP '.$response->status().': '.mb_substr((string) $response->body(), 0, 500),
                ];
            }

            if ($this->sheinResponseIndicatesSuccess($json)) {
                Log::info('Shein updateBulletPoints success', ['sku' => $sku]);

                return ['success' => true, 'message' => 'Shein product description updated.'];
            }

            return ['success' => false, 'message' => $this->sheinExtractErrorMessage($json)];
        } catch (\Throwable $e) {
            Log::error('Shein updateBulletPoints exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function updateProductDescription(string $identifier, string $description): array
    {
        return $this->updateBulletPoints($identifier, $description);
    }

    /**
     * @param  list<string>  $videos
     * @return array{success: bool, message: string, normalized_urls?: list<string>}
     */
    public function updateVideos(string $identifier, array $videos, string $mode = 'replace'): array
    {
        $videos = array_slice(array_values(array_unique(array_filter(array_map('trim', $videos), fn ($v) => $v !== ''))), 0, 5);
        if (trim($identifier) === '' || $videos === []) {
            return ['success' => false, 'message' => 'SKU (or spu) and at least one video URL are required.'];
        }

        foreach ($videos as $url) {
            if (! preg_match('#^https?://#i', $url)) {
                return ['success' => false, 'message' => 'Invalid video URL (must be http/https).'];
            }
        }

        $sku = $this->resolveSheinSellerSku($identifier);
        $openKeyId = config('services.shein.open_key_id');
        $secretKey = config('services.shein.secret_key');
        if (empty($openKeyId) || empty($secretKey)) {
            return ['success' => false, 'message' => 'Configure SHEIN_OPEN_KEY_ID and SHEIN_SECRET_KEY in .env.'];
        }

        $endpoint = (string) config('services.shein.product_update_path', '/open-api/openapi-business-backend/product/update');
        $url = $this->baseUrl.$endpoint;
        $timestamp = (int) round(microtime(true) * 1000);
        $random = Str::random(5);
        $signature = $this->generateSheinSignature($endpoint, $timestamp, $random);
        $metric = $this->safeSheinMetricFindBySku($sku);
        $productName = $metric && ! empty($metric->product_name) ? $metric->product_name : $sku;

        $payloadAttempts = [
            ['skuCode' => $sku, 'productName' => $productName, 'videoUrl' => $videos[0], 'productVideoUrl' => $videos[0]],
            ['skuCode' => $sku, 'productName' => $productName, 'videoList' => array_map(fn ($v) => ['videoUrl' => $v], $videos)],
        ];
        if ($metric && ! empty($metric->spu_name)) {
            foreach ($payloadAttempts as &$payload) {
                $payload['spuCode'] = $metric->spu_name;
            }
            unset($payload);
        }

        $lastMessage = 'Shein video update failed.';
        foreach ($payloadAttempts as $payload) {
            try {
                $response = Http::withoutVerifying()->timeout(60)->withHeaders([
                    'Language' => 'en-us',
                    'x-lt-openKeyId' => $openKeyId,
                    'x-lt-timestamp' => (string) $timestamp,
                    'x-lt-signature' => $signature,
                    'Content-Type' => 'application/json',
                ])->post($url, $payload);

                $json = is_array($response->json()) ? $response->json() : null;
                if ($response->successful() && $this->sheinResponseIndicatesSuccess($json)) {
                    $this->saveVideoUrlsToMetricsRow('shein_metrics', $sku, $videos);

                    return ['success' => true, 'message' => 'Shein product video updated.', 'normalized_urls' => $videos];
                }
                $lastMessage = $this->sheinExtractErrorMessage($json) ?: ('HTTP '.$response->status());
            } catch (\Throwable $e) {
                $lastMessage = $e->getMessage();
            }
        }

        return ['success' => false, 'message' => $lastMessage];
    }

    /**
     * @param  list<string>  $images
     * @return array{success: bool, message: string, normalized_urls?: list<string>}
     */
    public function updateImages(string $identifier, array $images, string $mode = 'replace'): array
    {
        $images = array_slice(array_values(array_unique(array_filter(array_map('trim', $images), fn ($v) => $v !== ''))), 0, 12);
        if (trim($identifier) === '' || $images === []) {
            return ['success' => false, 'message' => 'SKU (or spu) and at least one image URL are required.'];
        }

        foreach ($images as $url) {
            if (! preg_match('#^https?://#i', $url)) {
                return ['success' => false, 'message' => 'Invalid image URL (must be http/https).'];
            }
        }

        $sku = $this->resolveSheinSellerSku($identifier);
        $openKeyId = config('services.shein.open_key_id');
        $secretKey = config('services.shein.secret_key');
        if (empty($openKeyId) || empty($secretKey)) {
            return ['success' => false, 'message' => 'Configure SHEIN_OPEN_KEY_ID and SHEIN_SECRET_KEY in .env.'];
        }

        $endpoint = (string) config('services.shein.product_update_path', '/open-api/openapi-business-backend/product/update');
        $url = $this->baseUrl.$endpoint;
        $timestamp = (int) round(microtime(true) * 1000);
        $random = Str::random(5);
        $signature = $this->generateSheinSignature($endpoint, $timestamp, $random);
        $metric = $this->safeSheinMetricFindBySku($sku);
        $productName = $metric && ! empty($metric->product_name) ? $metric->product_name : $sku;
        $imageList = array_map(fn ($imageUrl, $i) => [
            'imageUrl' => $imageUrl,
            'imageSort' => $i + 1,
            'imageType' => $i === 0 ? 1 : 2,
        ], $images, array_keys($images));

        $payloadAttempts = [
            ['skuCode' => $sku, 'productName' => $productName, 'imageList' => $imageList],
            ['skuCode' => $sku, 'productName' => $productName, 'mainImageUrl' => $images[0], 'imageUrls' => $images],
        ];
        if ($metric && ! empty($metric->spu_name)) {
            foreach ($payloadAttempts as &$payload) {
                $payload['spuCode'] = $metric->spu_name;
            }
            unset($payload);
        }

        $lastMessage = 'Shein image update failed.';
        foreach ($payloadAttempts as $payload) {
            try {
                $response = Http::withoutVerifying()->timeout(60)->withHeaders([
                    'Language' => 'en-us',
                    'x-lt-openKeyId' => $openKeyId,
                    'x-lt-timestamp' => (string) $timestamp,
                    'x-lt-signature' => $signature,
                    'Content-Type' => 'application/json',
                ])->post($url, $payload);

                $json = is_array($response->json()) ? $response->json() : null;
                if ($response->successful() && $this->sheinResponseIndicatesSuccess($json)) {
                    $this->saveImageUrlsToMetricsRow('shein_metrics', $sku, $images);

                    return ['success' => true, 'message' => 'Shein product images updated.', 'normalized_urls' => $images];
                }
                $lastMessage = $this->sheinExtractErrorMessage($json) ?: ('HTTP '.$response->status());
            } catch (\Throwable $e) {
                $lastMessage = $e->getMessage();
            }
        }

        return ['success' => false, 'message' => $lastMessage];
    }
}
