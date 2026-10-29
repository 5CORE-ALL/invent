<?php

namespace App\Services;

use App\Models\PurchasingPowerProduct;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Purchasing Power — Mirakl Connect channel + MCM seller APIs (OF21 / OR11 / PRI01).
 */
class PurchasingPowerApiService extends BestBuyApiService
{
    protected function miraklChannelCode(): string
    {
        return 'purchasingpower';
    }

    protected function miraklMcmConfigKey(): string
    {
        return 'purchasingpower';
    }

    protected function miraklMcmMarketplaceLabel(): string
    {
        return 'Purchasing Power';
    }

    protected function miraklMcmHierarchyTable(): ?string
    {
        return null;
    }

    /**
     * Purchasing Power rejects every P41 row that does not carry its full attr-* set
     * (error 1000 "The attribute 'attr-…' is required"), even for a bullets-only update.
     *
     * @return array<string, string>
     */
    protected function miraklMcmP41AttributeSemanticMap(): array
    {
        return [
            'attr-cbe' => 'battery_flag',
            'attr-suppliernumber' => 'sku',
            'attr-webname' => 'title',
            'attr-brand' => 'brand',
            'attr-upc' => 'upc',
            'attr-mfgnumber' => 'mpn',
            'attr-longdescription' => 'description',
            'attr-features1' => 'bullets',
            'attr-features2' => 'bullets',
            'attr-features3' => 'bullets',
            'attr-features4' => 'bullets',
            'attr-features5' => 'bullets',
            'attr-prop65' => 'prop65',
            'attr-isvariant' => 'variant_flag',
            'attr-numberofboxes' => 'box_count',
            'attr-globaltype' => 'list_guess',
            'attr-globalsize' => 'size',
        ];
    }

    /** Best Buy microphone fallbacks do not apply to the Purchasing Power catalog. */
    protected function miraklMcmP41ExtraAttributeValues(string $sku, ?string $hierarchy, array $offer, mixed $priceRow): array
    {
        return [];
    }

    /**
     * Purchasing Power has no price-data sheet; when neither the live offer (OF21) nor the MCM
     * product (P31) names the category, reuse the category_code Mirakl put on this SKU's order lines.
     */
    protected function resolveMiraklMcmHierarchyExtraFallback(string $sku): ?string
    {
        $candidates = array_values(array_unique(array_filter([
            trim($sku),
            str_replace(' ', '', trim($sku)),
            ...$this->miraklMcmRelatedSkuCandidates($sku),
        ])));
        foreach (['purchasing_power_sales', 'purchasing_power_sales_l60'] as $table) {
            try {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'category_code')) {
                    continue;
                }
                $code = DB::table($table)
                    ->where(function ($q) use ($candidates) {
                        $q->whereIn('offer_sku', $candidates)->orWhereIn('product_sku', $candidates);
                    })
                    ->whereNotNull('category_code')
                    ->where('category_code', '!=', '')
                    ->orderByDesc('id')
                    ->value('category_code');
            } catch (\Throwable $e) {
                Log::warning('Purchasing Power category lookup from sales failed', ['table' => $table, 'error' => $e->getMessage()]);
                continue;
            }
            if (is_string($code) && trim($code) !== '') {
                return trim($code);
            }
        }

        return null;
    }

    /**
     * Purchasing Power live listing content is MCM P41 only (no Best Buy Connect catalog call).
     *
     * @return array{success: bool, message: string}
     */
    public function updateTitle(string $sku, string $title): array
    {
        $title = mb_substr(trim($title), 0, 150);
        if (trim($sku) === '' || $title === '') {
            return ['success' => false, 'message' => 'SKU and title are required.'];
        }
        if (! filter_var($this->miraklMcmConfig('mcm_title_push', true), FILTER_VALIDATE_BOOL)) {
            return ['success' => false, 'message' => 'Purchasing Power MCM P41 title push is disabled.'];
        }

        return $this->pushTitleViaMiraklMcm(trim($sku), $title);
    }

    /**
     * @param  list<string>  $imageUrls
     * @return array{success: bool, message: string}
     */
    public function updateDescription(string $identifier, string $description, array $imageUrls = []): array
    {
        $sku = trim($identifier);
        $description = trim($description);
        if ($sku === '' || $description === '') {
            return ['success' => false, 'message' => 'SKU and description are required.'];
        }

        return $this->pushDescriptionViaMiraklMcm($this->resolveMiraklMcmLiveShopSku($sku), $description);
    }

    /**
     * @param  list<string>  $imageUrls
     * @return array{success: bool, message: string, normalized_urls?: list<string>}
     */
    public function updateListingImages(string $sku, array $imageUrls): array
    {
        $sku = $this->resolveMiraklMcmLiveShopSku(trim($sku));
        $urls = array_slice(array_values(array_unique(array_filter(array_map('trim', $imageUrls), fn ($url) => $url !== ''))), 0, 11);
        if ($sku === '' || $urls === []) {
            return ['success' => false, 'message' => 'SKU and at least one image URL are required.'];
        }

        $result = $this->pushImagesViaMiraklMcm($sku, $urls);
        if ($result['success'] ?? false) {
            $result['normalized_urls'] = $urls;
        }

        return $result;
    }

    /**
     * OR11 — list Purchasing Power MCM orders (paginated).
     *
     * @return array{orders: list<array<string, mixed>>, total_count: int}
     */
    public function fetchOrders(?Carbon $startDate = null, ?Carbon $endDate = null, int $max = 100): array
    {
        $apiKey = trim((string) config('services.purchasingpower.mcm_api_key', ''));
        $baseUrl = rtrim((string) config('services.purchasingpower.mcm_base_url', ''), '/');

        if ($apiKey === '' || $baseUrl === '') {
            throw new \RuntimeException('Purchasing Power MCM API key/base URL not configured (PURCHASING_POWER_MCM_API_KEY).');
        }

        $allOrders = [];
        $offset = 0;
        $totalCount = 0;
        $page = 0;
        $maxPages = 50;

        do {
            $page++;
            $params = [
                'max' => $max,
                'offset' => $offset,
            ];
            if ($startDate) {
                $params['start_date'] = $startDate->copy()->utc()->format('Y-m-d\TH:i:s\Z');
            }
            if ($endDate) {
                $params['end_date'] = $endDate->copy()->utc()->format('Y-m-d\TH:i:s\Z');
            }

            $shopId = config('services.purchasingpower.shop_id');
            if ($shopId !== null && $shopId !== '') {
                $params['shop_id'] = (int) $shopId;
            }

            $response = null;
            for ($attempt = 1; $attempt <= 5; $attempt++) {
                $response = Http::withoutVerifying()
                    ->withHeaders([
                        'Authorization' => $apiKey,
                        'Accept' => 'application/json',
                    ])
                    ->timeout(60)
                    ->get($baseUrl.'/api/orders', $params);

                if ($response->status() !== 429) {
                    break;
                }
                sleep(min(30, 3 * $attempt));
            }

            if (! $response || ! $response->successful()) {
                $status = $response ? $response->status() : 0;
                $body = $response ? substr($response->body(), 0, 500) : 'no response';
                Log::error('Purchasing Power MCM OR11 failed', [
                    'status' => $status,
                    'body' => $body,
                    'offset' => $offset,
                ]);
                throw new \RuntimeException("Purchasing Power MCM OR11 failed: HTTP {$status}");
            }

            $json = $response->json() ?? [];
            $orders = $json['orders'] ?? [];
            $totalCount = (int) ($json['total_count'] ?? 0);
            $fetched = is_array($orders) ? count($orders) : 0;

            if ($fetched > 0) {
                foreach ($orders as $order) {
                    if (is_array($order)) {
                        $allOrders[] = $order;
                    }
                }
            }

            $offset += $max;
            $hasMore = $fetched >= $max && ($totalCount === 0 || $offset < $totalCount);

            if ($hasMore) {
                usleep(250000); // avoid Mirakl rate limits
            }
        } while ($hasMore && $page < $maxPages);

        return [
            'orders' => $allOrders,
            'total_count' => $totalCount > 0 ? $totalCount : count($allOrders),
        ];
    }

    /**
     * Flatten OR11 orders into one row per order line for the sales grid.
     *
     * @param  list<array<string, mixed>>  $orders
     * @return list<object>
     */
    public function flattenOrdersToLineRows(array $orders): array
    {
        $rows = [];

        foreach ($orders as $order) {
            if (! is_array($order)) {
                continue;
            }

            $customer = is_array($order['customer'] ?? null) ? $order['customer'] : [];
            $shipAddr = is_array($customer['shipping_address'] ?? null) ? $customer['shipping_address'] : [];
            $customerName = trim(
                trim((string) ($customer['firstname'] ?? '')).' '.trim((string) ($customer['lastname'] ?? ''))
            );

            $lines = $order['order_lines'] ?? [];
            if (! is_array($lines) || $lines === []) {
                continue;
            }

            foreach ($lines as $line) {
                if (! is_array($line)) {
                    continue;
                }

                $qty = max(0, (int) ($line['quantity'] ?? $line['quantity_sold'] ?? $line['stock'] ?? 0));
                $linePrice = (float) ($line['price'] ?? $line['price_amount'] ?? 0);
                $unitPrice = isset($line['price_unit']) && is_numeric($line['price_unit'])
                    ? (float) $line['price_unit']
                    : ($qty > 0 ? $linePrice / $qty : $linePrice);
                $totalPrice = isset($line['total_price']) && is_numeric($line['total_price'])
                    ? (float) $line['total_price']
                    : $linePrice;
                $commission = (float) ($line['total_commission'] ?? $line['commission_fee'] ?? 0);

                $sku = trim((string) ($line['offer_sku'] ?? $line['product_shop_sku'] ?? ''));
                if ($sku === '') {
                    $sku = trim((string) ($line['product_sku'] ?? ''));
                }

                $rows[] = (object) [
                    'id' => $line['order_line_id'] ?? ($order['order_id'] ?? null),
                    'order_date' => $order['created_date'] ?? ($line['created_date'] ?? null),
                    'order_number' => $order['commercial_id'] ?? ($order['order_id'] ?? null),
                    'order_id' => $order['order_id'] ?? null,
                    'status' => $line['order_line_state'] ?? ($order['order_state'] ?? ''),
                    'sku' => $sku,
                    'product_name' => $line['product_title'] ?? null,
                    'quantity' => $qty,
                    'stock' => $qty,
                    'unit_price' => $unitPrice,
                    'amount' => $totalPrice,
                    'commission' => $commission,
                    'commission_rule' => null,
                    'amount_transferred' => round($totalPrice - $commission, 2),
                    'shipping_company' => $order['shipping_company'] ?? null,
                    'tracking_number' => $order['shipping_tracking'] ?? null,
                    'tracking_url' => $order['shipping_tracking_url'] ?? null,
                    'customer' => $customerName,
                    'city' => $shipAddr['city'] ?? null,
                    'state' => $shipAddr['state'] ?? null,
                    'country' => $shipAddr['country_iso_code'] ?? ($shipAddr['country'] ?? null),
                    'category_label' => $line['category_label'] ?? null,
                    'mirakl_product_sku' => $line['product_sku'] ?? null,
                ];
            }
        }

        return $rows;
    }

    protected function getAccessToken()
    {
        $clientId = trim((string) config('services.purchasingpower.client_id', ''));
        $clientSecret = trim((string) config('services.purchasingpower.client_secret', ''));
        if ($clientId === '' || $clientSecret === '') {
            return parent::getAccessToken();
        }

        $payload = [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ];
        $companyId = trim((string) config('services.purchasingpower.company_id', ''));
        if ($companyId !== '') {
            $payload['audience'] = $companyId;
        }

        $response = Http::withoutVerifying()->asForm()->post('https://auth.mirakl.net/oauth/token', $payload);

        return $response->successful() ? ($response->json()['access_token'] ?? null) : null;
    }

    public function isConfigured(): bool
    {
        $mcmKey = trim((string) config('services.purchasingpower.mcm_api_key', ''));
        $apiKey = trim((string) config('services.purchasingpower.api_key', ''));
        $clientId = trim((string) config('services.purchasingpower.client_id', ''));
        $clientSecret = trim((string) config('services.purchasingpower.client_secret', ''));

        return $mcmKey !== '' || $apiKey !== '' || ($clientId !== '' && $clientSecret !== '');
    }

    /**
     * @return array{success: bool, message: string, sample_count?: int}
     */
    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Purchasing Power credentials missing. Set PURCHASING_POWER_MCM_API_KEY + PURCHASING_POWER_SHOP_ID (and Connect client id/secret).',
            ];
        }

        $parts = [];
        $sample = 0;

        $apiKey = $this->miraklMcmApiKey();
        $baseUrl = rtrim((string) config('services.purchasingpower.mcm_base_url', 'https://purchasingpowerus-prod.mirakl.net'), '/');
        if ($apiKey !== null && $apiKey !== '' && $baseUrl !== '') {
            try {
                $params = ['max' => 1];
                $shopId = config('services.purchasingpower.shop_id');
                if ($shopId !== null && $shopId !== '') {
                    $params['shop_id'] = (int) $shopId;
                }
                $response = Http::withoutVerifying()
                    ->withHeaders([
                        'Authorization' => $apiKey,
                        'Accept' => 'application/json',
                    ])
                    ->timeout(30)
                    ->get($baseUrl.'/api/offers', $params);

                if ($response->status() === 404 && isset($params['shop_id'])) {
                    unset($params['shop_id']);
                    $shopId = null;
                    $response = Http::withoutVerifying()
                        ->withHeaders([
                            'Authorization' => $apiKey,
                            'Accept' => 'application/json',
                        ])
                        ->timeout(30)
                        ->get($baseUrl.'/api/offers', $params);
                }

                if (! $response->successful()) {
                    return [
                        'success' => false,
                        'message' => 'Purchasing Power MCM OF21 failed: HTTP '.$response->status().' '.substr($response->body(), 0, 200),
                    ];
                }
                $offers = $response->json('offers') ?? [];
                $sample = is_array($offers) ? count($offers) : 0;
                $parts[] = 'MCM '.$baseUrl.' shop '.(string) ($shopId ?? 'n/a')." ({$sample} offer sample)";
            } catch (\Throwable $e) {
                return [
                    'success' => false,
                    'message' => 'Purchasing Power MCM connection failed: '.$e->getMessage(),
                ];
            }
        }

        $token = $this->getAccessToken();
        if ($token) {
            try {
                $response = Http::withoutVerifying()->withToken($token)->get(
                    'https://miraklconnect.com/api/products?limit=1&channel_code='.$this->miraklChannelCode()
                );
                if ($response->successful()) {
                    $parts[] = 'Mirakl Connect channel '.$this->miraklChannelCode();
                } else {
                    $parts[] = 'Mirakl Connect ping HTTP '.$response->status();
                }
            } catch (\Throwable $e) {
                $parts[] = 'Mirakl Connect error: '.$e->getMessage();
            }
        }

        if ($parts === []) {
            return [
                'success' => false,
                'message' => 'No Purchasing Power API endpoint could be reached.',
            ];
        }

        return [
            'success' => true,
            'message' => 'Purchasing Power connected: '.implode('; ', $parts).'.',
            'sample_count' => $sample,
        ];
    }

    /**
     * Push listed price via Mirakl MCM PRI01 (uses PURCHASING_POWER_MCM_API_KEY).
     * Overrides BestBuy Connect updatePrice — PP seller price lives on MCM offers, not Connect catalog.
     *
     * @return array{success: bool, message: string, status_code?: int|null, import_id?: string|null}
     */
    public function updatePrice(string $sku, float $price): array
    {
        $sku = trim($sku);
        $price = round((float) $price, 2);

        if ($sku === '' || $price <= 0) {
            return ['success' => false, 'message' => 'Valid SKU and price are required.', 'status_code' => 422];
        }

        $block = \App\Http\Controllers\MarketPlace\PurchasingPowerController::pricePushBlockReason($sku);
        if ($block !== null) {
            return ['success' => false, 'message' => $block, 'status_code' => 422, 'skipped' => true];
        }

        $apiKey = (string) ($this->miraklMcmApiKey() ?? '');
        $baseUrl = rtrim((string) config('services.purchasingpower.mcm_base_url', ''), '/');
        if ($apiKey === '' || $baseUrl === '') {
            return [
                'success' => false,
                'message' => 'Purchasing Power MCM API key/base URL not configured (PURCHASING_POWER_MCM_API_KEY).',
                'status_code' => 401,
            ];
        }

        // One exact OF21 lookup. The sku filter must match the shop SKU's case
        // and non-breaking spaces. Do not spray variants — that submitted PRI01
        // for unlisted SKUs and waited ~20–40s each just to get "No existing offer".
        $listed = \App\Http\Controllers\MarketPlace\PurchasingPowerController::findProductBySku($sku);
        $lookupSku = \App\Http\Controllers\MarketPlace\PurchasingPowerController::offerLookupSku(
            $sku,
            $listed->sku ?? null
        );
        $lookup = $this->lookupLiveMcmOfferSku($lookupSku, $apiKey, $baseUrl);
        $offerSku = $lookup['offer'];
        if ($offerSku === null) {
            if ($lookup['transient']) {
                return $this->retryableLookupFailure($lookupSku);
            }

            return [
                'success' => false,
                'message' => "SKU is not listed on Purchasing Power MCM: {$lookupSku}",
                'status_code' => 404,
            ];
        }

        $csv = "offer-sku;price\n"
            .'"'.str_replace('"', '""', $offerSku).'";'
            .number_format($price, 2, '.', '')."\n";

        $query = [];
        $shopId = config('services.purchasingpower.shop_id');
        if ($shopId !== null && $shopId !== '') {
            $query['shop_id'] = (int) $shopId;
        }

        try {
            $url = $baseUrl.'/api/offers/pricing/imports';
            if ($query !== []) {
                $url .= '?'.http_build_query($query);
            }

            $filename = 'pp-price-'.preg_replace('/[^A-Za-z0-9_-]+/', '_', $offerSku).'.csv';
            $response = $this->miraklMcmPostPricingImport($apiKey, $url, $csv, $filename);

            if ($response !== null && $response->status() === 404 && $query !== []) {
                $response = $this->miraklMcmPostPricingImport(
                    $apiKey,
                    $baseUrl.'/api/offers/pricing/imports',
                    $csv,
                    $filename
                );
            }

            if ($response === null || ! $response->successful()) {
                $status = $response?->status();
                $body = $response ? substr($response->body(), 0, 300) : 'no response';
                Log::warning('Purchasing Power MCM PRI01 price push failed', [
                    'sku' => $sku,
                    'offer_sku' => $offerSku,
                    'status' => $status,
                    'body' => $body,
                ]);

                return [
                    'success' => false,
                    'message' => 'Purchasing Power price push failed: HTTP '.($status ?? 'n/a').' '.$body,
                    'status_code' => $status,
                ];
            }

            $json = $response->json() ?? [];
            $importId = $json['import_id'] ?? $json['importId'] ?? null;
            if ($importId === null || $importId === '') {
                return [
                    'success' => false,
                    'message' => 'Purchasing Power price push accepted no import_id.',
                    'status_code' => $response->status(),
                ];
            }

            $import = $this->waitForPricingImport((string) $importId, $apiKey, $baseUrl);
            $linesOk = (int) ($import['lines_in_success'] ?? 0);
            $linesErr = (int) ($import['lines_in_error'] ?? 0);
            $offersUpdated = (int) ($import['offers_updated'] ?? 0);
            $status = strtoupper((string) ($import['status'] ?? ''));

            if ($linesErr > 0 || ($linesOk < 1 && $status !== 'COMPLETE')) {
                $errMsg = $this->fetchPricingImportErrorSummary((string) $importId, $apiKey, $baseUrl);
                Log::warning('Purchasing Power MCM PRI01 completed with errors', [
                    'sku' => $sku,
                    'offer_sku' => $offerSku,
                    'import_id' => $importId,
                    'status' => $status,
                    'lines_in_success' => $linesOk,
                    'lines_in_error' => $linesErr,
                    'error' => $errMsg,
                ]);

                return [
                    'success' => false,
                    'message' => $errMsg !== ''
                        ? ('Purchasing Power price push failed: '.$errMsg)
                        : ('Purchasing Power price push failed (import '.$importId.' status '.$status.')'),
                    'status_code' => 400,
                    'import_id' => (string) $importId,
                ];
            }

            // Keep local listed price in sync only after MCM confirms update
            try {
                PurchasingPowerProduct::updateOrCreate(
                    ['sku' => $offerSku],
                    ['price' => $price]
                );
            } catch (\Throwable $e) {
                Log::warning('Purchasing Power local price sync after PRI01 failed', [
                    'sku' => $offerSku,
                    'error' => $e->getMessage(),
                ]);
            }

            Log::info('Purchasing Power MCM PRI01 price push complete', [
                'sku' => $sku,
                'offer_sku' => $offerSku,
                'price' => $price,
                'import_id' => $importId,
            ]);

            return [
                'success' => true,
                'message' => 'Price $'.number_format($price, 2).' pushed to Purchasing Power for SKU: '.$offerSku
                    .' (import '.$importId.')',
                'status_code' => $response->status(),
                'import_id' => (string) $importId,
            ];
        } catch (\Throwable $e) {
            Log::error('Purchasing Power MCM PRI01 exception', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Purchasing Power API error: '.$e->getMessage(),
                'status_code' => null,
            ];
        }
    }

    /**
     * Push many prices in ONE PRI01 import. A single push waits ~20–40 s for the import to finish, so 170 SKUs one by
     * one took 1–2 hours; one import for up to a few dozen SKUs finishes in about the same time as one push.
     *
     * Every SKU still gets the same checks as updatePrice() (not listed / inactive / OF21 offer lookup). If the batch
     * import is rejected, or any line errors, or it does not finish, the SKUs are pushed one by one with updatePrice(),
     * so a batch problem never loses a push or hides which SKU failed.
     *
     * @param  list<array{sku: string, price: float}>  $items
     * @return array<string, array{success: bool, message: string, status_code?: int|null, import_id?: string|null}> keyed by the SKU as given
     */
    public function updatePricesBatch(array $items): array
    {
        $results = [];
        /** @var array<string, array{sku: string, price: float}> $ready offer SKU => item */
        $ready = [];
        $mcmThrottled = false;

        $apiKey = (string) ($this->miraklMcmApiKey() ?? '');
        $baseUrl = rtrim((string) config('services.purchasingpower.mcm_base_url', ''), '/');

        foreach ($items as $item) {
            $sku = trim((string) ($item['sku'] ?? ''));
            $price = round((float) ($item['price'] ?? 0), 2);
            if ($sku === '' || $price <= 0) {
                $results[$sku] = ['success' => false, 'message' => 'Valid SKU and price are required.', 'status_code' => 422];

                continue;
            }
            if ($apiKey === '' || $baseUrl === '') {
                $results[$sku] = [
                    'success' => false,
                    'message' => 'Purchasing Power MCM API key/base URL not configured (PURCHASING_POWER_MCM_API_KEY).',
                    'status_code' => 401,
                ];

                continue;
            }
            if ($mcmThrottled) {
                // MCM kept answering 429 after the waits: do not make every remaining SKU wait the same minutes.
                $results[$sku] = $this->retryableLookupFailure($sku);

                continue;
            }
            [$offerSku, $fail] = $this->resolveBatchOfferSku($sku, $apiKey, $baseUrl);
            if ($offerSku === null) {
                $results[$sku] = $fail;
                $mcmThrottled = ! empty($fail['retryable']);

                continue;
            }
            $ready[$offerSku] = ['sku' => $sku, 'price' => $price];
        }

        $single = function () use (&$results, &$ready): array {
            foreach ($ready as $r) {
                $results[$r['sku']] = $this->updatePrice($r['sku'], $r['price']);
            }

            return $results;
        };

        if (count($ready) < 2) {
            return $single();
        }

        $csv = "offer-sku;price\n";
        foreach ($ready as $offerSku => $r) {
            $csv .= '"'.str_replace('"', '""', (string) $offerSku).'";'.number_format($r['price'], 2, '.', '')."\n";
        }
        $query = [];
        $shopId = config('services.purchasingpower.shop_id');
        if ($shopId !== null && $shopId !== '') {
            $query['shop_id'] = (int) $shopId;
        }

        try {
            $url = $baseUrl.'/api/offers/pricing/imports'.($query !== [] ? '?'.http_build_query($query) : '');
            $filename = 'pp-price-batch-'.date('YmdHis').'.csv';
            $response = $this->miraklMcmPostPricingImport($apiKey, $url, $csv, $filename);
            if ($response !== null && $response->status() === 404 && $query !== []) {
                $response = $this->miraklMcmPostPricingImport($apiKey, $baseUrl.'/api/offers/pricing/imports', $csv, $filename);
            }
            $importId = ($response !== null && $response->successful())
                ? (string) ($response->json('import_id') ?? $response->json('importId') ?? '')
                : '';
            if ($importId === '') {
                Log::warning('Purchasing Power batch PRI01 not accepted — pushing one by one', [
                    'count' => count($ready),
                    'status' => $response?->status(),
                    'body' => $response ? substr($response->body(), 0, 300) : 'no response',
                ]);

                return $single();
            }

            $import = $this->waitForPricingImport($importId, $apiKey, $baseUrl, 150);
            $status = strtoupper((string) ($import['status'] ?? ''));
            $linesOk = (int) ($import['lines_in_success'] ?? 0);
            $linesErr = (int) ($import['lines_in_error'] ?? 0);
            if ($status !== 'COMPLETE' || $linesErr > 0 || $linesOk < count($ready)) {
                Log::warning('Purchasing Power batch PRI01 not clean — pushing one by one', [
                    'import_id' => $importId,
                    'status' => $status,
                    'lines_in_success' => $linesOk,
                    'lines_in_error' => $linesErr,
                    'count' => count($ready),
                ]);

                return $single();
            }
        } catch (\Throwable $e) {
            Log::error('Purchasing Power batch PRI01 exception — pushing one by one', ['error' => $e->getMessage()]);

            return $single();
        }

        foreach ($ready as $offerSku => $r) {
            $this->syncLocalPriceAfterPush((string) $offerSku, $r['price']);
            $results[$r['sku']] = [
                'success' => true,
                'message' => 'Price $'.number_format($r['price'], 2).' pushed to Purchasing Power for SKU: '.$offerSku
                    .' (batch import '.$importId.')',
                'status_code' => 200,
                'import_id' => $importId,
            ];
        }
        Log::info('Purchasing Power MCM PRI01 batch push complete', ['count' => count($ready), 'import_id' => $importId]);

        return $results;
    }

    /**
     * The same per-SKU checks updatePrice() runs before it pushes: inactive / not listed, then one exact OF21 lookup.
     *
     * @return array{0: ?string, 1: ?array} [offer SKU, null] or [null, failed result]
     */
    protected function resolveBatchOfferSku(string $sku, string $apiKey, string $baseUrl): array
    {
        $block = \App\Http\Controllers\MarketPlace\PurchasingPowerController::pricePushBlockReason($sku);
        if ($block !== null) {
            return [null, ['success' => false, 'message' => $block, 'status_code' => 422, 'skipped' => true]];
        }
        $listed = \App\Http\Controllers\MarketPlace\PurchasingPowerController::findProductBySku($sku);
        $lookupSku = \App\Http\Controllers\MarketPlace\PurchasingPowerController::offerLookupSku($sku, $listed->sku ?? null);
        $lookup = $this->lookupLiveMcmOfferSku($lookupSku, $apiKey, $baseUrl);
        $offerSku = $lookup['offer'];
        if ($offerSku === null) {
            if ($lookup['transient']) {
                return [null, $this->retryableLookupFailure($lookupSku)];
            }

            return [null, [
                'success' => false,
                'message' => "SKU is not listed on Purchasing Power MCM: {$lookupSku}",
                'status_code' => 404,
            ]];
        }

        return [$offerSku, null];
    }

    /** Keep the local listed price in step once MCM confirmed the import. */
    protected function syncLocalPriceAfterPush(string $offerSku, float $price): void
    {
        try {
            PurchasingPowerProduct::updateOrCreate(['sku' => $offerSku], ['price' => $price]);
        } catch (\Throwable $e) {
            Log::warning('Purchasing Power local price sync after batch PRI01 failed', [
                'sku' => $offerSku,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Single OF21 GET for this shop SKU. No candidate spray, no 429 15s backoff loop.
     */
    protected function resolveLiveMcmOfferSkuExact(string $sku, string $apiKey, string $baseUrl): ?string
    {
        return $this->lookupLiveMcmOfferSku($sku, $apiKey, $baseUrl)['offer'];
    }

    /** Microtime of the last OF21 lookup, so back-to-back lookups (a batch of SKUs) stay under the MCM rate limit. */
    protected static float $lastOf21LookupAt = 0.0;

    /** Result for a SKU whose OF21 lookup was rate-limited / failed transiently: not a verdict on the SKU, retry later. */
    protected function retryableLookupFailure(string $sku): array
    {
        return [
            'success' => false,
            'retryable' => true,
            'message' => "Purchasing Power MCM is rate-limiting requests (HTTP 429) — could not check {$sku}, will retry",
            'status_code' => 429,
        ];
    }

    /**
     * OF21 lookup for this shop SKU that tells "not listed" apart from "MCM said try again later".
     *
     * Mirakl answers HTTP 429 (Retry-After: 60) once ~20 lookups arrive in a burst. That used to be read as
     * "SKU is not listed", so a 25-SKU batch pushed ~22 and failed the rest. Now: pace lookups, and on 429 / 5xx /
     * network errors wait and retry (CLI / background runner only — a web request must not hang for a minute).
     *
     * @return array{offer: ?string, transient: bool}
     */
    protected function lookupLiveMcmOfferSku(string $sku, string $apiKey, string $baseUrl): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return ['offer' => null, 'transient' => false];
        }

        $params = [
            'sku' => $sku,
            'max' => 5,
        ];
        $shopId = config('services.purchasingpower.shop_id');
        if ($shopId !== null && $shopId !== '') {
            $params['shop_id'] = (int) $shopId;
        }

        $background = app()->runningInConsole();
        $maxAttempts = $background ? 4 : 1;
        $backoff = [20, 40, 60];

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            if ($background) {
                $gap = 0.6 - (microtime(true) - self::$lastOf21LookupAt);
                if ($gap > 0) {
                    usleep((int) ($gap * 1000000));
                }
            }

            $response = null;
            try {
                $response = Http::withoutVerifying()
                    ->withHeaders([
                        'Authorization' => $apiKey,
                        'Accept' => 'application/json',
                    ])
                    ->timeout(12)
                    ->get($baseUrl.'/api/offers', $params);
            } catch (\Throwable $e) {
                Log::warning('Purchasing Power OF21 exact lookup failed', [
                    'sku' => $sku,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);
            } finally {
                self::$lastOf21LookupAt = microtime(true);
            }

            $transient = $response === null || $response->status() === 429 || $response->status() >= 500;
            if ($transient) {
                $status = $response?->status();
                Log::warning('Purchasing Power OF21 lookup throttled / unavailable', [
                    'sku' => $sku,
                    'status' => $status,
                    'attempt' => $attempt,
                ]);
                if ($attempt < $maxAttempts) {
                    $wait = $backoff[$attempt - 1] ?? 60;
                    $retryAfter = $response ? (int) $response->header('Retry-After') : 0;
                    if ($retryAfter > 0) {
                        $wait = min($wait, $retryAfter);
                    }
                    sleep(max(1, $wait));
                }

                continue;
            }

            if (! $response->successful()) {
                return ['offer' => null, 'transient' => false];
            }

            foreach ($response->json('offers') ?? [] as $offer) {
                if (! is_array($offer)) {
                    continue;
                }
                $shopSku = trim((string) ($offer['shop_sku'] ?? ''));
                $productSku = trim((string) ($offer['product_sku'] ?? ''));
                foreach ([$shopSku, $productSku] as $value) {
                    if ($value !== '' && strcasecmp($value, $sku) === 0) {
                        return ['offer' => $shopSku !== '' ? $shopSku : $value, 'transient' => false];
                    }
                }
            }

            return ['offer' => null, 'transient' => false];
        }

        return ['offer' => null, 'transient' => true];
    }

    protected function waitForPricingImport(string $importId, string $apiKey, string $baseUrl, int $maxPolls = 10): array
    {
        for ($i = 0; $i < $maxPolls; $i++) {
            if ($i > 0) {
                usleep(400000);
            }
            try {
                $response = Http::withoutVerifying()
                    ->withHeaders([
                        'Authorization' => $apiKey,
                        'Accept' => 'application/json',
                    ])
                    ->timeout(30)
                    ->get($baseUrl.'/api/offers/pricing/imports', ['import_id' => $importId]);

                if (! $response->successful()) {
                    continue;
                }

                $row = ($response->json('data') ?? [])[0] ?? null;
                if (! is_array($row)) {
                    continue;
                }

                $status = strtoupper((string) ($row['status'] ?? ''));
                if (in_array($status, ['COMPLETE', 'FAILED', 'CANCELLED'], true)) {
                    return $row;
                }
            } catch (\Throwable $e) {
                Log::warning('Purchasing Power PRI01 status poll failed', [
                    'import_id' => $importId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [];
    }

    protected function fetchPricingImportErrorSummary(string $importId, string $apiKey, string $baseUrl): string
    {
        try {
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => $apiKey,
                    'Accept' => 'application/json',
                ])
                ->timeout(30)
                ->get($baseUrl.'/api/offers/pricing/imports/'.$importId.'/error_report');

            if (! $response->successful()) {
                return '';
            }

            $body = trim((string) $response->body());
            $lines = preg_split("/\r\n|\n|\r/", $body) ?: [];
            // Skip CSV header; return first error line condensed
            foreach ($lines as $idx => $line) {
                if ($idx === 0) {
                    continue;
                }
                $line = trim($line);
                if ($line !== '') {
                    return substr($line, 0, 300);
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return '';
    }

    /**
     * Push stock updates to Mirakl MCM offers (stub until OF24 wired).
     *
     * @param  array<int, array{sku: string, quantity: int}>  $items
     * @return array{pushed: int, failed: int, message: string}
     */
    public function updateItemInventoryBulk(array $items): array
    {
        if ($items === []) {
            return ['pushed' => 0, 'failed' => 0, 'message' => 'No items to push.'];
        }

        Log::info('PurchasingPowerApiService: updateItemInventoryBulk stub — local stock persisted only', [
            'count' => count($items),
        ]);

        return [
            'pushed' => count($items),
            'failed' => 0,
            'message' => 'Mirakl offer stock push is not wired yet — updated local purchasing_power_products.stock only.',
        ];
    }
}
