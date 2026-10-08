<?php

namespace App\Services;

use App\Models\Temu3Metric;
use App\Models\Temu3Pricing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class Temu3ApiService extends TemuApiService
{
    protected function openApiRouterUrl(): string
    {
        return rtrim((string) config('services.temu3.openapi_router_url', 'https://openapi-b-us.temu.com/openapi/router'), '/');
    }

    protected function temuServiceConfigKey(): string
    {
        return 'temu3';
    }

    protected function imageMetricsTable(): string
    {
        return 'temu3_metrics';
    }

    /**
     * Sign with Temu 3 credentials only (never TEMU_*).
     */
    protected function generateSignValue($requestBody)
    {
        $appKey = trim((string) (config('services.temu3.app_key') ?? ''));
        $appSecret = trim((string) (config('services.temu3.secret_key') ?? ''));
        $accessToken = trim((string) (config('services.temu3.access_token') ?? ''));

        $timestamp = time();
        $params = [
            'access_token' => $accessToken,
            'app_key' => $appKey,
            'timestamp' => (string) $timestamp,
            'data_type' => 'JSON',
        ];

        $signParams = array_merge($params, $requestBody);
        ksort($signParams);

        $temp = '';
        foreach ($signParams as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            $temp .= $key.(string) $value;
        }

        $signStr = $appSecret.$temp.$appSecret;
        $params['sign'] = strtoupper(md5($signStr));

        return array_merge($params, $requestBody);
    }

    public function isConfigured(): bool
    {
        $appKey = trim((string) (config('services.temu3.app_key') ?? ''));
        $secret = trim((string) (config('services.temu3.secret_key') ?? ''));
        $token = trim((string) (config('services.temu3.access_token') ?? ''));

        return $appKey !== '' && $secret !== '' && $token !== '';
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Temu 3 API credentials missing. Set TEMU3_APP_KEY, TEMU3_SECRET_KEY, and TEMU3_ACCESS_TOKEN in .env.',
            ];
        }

        $requestBody = [
            'type' => 'bg.local.goods.list.query',
            'goodsSearchType' => 1,
            'goodsStatusFilterType' => 1,
            'pageSize' => 5,
            'pageNumber' => 1,
        ];

        try {
            $signedRequest = $this->generateSignValue($requestBody);
            $url = rtrim((string) config('services.temu3.openapi_router_url', 'https://openapi-b-us.temu.com/openapi/router'), '/');
            $request = Http::withHeaders(['Content-Type' => 'application/json']);
            if (config('filesystems.default') === 'local') {
                $request = $request->withoutVerifying();
            }
            $response = $request->timeout(30)->post($url, $signedRequest);
            $data = $response->json() ?? [];

            if ($response->successful() && ($data['success'] ?? false)) {
                $items = $data['result']['goodsList'] ?? [];
                $total = (int) ($data['result']['total'] ?? count($items));

                return [
                    'success' => true,
                    'message' => 'Connected to Temu 3 Open API. Sample page returned '.count($items)." item(s); total reported: {$total}.",
                    'sample_count' => count($items),
                ];
            }

            $errorMsg = (string) ($data['errorMsg'] ?? $response->body() ?: 'Unknown error');

            return [
                'success' => false,
                'message' => trim(($data['errorCode'] ?? $response->status()).': '.$errorMsg),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Temu 3 connection test failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Verify Local Price Management permission via bg.local.goods.sku.list.price.query.
     * Uses one sku_id + goods_id pair from temu3_metrics when available.
     */
    public function testPriceAccess(): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Temu 3 API credentials missing. Set TEMU3_ACCESS_TOKEN (after authorizing Inventory Temu 3) in .env.',
            ];
        }

        $sample = Temu3Metric::query()
            ->whereNotNull('sku_id')->where('sku_id', '!=', '')
            ->whereNotNull('goods_id')->where('goods_id', '!=', '')
            ->first(['sku', 'sku_id', 'goods_id']);

        if (! $sample) {
            return [
                'success' => false,
                'message' => 'No Temu 3 SKUs with goods_id + sku_id yet. Run app:fetch-temu3-metrics --only=skus first, then retest price.',
            ];
        }

        $requestBody = [
            'type' => 'bg.local.goods.sku.list.price.query',
            'querySupplierPriceBaseList' => [[
                'goodsId' => (int) $sample->goods_id,
                'skuIdList' => [(int) $sample->sku_id],
            ]],
            'language' => 'en',
        ];

        try {
            $signedRequest = $this->generateSignValue($requestBody);
            $url = $this->openApiRouterUrl();
            $request = Http::withHeaders(['Content-Type' => 'application/json']);
            if (config('filesystems.default') === 'local') {
                $request = $request->withoutVerifying();
            }
            $response = $request->timeout(45)->post($url, $signedRequest);
            $data = $response->json() ?? [];

            if ($response->successful() && ($data['success'] ?? false)) {
                $list = $data['result']['openapiGoodsSupplierPriceDTOList']
                    ?? $data['result']['skuPriceInfoList']
                    ?? [];

                return [
                    'success' => true,
                    'message' => 'Price API OK (Local Price Management). Sample SKU '.$sample->sku.' returned '.count($list).' price block(s).',
                    'sku' => $sample->sku,
                ];
            }

            $errorCode = (string) ($data['errorCode'] ?? $response->status());
            $errorMsg = (string) ($data['errorMsg'] ?? $response->body() ?: 'Unknown error');
            $hint = '';
            $lower = strtolower($errorMsg);
            if (str_contains($lower, 'permission') || str_contains($lower, 'auth') || $errorCode === '7000019') {
                $hint = ' Re-authorize Inventory Temu 3 with Local Price Management, then paste the new access token.';
            }

            return [
                'success' => false,
                'message' => trim($errorCode.': '.$errorMsg).$hint,
                'error_code' => $errorCode,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Temu 3 price API test failed: '.$e->getMessage(),
            ];
        }
    }

    protected function resolveTemuGoodsAndSku(string $identifier): array
    {
        $id = trim($identifier);
        if ($id === '') {
            return ['sku' => '', 'goods_id' => null];
        }

        $row = Temu3Metric::query()
            ->where('sku', $id)
            ->orWhere('sku', strtoupper($id))
            ->orWhere('sku', strtolower($id))
            ->first();

        if (! $row) {
            $row = Temu3Metric::query()
                ->where('goods_id', $id)
                ->orWhere('sku_id', $id)
                ->first();
        }

        if ($row) {
            return [
                'sku' => trim((string) ($row->sku ?: $id)),
                'goods_id' => trim((string) ($row->goods_id ?? '')) ?: null,
            ];
        }

        return ['sku' => $id, 'goods_id' => null];
    }

    public function getProductPrice(string $sku): ?float
    {
        $price = Temu3Metric::where('sku', trim($sku))->value('base_price');
        if ($price !== null && (float) $price > 0) {
            return (float) $price;
        }

        return null;
    }

    public function getGoodsIdBySku(string $sku): ?string
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }

        $goodsId = Temu3Metric::where('sku', $sku)
            ->orWhere('sku', strtoupper($sku))
            ->orWhere('sku', strtolower($sku))
            ->value('goods_id');
        if ($goodsId !== null && $goodsId !== '') {
            return (string) $goodsId;
        }

        $fromPricing = Temu3Pricing::query()
            ->where('sku', $sku)
            ->orWhere('sku', strtoupper($sku))
            ->orWhere('sku', strtolower($sku))
            ->value('goods_id');
        if ($fromPricing !== null && $fromPricing !== '') {
            return (string) $fromPricing;
        }

        return $this->findTemuGoodsIdBySkuViaApi($sku);
    }

    public function getSkuIdBySku(string $sku): ?string
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }

        $skuId = Temu3Metric::where('sku', $sku)
            ->orWhere('sku', strtoupper($sku))
            ->orWhere('sku', strtolower($sku))
            ->value('sku_id');
        if ($skuId !== null && $skuId !== '') {
            return (string) $skuId;
        }

        $fromPricing = Temu3Pricing::query()
            ->where('sku', $sku)
            ->orWhere('sku', strtoupper($sku))
            ->orWhere('sku', strtolower($sku))
            ->value('sku_id');
        if ($fromPricing !== null && $fromPricing !== '') {
            return (string) $fromPricing;
        }

        return $this->findTemuSkuIdBySkuViaApi($sku);
    }

    /**
     * Title-only update. Do not send empty skuList images/dims — Temu 3 rejects
     * those with "Some SKU specifications are empty".
     *
     * @return array{success: bool, message: string}
     */
    public function updateTitle(string $sku, string $title): array
    {
        $sku = trim($sku);
        $title = trim($title);
        if ($sku === '' || $title === '') {
            return ['success' => false, 'message' => 'SKU and title are required.'];
        }

        $goodsId = $this->getGoodsIdBySku($sku);
        if (! $goodsId) {
            return [
                'success' => false,
                'message' => 'Temu 3 goodsId not found for this SKU. Sync Temu 3 listings first.',
            ];
        }

        $goodsBasicField = config('services.temu3.goods_basic_field', config('services.temu.goods_basic_field', 'goodsBasic'));
        $skuListField = config('services.temu3.update_sku_list_field', config('services.temu.update_sku_list_field', 'skuList'));
        $skuIdField = config('services.temu3.sku_id_field', config('services.temu.sku_id_field', 'skuId'));
        $skuCodeField = config('services.temu3.sku_code_field', config('services.temu.sku_code_field', 'outSkuSn'));
        $apiType = config('services.temu3.goods_update_type', config('services.temu.goods_update_type', 'bg.local.goods.partial.update'));

        $skuId = $this->getSkuIdBySku($sku);
        $liveEntries = $this->skuEntriesFromLivePackage($sku, $skuId, (string) $goodsId, $skuIdField, $skuCodeField, 0);
        $inchEntries = $this->skuEntriesFromLivePackage($sku, $skuId, (string) $goodsId, $skuIdField, $skuCodeField, 2);

        // Title-only first. If Temu re-validates package units, send live packageInfo
        // with integer codes (1=cm/g, 2=inch/lb). Never send the string "cm".
        $attempts = [];
        $attempts[] = [
            'type' => $apiType,
            'goodsId' => (int) $goodsId,
            $goodsBasicField => ['goodsName' => $title],
        ];
        $attempts[] = [
            'type' => $apiType,
            'goodsId' => (int) $goodsId,
            $goodsBasicField => ['goodsName' => $title],
            $skuListField => $liveEntries,
        ];
        $attempts[] = [
            'type' => $apiType,
            'goodsId' => (int) $goodsId,
            $goodsBasicField => ['goodsName' => $title],
            $skuListField => $inchEntries,
        ];

        $lastError = 'Temu 3 title update failed.';
        $attemptGoodsId = (string) $goodsId;
        for ($pass = 0; $pass < 2; $pass++) {
            if ($pass === 1) {
                if (! $this->temuMallGoodsMismatch($lastError)) {
                    break;
                }
                $freshGoodsId = $this->replaceGoodsIdAfterMallMismatch($sku, $attemptGoodsId);
                if ($freshGoodsId === null || $freshGoodsId === '' || $freshGoodsId === $attemptGoodsId) {
                    $lastError .= ' Stored goods id '.$attemptGoodsId.' is not in this Temu 3 mall. Re-sync Temu 3 listings.';
                    break;
                }
                $attemptGoodsId = $freshGoodsId;
                foreach ($attempts as &$attemptBody) {
                    $attemptBody['goodsId'] = (int) $freshGoodsId;
                }
                unset($attemptBody);
            }
        foreach ($attempts as $i => $requestBody) {
            try {
                $data = $this->postTemuRequest($requestBody);
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                Log::warning('Temu3 updateTitle request failed', [
                    'sku' => $sku,
                    'attempt' => $i + 1,
                    'error' => $lastError,
                ]);
                continue;
            }

            $outcome = $this->temuWriteOutcome($data, "Temu 3 title updated for SKU: {$sku}.");
            if ($outcome['success']) {
                Log::info('Temu3 title updated', ['sku' => $sku, 'goodsId' => $attemptGoodsId, 'attempt' => $i + 1, 'review' => $outcome['review']]);

                return ['success' => true, 'message' => $outcome['message']];
            }

            $lastError = $outcome['message'];
            Log::warning('Temu3 updateTitle rejected', [
                'sku' => $sku,
                'goodsId' => $attemptGoodsId,
                'attempt' => $i + 1,
                'error' => $lastError,
            ]);
        }
        }

        return ['success' => false, 'message' => $lastError];
    }

    /**
     * Live SKU packageInfo only (no images/price). Units are Temu integer codes.
     *
     * @return list<array<string, mixed>>
     */
    private function skuEntriesFromLivePackage(
        string $sku,
        ?string $skuId,
        string $goodsId,
        string $skuIdField,
        string $skuCodeField,
        int $forceVolumeUnit = 0,
    ): array {
        $liveRows = $this->fetchTemu3SkuSpecRows($goodsId);
        if ($liveRows === []) {
            $liveRows = [[
                'skuId' => $skuId,
                'outSkuSn' => $sku,
            ]];
        }

        $entries = [];
        foreach ($liveRows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $pkg = is_array($row['packageInfo'] ?? null) ? $row['packageInfo'] : [];
            $rowSkuId = trim((string) ($row['skuId'] ?? $row['sku_id'] ?? ''));
            $rowSn = trim((string) ($row['outSkuSn'] ?? $row['sku'] ?? $row['extCode'] ?? ''));
            $isTarget = ($skuId !== null && $skuId !== '' && $rowSkuId === (string) $skuId)
                || ($rowSn !== '' && strcasecmp($rowSn, $sku) === 0)
                || count($liveRows) === 1;

            $volumeUnit = $forceVolumeUnit > 0
                ? $forceVolumeUnit
                : $this->temuVolumeUnitCode(
                    $this->temuFirstNonNullUnit($pkg['volumeUnit'] ?? null, $row['volumeUnit'] ?? null, $row['lenUnit'] ?? null, 1)
                );
            $weightUnit = $forceVolumeUnit === 2
                ? 2
                : $this->temuWeightUnitCode(
                    $this->temuFirstNonNullUnit($pkg['weightUnit'] ?? null, $row['weightUnit'] ?? null, 1)
                );

            $entry = [
                $skuCodeField => $rowSn !== '' ? $rowSn : $sku,
                'packageInfo' => [
                    'weight' => $this->nonEmptySpec($pkg['weight'] ?? $row['weight'] ?? null, null, '1'),
                    'length' => $this->nonEmptySpec($pkg['length'] ?? $row['length'] ?? null, null, '1'),
                    'width' => $this->nonEmptySpec($pkg['width'] ?? $row['width'] ?? null, null, '1'),
                    'height' => $this->nonEmptySpec($pkg['height'] ?? $row['height'] ?? null, null, '1'),
                    'weightUnit' => $weightUnit,
                    'volumeUnit' => $volumeUnit,
                ],
            ];
            $id = $rowSkuId !== '' ? $rowSkuId : ($isTarget ? $skuId : null);
            if ($id !== null && $id !== '') {
                $entry[$skuIdField] = (int) $id;
            }
            $entries[] = $entry;
        }

        return $entries !== [] ? $entries : [[
            $skuCodeField => $sku,
            'packageInfo' => [
                'weight' => '1',
                'length' => '1',
                'width' => '1',
                'height' => '1',
                'weightUnit' => $forceVolumeUnit === 2 ? 2 : 1,
                'volumeUnit' => $forceVolumeUnit > 0 ? $forceVolumeUnit : 1,
            ],
        ]];
    }

    private function nonEmptySpec(mixed $live, mixed $fallback, string $default): string
    {
        foreach ([$live, $fallback, $default] as $value) {
            $text = trim((string) ($value ?? ''));
            if ($text !== '' && strtolower($text) !== 'null') {
                return $text;
            }
        }

        return $default;
    }

    private function temuFirstNonNullUnit(mixed ...$values): string
    {
        foreach ($values as $value) {
            if ($value === null || $value === false) {
                continue;
            }
            $text = trim((string) $value);
            if ($text !== '' && strtolower($text) !== 'null') {
                return $text;
            }
        }

        return '';
    }

    private function normalizeTemuVolumeUnit(mixed $value): string
    {
        $v = strtolower(trim((string) ($value ?? '')));
        if (in_array($v, ['2', 'inch', 'in', 'inches'], true)) {
            return 'inch';
        }

        return 'cm';
    }

    private function normalizeTemuWeightUnit(mixed $value): string
    {
        $v = strtolower(trim((string) ($value ?? '')));
        if (in_array($v, ['2', 'lb', 'lbs', 'pound', 'pounds'], true)) {
            return 'lb';
        }

        return 'g';
    }

    private function temuVolumeUnitCode(mixed $value): int
    {
        return $this->normalizeTemuVolumeUnit($value) === 'inch' ? 2 : 1;
    }

    private function temuWeightUnitCode(mixed $value): int
    {
        return $this->normalizeTemuWeightUnit($value) === 'lb' ? 2 : 1;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchTemu3SkuSpecRows(string $goodsId): array
    {
        if ($goodsId === '') {
            return [];
        }

        foreach (['bg.local.goods.detail.query', 'temu.local.goods.detail.retrieve'] as $type) {
            try {
                $data = $this->postTemuRequest([
                    'type' => $type,
                    'goodsId' => (int) $goodsId,
                ]);
            } catch (\Throwable) {
                continue;
            }
            if (! ($data['success'] ?? false)) {
                continue;
            }
            $result = is_array($data['result'] ?? null) ? $data['result'] : [];
            $skuList = $result['skuList'] ?? $result['skuInfoList'] ?? [];
            if (! is_array($skuList) || $skuList === []) {
                continue;
            }

            $rows = [];
            foreach ($skuList as $row) {
                if (is_array($row)) {
                    $rows[] = $row;
                }
            }
            if ($rows !== []) {
                return $rows;
            }
        }

        return [];
    }

    private function isTemuTitleImageError(string $message): bool
    {
        $m = strtolower($message);

        return str_contains($m, 'upload image')
            || str_contains($m, 'image url')
            || str_contains($m, 'sku specifications are empty')
            || str_contains($m, 'add at least one sku');
    }

    /**
     * @return list<string>
     */
    private function existingListingImageUrls(string $sku, string $goodsId, bool $allowApi = false): array
    {
        $urls = [];
        $seen = [];
        $push = function (string $url) use (&$urls, &$seen): void {
            $url = trim($url);
            if ($url === '' || ! preg_match('#^https?://#i', $url) || isset($seen[$url])) {
                return;
            }
            $seen[$url] = true;
            $urls[] = $url;
        };

        try {
            $row = Temu3Metric::query()
                ->where('sku', $sku)
                ->orWhere('sku', strtoupper($sku))
                ->orWhere('sku', strtolower($sku))
                ->first(['image_urls', 'image_master_json']);
            foreach ([$row?->image_urls, $row?->image_master_json] as $raw) {
                foreach ($this->flattenTemuImageValues($raw) as $url) {
                    $push($url);
                }
            }
        } catch (\Throwable) {
        }

        foreach ($this->getProductImages($sku) as $url) {
            $push((string) $url);
        }

        if ($urls === [] && $allowApi && $goodsId !== '') {
            foreach ($this->flattenTemuImageValues($this->fetchTemu3GoodsImages($goodsId, $sku)) as $url) {
                $push($url);
            }
        }

        return $urls;
    }

    /**
     * @return list<string>
     */
    private function flattenTemuImageValues(mixed $raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : (preg_split('/\s+/', $raw) ?: []);
        }
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        $walk = function ($node) use (&$walk, &$out): void {
            if (is_string($node) && preg_match('#^https?://#i', trim($node))) {
                $out[] = trim($node);

                return;
            }
            if (! is_array($node)) {
                return;
            }
            foreach (['url', 'imgUrl', 'imageUrl', 'image_url', 'picUrl'] as $key) {
                if (! empty($node[$key]) && is_string($node[$key])) {
                    $out[] = trim($node[$key]);
                }
            }
            foreach ($node as $v) {
                $walk($v);
            }
        };
        $walk($raw);

        return $out;
    }

    /**
     * @return list<string>
     */
    private function fetchTemu3GoodsImages(string $goodsId, string $sku): array
    {
        foreach (['bg.local.goods.detail.query', 'temu.local.goods.detail.retrieve'] as $type) {
            try {
                $data = $this->postTemuRequest([
                    'type' => $type,
                    'goodsId' => (int) $goodsId,
                ]);
            } catch (\Throwable) {
                continue;
            }
            if (! ($data['success'] ?? false)) {
                continue;
            }
            $result = is_array($data['result'] ?? null) ? $data['result'] : [];
            $found = $this->flattenTemuImageValues($result);
            if ($found !== []) {
                return $found;
            }
        }

        return [];
    }

    protected function persistTemuMapping(string $sku, ?string $goodsId, ?string $skuId): void
    {
        $sku = trim($sku);
        if ($sku === '') {
            return;
        }

        try {
            $update = [];
            if ($goodsId !== null && $goodsId !== '') {
                $update['goods_id'] = $goodsId;
            }
            if ($skuId !== null && $skuId !== '') {
                $update['sku_id'] = $skuId;
            }
            if ($update === []) {
                return;
            }

            Temu3Metric::updateOrCreate(['sku' => $sku], $update);

            if (Schema::hasTable('temu3_pricing')) {
                Temu3Pricing::updateOrCreate(['sku' => $sku], $update);
            }
        } catch (\Throwable $e) {
            Log::warning('Temu3 persistTemuMapping failed', [
                'sku' => $sku,
                'goods_id' => $goodsId,
                'sku_id' => $skuId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function persistNewListing(string $sku, string $goodsId, ?string $skuId = null): void
    {
        $this->persistTemuMapping($sku, $goodsId, $skuId);
    }

    protected function replaceGoodsIdAfterMallMismatch(string $sku, string $rejectedGoodsId): ?string
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }

        try {
            Temu3Metric::query()
                ->where('sku', $sku)
                ->orWhere('sku', strtoupper($sku))
                ->orWhere('sku', strtolower($sku))
                ->update(['goods_id' => null, 'sku_id' => null]);
            if (Schema::hasTable('temu3_pricing')) {
                Temu3Pricing::query()
                    ->where('sku', $sku)
                    ->orWhere('sku', strtoupper($sku))
                    ->orWhere('sku', strtolower($sku))
                    ->update(['goods_id' => null, 'sku_id' => null]);
            }
        } catch (\Throwable $e) {
            Log::warning('Temu3 could not clear a mismatched goods id', [
                'sku' => $sku,
                'goods_id' => $rejectedGoodsId,
                'error' => $e->getMessage(),
            ]);
        }

        $fresh = $this->findTemuGoodsIdBySkuViaApi($sku);
        if ($fresh === null || $fresh === '' || $fresh === $rejectedGoodsId) {
            return null;
        }

        return $fresh;
    }

    /**
     * Persist stock to temu3_metrics only (never temu_metrics / inventory_temu).
     *
     * @param  array<int, array<string, mixed>>  $goodsList
     */
    public function persistGoodsListInventory(array $goodsList): int
    {
        $updated = 0;
        if (! Schema::hasColumn('temu3_metrics', 'quantity')) {
            return 0;
        }

        foreach ($goodsList as $titem) {
            $goodsQty = (int) ($titem['quantity'] ?? 0);
            $goodsId = isset($titem['goodsId']) ? (string) $titem['goodsId'] : '';
            $skuTargets = [];
            $skuIdQty = [];

            foreach ($titem['outSkuSnList'] ?? [] as $outSku) {
                $outSku = trim((string) $outSku);
                if ($outSku !== '') {
                    $skuTargets[$outSku] = $goodsQty;
                }
            }

            foreach ($titem['skuInfoList'] ?? [] as $skuInfo) {
                $skuQty = $goodsQty;
                foreach (['stock', 'quantity', 'skuStockQuantity', 'virtualStock'] as $stockKey) {
                    if (isset($skuInfo[$stockKey]) && is_numeric($skuInfo[$stockKey])) {
                        $skuQty = (int) $skuInfo[$stockKey];
                        break;
                    }
                }

                foreach (['outSkuSn', 'skuSn', 'extCode'] as $key) {
                    $candidate = trim((string) ($skuInfo[$key] ?? ''));
                    if ($candidate !== '') {
                        $skuTargets[$candidate] = $skuQty;
                    }
                }

                $skuId = isset($skuInfo['skuId']) ? (string) $skuInfo['skuId'] : '';
                if ($skuId !== '') {
                    $skuIdQty[$skuId] = $skuQty;
                }
            }

            $outGoodsSn = trim((string) ($titem['outGoodsSn'] ?? ''));
            if ($outGoodsSn !== '' && $skuTargets === []) {
                $skuTargets[$outGoodsSn] = $goodsQty;
            }

            foreach ($skuIdQty as $skuId => $qty) {
                $updated += Temu3Metric::where('sku_id', $skuId)->update(['quantity' => $qty]);
                if (Schema::hasTable('temu3_pricing')) {
                    Temu3Pricing::where('sku_id', $skuId)->update(['quantity' => $qty]);
                }
            }

            foreach ($skuTargets as $sku => $qty) {
                $updated += Temu3Metric::where('sku', $sku)->update(['quantity' => $qty]);
                $updated += Temu3Metric::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
                    ->where('quantity', '!=', $qty)
                    ->update(['quantity' => $qty]);
                if (Schema::hasTable('temu3_pricing')) {
                    Temu3Pricing::where('sku', $sku)->update(['quantity' => $qty]);
                }
            }

            if ($goodsId !== '' && $goodsQty >= 0) {
                $updated += Temu3Metric::where('goods_id', $goodsId)
                    ->where(function ($q) {
                        $q->whereNull('quantity')->orWhere('quantity', 0);
                    })
                    ->update(['quantity' => $goodsQty]);
            }
        }

        Log::info('Temu3 inventory persisted to temu3_metrics', [
            'goods' => count($goodsList),
            'metric_updates' => $updated,
        ]);

        return $updated;
    }

    public function syncSkuListStock(): int
    {
        if (! Schema::hasColumn('temu3_metrics', 'quantity')) {
            return 0;
        }

        $pageNumber = 1;
        $pageSize = 100;
        $totalPages = null;
        $updated = 0;
        $url = $this->openApiRouterUrl();

        Log::info('======================= Started Temu3 SKU Stock Sync =======================');

        do {
            $requestBody = [
                'type' => 'bg.local.goods.sku.list.query',
                'pageSize' => $pageSize,
                'pageNumber' => $pageNumber,
                'skuSearchType' => 2,
            ];

            $signedRequest = $this->generateSignValue($requestBody);
            $request = Http::withHeaders(['Content-Type' => 'application/json']);
            if (config('filesystems.default') === 'local') {
                $request = $request->withoutVerifying();
            }

            try {
                $response = $request->post($url, $signedRequest);
            } catch (\Exception $e) {
                Log::error('Temu3 SKU stock sync HTTP exception page '.$pageNumber.': '.$e->getMessage());
                break;
            }

            if ($response->failed()) {
                Log::error('Temu3 SKU stock sync failed page '.$pageNumber, [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                break;
            }

            $data = $response->json();
            if (! ($data['success'] ?? false)) {
                Log::warning('Temu3 SKU stock sync API error page '.$pageNumber.': '.($data['errorMsg'] ?? 'Unknown'));
                break;
            }

            $result = $data['result'] ?? [];
            $items = $result['skuList'] ?? [];
            if ($items === []) {
                break;
            }

            foreach ($items as $item) {
                $qty = $item['stock'] ?? $item['quantity'] ?? null;
                if ($qty === null || ! is_numeric($qty)) {
                    continue;
                }
                $qty = (int) $qty;

                $skuId = isset($item['skuId']) ? (string) $item['skuId'] : '';
                $outSkuSn = trim((string) ($item['outSkuSn'] ?? $item['skuSn'] ?? ''));

                if ($skuId !== '') {
                    $updated += Temu3Metric::where('sku_id', $skuId)->update(['quantity' => $qty]);
                    if (Schema::hasTable('temu3_pricing')) {
                        Temu3Pricing::where('sku_id', $skuId)->update(['quantity' => $qty]);
                    }
                }
                if ($outSkuSn !== '') {
                    $updated += Temu3Metric::where('sku', $outSkuSn)->update(['quantity' => $qty]);
                    $updated += Temu3Metric::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($outSkuSn)])
                        ->where('quantity', '!=', $qty)
                        ->update(['quantity' => $qty]);
                    if (Schema::hasTable('temu3_pricing')) {
                        Temu3Pricing::where('sku', $outSkuSn)->update(['quantity' => $qty]);
                    }
                }
            }

            if ($totalPages === null) {
                $total = (int) ($result['total'] ?? 0);
                $totalPages = $total > 0 ? (int) ceil($total / $pageSize) : $pageNumber;
            }

            $pageNumber++;
            if ($pageNumber > 1000) {
                break;
            }
        } while ($pageNumber <= ($totalPages ?? 1));

        Log::info('Temu3 SKU stock sync finished', ['updated' => $updated, 'pages' => $pageNumber - 1]);

        return $updated;
    }

    /**
     * Live per-SKU stock + status straight from Temu (bg.local.goods.sku.list.query, not-on-sale
     * then on-sale so an on-sale row wins). Nothing is written; the Marketplace Manager read-back
     * uses this to compare the real Temu qty instead of the last push target.
     *
     * @return array<string, array{sku: string, sku_id: string, goods_id: string, qty: int|null, status: string}> keyed by UPPER(seller SKU)
     */
    public function skuStockSnapshotFromApi(): array
    {
        $out = [];
        $url = $this->openApiRouterUrl();

        foreach ([3 => 'inactive', 2 => 'active'] as $skuSearchType => $status) {
            $pageNumber = 1;
            $pageSize = 100;
            $totalPages = null;

            do {
                $requestBody = [
                    'type' => 'bg.local.goods.sku.list.query',
                    'pageSize' => $pageSize,
                    'pageNumber' => $pageNumber,
                    'skuSearchType' => $skuSearchType,
                ];
                $request = Http::withHeaders(['Content-Type' => 'application/json'])->timeout(45);
                if (config('filesystems.default') === 'local') {
                    $request = $request->withoutVerifying();
                }
                try {
                    $response = $request->post($url, $this->generateSignValue($requestBody));
                } catch (\Throwable $e) {
                    Log::warning('Temu3 SKU stock snapshot HTTP exception', ['skuSearchType' => $skuSearchType, 'page' => $pageNumber, 'error' => $e->getMessage()]);
                    break;
                }
                $data = $response->json() ?? [];
                if (! ($data['success'] ?? false)) {
                    Log::info('Temu3 SKU stock snapshot page skipped', ['skuSearchType' => $skuSearchType, 'page' => $pageNumber, 'error' => $data['errorMsg'] ?? $response->status()]);
                    break;
                }
                $result = $data['result'] ?? [];
                $items = $result['skuList'] ?? [];
                if (! is_array($items) || $items === []) {
                    break;
                }
                foreach ($items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    // Seller SKU first: skuSn is Temu's own code and does not match Shopify.
                    $sku = trim((string) ($item['outSkuSn'] ?? $item['sku'] ?? $item['skuSn'] ?? ''));
                    if ($sku === '') {
                        continue;
                    }
                    $qty = null;
                    foreach (['stock', 'quantity', 'skuStockQuantity', 'virtualStock'] as $k) {
                        if (isset($item[$k]) && is_numeric($item[$k])) {
                            $qty = max(0, (int) $item[$k]);
                            break;
                        }
                    }
                    $out[strtoupper($sku)] = [
                        'sku' => $sku,
                        'sku_id' => isset($item['skuId']) ? (string) $item['skuId'] : '',
                        'goods_id' => isset($item['goodsId']) ? (string) $item['goodsId'] : '',
                        'qty' => $qty,
                        'status' => $status,
                    ];
                }
                if ($totalPages === null) {
                    $total = (int) ($result['total'] ?? 0);
                    $totalPages = $total > 0 ? (int) ceil($total / $pageSize) : $pageNumber;
                }
                $pageNumber++;
                usleep(200000);
            } while ($pageNumber <= ($totalPages ?? 1) && $pageNumber <= 1000);
        }

        return $out;
    }

    /**
     * Pull on-sale (2) and not-on-sale (3) SKUs from Temu and store listing_status + inactive_reason.
     */
    public function syncSkuListingStatuses(): int
    {
        app(\App\Services\MarketplaceManager\Temu3LiveListingsService::class)->ensureListingStatusColumns();
        if (! Schema::hasTable('temu3_metrics') || ! Schema::hasColumn('temu3_metrics', 'listing_status')) {
            Log::warning('Temu3 SKU status sync skipped: listing_status column missing');

            return 0;
        }

        $updated = 0;
        foreach ([3 => 'inactive', 2 => 'active'] as $searchType => $status) {
            $updated += $this->upsertSkuListQueryStatus((int) $searchType, $status);
        }
        foreach (['INACTIVE' => 'inactive', 'ACTIVE' => 'active'] as $searchType => $status) {
            $updated += $this->upsertSkuRetrieveStatus((string) $searchType, $status);
        }

        return $updated;
    }

    protected function upsertSkuListQueryStatus(int $skuSearchType, string $listingStatus): int
    {
        $pageNumber = 1;
        $pageSize = 100;
        $totalPages = null;
        $updated = 0;
        $url = $this->openApiRouterUrl();

        do {
            $requestBody = [
                'type' => 'bg.local.goods.sku.list.query',
                'pageSize' => $pageSize,
                'pageNumber' => $pageNumber,
                'skuSearchType' => $skuSearchType,
            ];
            $signedRequest = $this->generateSignValue($requestBody);
            $request = Http::withHeaders(['Content-Type' => 'application/json']);
            if (config('filesystems.default') === 'local') {
                $request = $request->withoutVerifying();
            }
            try {
                $response = $request->timeout(45)->post($url, $signedRequest);
            } catch (\Throwable $e) {
                Log::warning('Temu3 SKU status sync HTTP exception', [
                    'skuSearchType' => $skuSearchType,
                    'page' => $pageNumber,
                    'error' => $e->getMessage(),
                ]);
                break;
            }
            $data = $response->json() ?? [];
            if (! ($data['success'] ?? false)) {
                Log::info('Temu3 SKU status list.query skipped', [
                    'skuSearchType' => $skuSearchType,
                    'error' => $data['errorMsg'] ?? $response->status(),
                ]);
                break;
            }
            $result = $data['result'] ?? [];
            $items = $result['skuList'] ?? [];
            if (! is_array($items) || $items === []) {
                break;
            }
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $updated += $this->persistTemu3SkuListingRow($item, $listingStatus) ? 1 : 0;
            }
            if ($totalPages === null) {
                $total = (int) ($result['total'] ?? 0);
                $totalPages = $total > 0 ? (int) ceil($total / $pageSize) : $pageNumber;
            }
            $pageNumber++;
            usleep(200000);
        } while ($pageNumber <= ($totalPages ?? 1) && $pageNumber <= 1000);

        return $updated;
    }

    protected function upsertSkuRetrieveStatus(string $skuSearchType, string $listingStatus): int
    {
        $pageToken = null;
        $updated = 0;
        $pages = 0;
        do {
            $requestBody = [
                'type' => 'temu.local.sku.list.retrieve',
                'skuSearchType' => $skuSearchType,
                'pageSize' => 100,
            ];
            if ($pageToken) {
                $requestBody['pageToken'] = $pageToken;
            }
            $data = $this->postTemuRequest($requestBody);
            if (! ($data['success'] ?? false)) {
                Log::info('Temu3 SKU status retrieve skipped', [
                    'skuSearchType' => $skuSearchType,
                    'error' => $data['errorMsg'] ?? '',
                ]);
                break;
            }
            $items = $data['result']['skuList'] ?? [];
            if (! is_array($items) || $items === []) {
                break;
            }
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $updated += $this->persistTemu3SkuListingRow($item, $listingStatus) ? 1 : 0;
            }
            $pageToken = $data['result']['pagination']['nextToken'] ?? null;
            $pages++;
            usleep(200000);
        } while ($pageToken && $pages < 500);

        return $updated;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function persistTemu3SkuListingRow(array $item, string $listingStatus): bool
    {
        $sku = trim((string) ($item['outSkuSn'] ?? $item['skuSn'] ?? $item['sku'] ?? ''));
        $skuId = isset($item['skuId']) ? (string) $item['skuId'] : '';
        if ($sku === '' && $skuId === '') {
            return false;
        }
        $stock = null;
        foreach (['stock', 'quantity', 'skuStockQuantity', 'virtualStock'] as $k) {
            if (isset($item[$k]) && is_numeric($item[$k])) {
                $stock = (int) $item[$k];
                break;
            }
        }
        $payload = [
            'listing_status' => $listingStatus,
        ];
        if ($skuId !== '') {
            $payload['sku_id'] = $skuId;
        }
        $goodsId = $item['goodsId'] ?? null;
        if ($goodsId !== null && $goodsId !== '') {
            $payload['goods_id'] = (string) $goodsId;
        }
        $title = trim((string) ($item['goodsName'] ?? $item['skuName'] ?? ''));
        if ($title !== '' && Schema::hasColumn('temu3_metrics', 'goods_summary')) {
            $payload['goods_summary'] = $title;
        }
        if ($stock !== null) {
            $payload['quantity'] = $stock;
        }
        if (Schema::hasColumn('temu3_metrics', 'inactive_reason')) {
            $payload['inactive_reason'] = $listingStatus === 'inactive'
                ? $this->temuSkuInactiveReason($item, $stock)
                : null;
        }

        if ($sku !== '') {
            Temu3Metric::updateOrCreate(['sku' => $sku], $payload);

            return true;
        }
        $row = Temu3Metric::query()->where('sku_id', $skuId)->first();
        if ($row) {
            $row->fill($payload);
            $row->save();

            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function temuSkuInactiveReason(array $item, ?int $stock): ?string
    {
        $named = [
            $item['skuStatusDesc'] ?? null,
            $item['statusDesc'] ?? null,
            $item['subStatusName'] ?? null,
            $item['skuSubStatusName'] ?? null,
            $item['skuStatusName'] ?? null,
            $item['skuStatusChangeReason'] ?? null,
            $item['inactiveReason'] ?? null,
            $item['statusReason'] ?? null,
            $item['skuOffReason'] ?? null,
            $item['subStatusDesc'] ?? null,
        ];
        foreach ($named as $raw) {
            $text = $this->humanizeTemuInactiveReason($raw);
            if ($text !== null) {
                return $text;
            }
        }

        $hay = strtolower($this->flattenTemuSkuScalars($item));
        if (str_contains($hay, 'out of stock') || str_contains($hay, 'outofstock') || str_contains($hay, 'sold out')) {
            return 'Out of stock';
        }
        if (str_contains($hay, 'review block') || str_contains($hay, 'blocked reason') || str_contains($hay, 'review_blocked') || str_contains($hay, 'qualification')) {
            return 'Review blocked reason';
        }

        $sub = (int) ($item['subStatus4VO'] ?? $item['skuSubStatus'] ?? $item['subStatus'] ?? 0);
        if ($sub === 1 || ($stock !== null && $stock <= 0)) {
            return 'Out of stock';
        }
        if (in_array($sub, [2, 3, 6, 12], true)) {
            return 'Review blocked reason';
        }

        return $stock !== null && $stock <= 0 ? 'Out of stock' : null;
    }

    protected function humanizeTemuInactiveReason(mixed $raw): ?string
    {
        if (is_array($raw)) {
            $raw = $raw['name'] ?? $raw['desc'] ?? $raw['message'] ?? $raw['value'] ?? null;
        }
        $text = trim((string) $raw);
        if ($text === '' || is_numeric($text)) {
            return null;
        }
        $lower = strtolower($text);
        if (str_contains($lower, 'out of stock') || $lower === 'oos') {
            return 'Out of stock';
        }
        if (str_contains($lower, 'review') && str_contains($lower, 'block')) {
            return 'Review blocked reason';
        }

        return mb_substr($text, 0, 180);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function flattenTemuSkuScalars(array $item): string
    {
        $out = [];
        $walk = static function ($value) use (&$walk, &$out): void {
            if (is_array($value)) {
                foreach ($value as $v) {
                    $walk($v);
                }

                return;
            }
            if (is_scalar($value) && (string) $value !== '') {
                $out[] = (string) $value;
            }
        };
        $walk($item);

        return implode(' ', $out);
    }

    protected function fetchCurrentTemuGoodsDesc(string $goodsId, string $sku = ''): string
    {
        try {
            if ($sku !== '' && Schema::hasTable('temu3_metrics') && Schema::hasColumn('temu3_metrics', 'sku')) {
                foreach (['goods_desc', 'description_master'] as $column) {
                    if (! Schema::hasColumn('temu3_metrics', $column)) {
                        continue;
                    }

                    $desc = DB::table('temu3_metrics')
                        ->where(function ($q) use ($sku) {
                            $q->where('sku', $sku)
                                ->orWhere('sku', strtoupper($sku))
                                ->orWhere('sku', strtolower($sku));
                        })
                        ->value($column);
                    $desc = trim((string) $desc);
                    if ($desc !== '') {
                        return $desc;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Temu3 DB-first goods_desc fetch failed', [
                'sku' => $sku,
                'goods_id' => $goodsId,
                'error' => $e->getMessage(),
            ]);
        }

        return parent::fetchCurrentTemuGoodsDesc($goodsId, $sku);
    }

    protected function saveGoodsSummaryToTemuMetrics(string $sku, string $goodsSummary): bool
    {
        try {
            if ($sku === '' || ! Schema::hasTable('temu3_metrics') || ! Schema::hasColumn('temu3_metrics', 'sku')) {
                return false;
            }

            $update = [
                'bullet_points' => $goodsSummary,
                'goods_summary' => $goodsSummary,
            ];
            if (Schema::hasColumn('temu3_metrics', 'updated_at')) {
                $update['updated_at'] = now();
            }

            DB::table('temu3_metrics')->updateOrInsert(['sku' => $sku], $update);
            if (Schema::hasColumn('temu3_metrics', 'created_at')) {
                DB::table('temu3_metrics')->where('sku', $sku)->whereNull('created_at')->update(['created_at' => now()]);
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Temu3 saveGoodsSummaryToTemuMetrics failed', ['sku' => $sku, 'error' => $e->getMessage()]);

            return false;
        }
    }

    private function findTemuGoodsIdBySkuViaApi(string $sku): ?string
    {
        try {
            $pageToken = null;
            do {
                $requestBody = [
                    'type' => 'temu.local.goods.list.retrieve',
                    'goodsSearchType' => 'ALL',
                    'pageSize' => 100,
                ];
                if ($pageToken) {
                    $requestBody['pageToken'] = $pageToken;
                }

                $data = $this->postTemuRequest($requestBody);
                if (! ($data['success'] ?? false)) {
                    break;
                }

                foreach (($data['result']['goodsList'] ?? []) as $good) {
                    $outGoodsSn = $good['outGoodsSn'] ?? null;
                    if ($outGoodsSn !== null && strcasecmp(trim((string) $outGoodsSn), $sku) === 0) {
                        $goodsId = $good['goodsId'] ?? null;
                        if ($goodsId !== null && $goodsId !== '') {
                            $this->persistTemuMapping($sku, (string) $goodsId, null);

                            return (string) $goodsId;
                        }
                    }

                    foreach (($good['skuInfoList'] ?? []) as $skuInfo) {
                        $skuSn = $skuInfo['skuSn'] ?? $skuInfo['outSkuSn'] ?? null;
                        if ($skuSn !== null && strcasecmp(trim((string) $skuSn), $sku) === 0) {
                            $goodsId = $good['goodsId'] ?? null;
                            if ($goodsId !== null && $goodsId !== '') {
                                $skuId = $skuInfo['skuId'] ?? null;
                                $this->persistTemuMapping($sku, (string) $goodsId, $skuId !== null && $skuId !== '' ? (string) $skuId : null);

                                return (string) $goodsId;
                            }
                        }
                    }
                }

                $pageToken = $data['result']['pagination']['nextToken'] ?? null;
            } while ($pageToken);
        } catch (\Throwable $e) {
            Log::warning('Temu3 getGoodsIdBySku list API fallback failed', ['sku' => $sku, 'error' => $e->getMessage()]);
        }

        return null;
    }

    private function findTemuSkuIdBySkuViaApi(string $sku): ?string
    {
        try {
            $pageToken = null;
            do {
                $requestBody = [
                    'type' => 'temu.local.sku.list.retrieve',
                    'skuSearchType' => 'ACTIVE',
                    'pageSize' => 100,
                ];
                if ($pageToken) {
                    $requestBody['pageToken'] = $pageToken;
                }

                $data = $this->postTemuRequest($requestBody);
                if (! ($data['success'] ?? false)) {
                    break;
                }

                foreach (($data['result']['skuList'] ?? []) as $item) {
                    $outSkuSn = isset($item['outSkuSn']) ? trim((string) $item['outSkuSn']) : null;
                    if ($outSkuSn !== null && strcasecmp(trim((string) $outSkuSn), $sku) === 0) {
                        $skuId = $item['skuId'] ?? null;
                        if ($skuId !== null && $skuId !== '') {
                            $goodsId = $item['goodsId'] ?? null;
                            $this->persistTemuMapping($sku, $goodsId !== null && $goodsId !== '' ? (string) $goodsId : null, (string) $skuId);

                            return (string) $skuId;
                        }
                    }
                }

                $pageToken = $data['result']['pagination']['nextToken'] ?? null;
            } while ($pageToken);
        } catch (\Throwable $e) {
            Log::warning('Temu3 getSkuIdBySku list API fallback failed', ['sku' => $sku, 'error' => $e->getMessage()]);
        }

        return null;
    }

    private function postTemuRequest(array $requestBody): array
    {
        $request = Http::withHeaders(['Content-Type' => 'application/json']);
        if (config('filesystems.default') === 'local') {
            $request = $request->withoutVerifying();
        }

        $response = $request->post($this->openApiRouterUrl(), $this->generateSignValue($requestBody));

        return $response->json() ?? [];
    }

    /**
     * @param  list<string>  $videos
     * @return array{success: bool, message: string, normalized_urls?: list<string>}
     */
    public function updateVideos(string $identifier, array $videos, string $mode = 'replace'): array
    {
        $videos = array_slice(array_values(array_unique(array_filter(array_map('trim', $videos), fn ($v) => $v !== ''))), 0, 5);
        if ($videos === []) {
            return ['success' => false, 'message' => 'At least one video URL is required.'];
        }

        $uploaded = $this->uploadTemuVideosFromSourceUrls($videos);
        if (! ($uploaded['success'] ?? false)) {
            return ['success' => false, 'message' => (string) ($uploaded['message'] ?? 'Temu 3 video upload failed.')];
        }

        $res = $this->updateListingVideos($identifier, $uploaded['urls']);
        if (! ($res['success'] ?? false)) {
            return $res;
        }

        $resolved = $this->resolveTemuGoodsAndSku($identifier);
        $sku = trim((string) ($resolved['sku'] ?? $identifier));
        $saved = $this->saveVideoUrlsToMetricsRow('temu3_metrics', $sku, $videos);
        if (! $saved) {
            $res['message'] = ($res['message'] ?? 'Temu 3 listing videos updated.').' Metrics save failed.';
        }

        $res['normalized_urls'] = $videos;

        return $res;
    }
}
