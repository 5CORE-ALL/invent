<?php

namespace App\Services\Support\Concerns;

use App\Models\ShopifySku;
use App\Support\Marketplace\ListingManagerAmazonHydrator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Mirakl MCM seller API bullet push: PM11 → P41 → P42 (+ P44 on failure).
 *
 * Requires Shop API Key on the operator MCM instance (not Mirakl Connect OAuth).
 */
trait MiraklMcmBulletImport
{
    /** @var array<string, array<string, mixed>> */
    private array $miraklMcmOperatorMasterProductCache = [];

    /**
     * Local MCM shop_sku index for the current request/process (bulk price push).
     *
     * @var array<string, array{exact: array<string, string>, norm: array<string, string>, compact: array<string, string>}>
     */
    private static array $miraklMcmOfferSkuIndex = [];

    /**
     * Uploaded offers-sheet index (macys_price_data / bestbuy_price_data): offer_sku preferred.
     *
     * @var array<string, array{exact: array<string, string>, norm: array<string, string>, compact: array<string, string>, product: array<string, string>}>
     */
    private static array $miraklMcmSheetOfferSkuIndex = [];

    abstract protected function miraklMcmConfigKey(): string;

    abstract protected function miraklMcmMarketplaceLabel(): string;

    /**
     * DB table with category_code / product_sku for PM11 hierarchy lookup.
     */
    protected function miraklMcmHierarchyTable(): ?string
    {
        return null;
    }

    protected function miraklMcmConfig(string $key, mixed $default = null): mixed
    {
        return config('services.'.$this->miraklMcmConfigKey().'.'.$key, $default);
    }

    protected function miraklMcmApiKeyEnvName(): string
    {
        return match ($this->miraklMcmConfigKey()) {
            'macy' => 'MACY_MCM_API_KEY',
            'bestbuy' => 'BESTBUY_MCM_API_KEY',
            'purchasingpower' => 'PURCHASING_POWER_MCM_API_KEY',
            default => strtoupper($this->miraklMcmConfigKey()).'_MCM_API_KEY',
        };
    }

    /**
     * @return array{success: bool, message: string, import_id?: int, import_status?: string|null, mcm_verified?: bool, attribute_codes?: list<string>}
     */
    protected function pushBulletPointsViaMiraklMcm(string $sku, string $bulletPoints): array
    {
        $envKey = $this->miraklMcmApiKeyEnvName();
        if ($this->miraklMcmApiKey() === null) {
            return [
                'success' => false,
                'message' => "{$envKey} is required for {$this->miraklMcmMarketplaceLabel()} bullet push (Mirakl MCM PM11 + P41). "
                    .'Mirakl Connect OAuth does not authenticate macysus-prod / bestbuyus-prod MCM endpoints.',
            ];
        }

        $lines = $this->miraklMcmBulletLines($bulletPoints);
        if ($lines === []) {
            return ['success' => false, 'message' => 'At least one bullet point line is required.'];
        }

        $useEnriched = filter_var($this->miraklMcmConfig('mcm_p41_enriched_row', true), FILTER_VALIDATE_BOOL);
        $hierarchy = $this->resolveMiraklMcmHierarchyForP41($sku);
        if ($useEnriched && ($hierarchy === null || trim($hierarchy) === '')) {
            $label = $this->miraklMcmMarketplaceLabel();

            return [
                'success' => false,
                'message' => "{$label} MCM P41 skipped: categoryCode could not be resolved for [{$sku}] "
                    ."(no live {$label} offer/product category, Connect mapping, or price-data row). "
                    ."Create the {$label} listing or add its price-data row before P41.",
            ];
        }

        $fbCodes = $this->resolveMiraklMcmBulletAttributeCodes($hierarchy);

        Log::info("{$this->miraklMcmMarketplaceLabel()} MCM bullet push (P41)", [
            'sku' => $sku,
            'hierarchy' => $hierarchy,
            'attribute_codes' => $fbCodes,
            'enriched_row' => filter_var($this->miraklMcmConfig('mcm_p41_enriched_row', true), FILTER_VALIDATE_BOOL),
        ]);

        $csv = $this->buildMiraklMcmP41BulletImportCsv($sku, $lines, $fbCodes, $hierarchy);
        $import = $this->importMiraklMcmProductsP41($csv);
        if (! ($import['success'] ?? false)) {
            return $import;
        }

        $importId = (int) ($import['import_id'] ?? 0);
        if ($importId <= 0) {
            return ['success' => false, 'message' => "{$this->miraklMcmMarketplaceLabel()} P41 import did not return an import_id."];
        }

        $poll = $this->waitForMiraklMcmImportP42($importId, $sku);
        if (! ($poll['success'] ?? false)) {
            return $this->miraklMcmAttachImportErrorReport($poll, $importId, $sku);
        }

        $verify = $this->verifyMiraklMcmBullets($sku, $lines, $fbCodes);
        $label = $this->miraklMcmMarketplaceLabel();
        $integrationPending = (bool) ($poll['mcm_integration_pending'] ?? false);
        $lockedNotice = $this->miraklMcmP42LockedValuesNotice(
            is_array($poll['response'] ?? null) ? $poll['response'] : null
        );
        if ($integrationPending) {
            $message = trim((string) ($poll['message'] ?? ''));
            if ($message === '') {
                $message = "{$label} P41 import #{$importId} accepted (SENT) — MCM Specifications not updated in UI yet.";
            }
        } else {
            $message = "{$label} bullets updated via MCM P41 (import #{$importId}).";
            if ($verify['verified'] ?? false) {
                $message .= ' MCM read-back verified.';
            } else {
                $message .= ' Warning: '.($verify['message'] ?? 'MCM read-back not verified yet.');
                if (($verify['partial'] ?? false) && $lockedNotice !== '') {
                    $message .= ' '.$lockedNotice;
                }
            }
        }
        if ($lockedNotice !== '' && ! str_contains($message, 'Protected value')) {
            $message .= ' '.$lockedNotice;
        }

        return [
            'success' => true,
            'message' => trim($message),
            'import_id' => $importId,
            'import_status' => $poll['import_status'] ?? null,
            'mcm_verified' => $verify['verified'] ?? false,
            'mcm_integration_pending' => $integrationPending,
            'mcm_locked_values_override' => $this->miraklMcmP42AllowsLockedOverride(
                is_array($poll['response'] ?? null) ? $poll['response'] : null
            ),
            'attribute_codes' => $fbCodes,
        ];
    }

    protected function miraklMcmApiKey(): ?string
    {
        $key = trim((string) $this->miraklMcmConfig('mcm_api_key', ''));

        if ($key === '') {
            return null;
        }

        // PM11/P41 docs: `Authorization: YOUR_API_KEY` (no Bearer prefix).
        if (stripos($key, 'authorization:') === 0) {
            $key = trim(substr($key, strlen('authorization:')));
        }
        if (stripos($key, 'bearer ') === 0) {
            $key = trim(substr($key, 7));
        }

        return $key !== '' ? $key : null;
    }

    /** @return array<string, string> */
    protected function miraklMcmAuthHeaders(): array
    {
        $key = $this->miraklMcmApiKey();
        if ($key === null) {
            throw new \RuntimeException($this->miraklMcmMarketplaceLabel().' MCM API key is not configured.');
        }

        return [
            'Authorization' => $key,
            'Accept' => 'application/json',
        ];
    }

    protected function miraklMcmRequest()
    {
        return Http::withoutVerifying()
            ->withHeaders($this->miraklMcmAuthHeaders())
            ->timeout(120);
    }

    protected function miraklMcmBaseUrl(): string
    {
        return rtrim((string) $this->miraklMcmConfig('mcm_base_url', ''), '/');
    }

    /**
     * P41 header that carries the category. Mirakl answers transformation error 1004
     * ("The category could not be identified") when this header does not match the operator's
     * category attribute, so it is taken from config, else detected from PM11, else defaulted.
     */
    protected function miraklMcmCategoryColumn(): string
    {
        $configured = trim((string) $this->miraklMcmConfig('mcm_category_column', ''));
        if ($configured !== '') {
            return $configured;
        }

        $cacheKey = $this->miraklMcmConfigKey().'_mcm_category_column';
        $rejected = $this->miraklMcmRejectedCategoryColumns();
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '' && ! in_array($cached, $rejected, true)) {
            return $cached;
        }

        $detected = $this->detectMiraklMcmCategoryColumn($rejected);
        $column = $detected;
        if ($column === null) {
            $candidates = array_values(array_diff($this->miraklMcmCategoryColumnCandidates(), $rejected));
            if ($candidates === []) {
                // Every guess was rejected by Mirakl: start the rotation over rather than give up.
                Cache::forget($this->miraklMcmConfigKey().'_mcm_category_column_rejected');
                $candidates = $this->miraklMcmCategoryColumnCandidates();
            }
            $column = $candidates[0];
        }
        Cache::put($cacheKey, $column, $detected !== null ? 43200 : 600);
        if ($detected === null) {
            Log::info($this->miraklMcmMarketplaceLabel().' MCM category column not found in PM11, using default', ['column' => $column, 'rejected' => $rejected]);
        }

        return $column;
    }

    /**
     * Header names tried in order when PM11 does not reveal the operator's category attribute.
     *
     * @return list<string>
     */
    protected function miraklMcmCategoryColumnCandidates(): array
    {
        $common = ['category', 'categoryCode', 'category_code', 'category-code', 'Category', 'CATEGORY', 'hierarchy', 'hierarchyCode'];
        if ($this->miraklMcmConfigKey() === 'macy') {
            array_unshift($common, 'categoryCode');
        }

        return array_values(array_unique($common));
    }

    /**
     * Columns Mirakl already answered with transformation error 1004 for; skipped on the next try.
     *
     * @return list<string>
     */
    protected function miraklMcmRejectedCategoryColumns(): array
    {
        $rejected = Cache::get($this->miraklMcmConfigKey().'_mcm_category_column_rejected');

        return is_array($rejected) ? array_values(array_filter($rejected, 'is_string')) : [];
    }

    /**
     * Remember a category header Mirakl rejected (1004) so the next publish tries another one, and
     * return the header that will be used next (null when the column is pinned by config).
     */
    protected function rememberMiraklMcmCategoryColumnRejected(string $column): ?string
    {
        if (trim((string) $this->miraklMcmConfig('mcm_category_column', '')) !== '') {
            return null;
        }
        $rejected = $this->miraklMcmRejectedCategoryColumns();
        if (! in_array($column, $rejected, true)) {
            $rejected[] = $column;
        }
        Cache::put($this->miraklMcmConfigKey().'_mcm_category_column_rejected', $rejected, 86400);
        Cache::forget($this->miraklMcmConfigKey().'_mcm_category_column');

        return $this->miraklMcmCategoryColumn();
    }

    /**
     * Look through PM11 for the attribute that represents the category (role or code).
     *
     * @param  list<string>  $exclude  attribute codes Mirakl already rejected as the category header
     */
    protected function detectMiraklMcmCategoryColumn(array $exclude = []): ?string
    {
        try {
            $attributes = $this->fetchMiraklMcmPm11Attributes();
        } catch (\Throwable $e) {
            Log::warning($this->miraklMcmMarketplaceLabel().' PM11 fetch for category column failed', ['error' => $e->getMessage()]);

            return null;
        }

        $best = null;
        $bestScore = 0;
        foreach ($attributes as $attribute) {
            if (! is_array($attribute)) {
                continue;
            }
            $code = trim((string) ($attribute['code'] ?? ''));
            if ($code === '' || in_array($code, $exclude, true)) {
                continue;
            }
            $score = 0;
            foreach ((array) ($attribute['roles'] ?? []) as $role) {
                $type = is_array($role) ? (string) ($role['type'] ?? '') : (string) $role;
                if (stripos($type, 'CATEGORY') !== false || stripos($type, 'HIERARCHY') !== false) {
                    $score = max($score, 100);
                }
            }
            $lower = strtolower($code);
            if (in_array($lower, ['category', 'categorycode', 'category-code', 'category_code', 'hierarchy', 'hierarchycode', 'hierarchy-code', 'hierarchy_code'], true)) {
                $score = max($score, 80);
            } elseif (str_contains($lower, 'categor') || str_contains($lower, 'hierarch')) {
                $score = max($score, 40);
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $code;
            }
        }

        return $best;
    }

    /** @return array<string, int|string> */
    protected function miraklMcmQueryParams(): array
    {
        $shopId = $this->miraklMcmConfig('shop_id');
        if ($shopId === null || $shopId === '') {
            return [];
        }

        return ['shop_id' => (int) $shopId];
    }

    protected function miraklMcmOfferProductsTable(): ?string
    {
        return match ($this->miraklMcmConfigKey()) {
            'macy' => 'macy_products',
            'bestbuy' => 'bestbuy_usa_products',
            'purchasingpower' => 'purchasing_power_products',
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    protected function miraklMcmOfferSkuCandidates(string $sku): array
    {
        $sku = trim($sku);
        $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);

        $compact = ShopifySku::compactSkuForLookup($sku);

        return array_values(array_unique(array_filter([
            $sku,
            strtoupper($sku),
            $norm,
            str_replace('-', ' ', $sku),
            (string) preg_replace('/\s+/u', ' ', $sku),
            str_replace(' ', '', $sku),
            strtoupper(str_replace(' ', '', $sku)),
            (string) preg_replace('/\s+/u', '-', $sku),
            $compact,
        ], static fn ($value) => is_string($value) && $value !== '')));
    }

    /**
     * @return array{exact: array<string, string>, norm: array<string, string>, compact: array<string, string>}
     */
    protected function miraklMcmOfferSkuIndex(string $table): array
    {
        if (isset(self::$miraklMcmOfferSkuIndex[$table])) {
            return self::$miraklMcmOfferSkuIndex[$table];
        }

        $exact = [];
        $norm = [];
        $compact = [];
        try {
            foreach (DB::table($table)->whereNotNull('sku')->where('sku', '!=', '')->select('sku')->cursor() as $row) {
                $shop = trim((string) $row->sku);
                if ($shop === '') {
                    continue;
                }
                $exact[strtoupper($shop)] = $shop;
                $n = ShopifySku::normalizeSkuForShopifyLookup($shop);
                if ($n !== '' && ! isset($norm[$n])) {
                    $norm[$n] = $shop;
                }
                $c = ShopifySku::compactSkuForLookup($shop);
                if ($c !== '' && ! isset($compact[$c])) {
                    $compact[$c] = $shop;
                }
            }
        } catch (\Throwable $e) {
            Log::warning($this->miraklMcmMarketplaceLabel().' local offer SKU index failed', [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);
        }

        return self::$miraklMcmOfferSkuIndex[$table] = [
            'exact' => $exact,
            'norm' => $norm,
            'compact' => $compact,
        ];
    }

    protected function resolveLocalMcmOfferSku(string $sku): ?string
    {
        $table = $this->miraklMcmOfferProductsTable();
        if ($table === null || ! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sku')) {
            return null;
        }

        $index = $this->miraklMcmOfferSkuIndex($table);
        $upper = strtoupper(trim($sku));
        if (isset($index['exact'][$upper])) {
            return $index['exact'][$upper];
        }
        $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
        if ($norm !== '' && isset($index['norm'][$norm])) {
            return $index['norm'][$norm];
        }
        $compact = ShopifySku::compactSkuForLookup($sku);
        if ($compact !== '' && isset($index['compact'][$compact])) {
            return $index['compact'][$compact];
        }

        return null;
    }

    /**
     * @return array{exact: array<string, string>, norm: array<string, string>, compact: array<string, string>, product: array<string, string>}
     */
    protected function miraklMcmSheetOfferSkuIndex(string $table): array
    {
        if (isset(self::$miraklMcmSheetOfferSkuIndex[$table])) {
            return self::$miraklMcmSheetOfferSkuIndex[$table];
        }

        $exact = [];
        $norm = [];
        $compact = [];
        $product = [];
        $hasSku = Schema::hasColumn($table, 'sku');
        $hasOffer = Schema::hasColumn($table, 'offer_sku');
        $hasProduct = Schema::hasColumn($table, 'product_sku');
        $columns = array_values(array_filter([
            $hasSku ? 'sku' : null,
            $hasOffer ? 'offer_sku' : null,
            $hasProduct ? 'product_sku' : null,
        ]));
        if ($columns === []) {
            return self::$miraklMcmSheetOfferSkuIndex[$table] = [
                'exact' => [],
                'norm' => [],
                'compact' => [],
                'product' => [],
            ];
        }

        try {
            foreach (DB::table($table)->select($columns)->cursor() as $row) {
                $offer = $hasOffer ? trim((string) ($row->offer_sku ?? '')) : '';
                $sheetSku = $hasSku ? trim((string) ($row->sku ?? '')) : '';
                $productSku = $hasProduct ? trim((string) ($row->product_sku ?? '')) : '';
                $shop = $offer !== '' ? $offer : $sheetSku;
                if ($shop === '') {
                    continue;
                }
                foreach (array_unique(array_filter([$offer, $sheetSku, $productSku])) as $key) {
                    $exact[strtoupper($key)] = $shop;
                    $n = ShopifySku::normalizeSkuForShopifyLookup($key);
                    if ($n !== '' && ! isset($norm[$n])) {
                        $norm[$n] = $shop;
                    }
                    $c = ShopifySku::compactSkuForLookup($key);
                    if ($c !== '' && ! isset($compact[$c])) {
                        $compact[$c] = $shop;
                    }
                    if ($productSku !== '') {
                        $product[strtoupper($key)] = $productSku;
                        if ($n !== '' && ! isset($product[$n])) {
                            $product[$n] = $productSku;
                        }
                        if ($c !== '' && ! isset($product[$c])) {
                            $product[$c] = $productSku;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning($this->miraklMcmMarketplaceLabel().' sheet offer SKU index failed', [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);
        }

        return self::$miraklMcmSheetOfferSkuIndex[$table] = [
            'exact' => $exact,
            'norm' => $norm,
            'compact' => $compact,
            'product' => $product,
        ];
    }

    protected function resolveLocalMcmSheetOfferSku(string $sku): ?string
    {
        $table = $this->miraklMcmHierarchyTable();
        if ($table === null || ! Schema::hasTable($table)) {
            return null;
        }

        $index = $this->miraklMcmSheetOfferSkuIndex($table);
        $upper = strtoupper(trim($sku));
        if (isset($index['exact'][$upper])) {
            return $index['exact'][$upper];
        }
        $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
        if ($norm !== '' && isset($index['norm'][$norm])) {
            return $index['norm'][$norm];
        }
        $compact = ShopifySku::compactSkuForLookup($sku);
        if ($compact !== '' && isset($index['compact'][$compact])) {
            return $index['compact'][$compact];
        }

        return null;
    }

    protected function resolveLocalMcmSheetProductSku(string $sku): ?string
    {
        $table = $this->miraklMcmHierarchyTable();
        if ($table === null || ! Schema::hasTable($table)) {
            return null;
        }

        $index = $this->miraklMcmSheetOfferSkuIndex($table);
        $product = $index['product'] ?? [];
        $upper = strtoupper(trim($sku));
        if (isset($product[$upper]) && $product[$upper] !== '') {
            return $product[$upper];
        }
        $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
        if ($norm !== '' && isset($product[$norm]) && $product[$norm] !== '') {
            return $product[$norm];
        }
        $compact = ShopifySku::compactSkuForLookup($sku);
        if ($compact !== '' && isset($product[$compact]) && $product[$compact] !== '') {
            return $product[$compact];
        }

        return null;
    }

    /**
     * PRI01 try order: live OF21 shop_sku, exact sheet Offer SKU (case preserved),
     * sheet Product SKU, then compact / hyphen / original.
     *
     * @param  array{live?: ?string, sheet_offer?: ?string, sheet_product?: ?string}  $hints
     * @return list<string>
     */
    protected function miraklMcmPricingOfferSkuQueue(string $sku, array $hints = []): array
    {
        $sheetOffer = array_key_exists('sheet_offer', $hints)
            ? $hints['sheet_offer']
            : $this->resolveLocalMcmSheetOfferSku($sku);
        $sheetProduct = array_key_exists('sheet_product', $hints)
            ? $hints['sheet_product']
            : $this->resolveLocalMcmSheetProductSku($sku);
        $live = $hints['live'] ?? null;

        $queue = [];
        $add = static function (?string $value) use (&$queue): void {
            $value = trim((string) $value);
            if ($value === '' || in_array($value, $queue, true)) {
                return;
            }
            $queue[] = $value;
        };

        $add(is_string($live) ? $live : null);
        $add(is_string($sheetOffer) ? $sheetOffer : null);
        $add(is_string($sheetProduct) ? $sheetProduct : null);
        $add(ShopifySku::compactSkuForLookup($sku));
        $add((string) preg_replace('/\s+/u', '-', trim($sku)));
        $add(str_replace(' ', '', $sku));
        $add($sku);
        foreach ($this->miraklMcmOfferSkuCandidates($sku) as $candidate) {
            $add($candidate);
        }

        return $queue;
    }

    /**
     * Resolve the live MCM shop_sku for PRI01.
     * Prefer OF21 (including sheet product_sku), then exact sheet Offer SKU case.
     */
    protected function resolveMcmOfferSku(string $sku, string $apiKey, string $baseUrl): ?string
    {
        $sheet = $this->resolveLocalMcmSheetOfferSku($sku);
        $productSku = $this->resolveLocalMcmSheetProductSku($sku);
        $fromApi = $this->resolveMcmOfferSkuFromOffersApi($sku, $apiKey, $baseUrl, array_values(array_filter([
            $productSku,
            $sheet,
        ])));
        if ($fromApi !== null && $fromApi !== '') {
            return $fromApi;
        }

        if ($sheet !== null && $sheet !== '') {
            return $sheet;
        }

        $local = $this->resolveLocalMcmOfferSku($sku);
        $localLooksLikeConnectId = $local !== null && preg_match('/\s/u', $local) === 1;
        if ($local !== null && ! $localLooksLikeConnectId) {
            return $local;
        }

        return $local;
    }

    /**
     * @param  list<string>  $extraCandidates
     */
    protected function resolveMcmOfferSkuFromOffersApi(string $sku, string $apiKey, string $baseUrl, array $extraCandidates = []): ?string
    {
        $wantedNorm = ShopifySku::normalizeSkuForShopifyLookup($sku);
        $wantedCompact = ShopifySku::compactSkuForLookup($sku);
        $wantedValues = array_values(array_unique(array_filter(array_merge(
            [$sku, $wantedNorm, $wantedCompact],
            $this->miraklMcmOfferSkuCandidates($sku),
            $extraCandidates
        ), static fn ($value) => is_string($value) && trim($value) !== '')));

        $lookups = [];
        $pushLookup = static function (array $params) use (&$lookups): void {
            $key = json_encode($params);
            if ($key !== false && ! isset($lookups[$key])) {
                $lookups[$key] = $params;
            }
        };

        foreach ($wantedValues as $candidate) {
            $pushLookup(['sku' => $candidate]);
        }
        foreach ($extraCandidates as $extra) {
            $extra = trim((string) $extra);
            if ($extra === '') {
                continue;
            }
            $pushLookup(['product_sku' => $extra]);
            $pushLookup(['shop_sku' => $extra]);
        }
        if ($wantedCompact !== '') {
            $pushLookup(['shop_sku' => $wantedCompact]);
        }

        foreach (array_values($lookups) as $lookup) {
            $params = $lookup + ['max' => 20];
            $shopId = $this->miraklMcmConfig('shop_id');
            if ($shopId !== null && $shopId !== '') {
                $params['shop_id'] = (int) $shopId;
            }

            $response = $this->miraklMcmGetOffers($apiKey, $baseUrl, $params);
            if ($response !== null && $response->status() === 404 && isset($params['shop_id'])) {
                unset($params['shop_id']);
                $response = $this->miraklMcmGetOffers($apiKey, $baseUrl, $params);
            }
            if ($response === null || ! $response->successful()) {
                continue;
            }

            foreach ($response->json('offers') ?? [] as $offer) {
                if (! is_array($offer)) {
                    continue;
                }
                $shopSku = trim((string) ($offer['shop_sku'] ?? ''));
                foreach (['shop_sku', 'product_sku'] as $field) {
                    $value = trim((string) ($offer[$field] ?? ''));
                    if ($value === '') {
                        continue;
                    }
                    $matches = false;
                    foreach ($wantedValues as $candidate) {
                        if (strcasecmp($value, $candidate) === 0) {
                            $matches = true;
                            break;
                        }
                        if ($wantedNorm !== '' && ShopifySku::normalizeSkuForShopifyLookup($value) === $wantedNorm) {
                            $matches = true;
                            break;
                        }
                        if ($wantedCompact !== '' && ShopifySku::compactSkuForLookup($value) === $wantedCompact) {
                            $matches = true;
                            break;
                        }
                    }
                    if (! $matches && isset($lookup['product_sku']) && strcasecmp($value, (string) $lookup['product_sku']) === 0) {
                        $matches = true;
                    }
                    if (! $matches) {
                        continue;
                    }

                    return $shopSku !== '' ? $shopSku : $value;
                }
            }
        }

        return null;
    }

    public static function isMiraklOfferNotFoundError(string $message): bool
    {
        $m = strtolower($message);

        return str_contains($m, 'no existing offer with sku')
            || str_contains($m, 'is not listed on');
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function miraklMcmGetOffers(string $apiKey, string $baseUrl, array $params): mixed
    {
        $last = null;
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                $response = Http::withoutVerifying()
                    ->withHeaders([
                        'Authorization' => $apiKey,
                        'Accept' => 'application/json',
                    ])
                    ->timeout(30)
                    ->get(rtrim($baseUrl, '/').'/api/offers', $params);
                $last = $response;
                if ($response->status() !== 429) {
                    return $response;
                }
            } catch (\Throwable $e) {
                Log::warning($this->miraklMcmMarketplaceLabel().' OF21 lookup failed', [
                    'sku' => $params['sku'] ?? null,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);
            }
            usleep(min(30, 3 * $attempt) * 1_000_000);
        }

        return $last;
    }

    protected function miraklMcmPostPricingImport(string $apiKey, string $url, string $csv, string $filename): mixed
    {
        $last = null;
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => $apiKey,
                    'Accept' => 'application/json',
                ])
                ->timeout(60)
                ->attach('file', $csv, $filename)
                ->post($url);
            $last = $response;
            if ($response->status() !== 429) {
                return $response;
            }
            usleep(min(30, 3 * $attempt) * 1_000_000);
        }

        return $last;
    }

    /** @return list<string> */
    protected function miraklMcmBulletLines(string $bulletPoints): array
    {
        return array_values(array_filter(array_map(
            fn ($line) => trim((string) $line),
            preg_split('/\r\n|\r|\n/', trim($bulletPoints)) ?: []
        ), fn ($line) => $line !== ''));
    }

    /** @var array<string, string> SKU (upper) => category code chosen by the caller for this request */
    protected array $miraklMcmHierarchyOverrides = [];

    /**
     * Category the user picked (Listing Manager) wins over anything derived from existing data.
     */
    public function setMiraklMcmHierarchyOverride(string $sku, string $categoryCode): void
    {
        $sku = strtoupper(trim($sku));
        $categoryCode = trim($categoryCode);
        if ($sku === '') {
            return;
        }
        if ($categoryCode === '') {
            unset($this->miraklMcmHierarchyOverrides[$sku]);

            return;
        }
        $this->miraklMcmHierarchyOverrides[$sku] = $categoryCode;
    }

    protected function resolveMiraklMcmHierarchyForSku(string $sku): ?string
    {
        return $this->resolveMiraklMcmHierarchyForP41($sku);
    }

    protected function resolveMiraklMcmHierarchyForP41(string $sku): ?string
    {
        $override = $this->miraklMcmHierarchyOverrides[strtoupper(trim($sku))] ?? '';
        if ($override === '' && count($this->miraklMcmHierarchyOverrides) === 1) {
            // Offer SKU may differ in case/spacing from the product SKU the caller registered.
            $override = (string) reset($this->miraklMcmHierarchyOverrides);
        }
        if ($override !== '') {
            return $override;
        }

        $fromMaster = $this->resolveMiraklMcmHierarchyFromMasterCatalog($sku);
        if ($fromMaster !== null) {
            return $fromMaster;
        }

        $fromOffer = $this->resolveMiraklMcmHierarchyFromOffer($sku);
        if ($fromOffer !== null) {
            return $fromOffer;
        }

        $fromProduct = $this->resolveMiraklMcmHierarchyFromMcmProduct($sku);
        if ($fromProduct !== null) {
            return $fromProduct;
        }

        $extra = $this->resolveMiraklMcmHierarchyExtraFallback($sku);
        if ($extra !== null && trim($extra) !== '') {
            return trim($extra);
        }

        $fromExactDb = $this->resolveMiraklMcmPriceDataCategoryCodeExact($sku);
        if ($fromExactDb !== null) {
            return $fromExactDb;
        }

        return $this->resolveMiraklMcmPriceDataCategoryCodeRelated($sku);
    }

    protected function resolveMiraklMcmHierarchyFromMasterCatalog(string $sku): ?string
    {
        return null;
    }

    protected function resolveMiraklMcmHierarchyFromOffer(string $sku): ?string
    {
        $offer = $this->fetchMiraklMcmOfferBySku($sku);
        if ($offer === []) {
            return null;
        }

        $code = trim((string) ($offer['category_code'] ?? ''));

        return $code !== '' ? $code : null;
    }

    protected function resolveMiraklMcmHierarchyFromMcmProduct(string $sku): ?string
    {
        $product = $this->fetchMiraklMcmProductBySku($sku);
        if ($product === []) {
            return null;
        }

        $code = trim((string) ($product['category_code'] ?? ''));
        if ($code === '') {
            $code = $this->miraklMcmReadProductAttributeValue($product, 'categoryCode');
        }

        return $code !== '' ? $code : null;
    }

    protected function resolveMiraklMcmHierarchyExtraFallback(string $sku): ?string
    {
        return null;
    }

    protected function resolveMiraklMcmPriceDataCategoryCodeExact(string $sku): ?string
    {
        $row = $this->fetchMiraklMcmPriceDataRowBySku($sku);
        if ($row === null) {
            return null;
        }

        $code = trim((string) ($row->category_code ?? ''));

        return $code !== '' ? $code : null;
    }

    protected function resolveMiraklMcmPriceDataCategoryCodeRelated(string $sku): ?string
    {
        $row = $this->fetchMiraklMcmRelatedPriceDataRow($sku);
        if ($row === null) {
            return null;
        }

        $code = trim((string) ($row->category_code ?? ''));

        return $code !== '' ? $code : null;
    }

    /** @deprecated Use resolveMiraklMcmPriceDataCategoryCodeExact/Related */
    protected function resolveMiraklMcmPriceDataCategoryCode(string $sku): ?string
    {
        return $this->resolveMiraklMcmPriceDataCategoryCodeExact($sku)
            ?? $this->resolveMiraklMcmPriceDataCategoryCodeRelated($sku);
    }

    /** @return list<string> */
    protected function miraklMcmRelatedSkuCandidates(string $sku): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return [];
        }

        $candidates = [];
        $patterns = [
            '/\s+[\(\[]?\d+\s*(?:PCS|pcs|Pcs|Pk|PK|Pack|PACK|Pieces?|Piece|Pc|PC)[\)\]]?\s*$/iu',
            '/\s+\d+PCS$/iu',
        ];

        foreach ($patterns as $pattern) {
            $stripped = trim((string) preg_replace($pattern, '', $sku));
            if ($stripped !== '' && strcasecmp($stripped, $sku) !== 0) {
                $candidates[] = $stripped;
            }
        }

        return array_values(array_unique($candidates));
    }

    /**
     * Mirakl Connect / marketplace-specific catalog fields for P41 enrichment.
     *
     * @return array{product_name?: string, upc?: string, brand?: string, product_sku?: string, connect_category_id?: string, connect_category_label?: string, connect_category_path?: string, mcm_category_code?: string}
     */
    protected function resolveMiraklMcmConnectCatalogContext(string $sku): array
    {
        return [];
    }

    /**
     * PM11 — resolve bullet / F&B attribute column codes for P41 CSV headers.
     *
     * @return list<string>
     */
    protected function resolveMiraklMcmBulletAttributeCodes(?string $hierarchy = null): array
    {
        $attributes = $this->fetchMiraklMcmPm11Attributes($hierarchy);
        $bySlot = [];
        $singleBulletCode = null;

        foreach ($attributes as $attr) {
            if (! is_array($attr)) {
                continue;
            }

            $code = trim((string) ($attr['code'] ?? ''));
            $label = strtolower(trim((string) ($attr['label'] ?? '')));
            if ($code === '') {
                continue;
            }

            $codeLower = strtolower($code);

            if (preg_match('/features_and_benefits_bullet_(\d+)/i', $code, $match) === 1) {
                $bySlot[(int) $match[1]] = $code;

                continue;
            }

            if (preg_match('/^fnb(\d+)$/i', $code, $match) === 1) {
                $bySlot[(int) $match[1]] = $code;

                continue;
            }

            if (preg_match('/bullet[_-]?point[_-]?(\d+)/i', $code, $match) === 1) {
                $bySlot[(int) $match[1]] = $code;

                continue;
            }

            if (str_contains($label, 'features') && str_contains($label, 'benefit')
                && preg_match('/(\d+)/', $label, $match) === 1) {
                $bySlot[(int) $match[1]] = $code;

                continue;
            }

            if (preg_match('/bullet[^0-9]{0,12}(\d+)/i', $code.' '.$label, $match) === 1) {
                $bySlot[(int) $match[1]] = $code;

                continue;
            }

            if (in_array($codeLower, ['bulletpoints', 'bullet_points', 'bullet-points'], true)
                || (str_contains($label, 'bullet') && ! preg_match('/\d/', $label))) {
                $singleBulletCode = $code;
            }
        }

        ksort($bySlot);

        if ($bySlot !== []) {
            $codes = [];
            for ($i = 1; $i <= 5; $i++) {
                $codes[] = $bySlot[$i] ?? "features_and_benefits_bullet_{$i}";
            }

            return $codes;
        }

        if ($singleBulletCode !== null) {
            return [$singleBulletCode];
        }

        $fallback = $this->miraklMcmConfig('mcm_bullet_fallback_codes');
        if (is_array($fallback) && $fallback !== []) {
            return array_values(array_map('strval', $fallback));
        }

        return ['bulletPoints'];
    }

    /**
     * Operator category tree (H11 GET /api/hierarchies): code, label, parent_code, level.
     * Cached 12h; an empty list is cached briefly so a failing operator does not slow every search.
     *
     * @return list<array{code: string, label: string, parent_code: string, level: int}>
     */
    public function fetchMiraklMcmHierarchies(): array
    {
        if ($this->miraklMcmApiKey() === null || $this->miraklMcmBaseUrl() === '') {
            return [];
        }
        $cacheKey = $this->miraklMcmConfigKey().'_mcm_h11_hierarchies';
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $rows = [];
        try {
            $response = $this->miraklMcmRequest()
                ->timeout(60)
                ->get($this->miraklMcmBaseUrl().'/api/hierarchies', array_merge($this->miraklMcmQueryParams(), ['max_level' => 10]));
            if ($response->status() === 400) {
                // Some operators reject max_level; retry with defaults.
                $response = $this->miraklMcmRequest()->timeout(60)->get($this->miraklMcmBaseUrl().'/api/hierarchies', $this->miraklMcmQueryParams());
            }
            if (! $response->successful()) {
                Log::warning($this->miraklMcmMarketplaceLabel().' H11 hierarchy fetch failed', [
                    'status' => $response->status(),
                    'response' => mb_substr($response->body(), 0, 1000),
                ]);
            } else {
                $list = $response->json('hierarchies');
                foreach (is_array($list) ? $list : [] as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $code = trim((string) ($item['code'] ?? ''));
                    if ($code === '') {
                        continue;
                    }
                    $rows[] = [
                        'code' => $code,
                        'label' => trim((string) ($item['label'] ?? $code)),
                        'parent_code' => trim((string) ($item['parent_code'] ?? '')),
                        'level' => (int) ($item['level'] ?? 0),
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning($this->miraklMcmMarketplaceLabel().' H11 hierarchy fetch error', ['error' => $e->getMessage()]);
        }

        Cache::put($cacheKey, $rows, $rows !== [] ? 43200 : 300);

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    protected function fetchMiraklMcmPm11Attributes(?string $hierarchy = null): array
    {
        $cacheKey = $this->miraklMcmConfigKey().'_mcm_pm11_'.($hierarchy ?? 'all');

        return Cache::remember($cacheKey, 3600, function () use ($hierarchy) {
            $query = array_merge($this->miraklMcmQueryParams(), [
                'all_operator_attributes' => 'true',
            ]);
            if ($hierarchy !== null && $hierarchy !== '') {
                $query['hierarchy'] = $hierarchy;
            }

            $response = $this->miraklMcmRequest()->get($this->miraklMcmBaseUrl().'/api/products/attributes', $query);
            if (! $response->successful()) {
                Log::warning($this->miraklMcmMarketplaceLabel().' PM11 attribute fetch failed', [
                    'hierarchy' => $hierarchy,
                    'status' => $response->status(),
                    'response' => mb_substr($response->body(), 0, 1000),
                ]);

                return [];
            }

            $attributes = $response->json('attributes');

            return is_array($attributes) ? $attributes : [];
        });
    }

    /**
     * @return array{success: bool, message: string, import_id?: int, import_status?: string|null, mcm_verified?: bool}
     */
    protected function pushTitleViaMiraklMcm(string $sku, string $title): array
    {
        $envKey = $this->miraklMcmApiKeyEnvName();
        if ($this->miraklMcmApiKey() === null) {
            return [
                'success' => false,
                'message' => "{$envKey} is required for {$this->miraklMcmMarketplaceLabel()} title push (MCM P41 productName).",
            ];
        }

        $title = mb_substr(trim($title), 0, 150);
        if ($title === '') {
            return ['success' => false, 'message' => 'Title is required for MCM P41.'];
        }

        $sku = $this->resolveMiraklMcmLiveShopSku($sku);

        $hierarchy = $this->resolveMiraklMcmHierarchyForP41($sku);

        $fbCodes = $this->resolveMiraklMcmBulletAttributeCodes($hierarchy);
        $bulletLines = $this->resolveMiraklMcmBulletLinesForP41Row($sku, $fbCodes);

        Log::info("{$this->miraklMcmMarketplaceLabel()} MCM title push (P41)", [
            'sku' => $sku,
            'hierarchy' => $hierarchy,
            'title_chars' => mb_strlen($title),
            'bullet_lines_for_row' => count($bulletLines),
        ]);

        $csv = $this->buildMiraklMcmP41TitleImportCsv($sku, $title, $bulletLines, $fbCodes, $hierarchy);
        $import = $this->importMiraklMcmProductsP41($csv);
        if (! ($import['success'] ?? false)) {
            return $import;
        }

        $importId = (int) ($import['import_id'] ?? 0);
        if ($importId <= 0) {
            return ['success' => false, 'message' => "{$this->miraklMcmMarketplaceLabel()} P41 title import did not return an import_id."];
        }

        $poll = $this->waitForMiraklMcmImportP42($importId, $sku);
        if (! ($poll['success'] ?? false)) {
            return $this->miraklMcmAttachImportErrorReport($poll, $importId, $sku);
        }

        $verify = $this->verifyMiraklMcmTitle($sku, $title);
        $label = $this->miraklMcmMarketplaceLabel();
        $integrationPending = (bool) ($poll['mcm_integration_pending'] ?? false);
        $lockedNotice = $this->miraklMcmP42LockedValuesNotice(
            is_array($poll['response'] ?? null) ? $poll['response'] : null
        );

        if ($integrationPending) {
            $message = trim((string) ($poll['message'] ?? ''));
            if ($message === '') {
                $message = "{$label} P41 title import #{$importId} accepted (SENT) — MCM productName may not show in UI yet.";
            }
        } else {
            $message = "{$label} title updated via MCM P41 (import #{$importId}).";
            if ($verify['verified'] ?? false) {
                $message .= ' MCM productName read-back verified.';
            } else {
                $message .= ' Warning: '.($verify['message'] ?? 'MCM productName not verified yet.');
            }
        }

        if ($lockedNotice !== '' && ! str_contains($message, 'manually edited')) {
            $message .= ' '.$lockedNotice;
        }

        return [
            'success' => true,
            'message' => trim($message),
            'import_id' => $importId,
            'import_status' => $poll['import_status'] ?? null,
            'mcm_verified' => $verify['verified'] ?? false,
            'mcm_integration_pending' => $integrationPending,
        ];
    }

    /**
     * Mirakl Connect HTTP success only updates the Connect catalog. Live Macy's / Best Buy /
     * Purchasing Power seller-portal titles require MCM P41 productName.
     *
     * @param  array{success?: bool, message?: string}  $connect
     * @return array{success: bool, message: string}
     */
    protected function completeMiraklTitlePushWithMcm(string $sku, string $title, array $connect): array
    {
        $label = $this->miraklMcmMarketplaceLabel();
        $envKey = $this->miraklMcmApiKeyEnvName();

        if ($this->miraklMcmApiKey() === null) {
            return [
                'success' => false,
                'message' => "{$envKey} is required for {$label} title push (MCM P41 productName). "
                    .'Mirakl Connect catalog acceptance does not update the live listing title.',
            ];
        }

        if (! filter_var($this->miraklMcmConfig('mcm_title_push', true), FILTER_VALIDATE_BOOL)) {
            return [
                'success' => false,
                'message' => "{$label} MCM P41 title push is disabled.",
            ];
        }

        $mcm = $this->pushTitleViaMiraklMcm($sku, $title);
        if ($mcm['success'] ?? false) {
            if ($connect['success'] ?? false) {
                $mcm['message'] = trim(($mcm['message'] ?? '').' Mirakl Connect upsert also accepted.');
            }

            return $mcm;
        }

        $connectNote = ($connect['success'] ?? false)
            ? ' Connect catalog accepted the change, but that does not update the live listing title.'
            : ' Connect: '.($connect['message'] ?? 'failed');
        $mcm['success'] = false;
        $mcm['message'] = trim(($mcm['message'] ?? "{$label} MCM P41 title failed.").$connectNote);

        return $mcm;
    }

    /**
     * @param  list<string>  $bulletLines
     * @param  list<string>  $attributeCodes
     */
    protected function buildMiraklMcmP41TitleImportCsv(
        string $sku,
        string $title,
        array $bulletLines,
        array $attributeCodes,
        ?string $hierarchy = null
    ): string {
        $maxLen = (int) $this->miraklMcmConfig('features_benefits_max_length', 254);
        $useEnriched = filter_var($this->miraklMcmConfig('mcm_p41_enriched_row', true), FILTER_VALIDATE_BOOL);
        if ($useEnriched && ($hierarchy === null || trim($hierarchy) === '')) {
            Log::info($this->miraklMcmMarketplaceLabel().' MCM P41 title using title-only row (no categoryCode)', [
                'sku' => $sku,
            ]);
            $useEnriched = false;
        }

        $rowValues = $useEnriched
            ? $this->resolveMiraklMcmP41RowValues($sku, $bulletLines, $attributeCodes, $hierarchy, $maxLen, $title)
            : $this->resolveMiraklMcmP41TitleOnlyRowValues($sku, $title, $hierarchy);
        $rowValues = $this->miraklMcmCompleteP41RequiredAttributes($sku, $hierarchy, $rowValues, [
            'title' => $title,
            'bullets' => $bulletLines,
        ]);

        $headers = array_keys($rowValues);
        $values = array_values($rowValues);

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);
        fputcsv($handle, $values);
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        Log::info($this->miraklMcmMarketplaceLabel().' MCM P41 title CSV columns', [
            'sku' => $sku,
            'columns' => $headers,
        ]);

        return "\xEF\xBB\xBF".($csv ?: '');
    }

    /**
     * @return array<string, string>
     */
    protected function resolveMiraklMcmP41TitleOnlyRowValues(string $sku, string $title, ?string $hierarchy): array
    {
        $skuColumn = (string) $this->miraklMcmConfig('mcm_sku_column', 'shopSku');
        $categoryColumn = $this->miraklMcmCategoryColumn();

        $row = [
            $skuColumn => $sku,
            'productName' => mb_substr(trim($title), 0, 150),
        ];
        if ($hierarchy !== null && trim($hierarchy) !== '') {
            $row[$categoryColumn] = trim($hierarchy);
        }

        return $row;
    }

    /**
     * Preserve fnb* on title-only P41 rows (metrics → live MCM product).
     *
     * @param  list<string>  $attributeCodes
     * @return list<string>
     */
    protected function resolveMiraklMcmBulletLinesForP41Row(string $sku, array $attributeCodes): array
    {
        $table = $this->miraklMcmMetricsTable();
        if ($table !== null && Schema::hasTable($table) && Schema::hasColumn($table, 'bullet_points')) {
            $fromMetrics = trim((string) (DB::table($table)->where('sku', $sku)->value('bullet_points') ?? ''));
            $lines = $this->miraklMcmBulletLines($fromMetrics);
            if ($lines !== []) {
                return $lines;
            }
        }

        $product = $this->fetchMiraklMcmProductBySku($sku);
        if ($product === []) {
            return [];
        }

        $lines = [];
        foreach ($attributeCodes as $index => $code) {
            $value = $this->miraklMcmExistingAttributeValue($product, $code);
            if ($value !== null && trim($value) !== '') {
                $lines[$index] = trim($value);
            }
        }

        return array_values(array_filter($lines, fn ($line) => trim((string) $line) !== ''));
    }

    protected function miraklMcmMetricsTable(): ?string
    {
        return match ($this->miraklMcmConfigKey()) {
            'macy' => 'macy_metrics',
            'bestbuy' => 'bestbuy_metrics',
            'purchasingpower' => 'purchasing_power_metrics',
            default => null,
        };
    }

    /**
     * @return array{verified: bool, message: string}
     */
    protected function verifyMiraklMcmTitle(string $sku, string $title): array
    {
        $expected = mb_substr(trim($title), 0, 150);
        $attempts = max(1, (int) $this->miraklMcmConfig('features_benefits_verify_attempts', 4));
        $delaySeconds = max(1, (int) $this->miraklMcmConfig('features_benefits_verify_delay_seconds', 2));

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            if ($attempt > 1) {
                sleep($delaySeconds);
            }

            $actual = $this->fetchMiraklMcmProductName($sku);
            if ($actual !== '' && strcasecmp($expected, $actual) === 0) {
                return ['verified' => true, 'message' => 'MCM productName matches PM title'];
            }
        }

        $actual = $this->fetchMiraklMcmProductName($sku);

        return [
            'verified' => false,
            'message' => $actual === ''
                ? 'MCM productName not returned by seller API (import may still be integrating)'
                : 'MCM productName mismatch after P41',
        ];
    }

    /**
     * @return array{verified: bool, message: string}
     */
    protected function verifyMiraklMcmDescription(string $sku, string $description): array
    {
        $expected = trim($description);
        $attempts = max(1, (int) $this->miraklMcmConfig('features_benefits_verify_attempts', 4));
        $delaySeconds = max(1, (int) $this->miraklMcmConfig('features_benefits_verify_delay_seconds', 2));

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            if ($attempt > 1) {
                sleep($delaySeconds);
            }

            $actual = $this->fetchMiraklMcmProductLongDescription($sku);
            if ($actual !== '' && strcasecmp($expected, $actual) === 0) {
                return ['verified' => true, 'message' => 'MCM productLongDescription matches PM description'];
            }
        }

        $actual = $this->fetchMiraklMcmProductLongDescription($sku);

        return [
            'verified' => false,
            'message' => $actual === ''
                ? 'MCM productLongDescription not returned by seller API (import may still be integrating)'
                : 'MCM productLongDescription mismatch after P41',
        ];
    }

    protected function fetchMiraklMcmProductName(string $sku): string
    {
        foreach ($this->miraklMcmProductLookupCandidates($sku) as $product) {
            $name = trim((string) ($product['product_title'] ?? ''));
            if ($name === '') {
                $name = trim((string) ($this->miraklMcmExistingAttributeValue($product, 'productName') ?? ''));
            }
            if ($name !== '') {
                return mb_substr($name, 0, 150);
            }
        }

        return '';
    }

    protected function fetchMiraklMcmProductLongDescription(string $sku): string
    {
        foreach ($this->miraklMcmProductLookupCandidates($sku) as $product) {
            $desc = trim((string) ($this->miraklMcmExistingAttributeValue($product, 'productLongDescription') ?? ''));
            if ($desc !== '') {
                return $desc;
            }
        }

        return '';
    }

    protected function fetchMiraklMcmMainImage(string $sku): string
    {
        foreach ($this->miraklMcmProductLookupCandidates($sku) as $product) {
            $url = trim((string) ($this->miraklMcmExistingAttributeValue($product, 'mainImage') ?? ''));
            if ($url !== '') {
                return $url;
            }
        }

        $priceRow = $this->fetchMiraklMcmPriceDataRowBySku($sku) ?? $this->fetchMiraklMcmRelatedPriceDataRow($sku);
        $upc = trim((string) ($priceRow?->upc ?? ''));
        if ($upc !== '') {
            try {
                $response = $this->miraklMcmRequest()->get(
                    $this->miraklMcmBaseUrl().'/api/products',
                    [
                        'product_references' => 'UPC|'.rawurlencode($upc),
                        'max' => 1,
                        'all_operator_attributes' => 'true',
                    ]
                );
                if ($response->successful()) {
                    $product = $response->json('products.0');
                    if (is_array($product)) {
                        $url = trim((string) ($this->miraklMcmExistingAttributeValue($product, 'mainImage') ?? ''));
                        if ($url !== '') {
                            return $url;
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::debug($this->miraklMcmMarketplaceLabel().' MCM mainImage UPC lookup failed', [
                    'sku' => $sku,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return '';
    }

    /**
     * @param  list<string>  $imageUrls
     * @return array{verified: bool, message: string}
     */
    protected function verifyMiraklMcmImages(string $sku, array $imageUrls): array
    {
        $expected = trim((string) ($imageUrls[0] ?? ''));
        if ($expected === '') {
            return ['verified' => false, 'message' => 'No main image URL to verify'];
        }

        $attempts = max(1, (int) $this->miraklMcmConfig('features_benefits_verify_attempts', 4));
        $delaySeconds = max(1, (int) $this->miraklMcmConfig('features_benefits_verify_delay_seconds', 2));

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            if ($attempt > 1) {
                sleep($delaySeconds);
            }

            $actual = $this->fetchMiraklMcmMainImage($sku);
            if ($actual !== '' && $this->miraklMcmImageUrlsMatch($expected, $actual)) {
                return ['verified' => true, 'message' => 'MCM mainImage matches PM image'];
            }
        }

        $actual = $this->fetchMiraklMcmMainImage($sku);

        return [
            'verified' => false,
            'message' => $actual === ''
                ? 'MCM mainImage not returned by seller API (import may still be integrating)'
                : 'MCM mainImage mismatch after P41',
        ];
    }

    protected function miraklMcmImageUrlsMatch(string $expected, string $actual): bool
    {
        $expected = trim($expected);
        $actual = trim($actual);
        if ($expected === '' || $actual === '') {
            return false;
        }
        if (strcasecmp($expected, $actual) === 0) {
            return true;
        }

        return basename(parse_url($expected, PHP_URL_PATH) ?: $expected)
            === basename(parse_url($actual, PHP_URL_PATH) ?: $actual);
    }

    /**
     * @param  list<string>  $imageUrls
     * @return array{success: bool, message: string, import_id?: int, import_status?: string|null, mcm_verified?: bool, mcm_integration_pending?: bool}
     */
    protected function pushImagesViaMiraklMcm(string $sku, array $imageUrls): array
    {
        $envKey = $this->miraklMcmApiKeyEnvName();
        if ($this->miraklMcmApiKey() === null) {
            return [
                'success' => false,
                'message' => "{$envKey} is required for {$this->miraklMcmMarketplaceLabel()} image push (MCM P41 mainImage).",
            ];
        }

        $imageUrls = array_slice(array_values(array_filter(array_map('trim', $imageUrls), fn ($s) => $s !== '')), 0, 11);
        if ($imageUrls === []) {
            return ['success' => false, 'message' => 'At least one image URL is required for MCM P41.'];
        }

        $useEnriched = filter_var($this->miraklMcmConfig('mcm_p41_enriched_row', true), FILTER_VALIDATE_BOOL);
        $hierarchy = $this->resolveMiraklMcmHierarchyForP41($sku);
        if ($useEnriched && ($hierarchy === null || trim($hierarchy) === '')) {
            Log::info($this->miraklMcmMarketplaceLabel().' MCM P41 image using image-only row (no categoryCode)', [
                'sku' => $sku,
            ]);
            $useEnriched = false;
        }

        $fbCodes = $this->resolveMiraklMcmBulletAttributeCodes($hierarchy);
        $bulletLines = $this->resolveMiraklMcmBulletLinesForP41Row($sku, $fbCodes);

        Log::info("{$this->miraklMcmMarketplaceLabel()} MCM image push (P41)", [
            'sku' => $sku,
            'hierarchy' => $hierarchy,
            'image_count' => count($imageUrls),
            'bullet_lines_for_row' => count($bulletLines),
        ]);

        $csv = $this->buildMiraklMcmP41ImageImportCsv($sku, $imageUrls, $bulletLines, $fbCodes, $hierarchy);
        $import = $this->importMiraklMcmProductsP41($csv, $this->miraklMcmP41ImportUpdateOptions(true));
        if (! ($import['success'] ?? false)) {
            return $import;
        }

        $importId = (int) ($import['import_id'] ?? 0);
        if ($importId <= 0) {
            return ['success' => false, 'message' => "{$this->miraklMcmMarketplaceLabel()} P41 image import did not return an import_id."];
        }

        $poll = $this->waitForMiraklMcmImportP42($importId, $sku);
        if (! ($poll['success'] ?? false)) {
            return $this->miraklMcmAttachImportErrorReport($poll, $importId, $sku);
        }

        $verify = $this->verifyMiraklMcmImages($sku, $imageUrls);
        $label = $this->miraklMcmMarketplaceLabel();
        $integrationPending = (bool) ($poll['mcm_integration_pending'] ?? false);
        $lockedNotice = $this->miraklMcmP42LockedValuesNotice(
            is_array($poll['response'] ?? null) ? $poll['response'] : null
        );

        if ($integrationPending) {
            $message = trim((string) ($poll['message'] ?? ''));
            if ($message === '') {
                $message = "{$label} P41 image import #{$importId} accepted (SENT) — MCM mainImage may not show in UI yet.";
            }
        } else {
            $message = "{$label} images updated via MCM P41 (import #{$importId}).";
            if ($verify['verified'] ?? false) {
                $message .= ' MCM mainImage read-back verified.';
            } else {
                $message .= ' Warning: '.($verify['message'] ?? 'MCM mainImage not verified yet.');
            }
        }

        if ($lockedNotice !== '' && ! str_contains($message, 'manually edited')) {
            $message .= ' '.$lockedNotice;
        }

        return [
            'success' => true,
            'message' => trim($message),
            'import_id' => $importId,
            'import_status' => $poll['import_status'] ?? null,
            'mcm_verified' => $verify['verified'] ?? false,
            'mcm_integration_pending' => $integrationPending,
        ];
    }

    /**
     * @param  list<string>  $imageUrls
     * @param  list<string>  $bulletLines
     * @param  list<string>  $attributeCodes
     */
    protected function buildMiraklMcmP41ImageImportCsv(
        string $sku,
        array $imageUrls,
        array $bulletLines,
        array $attributeCodes,
        ?string $hierarchy = null
    ): string {
        $maxLen = (int) $this->miraklMcmConfig('features_benefits_max_length', 254);
        $useEnriched = filter_var($this->miraklMcmConfig('mcm_p41_enriched_row', true), FILTER_VALIDATE_BOOL);
        if ($useEnriched && ($hierarchy === null || trim($hierarchy) === '')) {
            Log::info($this->miraklMcmMarketplaceLabel().' MCM P41 image using image-only row (no categoryCode)', [
                'sku' => $sku,
            ]);
            $useEnriched = false;
        }

        $rowValues = $useEnriched
            ? $this->resolveMiraklMcmP41RowValues($sku, $bulletLines, $attributeCodes, $hierarchy, $maxLen, null, null, $imageUrls)
            : $this->resolveMiraklMcmP41ImageOnlyRowValues($sku, $imageUrls, $hierarchy);
        $rowValues = $this->miraklMcmCompleteP41RequiredAttributes($sku, $hierarchy, $rowValues, [
            'images' => $imageUrls,
            'bullets' => $bulletLines,
        ]);

        $headers = array_keys($rowValues);
        $values = array_values($rowValues);

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);
        fputcsv($handle, $values);
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        Log::info($this->miraklMcmMarketplaceLabel().' MCM P41 image CSV columns', [
            'sku' => $sku,
            'columns' => $headers,
        ]);

        return "\xEF\xBB\xBF".($csv ?: '');
    }

    /**
     * @param  list<string>  $imageUrls
     * @return array<string, string>
     */
    protected function resolveMiraklMcmP41ImageOnlyRowValues(string $sku, array $imageUrls, ?string $hierarchy): array
    {
        $skuColumn = (string) $this->miraklMcmConfig('mcm_sku_column', 'shopSku');
        $categoryColumn = $this->miraklMcmCategoryColumn();

        $row = [
            $skuColumn => $sku,
        ];
        if ($hierarchy !== null && trim($hierarchy) !== '') {
            $row[$categoryColumn] = trim($hierarchy);
        }

        return array_merge($row, $this->miraklMcmP41ImageFieldValues($imageUrls));
    }

    /**
     * Minimal enriched P41 row for image-only updates (shop identifiers + image MEDIA columns).
     *
     * @param  list<string>  $imageUrls
     * @return array<string, string>
     */
    protected function resolveMiraklMcmP41ImageRowValues(string $sku, array $imageUrls, ?string $hierarchy): array
    {
        $skuColumn = (string) $this->miraklMcmConfig('mcm_sku_column', 'shopSku');
        $categoryColumn = $this->miraklMcmCategoryColumn();
        $offer = $this->fetchMiraklMcmOfferBySku($sku);
        $priceRow = $this->fetchMiraklMcmPriceDataRowBySku($sku) ?? $this->fetchMiraklMcmRelatedPriceDataRow($sku);
        $connect = $this->resolveMiraklMcmConnectCatalogContext($sku);

        $row = [
            $skuColumn => $sku,
        ];
        if ($hierarchy !== null && trim($hierarchy) !== '') {
            $row[$categoryColumn] = trim($hierarchy);
        }

        $productSku = trim((string) ($offer['product_sku'] ?? $priceRow?->product_sku ?? $connect['product_sku'] ?? ''));
        if ($productSku !== '') {
            $row['pid'] = $productSku;
        }

        $upc = $this->miraklMcmOfferReference($offer, 'UPC')
            ?: trim((string) ($connect['upc'] ?? ''))
            ?: trim((string) ($priceRow?->upc ?? ''));
        if ($upc !== '') {
            $row['UPC'] = $upc;
        }

        foreach ($this->miraklMcmP41ImageFieldValues($imageUrls) as $code => $value) {
            $row[$code] = $value;
        }

        return $row;
    }

    /**
     * Map PM image URLs to Macy MCM P41 columns (mainImage + second/third + images_media:image3-10).
     *
     * @param  list<string>  $urls
     * @return array<string, string>
     */
    protected function miraklMcmP41ImageFieldValues(array $urls): array
    {
        $urls = array_slice(array_values(array_filter(array_map('trim', $urls), fn ($s) => $s !== '')), 0, 11);
        if ($urls === []) {
            return [];
        }

        $row = [
            'mainImage' => $urls[0],
            'secondImage' => $urls[1] ?? $urls[0],
            'thirdImage' => $urls[2] ?? $urls[0],
        ];

        for ($i = 3; $i < count($urls) && $i <= 10; $i++) {
            $row['images_media:image'.$i] = $urls[$i];
        }

        return $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function miraklMcmProductLookupCandidates(string $sku): array
    {
        $products = [];
        $seen = [];

        $primary = $this->fetchMiraklMcmProductBySku($sku);
        if ($primary !== []) {
            $products[] = $primary;
        }

        $priceRow = $this->fetchMiraklMcmPriceDataRowBySku($sku);
        $upc = trim((string) ($priceRow->upc ?? ''));
        if ($upc !== '') {
            $response = $this->miraklMcmRequest()->get(
                $this->miraklMcmBaseUrl().'/api/products',
                array_merge($this->miraklMcmQueryParams(), [
                    'product_references' => 'UPC|'.rawurlencode($upc),
                    'max' => 1,
                    'all_operator_attributes' => 'true',
                ])
            );
            if ($response->successful()) {
                foreach ((array) ($response->json('products') ?? []) as $product) {
                    if (! is_array($product)) {
                        continue;
                    }
                    $key = (string) ($product['product_sku'] ?? $product['shop_sku'] ?? json_encode($product));
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $products[] = $product;
                }
            }
        }

        return $products;
    }

    /**
     * @param  list<string>  $bulletLines
     * @param  list<string>  $attributeCodes
     */
    protected function buildMiraklMcmP41BulletImportCsv(string $sku, array $bulletLines, array $attributeCodes, ?string $hierarchy = null): string
    {
        $maxLen = (int) $this->miraklMcmConfig('features_benefits_max_length', 254);
        $useEnriched = filter_var($this->miraklMcmConfig('mcm_p41_enriched_row', true), FILTER_VALIDATE_BOOL);

        $rowValues = $useEnriched
            ? $this->resolveMiraklMcmP41RowValues($sku, $bulletLines, $attributeCodes, $hierarchy, $maxLen)
            : $this->resolveMiraklMcmP41BulletOnlyRowValues($sku, $bulletLines, $attributeCodes, $hierarchy, $maxLen);
        $rowValues = $this->miraklMcmCompleteP41RequiredAttributes($sku, $hierarchy, $rowValues, [
            'bullets' => $bulletLines,
        ]);

        $headers = array_keys($rowValues);
        $values = array_values($rowValues);

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);
        fputcsv($handle, $values);
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        Log::info($this->miraklMcmMarketplaceLabel().' MCM P41 CSV columns', [
            'sku' => $sku,
            'columns' => $headers,
        ]);

        return "\xEF\xBB\xBF".($csv ?: '');
    }

    /**
     * @return array{success: bool, message: string, import_id?: int, import_status?: string|null, mcm_verified?: bool}
     */
    protected function pushDescriptionViaMiraklMcm(string $sku, string $description): array
    {
        $envKey = $this->miraklMcmApiKeyEnvName();
        if ($this->miraklMcmApiKey() === null) {
            return [
                'success' => false,
                'message' => "{$envKey} is required for {$this->miraklMcmMarketplaceLabel()} description push (MCM P41 productLongDescription).",
            ];
        }

        $description = trim($description);
        if ($description === '') {
            return ['success' => false, 'message' => 'Description is required for MCM P41.'];
        }

        $useEnriched = filter_var($this->miraklMcmConfig('mcm_p41_enriched_row', true), FILTER_VALIDATE_BOOL);
        $hierarchy = $this->resolveMiraklMcmHierarchyForP41($sku);
        if ($useEnriched && ($hierarchy === null || trim($hierarchy) === '')) {
            $label = $this->miraklMcmMarketplaceLabel();

            return [
                'success' => false,
                'message' => "{$label} MCM P41 description skipped: categoryCode could not be resolved for [{$sku}].",
            ];
        }

        $fbCodes = $this->resolveMiraklMcmBulletAttributeCodes($hierarchy);
        $bulletLines = $this->resolveMiraklMcmBulletLinesForP41Row($sku, $fbCodes);

        Log::info("{$this->miraklMcmMarketplaceLabel()} MCM description push (P41)", [
            'sku' => $sku,
            'hierarchy' => $hierarchy,
            'description_chars' => mb_strlen($description),
            'bullet_lines_for_row' => count($bulletLines),
        ]);

        $csv = $this->buildMiraklMcmP41DescriptionImportCsv($sku, $description, $bulletLines, $fbCodes, $hierarchy);
        $import = $this->importMiraklMcmProductsP41($csv);
        if (! ($import['success'] ?? false)) {
            return $import;
        }

        $importId = (int) ($import['import_id'] ?? 0);
        if ($importId <= 0) {
            return ['success' => false, 'message' => "{$this->miraklMcmMarketplaceLabel()} P41 description import did not return an import_id."];
        }

        $poll = $this->waitForMiraklMcmImportP42($importId, $sku);
        if (! ($poll['success'] ?? false)) {
            return $this->miraklMcmAttachImportErrorReport($poll, $importId, $sku);
        }

        $verify = $this->verifyMiraklMcmDescription($sku, $description);
        $label = $this->miraklMcmMarketplaceLabel();
        $integrationPending = (bool) ($poll['mcm_integration_pending'] ?? false);
        $lockedNotice = $this->miraklMcmP42LockedValuesNotice(
            is_array($poll['response'] ?? null) ? $poll['response'] : null
        );

        if ($integrationPending) {
            $message = trim((string) ($poll['message'] ?? ''));
            if ($message === '') {
                $message = "{$label} P41 description import #{$importId} accepted (SENT) — MCM productLongDescription may not show in UI yet.";
            }
        } else {
            $message = "{$label} description updated via MCM P41 (import #{$importId}).";
            if ($verify['verified'] ?? false) {
                $message .= ' MCM description read-back verified.';
            } else {
                $message .= ' Warning: '.($verify['message'] ?? 'MCM description not verified yet.');
            }
        }

        if ($lockedNotice !== '' && ! str_contains($message, 'manually edited')) {
            $message .= ' '.$lockedNotice;
        }

        return [
            'success' => true,
            'message' => trim($message),
            'import_id' => $importId,
            'import_status' => $poll['import_status'] ?? null,
            'mcm_verified' => $verify['verified'] ?? false,
            'mcm_integration_pending' => $integrationPending,
        ];
    }

    /**
     * @param  list<string>  $bulletLines
     * @param  list<string>  $attributeCodes
     */
    protected function buildMiraklMcmP41DescriptionImportCsv(
        string $sku,
        string $description,
        array $bulletLines,
        array $attributeCodes,
        ?string $hierarchy = null
    ): string {
        $maxLen = (int) $this->miraklMcmConfig('features_benefits_max_length', 254);
        $useEnriched = filter_var($this->miraklMcmConfig('mcm_p41_enriched_row', true), FILTER_VALIDATE_BOOL);

        $rowValues = $useEnriched
            ? $this->resolveMiraklMcmP41RowValues($sku, $bulletLines, $attributeCodes, $hierarchy, $maxLen, null, $description)
            : $this->resolveMiraklMcmP41DescriptionOnlyRowValues($sku, $description, $hierarchy);
        $rowValues = $this->miraklMcmCompleteP41RequiredAttributes($sku, $hierarchy, $rowValues, [
            'description' => $description,
            'bullets' => $bulletLines,
        ]);

        $headers = array_keys($rowValues);
        $values = array_values($rowValues);

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);
        fputcsv($handle, $values);
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        Log::info($this->miraklMcmMarketplaceLabel().' MCM P41 description CSV columns', [
            'sku' => $sku,
            'columns' => $headers,
        ]);

        return "\xEF\xBB\xBF".($csv ?: '');
    }

    /**
     * @return array<string, string>
     */
    protected function resolveMiraklMcmP41DescriptionOnlyRowValues(string $sku, string $description, ?string $hierarchy): array
    {
        $skuColumn = (string) $this->miraklMcmConfig('mcm_sku_column', 'shopSku');
        $categoryColumn = $this->miraklMcmCategoryColumn();

        $row = [
            $skuColumn => $sku,
            'productLongDescription' => trim($description),
        ];
        if ($hierarchy !== null && trim($hierarchy) !== '') {
            $row[$categoryColumn] = trim($hierarchy);
        }

        return $row;
    }

    /**
     * @param  list<string>  $bulletLines
     * @param  list<string>  $attributeCodes
     * @return array<string, string>
     */
    protected function resolveMiraklMcmP41BulletOnlyRowValues(
        string $sku,
        array $bulletLines,
        array $attributeCodes,
        ?string $hierarchy,
        int $maxLen
    ): array {
        $skuColumn = (string) $this->miraklMcmConfig('mcm_sku_column', 'shopSku');
        $categoryColumn = $this->miraklMcmCategoryColumn();

        $row = [$skuColumn => $sku];
        if ($hierarchy !== null && trim($hierarchy) !== '') {
            $row[$categoryColumn] = trim($hierarchy);
        }

        return array_merge($row, $this->miraklMcmP41BulletFieldValues($bulletLines, $attributeCodes, $maxLen));
    }

    /**
     * PM11 REQUIRED fields + bullets for Macy MCM operator catalog (P41 operator_format).
     *
     * @param  list<string>  $bulletLines
     * @param  list<string>  $attributeCodes
     * @return array<string, string>
     */
    protected function resolveMiraklMcmP41RowValues(
        string $sku,
        array $bulletLines,
        array $attributeCodes,
        ?string $hierarchy,
        int $maxLen,
        ?string $productNameOverride = null,
        ?string $productLongDescriptionOverride = null,
        ?array $imageUrlOverrides = null
    ): array {
        $offer = $this->fetchMiraklMcmOfferBySku($sku);
        $exactPriceRow = $this->fetchMiraklMcmPriceDataRowBySku($sku);
        $priceRow = $exactPriceRow ?? $this->fetchMiraklMcmRelatedPriceDataRow($sku);
        $connect = $this->resolveMiraklMcmConnectCatalogContext($sku);
        $existing = $this->fetchMiraklMcmProductBySku($sku);
        $variantMaster = $this->fetchMiraklMcmOperatorMasterProductByReferences(
            $this->miraklMcmMasterCatalogVariantReferenceCandidates($sku, $connect, $priceRow)
        );
        $master = $this->fetchMiraklMcmOperatorMasterProduct($sku, $connect, $priceRow);

        if ($hierarchy === null || trim((string) $hierarchy) === '') {
            $hierarchy = $this->miraklMcmCategoryCodeFromProduct($variantMaster) ?: null;
        }
        if ($hierarchy === null || trim((string) $hierarchy) === '') {
            $hierarchy = trim((string) ($connect['mcm_category_code'] ?? '')) ?: null;
        }
        if ($hierarchy === null || trim((string) $hierarchy) === '') {
            $hierarchy = $this->resolveMiraklMcmHierarchyForP41($sku);
        }

        $defaults = (array) $this->miraklMcmConfig('mcm_p41_defaults', []);
        $hierarchyDefaults = (array) ($this->miraklMcmConfig('mcm_p41_hierarchy_defaults', [])[$hierarchy ?? ''] ?? []);
        $skuColumn = (string) $this->miraklMcmConfig('mcm_sku_column', 'shopSku');
        $categoryColumn = $this->miraklMcmCategoryColumn();

        $row = $this->miraklMcmP41RowFromMasterProduct($master);
        if ($this->miraklMcmCategoryCodeFromProduct($variantMaster) === '') {
            unset($row[$categoryColumn], $row['categoryCode']);
        }

        $upc = $this->miraklMcmOfferReference($offer, 'UPC')
            ?: trim((string) ($connect['upc'] ?? ''))
            ?: trim((string) ($exactPriceRow?->upc ?? $priceRow?->upc ?? ''))
            ?: trim((string) ($row['UPC'] ?? ''));
        $productSku = trim((string) (
            $offer['product_sku']
            ?? $exactPriceRow?->product_sku
            ?? $master['product_sku']
            ?? $connect['product_sku']
            ?? $priceRow?->product_sku
            ?? $row['pid']
            ?? ''
        ));

        $row[$skuColumn] = $sku;
        $row[$categoryColumn] = $hierarchy ?? ($row[$categoryColumn] ?? '');
        $row['pid'] = $productSku !== '' ? $productSku : $sku;
        if ($upc !== '') {
            $row['UPC'] = $upc;
        }
        $row['productName'] = $this->miraklMcmFirstNonEmptyString(
            $row['productName'] ?? null,
            $offer['product_title'] ?? null,
            $connect['product_name'] ?? null,
            $exactPriceRow?->product_name ?? null,
            $priceRow?->product_name ?? null
        );
        $row['brand'] = $this->miraklMcmFirstNonEmptyString(
            $row['brand'] ?? null,
            $offer['product_brand'] ?? null,
            $connect['brand'] ?? null,
            $exactPriceRow?->brand ?? null,
            $priceRow?->brand ?? null,
            '5 Core'
        );
        $row['productLongDescription'] = $this->miraklMcmFirstNonEmptyString(
            $row['productLongDescription'] ?? null,
            $offer['product_description'] ?? null,
            $this->miraklMcmExistingAttributeValue($existing, 'productLongDescription')
        );
        $row['msrp'] = $this->miraklMcmFirstNonEmptyString(
            $row['msrp'] ?? null,
            $offer['msrp'] ?? null,
            $exactPriceRow?->original_price ?? null,
            $exactPriceRow?->price ?? null,
            $priceRow?->original_price ?? null,
            $priceRow?->price ?? null
        );

        $row = array_merge($row, $this->miraklMcmP41BulletFieldValues($bulletLines, $attributeCodes, $maxLen));

        foreach ($this->resolveMiraklMcmPm11RequiredAttributeCodes($hierarchy) as $code) {
            if ($this->miraklMcmP41RowValueIsFilled($row, $code)) {
                continue;
            }
            $fromExisting = $this->miraklMcmExistingAttributeValue($existing, $code);
            if ($fromExisting !== null && $fromExisting !== '') {
                $row[$code] = $fromExisting;

                continue;
            }
            if (isset($hierarchyDefaults[$code]) && trim((string) $hierarchyDefaults[$code]) !== '') {
                $row[$code] = trim((string) $hierarchyDefaults[$code]);

                continue;
            }
            if (isset($defaults[$code]) && trim((string) $defaults[$code]) !== '') {
                $row[$code] = trim((string) $defaults[$code]);
            }
        }

        if (trim((string) ($row['productLongDescription'] ?? '')) === '') {
            $row['productLongDescription'] = implode("\n", array_slice($bulletLines, 0, 5));
        }
        if (trim((string) ($row['msrp'] ?? '')) === '') {
            unset($row['msrp']);
        }
        if (trim((string) ($row['productName'] ?? '')) !== '') {
            $row['productName'] = mb_substr(trim((string) $row['productName']), 0, 150);
        }

        $images = $this->resolveMiraklMcmP41ImageUrls($sku, $master !== [] ? $master : $existing);
        if ($images !== []) {
            $row['mainImage'] = $images[0] ?? '';
            $row['secondImage'] = $images[1] ?? $images[0] ?? '';
            $row['thirdImage'] = $images[2] ?? $images[0] ?? '';
        }

        foreach ($this->miraklMcmP41ExtraAttributeValues($sku, $hierarchy, $offer, $priceRow) as $code => $value) {
            if ($this->miraklMcmP41RowValueIsFilled($row, $code)) {
                continue;
            }
            if (trim((string) $value) !== '') {
                $row[$code] = trim((string) $value);
            }
        }

        if ($master !== []) {
            Log::debug($this->miraklMcmMarketplaceLabel().' MCM P41 row enriched from operator master catalog', [
                'sku' => $sku,
                'master_product_sku' => $master['product_sku'] ?? null,
                'master_category' => $this->miraklMcmCategoryCodeFromProduct($master),
                'row_keys_from_master' => array_keys($this->miraklMcmP41RowFromMasterProduct($master)),
            ]);
        }

        if ($productNameOverride !== null && trim($productNameOverride) !== '') {
            $row['productName'] = mb_substr(trim($productNameOverride), 0, 150);
        }
        if ($productLongDescriptionOverride !== null && trim($productLongDescriptionOverride) !== '') {
            $row['productLongDescription'] = trim($productLongDescriptionOverride);
        }
        if ($imageUrlOverrides !== null && $imageUrlOverrides !== []) {
            foreach ($this->miraklMcmP41ImageFieldValues($imageUrlOverrides) as $code => $value) {
                $row[$code] = $value;
            }
        }

        return $row;
    }

    /**
     * Marketplace-specific P41 fields (images, dimensions). Override in MacysApiService.
     *
     * @return array<string, string>
     */
    protected function miraklMcmP41ExtraAttributeValues(string $sku, ?string $hierarchy, array $offer, mixed $priceRow): array
    {
        return [];
    }

    /**
     * When true, every P41 update row (title / bullets / description / images) is completed with the
     * PM11 REQUIRED attributes, filled from the live product, config defaults, then our masters.
     */
    protected function miraklMcmFillRequiredFromMasters(): bool
    {
        return false;
    }

    /**
     * Operator attribute code => semantic (see miraklMcmAttributeSemantic). Codes listed here are
     * always treated as required, so they are sent even when PM11 cannot be reached.
     *
     * @return array<string, string>
     */
    protected function miraklMcmP41AttributeSemanticMap(): array
    {
        return [];
    }

    /**
     * @param  array<string, string>  $row
     * @param  array{title?: string, description?: string, bullets?: list<string>, images?: list<string>}  $context
     * @return array<string, string>
     */
    protected function miraklMcmCompleteP41RequiredAttributes(string $sku, ?string $hierarchy, array $row, array $context = []): array
    {
        if (! $this->miraklMcmFillRequiredFromMasters()) {
            return $row;
        }

        try {
            $attributes = $this->fetchMiraklMcmPm11Attributes($hierarchy);
        } catch (\Throwable $e) {
            $attributes = [];
        }

        $byCode = [];
        foreach ($attributes as $attr) {
            $code = is_array($attr) ? trim((string) ($attr['code'] ?? '')) : '';
            if ($code !== '') {
                $byCode[$code] = $attr;
            }
        }
        $explicit = $this->miraklMcmP41AttributeSemanticMap();

        $row = $this->miraklMcmApplyP41ContentToOperatorCodes($row, $byCode, $explicit, $context);

        $required = [];
        foreach ($byCode as $code => $attr) {
            if (($attr['requirement_level'] ?? '') === 'REQUIRED') {
                $required[$code] = true;
            }
        }
        foreach (array_keys($explicit) as $code) {
            $required[$code] = true;
        }

        $skuColumn = strtolower((string) $this->miraklMcmConfig('mcm_sku_column', 'shopSku'));
        $categoryColumn = strtolower($this->miraklMcmCategoryColumn());
        $defaults = (array) $this->miraklMcmConfig('mcm_p41_defaults', []);
        $hierarchyDefaults = (array) ($this->miraklMcmConfig('mcm_p41_hierarchy_defaults', [])[$hierarchy ?? ''] ?? []);

        $existing = null;
        $master = null;
        $filled = [];
        $missing = [];
        foreach (array_keys($required) as $code) {
            $lower = strtolower($code);
            if ($lower === $skuColumn || $lower === $categoryColumn || str_contains($lower, 'category')
                || $this->miraklMcmP41RowValueIsFilled($row, $code)) {
                continue;
            }

            $attr = $byCode[$code] ?? ['code' => $code, 'label' => ''];
            $semantic = $explicit[$code] ?? $this->miraklMcmAttributeSemantic($attr);

            $value = $this->miraklMcmContextValueForSemantic($semantic, $attr, $context);
            if ($value === '') {
                if ($existing === null) {
                    try {
                        $existing = $this->fetchMiraklMcmProductBySku($sku);
                    } catch (\Throwable $e) {
                        $existing = [];
                    }
                }
                $value = trim((string) ($this->miraklMcmExistingAttributeValue($existing, $code) ?? ''));
            }
            if ($value === '') {
                $value = trim((string) ($hierarchyDefaults[$code] ?? $defaults[$code] ?? ''));
            }
            if ($value === '') {
                $master ??= $this->miraklMcmMasterData($sku);
                $value = $this->miraklMcmMasterValueForSemantic($semantic, $attr, $master, $sku, $context);
            }
            if ($value !== '') {
                $value = $this->miraklMcmCoerceP41AttributeValue($attr, $value, $semantic);
            }

            if ($value !== '') {
                $row[$code] = $value;
                $filled[] = $code;
            } else {
                $missing[] = $code;
            }
        }

        if ($filled !== [] || $missing !== []) {
            Log::info($this->miraklMcmMarketplaceLabel().' MCM P41 required attributes completed from masters', [
                'sku' => $sku,
                'hierarchy' => $hierarchy,
                'filled' => $filled,
                'still_missing' => $missing,
            ]);
        }

        return $row;
    }

    /**
     * Operators with their own codes (e.g. attr-webname / attr-longdescription) ignore our generic
     * productName / productLongDescription / mainImage columns, so copy pushed content onto them.
     *
     * @param  array<string, string>  $row
     * @param  array<string, array<string, mixed>>  $byCode
     * @param  array<string, string>  $explicit
     * @param  array<string, mixed>  $context
     * @return array<string, string>
     */
    protected function miraklMcmApplyP41ContentToOperatorCodes(array $row, array $byCode, array $explicit, array $context): array
    {
        if ($byCode === [] && $explicit === []) {
            return $row;
        }

        $generic = [
            'title' => ['productName'],
            'description' => ['productLongDescription'],
            'image' => ['mainImage', 'secondImage', 'thirdImage'],
        ];
        $hasContext = [
            'title' => trim((string) ($context['title'] ?? '')) !== '',
            'description' => trim((string) ($context['description'] ?? '')) !== '',
            'image' => ! empty($context['images']),
        ];

        $required = array_filter($byCode, fn ($attr) => ($attr['requirement_level'] ?? '') === 'REQUIRED');
        foreach (array_keys($explicit) as $code) {
            $required[$code] ??= $byCode[$code] ?? ['code' => $code, 'label' => ''];
        }

        foreach ($generic as $semantic => $genericCodes) {
            if (! $hasContext[$semantic] || isset($byCode[$genericCodes[0]])) {
                continue;
            }
            // Text content only goes to required/known codes so e.g. "Warranty Description" is not overwritten.
            $candidates = $semantic === 'image' ? $byCode + $required : $required;
            $mapped = false;
            foreach ($candidates as $code => $attr) {
                $codeSemantic = $explicit[$code] ?? $this->miraklMcmAttributeSemantic(is_array($attr) ? $attr : []);
                if ($codeSemantic !== $semantic || in_array($code, $genericCodes, true)) {
                    continue;
                }
                $value = $this->miraklMcmContextValueForSemantic($semantic, is_array($attr) ? $attr : [], $context);
                if ($value !== '') {
                    $row[$code] = $value;
                    $mapped = true;
                }
            }
            if ($mapped && $byCode !== []) {
                foreach ($row as $code => $_) {
                    if (in_array($code, $genericCodes, true)
                        || ($semantic === 'image' && str_starts_with((string) $code, 'images_media:'))) {
                        unset($row[$code]);
                    }
                }
            }
        }

        return $row;
    }

    /**
     * Map a PM11 attribute (code + label) to the master field that can fill it.
     *
     * @param  array<string, mixed>  $attr
     */
    protected function miraklMcmAttributeSemantic(array $attr): string
    {
        $code = (string) ($attr['code'] ?? '');
        $leaf = str_contains($code, '.') ? substr($code, (int) strrpos($code, '.') + 1) : $code;
        $text = strtolower(trim(preg_replace('/[-_.]+/', ' ', $leaf).' '.(string) ($attr['label'] ?? '')));
        if ($text === '') {
            return '';
        }

        $isDimension = (bool) preg_match('/length|width|height|depth|dimension/', $text);
        $rules = [
            'battery_flag' => '/battery.*(embedded|contain|include|covered|install|lithium)|(embedded|contain|include|covered|install|lithium).*battery|\bcbe\b/',
            'weight_unit' => '/weight.*(unit|uom)|(unit|uom).*weight/',
            'dimension_unit' => '/unitofmeasur|unit of measur|\buom\b|dimension.*unit|(length|width|height).*unit/',
            'upc' => '/upc|gtin|\bean\b/',
            'mpn' => '/mfg ?(part|number|no\b|#)|mfgnumber|manufacturer ?part|model ?(number|no\b|#)|modelnumber|\bmpn\b|supplier ?(part|number|no\b|#|sku)|suppliernumber|vendor ?(part|sku|number)|part ?(number|#|no\b)/',
            'brand' => '/brand/',
            'manufacturer' => '/manufacturer|mfg ?name|mfgname/',
            'short_description' => '/short ?desc/',
            'description' => '/desc/',
            'bullets' => '/bullet|feature/',
            'image' => '/image|photo|picture/',
            'title' => '/webname|web name|product ?name|productname|title|item ?name|\bname\b/',
            'weight' => '/weight/',
            'length' => '/length|depth/',
            'width' => '/width/',
            'height' => '/height/',
            'color' => '/colou?r/',
            'country' => '/country|origin/',
            'msrp' => '/msrp|list ?price|retail ?price/',
            'condition' => '/condition/',
        ];
        foreach ($rules as $semantic => $pattern) {
            if ($semantic === 'dimension_unit' && ! $isDimension && ! preg_match('/unitofmeasur|unit of measur|\buom\b/', $text)) {
                continue;
            }
            if ($semantic === 'image' && preg_match('/\balt\b|count|type|text/', $text)) {
                continue;
            }
            if (in_array($semantic, ['description', 'short_description', 'title'], true)
                && preg_match('/warranty|return|shipping|package|battery|colou?r|size|model|category|keyword|seo|meta/', $text)) {
                continue;
            }
            if (preg_match($pattern, $text) === 1) {
                return $semantic;
            }
        }

        return '';
    }

    /** @param  array<string, mixed>  $attr */
    protected function miraklMcmAttributeSlot(array $attr): int
    {
        $text = strtolower((string) ($attr['code'] ?? '').' '.(string) ($attr['label'] ?? ''));
        if (str_contains($text, 'main') || str_contains($text, 'primary')) {
            return 1;
        }
        if (str_contains($text, 'second')) {
            return 2;
        }
        if (str_contains($text, 'third')) {
            return 3;
        }

        return preg_match('/(\d+)/', $text, $m) === 1 ? max(1, (int) $m[1]) : 1;
    }

    /**
     * @param  array<string, mixed>  $attr
     * @param  array<string, mixed>  $context
     */
    protected function miraklMcmContextValueForSemantic(string $semantic, array $attr, array $context): string
    {
        return match ($semantic) {
            'title' => mb_substr(trim((string) ($context['title'] ?? '')), 0, 150),
            'description' => trim((string) ($context['description'] ?? '')),
            'image' => trim((string) (array_values((array) ($context['images'] ?? []))[$this->miraklMcmAttributeSlot($attr) - 1] ?? '')),
            default => '',
        };
    }

    /**
     * Product master / Amazon hydrator / Shopify data for one SKU (cached for the request).
     *
     * @return array<string, mixed>
     */
    protected function miraklMcmMasterData(string $sku): array
    {
        static $cache = [];
        $key = static::class.'|'.$sku;
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $data = [];
        try {
            $data = ListingManagerAmazonHydrator::hydrate($sku);
        } catch (\Throwable $e) {
            Log::warning($this->miraklMcmMarketplaceLabel().' master hydrate failed for P41 fill', ['sku' => $sku, 'error' => $e->getMessage()]);
        }

        try {
            $dims = ListingManagerAmazonHydrator::dimWtPackage($sku);
            foreach (['length', 'width', 'height', 'weight_lb', 'weight_oz'] as $field) {
                $key2 = 'package_'.$field;
                if (trim((string) ($data[$key2] ?? '')) === '' && trim((string) ($dims[$field] ?? '')) !== '') {
                    $data[$key2] = $dims[$field];
                }
            }
        } catch (\Throwable) {
        }

        if (trim((string) ($data['title'] ?? '')) === '' || trim((string) ($data['title'] ?? '')) === $sku) {
            try {
                $data['title'] = trim((string) (ShopifySku::query()->where('sku', $sku)->value('product_title') ?? '')) ?: ($data['title'] ?? '');
            } catch (\Throwable) {
            }
        }
        if (trim((string) ($data['description'] ?? '')) === '') {
            try {
                $data['description'] = ListingManagerAmazonHydrator::shopifyDescription($sku);
            } catch (\Throwable) {
            }
        }
        if (empty($data['images'])) {
            try {
                $data['images'] = ListingManagerAmazonHydrator::publishImageUrls($sku);
            } catch (\Throwable) {
            }
        }

        return $cache[$key] = $data;
    }

    /**
     * @param  array<string, mixed>  $attr
     * @param  array<string, mixed>  $master
     * @param  array<string, mixed>  $context
     */
    protected function miraklMcmMasterValueForSemantic(string $semantic, array $attr, array $master, string $sku, array $context): string
    {
        $str = static fn ($v): string => trim((string) ($v ?? ''));
        $bullets = array_values(array_filter(array_map('trim', (array) (! empty($context['bullets']) ? $context['bullets'] : ($master['bullets'] ?? [])))));
        $title = $str($context['title'] ?? null) ?: $str($master['title'] ?? null);
        $description = $str($context['description'] ?? null) ?: $str($master['description'] ?? null);

        switch ($semantic) {
            case 'title':
                return mb_substr($title, 0, 150);
            case 'description':
                return $description !== '' ? $description : implode("\n", $bullets);
            case 'short_description':
                $plain = trim((string) preg_replace('/\s+/', ' ', strip_tags($description)));

                return mb_substr($plain !== '' ? $plain : $title, 0, 250);
            case 'bullets':
                $slot = preg_match('/\d/', (string) ($attr['code'] ?? '').(string) ($attr['label'] ?? '')) === 1
                    ? $this->miraklMcmAttributeSlot($attr)
                    : 0;

                return $slot > 0 ? ($bullets[$slot - 1] ?? '') : implode("\n", array_slice($bullets, 0, 5));
            case 'brand':
                return $str($master['brand'] ?? null)
                    ?: (trim((string) config('listing_manager.default_brand', '5 Core')) ?: '5 Core');
            case 'manufacturer':
                return $str($master['manufacturer'] ?? null)
                    ?: (trim((string) config('listing_manager.default_manufacturer', '5 Core')) ?: '5 Core');
            case 'upc':
                return $str($master['upc'] ?? null);
            case 'mpn':
            case 'sku':
                return $sku;
            case 'image':
                $images = array_values(array_filter(array_map('trim', (array) (! empty($context['images']) ? $context['images'] : ($master['images'] ?? [])))));

                return $images[$this->miraklMcmAttributeSlot($attr) - 1] ?? '';
            case 'weight':
                $lb = $str($master['package_weight_lb'] ?? null);
                if ($lb === '' && is_numeric($master['package_weight_oz'] ?? null)) {
                    $lb = (string) round(((float) $master['package_weight_oz']) / 16, 2);
                }

                return $lb;
            case 'length':
            case 'width':
            case 'height':
                return $str($master['package_'.$semantic] ?? null);
            case 'weight_unit':
                return 'LB';
            case 'dimension_unit':
                return 'IN';
            case 'color':
                return $str($master['color'] ?? null);
            case 'country':
                return $str($master['country_of_origin'] ?? null);
            case 'msrp':
                return $str($master['list_price'] ?? null) ?: $str($master['price'] ?? null);
            case 'condition':
                return $str($master['condition'] ?? null) ?: 'New';
            case 'battery_flag':
                $haystack = $title.' '.implode(' ', $bullets);

                return preg_match('/\b(rechargeable|lithium|li-?ion|li-?po|built-?in battery)\b/i', $haystack) === 1 ? 'Yes' : 'No';
        }

        return '';
    }

    /**
     * LIST attributes must carry a value code from the operator values list (V11).
     *
     * @param  array<string, mixed>  $attr
     */
    protected function miraklMcmCoerceP41AttributeValue(array $attr, string $value, string $semantic): string
    {
        $type = strtoupper((string) ($attr['type'] ?? ''));
        if (! str_starts_with($type, 'LIST')) {
            return $value;
        }

        $listCode = trim((string) ($attr['values_list'] ?? ''));
        foreach ((array) ($attr['type_parameters'] ?? []) as $param) {
            if (is_array($param) && in_array(strtoupper((string) ($param['name'] ?? '')), ['LIST_CODE', 'VALUES_LIST', 'VALUE_LIST'], true)) {
                $listCode = trim((string) ($param['value'] ?? '')) ?: $listCode;
            }
        }
        $values = $listCode !== '' ? $this->fetchMiraklMcmValuesList($listCode) : [];
        if ($values === []) {
            return $value;
        }

        $norm = static fn (string $s): string => (string) preg_replace('/[^a-z0-9]+/', '', strtolower($s));
        $target = $norm($value);
        foreach ($values as $code => $label) {
            if ($norm((string) $code) === $target || $norm($label) === $target) {
                return (string) $code;
            }
        }

        $booleanSynonyms = match ($target) {
            'no' => ['no', 'n', 'false', '0', 'none'],
            'yes' => ['yes', 'y', 'true', '1'],
            default => [],
        };
        foreach ($values as $code => $label) {
            if (in_array($norm((string) $code), $booleanSynonyms, true) || in_array($norm($label), $booleanSynonyms, true)) {
                return (string) $code;
            }
        }
        if ($semantic === 'battery_flag' || $target === 'no') {
            foreach ($values as $code => $label) {
                if (str_starts_with($norm($label), 'no') || str_starts_with($norm((string) $code), 'no')) {
                    return (string) $code;
                }
            }
        }
        foreach ($values as $code => $label) {
            $l = $norm($label);
            if ($target !== '' && $l !== '' && (str_starts_with($l, $target) || str_starts_with($target, $l))) {
                return (string) $code;
            }
        }

        return '';
    }

    /**
     * V11 GET /api/values_lists?code= — value code => label (cached 12h).
     *
     * @return array<string, string>
     */
    protected function fetchMiraklMcmValuesList(string $listCode): array
    {
        $cacheKey = $this->miraklMcmConfigKey().'_mcm_v11_'.md5($listCode);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $values = (function () use ($listCode) {
            try {
                $response = $this->miraklMcmRequest()->timeout(60)->get(
                    $this->miraklMcmBaseUrl().'/api/values_lists',
                    array_merge($this->miraklMcmQueryParams(), ['code' => $listCode])
                );
                if (! $response->successful()) {
                    return [];
                }
                $out = [];
                foreach ((array) ($response->json('values_lists') ?? []) as $list) {
                    if (! is_array($list) || (isset($list['code']) && strcasecmp((string) $list['code'], $listCode) !== 0)) {
                        continue;
                    }
                    foreach ((array) ($list['values'] ?? []) as $item) {
                        $code = is_array($item) ? trim((string) ($item['code'] ?? '')) : '';
                        if ($code !== '') {
                            $out[$code] = trim((string) ($item['label'] ?? $code));
                        }
                    }
                }

                return $out;
            } catch (\Throwable $e) {
                Log::warning($this->miraklMcmMarketplaceLabel().' V11 values list fetch failed', ['list' => $listCode, 'error' => $e->getMessage()]);

                return [];
            }
        })();
        Cache::put($cacheKey, $values, $values !== [] ? 43200 : 300);

        return $values;
    }

    /**
     * @param  list<string>  $bulletLines
     * @param  list<string>  $attributeCodes
     * @return array<string, string>
     */
    protected function miraklMcmP41BulletFieldValues(array $bulletLines, array $attributeCodes, int $maxLen): array
    {
        $fields = [];
        if (count($attributeCodes) === 1 && strtolower($attributeCodes[0]) === 'bulletpoints') {
            $lines = array_slice($bulletLines, 0, 5);
            $fields[$attributeCodes[0]] = implode("\n", array_map(fn ($line) => mb_substr($line, 0, $maxLen), $lines));

            return $fields;
        }

        for ($i = 0; $i < 5; $i++) {
            $code = $attributeCodes[$i] ?? 'features_and_benefits_bullet_'.($i + 1);
            $line = $bulletLines[$i] ?? '';
            $fields[$code] = $line === '' ? '' : mb_substr($line, 0, $maxLen);
        }

        return $fields;
    }

    /** @return list<string> */
    protected function resolveMiraklMcmPm11RequiredAttributeCodes(?string $hierarchy): array
    {
        $codes = [];
        foreach ($this->fetchMiraklMcmPm11Attributes($hierarchy) as $attr) {
            if (! is_array($attr)) {
                continue;
            }
            if (($attr['requirement_level'] ?? '') === 'REQUIRED') {
                $code = trim((string) ($attr['code'] ?? ''));
                if ($code !== '') {
                    $codes[] = $code;
                }
            }
        }

        return array_values(array_unique($codes));
    }

    /** @return array<string, mixed> */
    protected function fetchMiraklMcmOfferBySku(string $sku): array
    {
        $queries = [
            array_merge($this->miraklMcmQueryParams(), ['sku' => $sku]),
        ];
        $shopId = $this->miraklMcmConfig('shop_id');
        if ($shopId !== null && $shopId !== '') {
            $queries[] = array_merge($this->miraklMcmQueryParams(), [
                'sku' => $sku,
                'shop_id' => (int) $shopId,
            ]);
        }

        try {
            foreach ($queries as $params) {
                $response = $this->miraklMcmRequest()->get(
                    $this->miraklMcmBaseUrl().'/api/offers',
                    $params
                );
                if (! $response->successful()) {
                    continue;
                }

                foreach ($response->json('offers') ?? [] as $offer) {
                    if (! is_array($offer)) {
                        continue;
                    }
                    $shopSku = trim((string) ($offer['shop_sku'] ?? ''));
                    if ($shopSku !== '' && strcasecmp($shopSku, $sku) === 0) {
                        return $offer;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning($this->miraklMcmMarketplaceLabel().' MCM offer fetch failed', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
        }

        return [];
    }

    /**
     * @return list<string>
     */
    protected function miraklMcmSkuCandidates(string $sku): array
    {
        $sku = trim($sku);
        $out = [];
        foreach ([
            $sku,
            str_replace(' ', '', $sku),
            preg_replace('/\s+/', '-', $sku) ?: '',
            strtoupper($sku),
            strtoupper(str_replace(' ', '', $sku)),
        ] as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && ! in_array($candidate, $out, true)) {
                $out[] = $candidate;
            }
        }

        return $out;
    }

    /**
     * Prefer the live MCM shop_sku (DFP05 vs "DFP 05") so P41 does not 404 / miss the offer.
     */
    protected function resolveMiraklMcmLiveShopSku(string $sku): string
    {
        $original = trim($sku);
        if ($original === '') {
            return $original;
        }

        foreach ($this->miraklMcmSkuCandidates($original) as $candidate) {
            $offer = $this->fetchMiraklMcmOfferBySku($candidate);
            $shopSku = trim((string) ($offer['shop_sku'] ?? ''));
            if ($shopSku !== '') {
                return $shopSku;
            }

            $product = $this->fetchMiraklMcmProductBySku($candidate);
            $productSku = trim((string) ($product['shop_sku'] ?? $product['product_sku'] ?? ''));
            if ($productSku !== '') {
                return $productSku;
            }
        }

        return $original;
    }

    protected function fetchMiraklMcmProductCategoryByReference(string $referenceType, string $reference): ?string
    {
        $product = $this->fetchMiraklMcmOperatorMasterProductByReference($referenceType, $reference);

        return $this->miraklMcmCategoryCodeFromProduct($product) ?: null;
    }

    /**
     * Operator Master Product Data Sheet (no shop_sku) for P41 enrichment.
     *
     * @return array<string, mixed>
     */
    protected function fetchMiraklMcmOperatorMasterProduct(string $sku, array $connectContext = [], mixed $priceRow = null): array
    {
        $cacheKey = trim($sku);
        if ($cacheKey !== '' && isset($this->miraklMcmOperatorMasterProductCache[$cacheKey])) {
            return $this->miraklMcmOperatorMasterProductCache[$cacheKey];
        }

        $variantMaster = $this->fetchMiraklMcmOperatorMasterProductByReferences(
            $this->miraklMcmMasterCatalogVariantReferenceCandidates($sku, $connectContext, $priceRow)
        );
        $familyMaster = $this->fetchMiraklMcmOperatorMasterProductByReferences(
            $this->miraklMcmMasterCatalogFamilyReferenceCandidates($sku, $connectContext, $priceRow)
        );

        $merged = $this->miraklMcmMergeOperatorMasterProducts($variantMaster, $familyMaster);

        if ($cacheKey !== '') {
            $this->miraklMcmOperatorMasterProductCache[$cacheKey] = $merged;
        }

        return $merged;
    }

    /**
     * @param  list<array{0: string, 1: string}>  $references
     * @return array<string, mixed>
     */
    protected function fetchMiraklMcmOperatorMasterProductByReferences(array $references): array
    {
        foreach ($references as [$referenceType, $reference]) {
            $product = $this->fetchMiraklMcmOperatorMasterProductByReference($referenceType, $reference);
            if ($product !== []) {
                return $product;
            }
        }

        return [];
    }

    /**
     * Variant-level master (this UPC / shop SKU). Category must come from here when available.
     *
     * @return list<array{0: string, 1: string}>
     */
    protected function miraklMcmMasterCatalogVariantReferenceCandidates(string $sku, array $connectContext = [], mixed $priceRow = null): array
    {
        $seen = [];
        $candidates = [];

        $add = static function (string $type, string $value) use (&$candidates, &$seen): void {
            $type = trim($type);
            $value = trim($value);
            if ($type === '' || $value === '') {
                return;
            }
            $key = strtolower($type).'|'.$value;
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            $candidates[] = [$type, $value];
        };

        $upc = trim((string) ($connectContext['upc'] ?? ''));
        if ($upc !== '') {
            $add('UPC', $upc);
            $add('GTIN', $upc);
            $add('EAN', $upc);
        }

        $variantGroup = trim((string) ($connectContext['variant_group_code'] ?? ''));
        if ($variantGroup !== '') {
            $add('variant_group_code', $variantGroup);
        }

        $sku = trim($sku);
        if ($sku !== '') {
            $add('shop_sku', $sku);
        }

        return $candidates;
    }

    /**
     * Parent / family master (shared pid). Used for pid and hierarchy-specific attrs, not variant category.
     *
     * @return list<array{0: string, 1: string}>
     */
    protected function miraklMcmMasterCatalogFamilyReferenceCandidates(string $sku, array $connectContext = [], mixed $priceRow = null): array
    {
        return $this->miraklMcmMasterCatalogExtraReferenceCandidates($sku, $connectContext, $priceRow);
    }

    /** @return list<array{0: string, 1: string}> */
    protected function miraklMcmMasterCatalogReferenceCandidates(string $sku, array $connectContext = [], mixed $priceRow = null): array
    {
        return array_merge(
            $this->miraklMcmMasterCatalogVariantReferenceCandidates($sku, $connectContext, $priceRow),
            $this->miraklMcmMasterCatalogFamilyReferenceCandidates($sku, $connectContext, $priceRow)
        );
    }

    /**
     * @param  array<string, mixed>  $variantMaster
     * @param  array<string, mixed>  $familyMaster
     * @return array<string, mixed>
     */
    protected function miraklMcmMergeOperatorMasterProducts(array $variantMaster, array $familyMaster): array
    {
        if ($variantMaster === []) {
            if ($familyMaster === []) {
                return [];
            }

            $familyOnly = $familyMaster;
            unset($familyOnly['category_code'], $familyOnly['category_label']);
            $attrs = $this->miraklMcmFlattenProductAttributes($familyOnly);
            unset($attrs['categoryCode']);
            $familyOnly['product_attributes'] = [];
            foreach ($attrs as $code => $value) {
                $familyOnly['product_attributes'][] = ['code' => $code, 'value' => $value];
            }

            return $familyOnly;
        }
        if ($familyMaster === []) {
            return $variantMaster;
        }

        $merged = $familyMaster;
        $merged['product_sku'] = $variantMaster['product_sku'] ?? $familyMaster['product_sku'] ?? null;
        if ($this->miraklMcmCategoryCodeFromProduct($variantMaster) !== '') {
            $merged['category_code'] = $variantMaster['category_code'] ?? null;
            $merged['category_label'] = $variantMaster['category_label'] ?? null;
        }

        $variantAttrs = $this->miraklMcmFlattenProductAttributes($variantMaster);
        $familyAttrs = $this->miraklMcmFlattenProductAttributes($familyMaster);
        $mergedAttrs = array_merge($familyAttrs, $variantAttrs);
        $merged['product_attributes'] = [];
        foreach ($mergedAttrs as $code => $value) {
            $merged['product_attributes'][] = ['code' => $code, 'value' => $value];
        }

        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchMiraklMcmOperatorMasterProductByReference(string $referenceType, string $reference): array
    {
        $reference = trim($reference);
        if ($reference === '') {
            return [];
        }

        try {
            $response = $this->miraklMcmRequest()->get(
                $this->miraklMcmBaseUrl().'/api/products',
                [
                    'product_references' => $referenceType.'|'.rawurlencode($reference),
                    'max' => 5,
                    'all_operator_attributes' => 'true',
                ]
            );
            if (! $response->successful()) {
                return [];
            }

            $operatorMatch = null;
            $fallback = null;

            foreach ($response->json('products') ?? [] as $product) {
                if (! is_array($product)) {
                    continue;
                }

                $shopSku = trim((string) ($product['shop_sku'] ?? ''));
                if ($shopSku === '') {
                    $operatorMatch = $product;

                    break;
                }

                $fallback ??= $product;
            }

            return $operatorMatch ?? $fallback ?? [];
        } catch (\Throwable $e) {
            Log::debug($this->miraklMcmMarketplaceLabel().' MCM operator master product lookup failed', [
                'reference_type' => $referenceType,
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    protected function fetchMiraklMcmOperatorProductCategoryByReference(string $referenceType, string $reference): ?string
    {
        return $this->fetchMiraklMcmProductCategoryByReference($referenceType, $reference);
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    protected function miraklMcmMasterCatalogExtraReferenceCandidates(string $sku, array $connectContext = [], mixed $priceRow = null): array
    {
        return [];
    }

    /**
     * Build P41 columns from operator master attributes (bullets excluded — those come from the push).
     *
     * @param  array<string, mixed>  $master
     * @return array<string, string>
     */
    protected function miraklMcmP41RowFromMasterProduct(array $master): array
    {
        if ($master === []) {
            return [];
        }

        $row = [];
        $category = $this->miraklMcmCategoryCodeFromProduct($master);
        if ($category !== '') {
            $row[$this->miraklMcmCategoryColumn()] = $category;
        }

        $productSku = trim((string) ($master['product_sku'] ?? ''));
        if ($productSku !== '') {
            $row['pid'] = $productSku;
        }

        foreach ($this->miraklMcmFlattenProductAttributes($master) as $code => $value) {
            if ($this->miraklMcmIsBulletAttributeCode($code)) {
                continue;
            }
            if (strcasecmp($code, 'shopSku') === 0) {
                continue;
            }
            $serialized = $this->miraklMcmSerializeAttributeValueForP41($value);
            if ($serialized !== '') {
                $row[$code] = $serialized;
            }
        }

        return $row;
    }

    /** @param  array<string, mixed>  $product */
    protected function miraklMcmCategoryCodeFromProduct(array $product): string
    {
        if ($product === []) {
            return '';
        }

        $code = trim((string) ($product['category_code'] ?? ''));
        if ($code === '') {
            $code = $this->miraklMcmReadProductAttributeValue($product, 'categoryCode');
        }

        return $code;
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    protected function miraklMcmFlattenProductAttributes(array $product): array
    {
        $flat = [];

        foreach (['product_attributes', 'attributes', 'data'] as $key) {
            $attrs = $product[$key] ?? null;
            if (! is_array($attrs)) {
                continue;
            }

            if (! isset($attrs[0]) && $key === 'data') {
                foreach ($attrs as $code => $value) {
                    if (is_string($code) && $code !== '') {
                        $flat[$code] = $value;
                    }
                }

                continue;
            }

            foreach ($attrs as $attr) {
                if (! is_array($attr)) {
                    continue;
                }
                $code = trim((string) ($attr['code'] ?? $attr['id'] ?? ''));
                if ($code === '') {
                    continue;
                }
                $flat[$code] = $attr['value'] ?? '';
            }
        }

        return $flat;
    }

    protected function miraklMcmIsBulletAttributeCode(string $code): bool
    {
        return (bool) preg_match('/^fnb[1-5]$/i', $code)
            || (bool) preg_match('/^features_and_benefits_bullet_[1-5]$/i', $code);
    }

    protected function miraklMcmSerializeAttributeValueForP41(mixed $value): string
    {
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $item) {
                if (is_array($item)) {
                    $item = $item['label'] ?? $item['code'] ?? $item['value'] ?? '';
                }
                $part = trim((string) $item);
                if ($part !== '') {
                    $parts[] = $part;
                }
            }

            return implode('|', array_values(array_unique($parts)));
        }

        return trim((string) $value);
    }

    /** @param  array<string, string>  $row */
    protected function miraklMcmP41RowValueIsFilled(array $row, string $code): bool
    {
        return isset($row[$code]) && trim((string) $row[$code]) !== '';
    }

    protected function miraklMcmFirstNonEmptyString(mixed ...$candidates): string
    {
        foreach ($candidates as $candidate) {
            $value = trim((string) ($candidate ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /** @param  array<string, mixed>  $product */
    protected function miraklMcmReadProductAttributeValue(array $product, string $attributeCode): string
    {
        return $this->miraklMcmProductAttributeValue($product, $attributeCode);
    }

    protected function resolveMiraklMcmPriceDataRow(string $sku): mixed
    {
        return $this->fetchMiraklMcmPriceDataRowBySku($sku)
            ?? $this->fetchMiraklMcmRelatedPriceDataRow($sku);
    }

    protected function fetchMiraklMcmPriceDataRowBySku(string $sku): mixed
    {
        $table = $this->miraklMcmHierarchyTable();
        if ($table === null || ! Schema::hasTable($table)) {
            return null;
        }

        $row = DB::table($table)->where(function ($q) use ($table, $sku) {
            if (Schema::hasColumn($table, 'sku')) {
                $q->where('sku', $sku);
            }
            if (Schema::hasColumn($table, 'offer_sku')) {
                $q->orWhere('offer_sku', $sku);
            }
            if (Schema::hasColumn($table, 'product_sku')) {
                $q->orWhere('product_sku', $sku);
            }
        })->first();

        return $row ?: null;
    }

    protected function fetchMiraklMcmRelatedPriceDataRow(string $sku): mixed
    {
        foreach ($this->miraklMcmRelatedSkuCandidates($sku) as $candidate) {
            $row = $this->fetchMiraklMcmPriceDataRowBySku($candidate);
            if ($row !== null) {
                return $row;
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $offer */
    protected function miraklMcmOfferReference(array $offer, string $type): string
    {
        foreach ((array) ($offer['product_references'] ?? []) as $ref) {
            if (! is_array($ref)) {
                continue;
            }
            if (strcasecmp((string) ($ref['reference_type'] ?? ''), $type) === 0) {
                return trim((string) ($ref['reference'] ?? ''));
            }
        }

        return '';
    }

    /** @param  array<string, mixed>  $product */
    protected function miraklMcmExistingAttributeValue(array $product, string $code): ?string
    {
        if ($product === []) {
            return null;
        }

        return $this->miraklMcmProductAttributeValue($product, $code) ?: null;
    }

    /** @return list<string> */
    protected function resolveMiraklMcmP41ImageUrls(string $sku, array $existingProduct): array
    {
        $urls = [];
        foreach (['mainImage', 'secondImage', 'thirdImage'] as $code) {
            $val = $this->miraklMcmExistingAttributeValue($existingProduct, $code);
            if ($val !== null && $val !== '') {
                $urls[] = $val;
            }
        }

        return array_values(array_unique(array_filter($urls)));
    }

    /**
     * @param  array<string, bool>  $updateOptions
     * @return array{success: bool, message: string, import_id?: int, response?: mixed}
     */
    protected function importMiraklMcmProductsP41(string $csvContent, array $updateOptions = []): array
    {
        $url = $this->miraklMcmBaseUrl().'/api/products/imports';
        $query = $this->miraklMcmQueryParams();
        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        $slug = str_replace(' ', '-', strtolower($this->miraklMcmMarketplaceLabel()));
        $label = $this->miraklMcmMarketplaceLabel();

        $multipart = [
            [
                'name' => 'file',
                'contents' => $csvContent,
                'filename' => "{$slug}-bullets.csv",
                'headers' => ['Content-Type' => 'text/csv; charset=UTF-8'],
            ],
            [
                'name' => 'operator_format',
                'contents' => 'true',
            ],
        ];
        if ($updateOptions !== []) {
            $multipart[] = [
                'name' => 'update_options',
                'contents' => json_encode($updateOptions),
            ];
            if (array_key_exists('allow_locked_values_override', $updateOptions)) {
                $multipart[] = [
                    'name' => 'update_options[allow_locked_values_override]',
                    'contents' => filter_var($updateOptions['allow_locked_values_override'], FILTER_VALIDATE_BOOL) ? 'true' : 'false',
                ];
            }
        }

        try {
            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 120]);
            $guzzleResponse = $client->post($url, [
                'headers' => $this->miraklMcmAuthHeaders(),
                'multipart' => $multipart,
            ]);
            $status = $guzzleResponse->getStatusCode();
            $body = (string) $guzzleResponse->getBody();
            $json = json_decode($body, true);
        } catch (\Throwable $e) {
            Log::warning("{$label} MCM P41 import request failed", ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => "{$label} P41 import failed: ".$e->getMessage(),
            ];
        }

        Log::info("{$label} MCM P41 import response", [
            'status' => $status,
            'response' => is_array($json) ? $json : mb_substr($body, 0, 2000),
        ]);

        if (! in_array($status, [200, 201], true)) {
            return [
                'success' => false,
                'message' => "{$label} P41 import failed (HTTP {$status}): ".mb_substr($body, 0, 1500),
            ];
        }

        $importId = (int) ($json['import_id'] ?? 0);
        if ($importId <= 0) {
            return [
                'success' => false,
                'message' => "{$label} P41 import returned no import_id.",
                'response' => $json,
            ];
        }

        return [
            'success' => true,
            'message' => "{$label} P41 import accepted.",
            'import_id' => $importId,
            'response' => $json,
        ];
    }

    /**
     * @return array{success: bool, message: string, import_status?: string, response?: mixed}
     */
    protected function waitForMiraklMcmImportP42(int $importId, ?string $sku = null): array
    {
        $maxAttempts = max(1, (int) $this->miraklMcmConfig('mcm_import_poll_attempts', 60));
        $delaySeconds = max(1, (int) $this->miraklMcmConfig('mcm_import_poll_delay_seconds', 2));
        // SENT = transformation finished and the file was handed to the operator; operator-side
        // integration can take hours, so only a short grace window is spent waiting for COMPLETE.
        $sentGraceAttempts = max(0, (int) $this->miraklMcmConfig('mcm_import_sent_grace_attempts', 3));
        $terminal = ['COMPLETE', 'FAILED', 'CANCELLED', 'TRANSFORMATION_FAILED'];
        $label = $this->miraklMcmMarketplaceLabel();

        $lastStatus = null;
        $lastBody = null;
        $sentSeen = 0;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            if ($attempt > 1) {
                sleep($delaySeconds);
            }

            $response = $this->miraklMcmRequest()->get(
                $this->miraklMcmBaseUrl().'/api/products/imports/'.$importId,
                $this->miraklMcmQueryParams()
            );

            if (! $response->successful()) {
                continue;
            }

            $json = $response->json();
            $lastBody = $json;
            $status = (string) ($json['import_status'] ?? '');
            $lastStatus = $status;

            if ($status === 'SENT') {
                if ((int) ($json['transform_lines_in_error'] ?? 0) <= 0 && $sentSeen < $sentGraceAttempts) {
                    $sentSeen++;

                    continue;
                }

                return $this->miraklMcmEvaluateSentImport($importId, is_array($json) ? $json : [], $sku);
            }

            if (! in_array($status, $terminal, true)) {
                continue;
            }

            $transformErrors = (int) ($json['transform_lines_in_error'] ?? 0);
            if ($transformErrors > 0) {
                $errorReport = $this->fetchMiraklMcmImportErrorReport($importId, $json);
                if ($errorReport === '' && ($json['has_transformation_error_report'] ?? false) === true) {
                    try {
                        $p47 = $this->miraklMcmRequest()->get(
                            $this->miraklMcmBaseUrl().'/api/products/imports/'.$importId.'/transformation_error_report',
                            $this->miraklMcmQueryParams()
                        );
                        if ($p47->successful()) {
                            $errorReport = trim($p47->body());
                        }
                    } catch (\Throwable $e) {
                        Log::warning("{$label} P47 transformation error report fetch failed", [
                            'import_id' => $importId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $hint = '';
                if (preg_match('/\b1004\b|category could not be identified/i', $errorReport)) {
                    $column = $this->miraklMcmCategoryColumn();
                    $envName = strtoupper(str_replace('purchasingpower', 'purchasing_power', $this->miraklMcmConfigKey())).'_MCM_CATEGORY_COLUMN';
                    $nextColumn = $this->rememberMiraklMcmCategoryColumnRejected($column);
                    $hint = " Mirakl did not recognise \"{$column}\" as the category column.";
                    $hint .= $nextColumn !== null && $nextColumn !== $column
                        ? " Click Publish again — the next attempt sends the category as \"{$nextColumn}\" (or set {$envName} to the operator's category attribute code)."
                        : " Set {$envName} to the operator's category attribute code.";
                } elseif (preg_match('/\b100[156]\b|category is unknown|leaf operator/i', $errorReport)) {
                    $hint = ' Pick a leaf category from the Category tab (the code sent is not a valid leaf on this marketplace).';
                }

                $summary = $this->miraklMcmImportReportSummary($errorReport, $sku);

                return [
                    'success' => false,
                    'message' => "{$label} P41 import #{$importId} {$status} with {$transformErrors} transform error(s)."
                        .($summary !== '' ? ' '.$summary : '').$hint,
                    'import_status' => $status,
                    'response' => $json,
                    'error_report_attached' => true,
                ];
            }

            if ($status === 'COMPLETE') {
                $synced = (int) ($json['integration_details']['products_successfully_synchronized'] ?? 0);
                $invalid = (int) ($json['integration_details']['invalid_products'] ?? 0);
                $rejected = (int) ($json['integration_details']['rejected_products'] ?? 0);

                if ($invalid > 0 || $rejected > 0) {
                    $summary = $this->miraklMcmImportReportSummary(
                        $this->fetchMiraklMcmImportErrorReport($importId, is_array($json) ? $json : null),
                        $sku
                    );

                    return [
                        'success' => false,
                        'message' => "{$label} P41 import #{$importId} COMPLETE with issues (invalid={$invalid}, rejected={$rejected})."
                            .($summary !== '' ? ' '.$summary : ''),
                        'import_status' => $status,
                        'response' => $json,
                        'error_report_attached' => true,
                    ];
                }

                return [
                    'success' => true,
                    'message' => "{$label} P41 import COMPLETE".($synced > 0 ? " ({$synced} product(s) synchronized)." : '.'),
                    'import_status' => $status,
                    'response' => $json,
                ];
            }

            $reason = trim((string) ($json['reason_status'] ?? ''));

            return [
                'success' => false,
                'message' => "{$label} P41 import {$status}".($reason !== '' ? ": {$reason}" : '.'),
                'import_status' => $status,
                'response' => $json,
            ];
        }

        if ($lastStatus === 'SENT') {
            return $this->miraklMcmEvaluateSentImport($importId, is_array($lastBody) ? $lastBody : [], $sku);
        }

        $summary = $this->miraklMcmImportReportSummary(
            $this->fetchMiraklMcmImportErrorReport($importId, is_array($lastBody) ? $lastBody : null),
            $sku
        );

        return [
            'success' => false,
            'message' => "{$label} P41 import #{$importId} still processing after polling"
                .($lastStatus !== null ? " (last status: {$lastStatus})." : '.')
                .($summary !== '' ? ' '.$summary : ' Re-check the import in the seller portal before pushing again.'),
            'import_status' => $lastStatus,
            'response' => $lastBody,
            'error_report_attached' => true,
        ];
    }

    /**
     * SENT: the transformation step is finished, so its error report is final for our row even though
     * the operator has not integrated the product yet.
     *
     * @param  array<string, mixed>  $body
     * @return array{success: bool, message: string, import_status: string, response: mixed, mcm_integration_pending?: bool, error_report_attached: bool}
     */
    protected function miraklMcmEvaluateSentImport(int $importId, array $body, ?string $sku): array
    {
        $label = $this->miraklMcmMarketplaceLabel();
        $transformErrors = (int) ($body['transform_lines_in_error'] ?? 0);
        $report = $this->fetchMiraklMcmImportErrorReport($importId, $body);
        $parsed = $this->miraklMcmParseImportReport($report, $sku);

        if ($parsed['errors'] !== [] || ($transformErrors > 0 && ! $parsed['parsed'])) {
            $summary = $this->miraklMcmImportReportSummary($report, $sku);

            return [
                'success' => false,
                'message' => "{$label} rejected P41 import #{$importId}"
                    .($sku !== null && $sku !== '' ? " for [{$sku}]" : '').'.'
                    .($summary !== '' ? ' '.$summary : " {$transformErrors} line(s) in error."),
                'import_status' => 'SENT',
                'response' => $body,
                'error_report_attached' => true,
            ];
        }

        $message = "{$label} P41 import #{$importId} sent to {$label} for catalog integration (status SENT) — "
            .'no errors on our row; the seller portal updates once the operator finishes integration.';
        if ($parsed['warnings'] !== []) {
            $message .= ' Warnings: '.$this->miraklMcmJoinReportMessages($parsed['warnings']);
        }

        return [
            'success' => true,
            'message' => $message,
            'import_status' => 'SENT',
            'response' => $body,
            'mcm_integration_pending' => true,
            'error_report_attached' => true,
        ];
    }

    /**
     * Parse a P44/P47 CSV report (quoted multi-line cells allowed) and collect the errors/warnings
     * column values for our shop SKU (all rows when no SKU column can be matched).
     *
     * @return array{parsed: bool, errors: list<string>, warnings: list<string>}
     */
    protected function miraklMcmParseImportReport(string $report, ?string $sku): array
    {
        $out = ['parsed' => false, 'errors' => [], 'warnings' => []];
        $report = trim((string) preg_replace('/^\xEF\xBB\xBF/', '', $report));
        if ($report === '') {
            return $out;
        }

        $firstLine = strtok($report, "\n") ?: '';
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $report);
        rewind($handle);
        $header = fgetcsv($handle, 0, $delimiter, '"', '');
        if (! is_array($header)) {
            fclose($handle);

            return $out;
        }
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);

        $errorIdx = $this->miraklMcmFindReportColumn($header, ['errors', 'error', 'error message', 'error_message', 'error-message']);
        $warningIdx = $this->miraklMcmFindReportColumn($header, ['warnings', 'warning', 'warning message', 'warning_message']);
        if ($errorIdx === null && $warningIdx === null) {
            fclose($handle);

            return $out;
        }
        $out['parsed'] = true;

        $skuColumn = strtolower((string) $this->miraklMcmConfig('mcm_sku_column', 'shopSku'));
        $skuIdx = $this->miraklMcmFindReportColumn($header, array_unique([$skuColumn, 'shop-sku', 'shopsku', 'shop_sku', 'sku']));

        $rows = [];
        while (($cells = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            if ($cells === [null] || $cells === []) {
                continue;
            }
            $rows[] = $cells;
        }
        fclose($handle);

        $matched = $rows;
        if ($skuIdx !== null && $sku !== null && trim($sku) !== '') {
            $forSku = array_values(array_filter(
                $rows,
                fn ($cells) => strcasecmp(trim((string) ($cells[$skuIdx] ?? '')), trim($sku)) === 0
            ));
            if ($forSku !== []) {
                $matched = $forSku;
            }
        }

        foreach ($matched as $cells) {
            if ($errorIdx !== null) {
                array_push($out['errors'], ...$this->miraklMcmSplitReportCell((string) ($cells[$errorIdx] ?? '')));
            }
            if ($warningIdx !== null) {
                array_push($out['warnings'], ...$this->miraklMcmSplitReportCell((string) ($cells[$warningIdx] ?? '')));
            }
        }
        $out['errors'] = array_values(array_unique($out['errors']));
        $out['warnings'] = array_values(array_unique($out['warnings']));

        return $out;
    }

    /**
     * @param  list<string>  $header
     * @param  list<string>  $names
     */
    protected function miraklMcmFindReportColumn(array $header, array $names): ?int
    {
        foreach ($names as $name) {
            $idx = array_search(strtolower($name), $header, true);
            if ($idx !== false) {
                return (int) $idx;
            }
        }

        return null;
    }

    /**
     * "1000|msg one,1000|msg two" → ["msg one (1000)", "msg two (1000)"].
     *
     * @return list<string>
     */
    protected function miraklMcmSplitReportCell(string $cell): array
    {
        $cell = trim($cell);
        if ($cell === '') {
            return [];
        }

        $parts = preg_split('/[,\n]\s*(?=\d{2,6}\|)/', $cell) ?: [$cell];
        $out = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (preg_match('/^(\d{2,6})\|(.*)$/s', $part, $m) === 1) {
                $part = trim($m[2]).' ('.$m[1].')';
            }
            $out[] = $part;
        }

        return $out;
    }

    /** @param  list<string>  $messages */
    protected function miraklMcmJoinReportMessages(array $messages, int $maxChars = 1200): string
    {
        return mb_substr(implode('; ', $messages), 0, $maxChars);
    }

    /**
     * Human summary of a P44/P47 report: only the errors/warnings columns, never the full CSV.
     */
    protected function miraklMcmImportReportSummary(string $report, ?string $sku = null): string
    {
        if (trim($report) === '') {
            return '';
        }

        $parsed = $this->miraklMcmParseImportReport($report, $sku);
        if (! $parsed['parsed']) {
            return 'Error report: '.mb_substr(trim($report), 0, 600);
        }

        $parts = [];
        if ($parsed['errors'] !== []) {
            $parts[] = 'Errors: '.$this->miraklMcmJoinReportMessages($parsed['errors']);
        }
        if ($parsed['warnings'] !== []) {
            $parts[] = 'Warnings: '.$this->miraklMcmJoinReportMessages($parsed['warnings'], 600);
        }

        return implode(' ', $parts);
    }

    /**
     * @param  array<string, mixed>  $poll
     * @return array<string, mixed>
     */
    protected function miraklMcmAttachImportErrorReport(array $poll, int $importId, string $sku): array
    {
        if ($poll['error_report_attached'] ?? false) {
            return $poll;
        }

        $summary = $this->miraklMcmImportReportSummary(
            $this->fetchMiraklMcmImportErrorReport($importId, is_array($poll['response'] ?? null) ? $poll['response'] : null),
            $sku
        );
        if ($summary !== '') {
            $poll['message'] = trim(($poll['message'] ?? 'P41 import failed.').' '.$summary);
        }
        $poll['error_report_attached'] = true;

        return $poll;
    }

    /**
     * OF01 — create or update this shop's offer (price + stock) for a product it imported via P41.
     *
     * P41 only fills the operator's product catalog; nothing is sellable until an offer exists,
     * so a Listing Manager publish must run this after the product pushes. The offer references
     * the product by SHOP_SKU (the same shopSku sent in P41), which Mirakl links automatically —
     * status WAITING_SYNCHRONIZATION_PRODUCT means the offer is accepted and waits for the product.
     *
     * @param  array{state_code?: string, product_id?: string, product_id_type?: string, upc?: string, leadtime_to_ship?: int|string, extra?: array<string, scalar>}  $options
     * @return array{success: bool, message: string, import_id?: int, import_status?: string|null, offer_id?: string, offer_pending_product?: bool, response?: mixed}
     */
    public function upsertMiraklMcmOffer(string $sku, float $price, int $quantity, array $options = []): array
    {
        $label = $this->miraklMcmMarketplaceLabel();
        $sku = trim($sku);
        $price = round($price, 2);
        if ($sku === '' || $price <= 0) {
            return ['success' => false, 'message' => "{$label} OF01 needs a SKU and a price > 0."];
        }
        if ($this->miraklMcmApiKey() === null || $this->miraklMcmBaseUrl() === '') {
            return [
                'success' => false,
                'message' => "{$this->miraklMcmApiKeyEnvName()} / MCM base URL are required for the {$label} offer (OF01).",
            ];
        }

        $productIdType = strtoupper(trim((string) ($options['product_id_type']
            ?? $this->miraklMcmConfig('mcm_offer_product_id_type', 'SHOP_SKU'))));
        $productId = trim((string) ($options['product_id'] ?? ''));
        if ($productId === '' && in_array($productIdType, ['UPC', 'EAN', 'GTIN'], true)) {
            $productId = preg_replace('/\D+/', '', (string) ($options['upc'] ?? '')) ?: '';
        }
        if ($productId === '' || $productIdType === 'SHOP_SKU') {
            $productId = $sku;
            $productIdType = 'SHOP_SKU';
        }
        $stateCode = trim((string) ($options['state_code'] ?? ''));
        if ($stateCode === '') {
            $stateCode = $this->resolveMiraklMcmOfferStateCode();
        }

        $row = [
            'sku' => $sku,
            'product-id' => $productId,
            'product-id-type' => $productIdType,
            'price' => number_format($price, 2, '.', ''),
            'quantity' => (string) max(0, $quantity),
            'state' => $stateCode,
            'update-delete' => '',
        ];
        $leadtime = $options['leadtime_to_ship'] ?? $this->miraklMcmConfig('mcm_offer_leadtime_to_ship');
        if ($leadtime !== null && $leadtime !== '') {
            $row['leadtime-to-ship'] = (string) (int) $leadtime;
        }
        // Operator-mandatory offer columns (logistic-class, shipping fields, …) come from config.
        $extra = $this->miraklMcmConfig('mcm_offer_extra_columns', []);
        $extra = is_array($extra) ? $extra : [];
        foreach (array_merge($extra, is_array($options['extra'] ?? null) ? $options['extra'] : []) as $column => $value) {
            $column = trim((string) $column);
            if ($column !== '' && is_scalar($value)) {
                $row[$column] = (string) $value;
            }
        }

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, array_keys($row), ';');
        fputcsv($handle, array_values($row), ';');
        rewind($handle);
        $csv = "\xEF\xBB\xBF".(string) stream_get_contents($handle);
        fclose($handle);

        Log::info("{$label} MCM OF01 offer import", [
            'sku' => $sku,
            'price' => $row['price'],
            'quantity' => $row['quantity'],
            'state' => $stateCode,
            'product_id_type' => $productIdType,
            'columns' => array_keys($row),
        ]);

        $url = $this->miraklMcmBaseUrl().'/api/offers/imports';
        $query = $this->miraklMcmQueryParams();
        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }
        try {
            $response = Http::withoutVerifying()
                ->withHeaders($this->miraklMcmAuthHeaders())
                ->timeout(120)
                ->attach(
                    'file',
                    $csv,
                    'offers-'.preg_replace('/[^A-Za-z0-9]+/', '-', $sku).'.csv',
                    ['Content-Type' => 'text/csv; charset=UTF-8']
                )
                ->post($url, ['import_mode' => 'NORMAL']);
            $status = $response->status();
            $body = $response->body();
        } catch (\Throwable $e) {
            Log::warning("{$label} MCM OF01 request failed", ['sku' => $sku, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => "{$label} OF01 offer import failed: ".$e->getMessage()];
        }

        $json = json_decode($body, true);
        Log::info("{$label} MCM OF01 response", ['sku' => $sku, 'status' => $status, 'response' => is_array($json) ? $json : mb_substr($body, 0, 1000)]);
        if (! in_array($status, [200, 201], true)) {
            return ['success' => false, 'message' => "{$label} OF01 offer import failed (HTTP {$status}): ".mb_substr($body, 0, 800)];
        }
        $importId = (int) ($json['import_id'] ?? 0);
        if ($importId <= 0) {
            return ['success' => false, 'message' => "{$label} OF01 offer import returned no import_id.", 'response' => $json];
        }

        $poll = $this->waitForMiraklMcmOfferImportOF02($importId);
        if (! ($poll['success'] ?? false)) {
            return $poll;
        }

        $offerId = '';
        if (empty($poll['offer_pending_product'])) {
            $offer = $this->fetchMiraklMcmOfferBySku($sku);
            $offerId = trim((string) ($offer['offer_id'] ?? ''));
        }

        return [
            'success' => true,
            'message' => $poll['message'],
            'import_id' => $importId,
            'import_status' => $poll['import_status'] ?? null,
            'offer_id' => $offerId,
            'offer_pending_product' => (bool) ($poll['offer_pending_product'] ?? false),
            'response' => $poll['response'] ?? null,
        ];
    }

    /**
     * OF02 poll. COMPLETE with no line errors → success; WAITING_SYNCHRONIZATION_PRODUCT → the offer
     * is accepted and will go live when the operator integrates the P41 product (can take hours),
     * so it is returned as success + offer_pending_product instead of blocking the publish.
     *
     * @return array{success: bool, message: string, import_status?: string|null, response?: mixed, offer_pending_product?: bool}
     */
    protected function waitForMiraklMcmOfferImportOF02(int $importId): array
    {
        $label = $this->miraklMcmMarketplaceLabel();
        $maxAttempts = max(1, (int) $this->miraklMcmConfig('mcm_offer_import_poll_attempts', 45));
        $delay = max(1, (int) $this->miraklMcmConfig('mcm_offer_import_poll_delay_seconds', 2));
        $lastStatus = null;
        $lastBody = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            if ($attempt > 1) {
                sleep($delay);
            }
            try {
                $response = $this->miraklMcmRequest()->get(
                    $this->miraklMcmBaseUrl().'/api/offers/imports/'.$importId,
                    $this->miraklMcmQueryParams()
                );
            } catch (\Throwable $e) {
                Log::warning("{$label} OF02 poll failed", ['import_id' => $importId, 'error' => $e->getMessage()]);
                continue;
            }
            if (! $response->successful()) {
                continue;
            }
            $json = $response->json();
            $lastBody = $json;
            $status = strtoupper((string) ($json['status'] ?? ''));
            $lastStatus = $status;

            if ($status === 'WAITING_SYNCHRONIZATION_PRODUCT') {
                return [
                    'success' => true,
                    'message' => "{$label} offer import #{$importId} accepted; it goes live once {$label} integrates the product (WAITING_SYNCHRONIZATION_PRODUCT).",
                    'import_status' => $status,
                    'response' => $json,
                    'offer_pending_product' => true,
                ];
            }
            if (! in_array($status, ['COMPLETE', 'FAILED'], true)) {
                continue;
            }

            $errors = (int) ($json['lines_in_error'] ?? 0);
            $pending = (int) ($json['lines_in_pending'] ?? 0);
            if ($status === 'FAILED' || $errors > 0) {
                $report = $this->fetchMiraklMcmOfferImportErrorReport($importId, $json);
                $reason = trim((string) ($json['reason_status'] ?? ''));

                return [
                    'success' => false,
                    'message' => "{$label} offer import #{$importId} {$status}"
                        .($errors > 0 ? " with {$errors} line error(s)" : '')
                        .($reason !== '' ? ": {$reason}" : '.')
                        .($report !== '' ? ' Error report: '.mb_substr($report, 0, 1200) : ''),
                    'import_status' => $status,
                    'response' => $json,
                ];
            }

            $inserted = (int) ($json['offer_inserted'] ?? 0);
            $updated = (int) ($json['offer_updated'] ?? 0);
            if ($pending > 0 && $inserted === 0 && $updated === 0) {
                return [
                    'success' => true,
                    'message' => "{$label} offer import #{$importId} accepted; the offer is pending product integration.",
                    'import_status' => $status,
                    'response' => $json,
                    'offer_pending_product' => true,
                ];
            }

            return [
                'success' => true,
                'message' => "{$label} offer ".($inserted > 0 ? 'created' : 'updated')." via OF01 (import #{$importId}).",
                'import_status' => $status,
                'response' => $json,
                'offer_pending_product' => false,
            ];
        }

        // Still queued on Mirakl's side; the import keeps running without us.
        return [
            'success' => true,
            'message' => "{$label} offer import #{$importId} submitted (still ".($lastStatus ?: 'WAITING').' after polling); check the Offers import log on the seller portal if it does not go live.',
            'import_status' => $lastStatus,
            'response' => $lastBody,
            'offer_pending_product' => true,
        ];
    }

    /**
     * OF03 — error report CSV for an offer import.
     *
     * @param  array<string, mixed>|null  $importStatus
     */
    protected function fetchMiraklMcmOfferImportErrorReport(int $importId, ?array $importStatus = null): string
    {
        if (is_array($importStatus) && array_key_exists('has_error_report', $importStatus) && ! $importStatus['has_error_report']) {
            return '';
        }
        try {
            $response = $this->miraklMcmRequest()->get(
                $this->miraklMcmBaseUrl().'/api/offers/imports/'.$importId.'/error_report',
                $this->miraklMcmQueryParams()
            );
            if ($response->successful()) {
                return trim($response->body());
            }
        } catch (\Throwable $e) {
            Log::warning($this->miraklMcmMarketplaceLabel().' OF03 error report fetch failed', [
                'import_id' => $importId,
                'error' => $e->getMessage(),
            ]);
        }

        return '';
    }

    /**
     * Offer state code for OF01 ("11" = New on stock Mirakl). Config wins, else copy the state the
     * shop already uses on a live offer so operator-specific codes are respected.
     */
    protected function resolveMiraklMcmOfferStateCode(): string
    {
        $configured = trim((string) $this->miraklMcmConfig('mcm_offer_state_code', ''));
        if ($configured !== '') {
            return $configured;
        }
        $cacheKey = $this->miraklMcmConfigKey().'_mcm_offer_state_code';
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }
        try {
            $response = $this->miraklMcmRequest()->get(
                $this->miraklMcmBaseUrl().'/api/offers',
                array_merge($this->miraklMcmQueryParams(), ['max' => 1])
            );
            if ($response->successful()) {
                foreach ($response->json('offers') ?? [] as $offer) {
                    $code = trim((string) ($offer['state_code'] ?? ''));
                    if ($code !== '') {
                        Cache::put($cacheKey, $code, now()->addDay());

                        return $code;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning($this->miraklMcmMarketplaceLabel().' offer state lookup failed', ['error' => $e->getMessage()]);
        }

        return '11';
    }

    /**
     * @param  array<string, mixed>|null  $importStatus
     */
    protected function miraklMcmP42AllowsLockedOverride(?array $importStatus): ?bool
    {
        $options = $importStatus['update_options'] ?? null;
        if (! is_array($options) || ! array_key_exists('allow_locked_values_override', $options)) {
            return null;
        }

        return filter_var($options['allow_locked_values_override'], FILTER_VALIDATE_BOOL);
    }

    /**
     * @return array<string, bool>
     */
    protected function miraklMcmP41ImportUpdateOptions(bool $forImagePush = false): array
    {
        $allowOverride = $forImagePush
            ? filter_var($this->miraklMcmConfig('mcm_image_allow_locked_override', true), FILTER_VALIDATE_BOOL)
            : filter_var($this->miraklMcmConfig('mcm_allow_locked_override', false), FILTER_VALIDATE_BOOL);

        if (! $allowOverride) {
            return [];
        }

        return ['allow_locked_values_override' => true];
    }

    /**
     * Macy MCM "Protected value" tooltip: manually edited fields are not overwritten by
     * Catalog Transformer or automatic catalog imports (including seller P41).
     *
     * @param  array<string, mixed>|null  $importStatus
     */
    protected function miraklMcmP42LockedValuesNotice(?array $importStatus): string
    {
        if ($this->miraklMcmP42AllowsLockedOverride($importStatus) !== false) {
            return '';
        }

        return 'Protected value fields were manually edited in MCM and will not be overwritten by P41/API imports '
            .'(Catalog Transformer protection). Update those slots in the MCM UI, clear the manual edit/protection '
            .'in seller portal if available, or ask Macy to reset protection for bulk API sync.';
    }

    /** @param  array<string, mixed>|null  $importStatus */
    protected function fetchMiraklMcmImportErrorReport(int $importId, ?array $importStatus = null): string
    {
        $endpoints = [];
        if (($importStatus['has_transformation_error_report'] ?? false) === true
            || (int) ($importStatus['transform_lines_in_error'] ?? 0) > 0
            || ($importStatus['import_status'] ?? '') === 'SENT') {
            $endpoints[] = 'transformation_error_report';
        }
        $endpoints[] = 'error_report';

        foreach ($endpoints as $suffix) {
            try {
                $response = $this->miraklMcmRequest()->get(
                    $this->miraklMcmBaseUrl().'/api/products/imports/'.$importId.'/'.$suffix,
                    $this->miraklMcmQueryParams()
                );

                if ($response->successful()) {
                    $body = trim($response->body());
                    if ($body !== '') {
                        return $body;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning($this->miraklMcmMarketplaceLabel()." {$suffix} fetch failed", [
                    'import_id' => $importId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return '';
    }

    /**
     * @param  list<string>  $bulletLines
     * @param  list<string>  $attributeCodes
     * @return array{verified: bool, message: string}
     */
    protected function verifyMiraklMcmBullets(string $sku, array $bulletLines, array $attributeCodes): array
    {
        $maxLen = (int) $this->miraklMcmConfig('features_benefits_max_length', 254);
        $attempts = max(1, (int) $this->miraklMcmConfig('features_benefits_verify_attempts', 4));
        $delaySeconds = max(1, (int) $this->miraklMcmConfig('features_benefits_verify_delay_seconds', 2));

        $lines = array_slice($bulletLines, 0, 5);
        $mismatches = [];

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            if ($attempt > 1) {
                sleep($delaySeconds);
            }

            $product = $this->fetchMiraklMcmProductBySku($sku);
            if ($product === []) {
                continue;
            }

            if (count($attributeCodes) === 1 && strtolower($attributeCodes[0]) === 'bulletpoints') {
                $expected = implode("\n", array_map(fn ($l) => mb_substr($l, 0, $maxLen), $lines));
                $actual = $this->miraklMcmProductAttributeValue($product, $attributeCodes[0]);
                if ($expected !== '' && stripos($actual, mb_substr($lines[0] ?? '', 0, 40)) === false) {
                    return ['verified' => false, 'message' => 'MCM bulletPoints read-back mismatch'];
                }

                return ['verified' => true, 'message' => 'MCM bulletPoints verified'];
            }

            $mismatches = [];
            foreach ($lines as $index => $line) {
                $code = $attributeCodes[$index] ?? 'features_and_benefits_bullet_'.($index + 1);
                $expected = mb_substr($line, 0, $maxLen);
                $actual = $this->miraklMcmProductAttributeValue($product, $code);
                if ($expected !== '' && strcasecmp($expected, $actual) !== 0) {
                    $mismatches[] = $index + 1;
                }
            }

            if ($mismatches === []) {
                return ['verified' => true, 'message' => 'MCM bullets match PM on read-back'];
            }
        }

        $matched = max(0, count($lines) - count($mismatches));

        return [
            'verified' => false,
            'partial' => $matched > 0,
            'message' => 'MCM bullet slots '.implode(', ', $mismatches).' mismatch after P41'
                .($matched > 0 ? " ({$matched} slot(s) did update)" : '')
                .' — MCM may still be processing',
        ];
    }

    /** @return array<string, mixed> */
    protected function fetchMiraklMcmProductBySku(string $sku): array
    {
        $productSku = $this->resolveMiraklMcmProductSkuForShopSku($sku);
        $referenceQueries = [
            'shop_sku|'.rawurlencode($sku),
        ];
        if ($productSku !== null && strcasecmp($productSku, $sku) !== 0) {
            $referenceQueries[] = 'product_sku|'.rawurlencode($productSku);
        }

        foreach ($referenceQueries as $productReferences) {
            $response = $this->miraklMcmRequest()->get(
                $this->miraklMcmBaseUrl().'/api/products',
                array_merge($this->miraklMcmQueryParams(), [
                    'product_references' => $productReferences,
                    'max' => 1,
                    'all_operator_attributes' => 'true',
                ])
            );

            if (! $response->successful()) {
                continue;
            }

            $body = $response->json();
            $products = $body['products'] ?? $body['data'] ?? [];
            if ($products === [] && isset($body[0]) && is_array($body[0])) {
                $products = $body;
            }

            foreach ((array) $products as $product) {
                if (! is_array($product)) {
                    continue;
                }
                $shopSku = trim((string) ($product['shop_sku'] ?? ''));
                $resolvedProductSku = trim((string) ($product['product_sku'] ?? ''));
                if ($shopSku !== '' && strcasecmp($shopSku, $sku) === 0) {
                    return $product;
                }
                if ($resolvedProductSku !== '' && strcasecmp($resolvedProductSku, $sku) === 0) {
                    return $product;
                }
            }

            if (is_array($products[0] ?? null)) {
                return $products[0];
            }
        }

        return [];
    }

    protected function resolveMiraklMcmProductSkuForShopSku(string $sku): ?string
    {
        $table = $this->miraklMcmHierarchyTable();
        if ($table === null || ! Schema::hasTable($table) || ! Schema::hasColumn($table, 'product_sku')) {
            return null;
        }

        $row = DB::table($table)->where(function ($q) use ($table, $sku) {
            if (Schema::hasColumn($table, 'sku')) {
                $q->where('sku', $sku);
            }
            if (Schema::hasColumn($table, 'offer_sku')) {
                $q->orWhere('offer_sku', $sku);
            }
            if (Schema::hasColumn($table, 'product_sku')) {
                $q->orWhere('product_sku', $sku);
            }
        })->first();

        $productSku = trim((string) ($row->product_sku ?? ''));

        return $productSku !== '' ? $productSku : null;
    }

    private function miraklMcmProductAttributeValue(array $product, string $attributeCode): string
    {
        $attrs = $product['product_attributes'] ?? $product['attributes'] ?? [];

        if (is_array($attrs) && ! isset($attrs[0]) && isset($attrs[$attributeCode])) {
            return $this->miraklMcmSerializeAttributeValueForP41($attrs[$attributeCode]);
        }

        foreach ((array) $attrs as $attr) {
            if (! is_array($attr)) {
                continue;
            }
            $code = (string) ($attr['code'] ?? $attr['id'] ?? '');
            if (strcasecmp($code, $attributeCode) === 0) {
                return $this->miraklMcmSerializeAttributeValueForP41($attr['value'] ?? '');
            }
        }

        $flat = $this->miraklMcmFlattenProductAttributes($product);
        if (isset($flat[$attributeCode])) {
            return $this->miraklMcmSerializeAttributeValueForP41($flat[$attributeCode]);
        }

        return '';
    }
}
