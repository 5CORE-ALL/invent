<?php

namespace App\Services\Support\Concerns;

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
                    .'(no live Macy offer/product category, Connect mapping, or macys_price_data). '
                    .'Create the Macy listing or add macys_price_data before P41.',
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

        $poll = $this->waitForMiraklMcmImportP42($importId);
        if (! ($poll['success'] ?? false)) {
            $errorReport = $this->fetchMiraklMcmImportErrorReport(
                $importId,
                is_array($poll['response'] ?? null) ? $poll['response'] : null
            );
            if ($errorReport !== '') {
                $poll['message'] = ($poll['message'] ?? 'P41 import failed.')
                    .' Error report: '.mb_substr($errorReport, 0, 1500);
            }

            return $poll;
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

    /** @return array<string, int|string> */
    protected function miraklMcmQueryParams(): array
    {
        $shopId = $this->miraklMcmConfig('shop_id');
        if ($shopId === null || $shopId === '') {
            return [];
        }

        return ['shop_id' => (int) $shopId];
    }

    /** @return list<string> */
    protected function miraklMcmBulletLines(string $bulletPoints): array
    {
        return array_values(array_filter(array_map(
            fn ($line) => trim((string) $line),
            preg_split('/\r\n|\r|\n/', trim($bulletPoints)) ?: []
        ), fn ($line) => $line !== ''));
    }

    protected function resolveMiraklMcmHierarchyForSku(string $sku): ?string
    {
        return $this->resolveMiraklMcmHierarchyForP41($sku);
    }

    protected function resolveMiraklMcmHierarchyForP41(string $sku): ?string
    {
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

        $poll = $this->waitForMiraklMcmImportP42($importId);
        if (! ($poll['success'] ?? false)) {
            $errorReport = $this->fetchMiraklMcmImportErrorReport(
                $importId,
                is_array($poll['response'] ?? null) ? $poll['response'] : null
            );
            if ($errorReport !== '') {
                $poll['message'] = ($poll['message'] ?? 'P41 title import failed.')
                    .' Error report: '.mb_substr($errorReport, 0, 1500);
            }

            return $poll;
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
        $categoryColumn = (string) $this->miraklMcmConfig('mcm_category_column', 'categoryCode');

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
            $label = $this->miraklMcmMarketplaceLabel();

            return [
                'success' => false,
                'message' => "{$label} MCM P41 image push skipped: categoryCode could not be resolved for [{$sku}].",
            ];
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

        $poll = $this->waitForMiraklMcmImportP42($importId);
        if (! ($poll['success'] ?? false)) {
            $errorReport = $this->fetchMiraklMcmImportErrorReport(
                $importId,
                is_array($poll['response'] ?? null) ? $poll['response'] : null
            );
            if ($errorReport !== '') {
                $poll['message'] = ($poll['message'] ?? 'P41 image import failed.')
                    .' Error report: '.mb_substr($errorReport, 0, 1500);
            }

            return $poll;
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

        $rowValues = $useEnriched
            ? $this->resolveMiraklMcmP41RowValues($sku, $bulletLines, $attributeCodes, $hierarchy, $maxLen, null, null, $imageUrls)
            : $this->resolveMiraklMcmP41ImageOnlyRowValues($sku, $imageUrls, $hierarchy);

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
        $categoryColumn = (string) $this->miraklMcmConfig('mcm_category_column', 'categoryCode');

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
        $categoryColumn = (string) $this->miraklMcmConfig('mcm_category_column', 'categoryCode');
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

        $poll = $this->waitForMiraklMcmImportP42($importId);
        if (! ($poll['success'] ?? false)) {
            $errorReport = $this->fetchMiraklMcmImportErrorReport(
                $importId,
                is_array($poll['response'] ?? null) ? $poll['response'] : null
            );
            if ($errorReport !== '') {
                $poll['message'] = ($poll['message'] ?? 'P41 description import failed.')
                    .' Error report: '.mb_substr($errorReport, 0, 1500);
            }

            return $poll;
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
        $categoryColumn = (string) $this->miraklMcmConfig('mcm_category_column', 'categoryCode');

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
        $categoryColumn = (string) $this->miraklMcmConfig('mcm_category_column', 'categoryCode');

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
        $categoryColumn = (string) $this->miraklMcmConfig('mcm_category_column', 'categoryCode');

        $row = $this->miraklMcmP41RowFromMasterProduct($master);
        if ($this->miraklMcmCategoryCodeFromProduct($variantMaster) === '') {
            unset($row['categoryCode']);
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
            $row['categoryCode'] = $category;
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
    protected function waitForMiraklMcmImportP42(int $importId): array
    {
        $maxAttempts = max(1, (int) $this->miraklMcmConfig('mcm_import_poll_attempts', 60));
        $delaySeconds = max(1, (int) $this->miraklMcmConfig('mcm_import_poll_delay_seconds', 2));
        $terminal = ['COMPLETE', 'FAILED', 'CANCELLED', 'TRANSFORMATION_FAILED'];
        $label = $this->miraklMcmMarketplaceLabel();

        $lastStatus = null;
        $lastBody = null;

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

                return [
                    'success' => false,
                    'message' => "{$label} P41 import {$status} with {$transformErrors} transform error(s)."
                        .($errorReport !== '' ? ' Error report: '.mb_substr($errorReport, 0, 1500) : ''),
                    'import_status' => $status,
                    'response' => $json,
                ];
            }

            if ($status === 'COMPLETE') {
                $synced = (int) ($json['integration_details']['products_successfully_synchronized'] ?? 0);
                $invalid = (int) ($json['integration_details']['invalid_products'] ?? 0);
                $rejected = (int) ($json['integration_details']['rejected_products'] ?? 0);

                if ($invalid > 0 || $rejected > 0) {
                    return [
                        'success' => false,
                        'message' => "{$label} P41 import COMPLETE with issues (invalid={$invalid}, rejected={$rejected}).",
                        'import_status' => $status,
                        'response' => $json,
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

        $sentPending = $this->miraklMcmImportSentTransformSuccessResult($lastStatus, is_array($lastBody) ? $lastBody : null, $label);
        if ($sentPending !== null) {
            return $sentPending;
        }

        return [
            'success' => false,
            'message' => "{$label} P41 import polling timed out"
                .($lastStatus !== null ? " (last status: {$lastStatus})." : '.'),
            'import_status' => $lastStatus,
            'response' => $lastBody,
        ];
    }

    /**
     * Mirakl often leaves Connect-sourced P41 imports at SENT after successful transform.
     * Treat that as accepted-but-pending so callers can surface accurate MCM UI expectations.
     *
     * @param  array<string, mixed>|null  $lastBody
     * @return array{success: bool, message: string, import_status?: string, response?: mixed, mcm_integration_pending?: bool}|null
     */
    protected function miraklMcmImportSentTransformSuccessResult(?string $lastStatus, ?array $lastBody, string $label): ?array
    {
        if ($lastStatus !== 'SENT' || ! is_array($lastBody)) {
            return null;
        }

        $transformOk = (int) ($lastBody['transform_lines_in_success'] ?? 0);
        $transformErr = (int) ($lastBody['transform_lines_in_error'] ?? 0);
        if ($transformOk <= 0 || $transformErr > 0) {
            return null;
        }

        return [
            'success' => true,
            'message' => "{$label} P41 import accepted (status SENT; Mirakl is still integrating seller catalog — "
                .'MCM Specifications tab may not update until operator review completes).',
            'import_status' => $lastStatus,
            'response' => $lastBody,
            'mcm_integration_pending' => true,
        ];
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
            || (int) ($importStatus['transform_lines_in_error'] ?? 0) > 0) {
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
