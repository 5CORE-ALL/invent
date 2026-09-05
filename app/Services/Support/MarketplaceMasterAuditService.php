<?php

namespace App\Services\Support;

use App\Models\Ebay2Metric;
use App\Models\Ebay3Metric;
use App\Models\EbayMetric;
use App\Models\ProductMaster;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Safe pre-flight checks for Product Master marketplace pushes (no writes unless explicitly requested).
 */
class MarketplaceMasterAuditService
{
    public function __construct(
        private MarketplaceApiConfigService $configService,
    ) {}

    /**
     * @return list<string>
     */
    public function bulletMarketplaces(): array
    {
        return array_keys(ProductMasterMarketplaceMaps::bulletServiceMap());
    }

    /**
     * Audit bullet-point push readiness for one or all marketplaces.
     *
     * @return array<string, array<string, mixed>>
     */
    public function auditBullet(
        ?string $sku = null,
        ?string $marketplaceFilter = null,
        bool $includeSamplePayload = true,
    ): array {
        $results = [];
        $marketplaces = $this->bulletMarketplaces();

        if ($marketplaceFilter !== null && $marketplaceFilter !== '') {
            $key = $this->configService->resolveKey($marketplaceFilter);
            $marketplaces = in_array($key, $marketplaces, true) ? [$key] : [$marketplaceFilter];
        }

        foreach ($marketplaces as $marketplace) {
            $results[$marketplace] = $this->auditBulletMarketplace($marketplace, $sku, $includeSamplePayload);
        }

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    private function auditBulletMarketplace(string $marketplace, ?string $sku, bool $includeSamplePayload): array
    {
        $tableMap = ProductMasterMarketplaceMaps::bulletTableMap();
        $resolver = app(MarketplaceMetricsTableResolver::class);
        $serviceMap = ProductMasterMarketplaceMaps::bulletServiceMap();
        $serviceClass = $serviceMap[$marketplace] ?? null;
        $table = $resolver->table($marketplace) ?? ($tableMap[$marketplace] ?? null);

        $row = [
            'marketplace' => $marketplace,
            'credentials_configured' => $this->configService->isConfigured($marketplace),
            'service_class' => $serviceClass,
            'service_exists' => $serviceClass && class_exists($serviceClass),
            'has_update_method' => false,
            'metrics_table' => $table,
            'metrics_table_exists' => $table && Schema::hasTable($table),
            'ready' => false,
            'issues' => [],
            'warnings' => [],
        ];

        if (! $row['credentials_configured']) {
            $row['issues'][] = 'API credentials not configured (.env / services config).';
        }

        if (! $row['service_exists']) {
            $row['issues'][] = 'API service class missing or not autoloadable.';
        } elseif ($serviceClass && method_exists($serviceClass, 'updateBulletPoints')) {
            $row['has_update_method'] = true;
        } else {
            $row['issues'][] = 'Service does not implement updateBulletPoints().';
        }

        if ($table && ! $row['metrics_table_exists']) {
            $row['warnings'][] = "Metrics table [{$table}] not found in this environment.";
        }

        if ($sku !== null && trim($sku) !== '') {
            $sku = trim($sku);
            $row['sku'] = $sku;
            $skuCtx = $this->auditBulletSkuContext($marketplace, $sku, $table);
            if (! empty($skuCtx['issues'])) {
                $row['issues'] = array_merge($row['issues'], $skuCtx['issues']);
            }
            if (! empty($skuCtx['warnings'])) {
                $row['warnings'] = array_merge($row['warnings'], $skuCtx['warnings']);
            }
            unset($skuCtx['issues'], $skuCtx['warnings']);
            $row = array_merge($row, $skuCtx);
            $bulletText = (string) ($row['bullet_text_preview'] ?? '');
            if ($bulletText === '') {
                $row['warnings'][] = 'No bullet text in metrics or product_master for this SKU.';
            } else {
                $limits = MarketplaceCharacterLimits::bulletLimits($marketplace);
                $lineCount = count(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $bulletText) ?: [])));
                if ($lineCount > $limits['max_bullets']) {
                    $row['warnings'][] = "Bullet count {$lineCount} exceeds recommended max {$limits['max_bullets']} for {$marketplace}.";
                }
            }
        }

        if ($includeSamplePayload && $sku && ($row['bullet_text_preview'] ?? '') !== '') {
            $row['would_push_chars'] = mb_strlen((string) $row['bullet_text_preview']);
            $row['would_push_lines'] = $this->countBulletLines((string) $row['bullet_text_preview']);
        }

        $row['ready'] = $row['credentials_configured']
            && $row['service_exists']
            && $row['has_update_method']
            && empty($row['issues']);

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function auditBulletSkuContext(string $marketplace, string $sku, ?string $table): array
    {
        $ctx = [
            'metrics_row_found' => false,
            'identifier' => $sku,
            'identifier_type' => 'sku',
            'bullet_text_preview' => '',
        ];

        if ($table && Schema::hasTable($table)) {
            $metric = DB::table($table)
                ->where('sku', $sku)
                ->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
                ->first();

            if ($metric) {
                $ctx['metrics_row_found'] = true;
                $ctx['bullet_text_preview'] = trim((string) ($metric->bullet_points ?? ''));
            }
        }

        if ($ctx['bullet_text_preview'] === '') {
            $ctx['bullet_text_preview'] = $this->masterBulletsFromProductMaster($sku);
        }

        if (in_array($marketplace, ['ebay', 'ebay2', 'ebay3'], true)) {
            $ebayCtx = $this->resolveEbayItemId($marketplace, $sku);
            $ctx = array_merge($ctx, $ebayCtx);
            if (! ($ebayCtx['listing_found'] ?? false)) {
                $ctx['warnings'][] = 'No item_id in metrics yet; eBay Inventory/GetSellerList lookup will run on push.';
            }
        }

        return $ctx;
    }

    /**
     * @return array{listing_found: bool, identifier: string, identifier_type: string, item_id?: string}
     */
    private function resolveEbayItemId(string $marketplace, string $sku): array
    {
        $model = match ($marketplace) {
            'ebay2' => Ebay2Metric::class,
            'ebay3' => Ebay3Metric::class,
            default => EbayMetric::class,
        };

        $metric = $model::query()
            ->where('sku', $sku)
            ->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
            ->first();

        if (! $metric || ! $metric->item_id) {
            return [
                'listing_found' => false,
                'identifier' => $sku,
                'identifier_type' => 'sku',
            ];
        }

        return [
            'listing_found' => true,
            'identifier' => (string) $metric->item_id,
            'identifier_type' => 'item_id',
            'item_id' => (string) $metric->item_id,
        ];
    }

    private function masterBulletsFromProductMaster(string $sku): string
    {
        if (! Schema::hasTable('product_master')) {
            return '';
        }

        $product = ProductMaster::query()
            ->where('sku', $sku)
            ->orWhere('SKU', $sku)
            ->first();

        if (! $product) {
            return '';
        }

        $lines = array_filter([
            $product->bullet1 ?? null,
            $product->bullet2 ?? null,
            $product->bullet3 ?? null,
            $product->bullet4 ?? null,
            $product->bullet5 ?? null,
        ], fn ($v) => trim((string) $v) !== '');

        return implode("\n", array_map('trim', $lines));
    }

    private function countBulletLines(string $text): int
    {
        return count(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', trim($text)) ?: []), fn ($line) => $line !== ''));
    }

    /**
     * @return list<string>
     */
    public function titleMarketplaces(): array
    {
        return ProductMasterMarketplaceMaps::titleMarketplaces();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function auditTitle(?string $sku = null, ?string $marketplaceFilter = null): array
    {
        return $this->auditServiceMethodMap(
            ProductMasterMarketplaceMaps::titleMarketplaces(),
            $marketplaceFilter,
            $sku,
            null,
            'title'
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function auditDescription(?string $sku = null, ?string $marketplaceFilter = null): array
    {
        $map = ProductMasterMarketplaceMaps::descriptionServiceMap();

        return $this->auditServiceMethodMap(array_keys($map), $marketplaceFilter, $sku, $map, 'description');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function auditImage(?string $sku = null, ?string $marketplaceFilter = null): array
    {
        $map = ProductMasterMarketplaceMaps::imagePushMap();

        return $this->auditServiceMethodMap(array_keys($map), $marketplaceFilter, $sku, $map, 'image');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function auditVideo(?string $sku = null, ?string $marketplaceFilter = null): array
    {
        $map = ProductMasterMarketplaceMaps::videoPushMap();

        return $this->auditServiceMethodMap(array_keys($map), $marketplaceFilter, $sku, $map, 'video');
    }

    /**
     * @return array<string, mixed>
     */
    public function auditAllMasters(string $sku): array
    {
        $masters = [
            'bullet' => $this->auditBullet($sku),
            'title' => $this->auditTitle($sku),
            'description' => $this->auditDescription($sku),
            'image' => $this->auditImage($sku),
            'video' => $this->auditVideo($sku),
        ];

        $summary = [];
        $notWorking = [];
        $liveRisks = [];

        foreach ($masters as $name => $results) {
            $ready = collect($results)->where('ready', true)->count();
            $summary[$name] = [
                'ready_count' => $ready,
                'total_count' => count($results),
                'marketplaces' => $results,
            ];
            foreach ($results as $mp => $r) {
                if (! ($r['ready'] ?? false)) {
                    $notWorking[] = [
                        'master' => $name,
                        'marketplace' => $mp,
                        'reason' => implode('; ', array_merge($r['issues'] ?? [], $r['warnings'] ?? [])) ?: 'Not ready',
                    ];
                }
                foreach ($r['warnings'] ?? [] as $warning) {
                    if ($this->isLiveRiskWarning($warning)) {
                        $liveRisks[] = [
                            'master' => $name,
                            'marketplace' => $mp,
                            'reason' => $warning,
                        ];
                    }
                }
            }
        }

        return [
            'test_sku' => $sku,
            'audited_at' => now()->toIso8601String(),
            'masters' => $summary,
            'not_working' => $notWorking,
            'live_risks' => $liveRisks,
        ];
    }

    /**
     * @param  list<string>  $marketplaces
     * @param  array<string, array{class-string, string}>|null  $serviceMethodMap
     * @return array<string, array<string, mixed>>
     */
    private function auditServiceMethodMap(
        array $marketplaces,
        ?string $marketplaceFilter,
        ?string $sku,
        ?array $serviceMethodMap,
        string $master,
    ): array {
        if ($marketplaceFilter !== null && $marketplaceFilter !== '') {
            $key = $this->configService->resolveKey($marketplaceFilter);
            $marketplaces = in_array($key, $marketplaces, true) ? [$key] : [$marketplaceFilter];
        }

        $results = [];
        foreach ($marketplaces as $marketplace) {
            $results[$marketplace] = $this->auditGenericMarketplace($marketplace, $sku, $serviceMethodMap, $master);
        }

        return $results;
    }

    /**
     * @param  array<string, array{class-string, string}>|null  $serviceMethodMap
     * @return array<string, mixed>
     */
    private function auditGenericMarketplace(
        string $marketplace,
        ?string $sku,
        ?array $serviceMethodMap,
        string $master,
    ): array {
        $resolver = app(MarketplaceMetricsTableResolver::class);
        $table = $resolver->table($marketplace);

        $serviceClass = null;
        $method = match ($master) {
            'bullet' => 'updateBulletPoints',
            'title' => 'updateTitle',
            'image' => 'updateImages',
            'video' => 'updateVideos',
            default => null,
        };

        if ($master === 'description' && $serviceMethodMap) {
            [$serviceClass, $method] = $serviceMethodMap[$marketplace] ?? [null, null];
        } elseif ($serviceMethodMap && isset($serviceMethodMap[$marketplace])) {
            [$serviceClass, $method] = $serviceMethodMap[$marketplace];
        } elseif ($master === 'title') {
            $serviceClass = \App\Services\MarketplaceTitlePushService::class;
            $method = 'push';
        } elseif ($master === 'bullet') {
            $serviceClass = ProductMasterMarketplaceMaps::bulletServiceMap()[$marketplace] ?? null;
            $method = 'updateBulletPoints';
        }

        $row = [
            'marketplace' => $marketplace,
            'credentials_configured' => $this->configService->isConfigured($marketplace),
            'service_class' => $serviceClass,
            'service_method' => $method,
            'service_exists' => $serviceClass && class_exists($serviceClass),
            'has_update_method' => false,
            'metrics_table' => $table,
            'metrics_table_exists' => $table && Schema::hasTable($table),
            'ready' => false,
            'issues' => [],
            'warnings' => [],
        ];

        if (! $row['credentials_configured']) {
            $row['issues'][] = 'API credentials not configured.';
        }

        if ($master === 'title') {
            $row['has_update_method'] = true;
        } elseif ($serviceClass && $method && $row['service_exists']) {
            if ($master === 'description' || method_exists($serviceClass, $method)) {
                $row['has_update_method'] = true;
            } else {
                $row['issues'][] = "Service missing method {$method}().";
            }
        } elseif ($serviceClass === null) {
            $row['issues'][] = 'No API service mapped for this master.';
        }

        if ($table && ! $row['metrics_table_exists']) {
            $row['warnings'][] = "Metrics table [{$table}] not found.";
        }

        if ($sku !== null && trim($sku) !== '') {
            $sku = trim($sku);
            $row['sku'] = $sku;
            $row['metrics_row_found'] = $this->metricsRowExists($table, $sku);

            if (in_array($marketplace, ['ebay', 'ebay2', 'ebay3'], true)) {
                $ebay = $this->resolveEbayItemId($marketplace, $sku);
                $row['listing_found'] = $ebay['listing_found'];
                if (! $ebay['listing_found']) {
                    $row['warnings'][] = 'No item_id in metrics; eBay API lookup on push.';
                }
            }

            $payload = $this->samplePayloadForMaster($sku, $marketplace, $master);
            $row = array_merge($row, $payload);

            if (($payload['payload_empty'] ?? false) && $master !== 'title') {
                $row['warnings'][] = 'No sample '.$master.' content in product_master for SKU.';
            }

            if ($marketplace === 'aliexpress') {
                if (! $this->aliexpressProductIdForSku($sku)) {
                    $row['warnings'][] = 'No aliexpress_metric product_id — live push may fail until synced.';
                }
            }

            $row['warnings'] = array_merge($row['warnings'], $this->marketplacePushWarnings($marketplace, $sku));
        }

        $row['ready'] = $row['credentials_configured']
            && $row['has_update_method']
            && ($row['service_exists'] || $master === 'title')
            && empty($row['issues']);

        return $row;
    }

    private function metricsRowExists(?string $table, string $sku): bool
    {
        if (! $table || ! Schema::hasTable($table)) {
            return false;
        }

        return DB::table($table)
            ->where('sku', $sku)
            ->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function samplePayloadForMaster(string $sku, string $marketplace, string $master): array
    {
        $product = ProductMaster::query()->where('sku', $sku)->orWhere('SKU', $sku)->first();
        if (! $product) {
            return ['payload_empty' => true];
        }

        return match ($master) {
            'title' => $this->sampleTitlePayload($product, $marketplace),
            'description' => $this->sampleDescriptionPayload($product, $marketplace),
            'image' => $this->sampleImagePayload($product),
            'video' => $this->sampleVideoPayload($product),
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleTitlePayload(ProductMaster $product, string $marketplace): array
    {
        $title = match (true) {
            in_array($marketplace, ['ebay', 'ebay2', 'ebay3'], true) => trim((string) ($product->title80 ?? '')),
            in_array($marketplace, ['shopify_main', 'shopify_pls', 'doba'], true) => trim((string) ($product->title100 ?? '')),
            in_array($marketplace, ['macy', 'faire'], true) => trim((string) ($product->title60 ?? '')),
            $marketplace === 'amazon' => trim((string) ($product->title75 ?? $product->title150 ?? $product->amazon_title ?? '')),
            default => trim((string) ($product->title150 ?? $product->amazon_title ?? '')),
        };

        return [
            'title_preview' => mb_substr($title, 0, 80),
            'title_chars' => mb_strlen($title),
            'payload_empty' => $title === '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleDescriptionPayload(ProductMaster $product, string $marketplace): array
    {
        $d1500 = trim((string) ($product->description_1500 ?? $product->product_description ?? ''));
        $text = match (true) {
            in_array($marketplace, ['shopify_main', 'shopify_pls', 'doba'], true) => trim((string) ($product->description_1000 ?? '')) ?: $d1500,
            in_array($marketplace, ['ebay', 'ebay2', 'ebay3'], true) => trim((string) ($product->description_800 ?? '')) ?: $d1500,
            in_array($marketplace, ['macy', 'faire'], true) => trim((string) ($product->description_600 ?? '')) ?: $d1500,
            default => $d1500,
        };

        return [
            'description_chars' => mb_strlen(strip_tags($text)),
            'payload_empty' => $text === '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleImagePayload(ProductMaster $product): array
    {
        $urls = [];
        for ($i = 1; $i <= 12; $i++) {
            $col = 'image'.$i;
            $v = trim((string) ($product->{$col} ?? ''));
            if ($v !== '' && preg_match('#^https?://#i', $v)) {
                $urls[] = $v;
            }
        }

        return [
            'image_count' => count($urls),
            'payload_empty' => $urls === [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleVideoPayload(ProductMaster $product): array
    {
        $urls = [];
        for ($i = 1; $i <= 5; $i++) {
            $col = 'video'.$i;
            $v = trim((string) ($product->{$col} ?? ''));
            if ($v !== '' && preg_match('#^https?://#i', $v)) {
                $urls[] = $v;
            }
        }

        return [
            'video_count' => count($urls),
            'payload_empty' => $urls === [],
        ];
    }

    private function aliexpressProductIdForSku(string $sku): ?string
    {
        if (! Schema::hasTable('aliexpress_metric')) {
            return null;
        }

        $row = DB::table('aliexpress_metric')
            ->where('sku', $sku)
            ->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
            ->first();

        return $row && ! empty($row->product_id) ? (string) $row->product_id : null;
    }

    /**
     * @return list<string>
     */
    private function marketplacePushWarnings(string $marketplace, string $sku): array
    {
        $warnings = [];

        if (in_array($marketplace, ['temu', 'temu2', 'tiktok', 'tiktok2'], true)) {
            $warnings[] = 'Channel may require server IP allowlist — push fails with NOT_IN_IP_WHITE_LIST / IP not in allow list.';
        }

        if ($marketplace === 'newegg') {
            $warnings[] = 'Newegg API may return HTTP 403 until this server IP is whitelisted in the Newegg seller portal.';
        }

        if ($marketplace === 'wayfair') {
            $warnings[] = 'Wayfair uses OAuth client credentials — verify WAYFAIR_CLIENT_ID / WAYFAIR_CLIENT_SECRET / WAYFAIR_AUDIENCE.';
        }

        if ($marketplace === 'walmart') {
            $warnings[] = 'Walmart feeds API requires active partner status — TERMINATED accounts get FORBIDDEN on v3/feeds.';
        }

        if ($marketplace === 'faire' && ! $this->configService->isConfigured('faire')) {
            $warnings[] = 'Set FAIRE_BEARER_TOKEN or FAIRE_ACCESS_TOKEN in .env (app_id/secret alone are not enough for push).';
        }

        if ($marketplace === 'alibaba' && ! $this->filledConfig('services.alibaba.access_token')) {
            $warnings[] = 'Set ALIBABA_ACCESS_TOKEN in .env (separate from AliExpress token).';
        }

        if ($marketplace === 'temu2' && ! $this->temu2GoodsIdForSku($sku)) {
            $warnings[] = 'No temu2_pricing/temu2_metrics goods_id — push will fail until Temu 2 listing is synced.';
        }

        if ($marketplace === 'topdawg' && ! $this->topdawgProductCodeForSku($sku)) {
            $warnings[] = 'No topdawg_products/tdid row for SKU — TopDawg product_code may be missing on push.';
        }

        if ($marketplace === 'doba') {
            if (! $this->dobaItemNoForSku($sku)) {
                $warnings[] = 'No doba_metrics item_id for SKU — cannot resolve Doba itemNo on push.';
            }
            $warnings[] = 'Doba OpenAPI requires server IP whitelist — /api/goods/update returns 403 otherwise.';
        }

        return $warnings;
    }

    private function filledConfig(string $key): bool
    {
        $value = config($key);

        return is_string($value) ? trim($value) !== '' : ! empty($value);
    }

    private function temu2GoodsIdForSku(string $sku): bool
    {
        if (Schema::hasTable('temu2_metrics') && Schema::hasColumn('temu2_metrics', 'goods_id')) {
            $id = DB::table('temu2_metrics')
                ->where('sku', $sku)
                ->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
                ->whereNotNull('goods_id')
                ->where('goods_id', '!=', '')
                ->value('goods_id');
            if ($id) {
                return true;
            }
        }

        if (Schema::hasTable('temu2_pricing') && Schema::hasColumn('temu2_pricing', 'goods_id')) {
            return DB::table('temu2_pricing')
                ->where('sku', $sku)
                ->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
                ->whereNotNull('goods_id')
                ->where('goods_id', '!=', '')
                ->exists();
        }

        return false;
    }

    private function topdawgProductCodeForSku(string $sku): bool
    {
        if (Schema::hasTable('topdawg_metrics')) {
            $exists = DB::table('topdawg_metrics')
                ->where('sku', $sku)
                ->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
                ->whereNotNull('product_id')
                ->where('product_id', '!=', '')
                ->exists();
            if ($exists) {
                return true;
            }
        }

        if (! Schema::hasTable('topdawg_products')) {
            return false;
        }

        return DB::table('topdawg_products')
            ->where('sku', $sku)
            ->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
            ->where(function ($q) {
                $q->whereNotNull('tdid')->where('tdid', '!=', '')
                    ->orWhere(function ($q2) {
                        $q2->whereNotNull('topdawg_listing_id')->where('topdawg_listing_id', '!=', '');
                    });
            })
            ->exists();
    }

    private function dobaItemNoForSku(string $sku): bool
    {
        if (! Schema::hasTable('doba_metrics')) {
            return false;
        }

        return DB::table('doba_metrics')
            ->where('sku', $sku)
            ->orWhereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
            ->whereNotNull('item_id')
            ->where('item_id', '!=', '')
            ->exists();
    }

    private function isLiveRiskWarning(string $warning): bool
    {
        $needles = [
            'may fail',
            'not found',
            'No sample',
            'No item_id',
            'product_id',
            'Metrics table',
            'not configured',
        ];
        foreach ($needles as $n) {
            if (stripos($warning, $n) !== false) {
                return true;
            }
        }

        return false;
    }
}
