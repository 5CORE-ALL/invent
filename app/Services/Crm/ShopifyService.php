<?php

namespace App\Services\Crm;

use App\Events\Crm\ShopifyOrderImported;
use App\Models\Crm\Customer;
use App\Models\Crm\ShopifyCustomer;
use App\Models\Crm\ShopifyOrder;
use App\Models\Crm\ShopifyProduct;
use App\Services\Crm\Contracts\ShopifyServiceInterface;
use App\Services\Crm\Exceptions\ShopifyApiException;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Utils;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class ShopifyService implements ShopifyServiceInterface
{
    private ?Client $client = null;

    public function __construct(
        protected ShopifyCustomerClassifier $customerClassifier
    ) {}

    public function isConfigured(): bool
    {
        return $this->normalizedStoreDomain() !== '' && $this->accessToken() !== '';
    }

    public function getCustomers(?int $since_id = null): array
    {
        if (! $this->isConfigured()) {
            return [
                'customers' => [],
                'next_page_info' => null,
                'has_next_page' => false,
            ];
        }

        $query = ['limit' => 250];
        if ($since_id !== null && $since_id > 0) {
            $query['since_id'] = $since_id;
        }

        $path = 'customers.json?'.http_build_query($query);
        $response = $this->sendWithRetries('GET', $this->absoluteAdminUrl($path));
        $decoded = $this->decodeJsonBody($response);
        $customers = $decoded['customers'] ?? [];
        
        if (! is_array($customers)) {
            $customers = [];
        }

        $nextUrl = $this->nextPageUrl($response->getHeaderLine('Link'));

        /** @var array<int, array<string, mixed>> $customers */
        return [
            'customers' => $customers,
            'next_page_info' => $this->extractPageInfoFromUrl($nextUrl),
            'has_next_page' => $nextUrl !== null,
        ];
    }

    public function getOrders(?int $since_id = null): array
    {
        if (! $this->isConfigured()) {
            return [
                'orders' => [],
                'next_page_info' => null,
                'has_next_page' => false,
            ];
        }

        $query = ['limit' => 250, 'status' => 'any'];
        if ($since_id !== null && $since_id > 0) {
            $query['since_id'] = $since_id;
        }

        $path = 'orders.json?'.http_build_query($query);
        $response = $this->sendWithRetries('GET', $this->absoluteAdminUrl($path));
        $decoded = $this->decodeJsonBody($response);
        $orders = $decoded['orders'] ?? [];

        if (! is_array($orders)) {
            $orders = [];
        }

        $normalized = [];
        foreach ($orders as $row) {
            if (! is_array($row) || ! isset($row['id'])) {
                continue;
            }
            $normalized[] = $this->normalizeOrderApiRow($row);
        }

        $nextUrl = $this->nextPageUrl($response->getHeaderLine('Link'));

        return [
            'orders' => $normalized,
            'next_page_info' => $this->extractPageInfoFromUrl($nextUrl),
            'has_next_page' => $nextUrl !== null,
        ];
    }

    /**
     * Map a single Shopify REST order object to CRM-facing fields.
     *
     * @param  array<string, mixed>  $row
     * @return array{
     *     shopify_order_id: int,
     *     shopify_customer_id: int|null,
     *     total_price: mixed,
     *     subtotal_price: mixed,
     *     total_discounts: mixed,
     *     total_tax: mixed,
     *     shipping_price: float|null,
     *     currency: mixed,
     *     source_name: string|null,
     *     source_identifier: string|null,
     *     landing_site: string|null,
     *     referring_site: string|null,
     *     line_items_count: int,
     *     order_status: string|null,
     *     financial_status: string|null,
     *     fulfillment_status: string|null,
     *     cancelled_at: string|null,
     *     order_date: string|null,
     *     last_synced_at: string,
     *     raw_payload: array<string, mixed>
     * }
     */
    protected function normalizeOrderApiRow(array $row): array
    {
        $customerPayload = $row['customer'] ?? null;
        $shopifyCustomerId = null;
        if (is_array($customerPayload) && isset($customerPayload['id'])) {
            $shopifyCustomerId = (int) $customerPayload['id'];
        } elseif (isset($row['user_id']) && is_numeric($row['user_id'])) {
            $shopifyCustomerId = (int) $row['user_id'];
        }

        $financial = isset($row['financial_status']) ? (string) $row['financial_status'] : '';
        $fulfillment = isset($row['fulfillment_status']) ? (string) $row['fulfillment_status'] : '';
        $orderStatus = null;
        if ($financial !== '' && $fulfillment !== '') {
            $orderStatus = $financial.' / '.$fulfillment;
        } elseif ($financial !== '') {
            $orderStatus = $financial;
        } elseif ($fulfillment !== '') {
            $orderStatus = $fulfillment;
        }
        if (! empty($row['cancelled_at'])) {
            $orderStatus = 'cancelled';
        }

        $createdAt = $row['created_at'] ?? null;

        return [
            'shopify_order_id' => (int) $row['id'],
            'shopify_customer_id' => $shopifyCustomerId,
            'total_price' => $row['current_total_price'] ?? $row['total_price'] ?? null,
            'subtotal_price' => $row['current_subtotal_price'] ?? $row['subtotal_price'] ?? null,
            'total_discounts' => $row['current_total_discounts'] ?? $row['total_discounts'] ?? null,
            'total_tax' => $row['current_total_tax'] ?? $row['total_tax'] ?? null,
            'shipping_price' => $this->shippingPriceFromOrderRow($row),
            'currency' => $row['currency'] ?? $row['presentment_currency'] ?? null,
            'source_name' => $this->nullableString($row['source_name'] ?? null),
            'source_identifier' => $this->nullableString($row['source_identifier'] ?? null),
            'landing_site' => $this->nullableString($row['landing_site'] ?? null),
            'referring_site' => $this->nullableString($row['referring_site'] ?? null),
            'line_items_count' => $this->lineItemsCountFromOrderRow($row),
            'order_status' => $orderStatus,
            'financial_status' => $financial !== '' ? $financial : null,
            'fulfillment_status' => $fulfillment !== '' ? $fulfillment : null,
            'cancelled_at' => ! empty($row['cancelled_at']) ? (string) $row['cancelled_at'] : null,
            'order_date' => $createdAt !== null && $createdAt !== '' ? (string) $createdAt : null,
            'last_synced_at' => now()->toIso8601String(),
            'raw_payload' => $row,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function shippingPriceFromOrderRow(array $row): ?float
    {
        if (isset($row['total_shipping_price_set']['shop_money']['amount'])) {
            return (float) $row['total_shipping_price_set']['shop_money']['amount'];
        }

        $shippingLines = $row['shipping_lines'] ?? null;
        if (! is_array($shippingLines)) {
            return null;
        }

        $total = 0.0;
        foreach ($shippingLines as $line) {
            if (is_array($line) && isset($line['price']) && is_numeric($line['price'])) {
                $total += (float) $line['price'];
            }
        }

        return $total > 0 ? $total : null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function lineItemsCountFromOrderRow(array $row): int
    {
        $lineItems = $row['line_items'] ?? null;
        if (! is_array($lineItems)) {
            return 0;
        }

        return count($lineItems);
    }

    /**
     * Full customer sync: paginate Admin REST customers, upsert into `shopify_customers`
     * with raw API payload and `last_synced_at` on each row.
     */
    public function syncCustomers(int $pageLimit = 250): int
    {
        if (! $this->isConfigured()) {
            return 0;
        }

        $pageLimit = max(1, min(250, $pageLimit));
        $count = 0;
        $path = 'customers.json?limit='.$pageLimit;

        $this->paginateRestResource($path, 'customers', function (array $page) use (&$count): void {
            foreach ($page as $row) {
                if (! is_array($row) || ! isset($row['id'])) {
                    continue;
                }
                $this->upsertCustomerRow($row);
                $count++;
            }
        });

        return $count;
    }

    public function syncOrders(int $pageLimit = 250): int
    {
        if (! $this->isConfigured()) {
            return 0;
        }

        $pageLimit = max(1, min(250, $pageLimit));
        $count = 0;
        $path = 'orders.json?status=any&limit='.$pageLimit;

        $this->paginateRestResource($path, 'orders', function (array $page) use (&$count): void {
            foreach ($page as $row) {
                if (! is_array($row) || ! isset($row['id'])) {
                    continue;
                }
                $this->upsertOrderRow($row);
                $count++;
            }
        });

        return $count;
    }

    public function syncProducts(int $pageLimit = 250): int
    {
        if (! $this->isConfigured()) {
            return 0;
        }

        $pageLimit = max(1, min(250, $pageLimit));
        $count = 0;
        $path = 'products.json?limit='.$pageLimit;

        $this->paginateRestResource($path, 'products', function (array $page) use (&$count): void {
            foreach ($page as $row) {
                if (! is_array($row) || ! isset($row['id'])) {
                    continue;
                }
                $this->upsertProductRow($row);
                $count++;
            }
        });

        return $count;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createCustomerFromCrm(array $data): ShopifyCustomer
    {
        $payload = $this->customerPayloadFromCrmData($data);
        $response = $this->sendWithRetries('POST', $this->absoluteAdminUrl('customers.json'), [
            'json' => ['customer' => $payload],
        ]);
        $decoded = $this->decodeJsonBody($response);
        $row = $decoded['customer'] ?? null;
        if (! is_array($row) || ! isset($row['id'])) {
            throw new ShopifyApiException('Shopify did not return a customer after create.');
        }

        return $this->upsertReturnedCustomerRow($row, isset($data['customer_id']) ? (int) $data['customer_id'] : null);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateShopifyCustomerFromCrm(ShopifyCustomer $record, array $data): ShopifyCustomer
    {
        $shopifyId = (int) $record->shopify_customer_id;
        if ($shopifyId <= 0) {
            return $this->createCustomerFromCrm($data);
        }

        $payload = $this->customerPayloadFromCrmData($data);
        $payload['id'] = $shopifyId;

        $response = $this->sendWithRetries('PUT', $this->absoluteAdminUrl('customers/'.$shopifyId.'.json'), [
            'json' => ['customer' => $payload],
        ]);
        $decoded = $this->decodeJsonBody($response);
        $row = $decoded['customer'] ?? null;
        if (! is_array($row) || ! isset($row['id'])) {
            throw new ShopifyApiException('Shopify did not return a customer after update.');
        }

        return $this->upsertReturnedCustomerRow($row, isset($data['customer_id']) ? (int) $data['customer_id'] : $record->customer_id);
    }

    /**
     * @throws \InvalidArgumentException When the row cannot be matched or auto-created in CRM
     */
    public function ensureCrmCustomerForShopifyRecord(ShopifyCustomer $shopifyCustomer): Customer
    {
        if ($shopifyCustomer->customer_id !== null) {
            $existing = Customer::query()->find($shopifyCustomer->customer_id);
            if ($existing !== null) {
                return $existing;
            }

            $shopifyCustomer->forceFill(['customer_id' => null])->save();
        }

        $this->linkShopifyCustomerToCrmCustomer($shopifyCustomer);
        $shopifyCustomer->refresh();

        if ($shopifyCustomer->customer_id === null) {
            throw new \InvalidArgumentException(
                'Could not link this Shopify row to a CRM customer. Ensure the row has a valid email, sync from Shopify, or enable CRM auto-creation for Shopify customers in config.'
            );
        }

        return Customer::query()->findOrFail($shopifyCustomer->customer_id);
    }

    /**
     * Reset script time limit for each paginated Shopify request so full sync does not die at PHP default (often 30s).
     */
    protected function resetMaxExecutionTimeForSyncPage(): void
    {
        $seconds = (int) config('services.shopify.sync_page_time_limit_seconds', 180);
        if (! function_exists('set_time_limit')) {
            return;
        }

        try {
            if ($seconds === 0) {
                set_time_limit(0);
            } else {
                set_time_limit(max(30, $seconds));
            }
        } catch (Throwable) {
            // Host may disable set_time_limit
        }
    }

    /**
     * @param  callable(array<int, array<string, mixed>>): void  $consume
     */
    protected function paginateRestResource(string $initialRelativePath, string $payloadKey, callable $consume): void
    {
        $url = $this->absoluteAdminUrl($initialRelativePath);

        while ($url !== null) {
            $this->resetMaxExecutionTimeForSyncPage();
            $response = $this->sendWithRetries('GET', $url);
            $decoded = $this->decodeJsonBody($response);
            $page = $decoded[$payloadKey] ?? [];

            if (! is_array($page)) {
                Log::warning('ShopifyService: unexpected payload shape', ['key' => $payloadKey]);
                break;
            }

            /** @var array<int, array<string, mixed>> $page */
            $consume($page);

            $url = $this->nextPageUrl($response->getHeaderLine('Link'));
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function sendWithRetries(string $method, string $url, array $options = []): ResponseInterface
    {
        $maxAttempts = max(1, (int) config('services.shopify.max_retries', 5));
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                $response = $this->httpClient()->request($method, $url, $options);
            } catch (ConnectException $e) {
                Log::warning('ShopifyService: connection error', [
                    'message' => $e->getMessage(),
                    'attempt' => $attempt,
                ]);
                if ($attempt >= $maxAttempts) {
                    throw new ShopifyApiException(
                        'Unable to reach Shopify: '.$e->getMessage(),
                        0,
                        $e
                    );
                }
                usleep(min(250_000 * $attempt, 2_000_000));

                continue;
            } catch (GuzzleException $e) {
                throw new ShopifyApiException(
                    'Shopify HTTP error: '.$e->getMessage(),
                    0,
                    $e
                );
            }

            $status = $response->getStatusCode();

            if ($status === 429) {
                $wait = $this->retryAfterSeconds($response, $attempt);
                Log::warning('ShopifyService: 429 Too Many Requests', [
                    'wait_seconds' => $wait,
                    'attempt' => $attempt,
                    'url' => $url,
                ]);
                sleep($wait);
                if ($attempt >= $maxAttempts) {
                    throw new ShopifyApiException(
                        'Shopify rate limit: exceeded retries.',
                        429,
                        null,
                        (string) $response->getBody()
                    );
                }

                continue;
            }

            if ($status >= 500 && $status < 600) {
                Log::warning('ShopifyService: server error', ['status' => $status, 'attempt' => $attempt]);
                if ($attempt >= $maxAttempts) {
                    $this->throwForFailedResponse($response);
                }
                sleep(min(2 ** min($attempt, 5), 30));

                continue;
            }

            if ($status >= 400) {
                $this->throwForFailedResponse($response);
            }

            $this->respectCallLimitHeader($response);

            return $response;
        }
    }

    protected function throwForFailedResponse(ResponseInterface $response): never
    {
        $status = $response->getStatusCode();
        $body = (string) $response->getBody();
        $summary = $body !== '' ? $body : '(empty body)';

        Log::error('ShopifyService: API error response', [
            'status' => $status,
            'body_preview' => mb_substr($summary, 0, 2000),
        ]);

        $decoded = null;
        try {
            /** @var array<string, mixed>|null $decoded */
            $decoded = $body !== '' ? Utils::jsonDecode($body, true) : null;
        } catch (Throwable) {
            $decoded = null;
        }

        $message = is_array($decoded) && isset($decoded['errors'])
            ? 'Shopify API error: '.(is_string($decoded['errors']) ? $decoded['errors'] : Utils::jsonEncode($decoded['errors']))
            : 'Shopify API request failed (HTTP '.$status.').';

        throw new ShopifyApiException($message, $status, null, $body);
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeJsonBody(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();

        if ($body === '') {
            return [];
        }

        try {
            /** @var array<string, mixed> $data */
            $data = Utils::jsonDecode($body, true);

            return is_array($data) ? $data : [];
        } catch (Throwable) {
            Log::error('ShopifyService: invalid JSON from Shopify');

            return [];
        }
    }

    protected function respectCallLimitHeader(ResponseInterface $response): void
    {
        $line = trim($response->getHeaderLine('X-Shopify-Shop-Api-Call-Limit'));
        if ($line === '' || ! preg_match('#(\d+)\s*/\s*(\d+)#', $line, $m)) {
            return;
        }

        $used = (int) $m[1];
        $max = max(1, (int) $m[2]);
        $threshold = (float) config('services.shopify.rate_limit_threshold', 0.85);
        $thresholdCalls = (int) floor($max * min(max($threshold, 0.5), 0.99));

        if ($used >= $thresholdCalls) {
            $pause = (int) config('services.shopify.rate_limit_pause_seconds', 2);
            $pause = max(1, min(60, $pause));
            Log::debug('ShopifyService: call limit high, pausing', [
                'header' => $line,
                'pause_seconds' => $pause,
            ]);
            sleep($pause);
        }
    }

    protected function retryAfterSeconds(ResponseInterface $response, int $attempt): int
    {
        $header = $response->getHeaderLine('Retry-After');
        if ($header !== '' && ctype_digit($header)) {
            return max(1, min(60, (int) $header));
        }

        return min(2 * $attempt, 10);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function customerPayloadFromCrmData(array $data): array
    {
        [$firstName, $lastName] = $this->splitName((string) ($data['name'] ?? ''));

        $payload = array_filter([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $this->nullableString($data['email'] ?? null),
            'phone' => $this->nullableString($data['phone'] ?? null),
            'tags' => $this->nullableString($data['tags'] ?? null),
        ], static fn ($value) => $value !== null && $value !== '');

        $address = array_filter([
            'province' => $this->nullableString($data['province'] ?? null),
            'zip' => $this->nullableString($data['zip'] ?? null),
            'phone' => $this->nullableString($data['phone'] ?? null),
            'default' => true,
        ], static fn ($value) => $value !== null && $value !== '');

        if (count($address) > 1) {
            $payload['addresses'] = [$address];
        }

        return $payload;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    protected function splitName(string $name): array
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);
        if ($name === '') {
            return [null, null];
        }

        $parts = explode(' ', $name, 2);

        return [$parts[0] ?: null, $parts[1] ?? null];
    }

    protected function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function upsertReturnedCustomerRow(array $row, ?int $crmCustomerId = null): ShopifyCustomer
    {
        $record = $this->upsertCustomerRow($row);
        if ($crmCustomerId !== null && $crmCustomerId > 0) {
            $record->forceFill(['customer_id' => $crmCustomerId])->save();
        }

        return $record->refresh();
    }

    protected function nextPageUrl(string $linkHeader): ?string
    {
        if ($linkHeader === '') {
            return null;
        }

        foreach (array_map('trim', explode(',', $linkHeader)) as $link) {
            if (! str_contains($link, 'rel=')) {
                continue;
            }
            if (! str_contains($link, 'next')) {
                continue;
            }
            if (preg_match('/<([^>]+)>/', $link, $m)) {
                $href = $m[1] ?? '';
                if ($href !== '') {
                    return $href;
                }
            }
        }

        return null;
    }

    protected function absoluteAdminUrl(string $relativePath): string
    {
        $relativePath = ltrim($relativePath, '/');

        return $this->baseUrl().$relativePath;
    }

    protected function baseUrl(): string
    {
        $configured = trim((string) config('shopify.base_url', ''));
        if ($configured !== '') {
            return rtrim($configured, '/').'/';
        }

        return 'https://'.$this->normalizedStoreDomain().'/admin/api/'.$this->apiVersion().'/';
    }

    protected function normalizedStoreDomain(): string
    {
        $raw = trim((string) (
            config('shopify.store_url')
            ?: config('services.shopify.store_url')
            ?: env('SHOPIFY_STORE_URL')
            ?: env('SHOPIFY_5CORE_DOMAIN')
            ?: env('BUSINESS_5CORE_SHOPIFY_DOMAIN')
        ));
        if ($raw === '') {
            return '';
        }

        $raw = preg_replace('#^https?://#i', '', $raw) ?? $raw;

        return rtrim($raw, '/');
    }

    protected function apiVersion(): string
    {
        return trim((string) config('shopify.api_version', config('services.shopify.api_version', '2024-01')), '/');
    }

    protected function accessToken(): string
    {
        return trim((string) (
            config('shopify.access_token')
            ?: config('services.shopify.access_token')
            ?: config('services.shopify.password')
            ?: env('SHOPIFY_ACCESS_TOKEN')
            ?: env('SHOPIFY_PASSWORD')
            ?: env('SHOPIFY_5CORE_PASSWORD')
            ?: env('BUSINESS_5CORE_SHOPIFY_ACCESS_TOKEN')
        ));
    }

    protected function extractPageInfoFromUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $query = parse_url($url, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return null;
        }

        parse_str($query, $params);
        $pageInfo = $params['page_info'] ?? null;

        return is_string($pageInfo) && $pageInfo !== '' ? $pageInfo : null;
    }

    protected function httpClient(): Client
    {
        if ($this->client instanceof Client) {
            return $this->client;
        }

        $timeout = (float) config('services.shopify.http_timeout', 120);
        $connect = (float) config('services.shopify.connect_timeout', 15);
        $verify = (bool) config('shopify.ssl_verify', true);

        $this->client = new Client([
            'timeout' => max(5.0, $timeout),
            'connect_timeout' => max(1.0, $connect),
            'http_errors' => false,
            'verify' => $verify,
            'headers' => [
                'X-Shopify-Access-Token' => $this->accessToken(),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);

        return $this->client;
    }

    /**
     * Upsert a single customer row from Shopify REST JSON (denormalized fields + optional `raw_payload`).
     *
     * @param  array<string, mixed>  $row
     */
    protected function upsertCustomerRow(array $row): ShopifyCustomer
    {
        $phone = $row['phone'] ?? null;
        if ($phone === null || $phone === '') {
            $defaultAddress = $row['default_address'] ?? null;
            if (is_array($defaultAddress) && ! empty($defaultAddress['phone'])) {
                $phone = $defaultAddress['phone'];
            }
        }

        $email = isset($row['email']) ? (string) $row['email'] : null;
        if ($email === '') {
            $email = null;
        }

        $syncedAt = now();

        $attributes = [
            'email' => $email,
            'first_name' => $row['first_name'] ?? null,
            'last_name' => $row['last_name'] ?? null,
            'phone' => $phone,
            'sync_status' => 'synced',
            'last_synced_at' => $syncedAt,
        ];

        if ($this->shopifyCustomersTableHasRawPayloadColumn()) {
            $attributes['raw_payload'] = $row;
        }

        $record = ShopifyCustomer::query()->updateOrCreate(
            ['shopify_customer_id' => (int) $row['id']],
            $attributes
        );

        $record = $this->customerClassifier->classify($record);
        $this->linkShopifyCustomerToCrmCustomer($record);

        return $record;
    }

    protected function shopifyCustomersTableHasRawPayloadColumn(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $cached = Schema::hasColumn('shopify_customers', 'raw_payload');

        return $cached;
    }

    protected function linkShopifyCustomerToCrmCustomer(ShopifyCustomer $shopifyCustomer): void
    {
        if ($shopifyCustomer->customer_id !== null) {
            return;
        }

        $email = $shopifyCustomer->email;
        if ($email === null || $email === '') {
            return;
        }

        $emailLower = mb_strtolower($email);

        $existingId = Customer::query()
            ->whereRaw('LOWER(email) = ?', [$emailLower])
            ->value('id');

        if ($existingId !== null) {
            $shopifyCustomer->forceFill(['customer_id' => $existingId])->save();

            return;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        if (! (bool) config('services.crm.shopify_auto_create_customers', true)) {
            return;
        }

        $name = trim(implode(' ', array_filter([
            $shopifyCustomer->first_name !== null ? trim((string) $shopifyCustomer->first_name) : '',
            $shopifyCustomer->last_name !== null ? trim((string) $shopifyCustomer->last_name) : '',
        ])));

        if ($name === '') {
            $name = $email;
        }

        $crmCustomer = Customer::query()->create([
            'company_id' => null,
            'name' => $name,
            'email' => $email,
            'phone' => $shopifyCustomer->phone,
        ]);

        $shopifyCustomer->forceFill(['customer_id' => $crmCustomer->id])->save();
    }

    /**
     * `shopify_orders.shopify_customer_id` is FK → `shopify_customers.shopify_customer_id`; parent row must exist first.
     *
     * @param  array<string, mixed>  $row
     */
    protected function upsertOrderRow(array $row): ShopifyOrder
    {
        $normalized = $this->normalizeOrderApiRow($row);
        $customerPayload = $row['customer'] ?? null;

        if (is_array($customerPayload) && isset($customerPayload['id'])) {
            $this->upsertCustomerRow($customerPayload);
        } elseif ($normalized['shopify_customer_id'] !== null) {
            $this->ensureStubShopifyCustomerForOrder($normalized['shopify_customer_id'], $row);
        }

        $shopifyOrderPk = $normalized['shopify_order_id'];
        $wasNew = ! ShopifyOrder::query()->where('shopify_order_id', $shopifyOrderPk)->exists();

        $orderDate = $normalized['order_date'] !== null
            ? Carbon::parse($normalized['order_date'])
            : null;
        $lastSyncedAt = isset($normalized['last_synced_at']) && is_string($normalized['last_synced_at'])
            ? Carbon::parse($normalized['last_synced_at'])
            : now();

        $order = ShopifyOrder::query()->updateOrCreate(
            ['shopify_order_id' => $shopifyOrderPk],
            [
                'shopify_customer_id' => $normalized['shopify_customer_id'],
                'total_price' => $normalized['total_price'],
                'subtotal_price' => $normalized['subtotal_price'],
                'total_discounts' => $normalized['total_discounts'],
                'total_tax' => $normalized['total_tax'],
                'shipping_price' => $normalized['shipping_price'],
                'currency' => $normalized['currency'],
                'source_name' => $normalized['source_name'],
                'source_identifier' => $normalized['source_identifier'],
                'landing_site' => $normalized['landing_site'],
                'referring_site' => $normalized['referring_site'],
                'line_items_count' => $normalized['line_items_count'],
                'order_status' => $normalized['order_status'],
                'financial_status' => $normalized['financial_status'],
                'fulfillment_status' => $normalized['fulfillment_status'],
                'cancelled_at' => $normalized['cancelled_at'] !== null
                    ? Carbon::parse($normalized['cancelled_at'])
                    : null,
                'order_date' => $orderDate,
                'last_synced_at' => $lastSyncedAt,
                'raw_payload' => $normalized['raw_payload'],
            ]
        );

        if ($order->shopify_customer_id !== null) {
            $customer = ShopifyCustomer::query()
                ->where('shopify_customer_id', $order->shopify_customer_id)
                ->first();

            if ($customer !== null) {
                $this->customerClassifier->classify($customer, $normalized['raw_payload']);
            }
        }

        Event::dispatch(new ShopifyOrderImported($order, $wasNew));

        return $order;
    }

    /**
     * When the order references a Shopify customer id (e.g. REST `user_id`) but does not embed `customer`,
     * ensure a parent `shopify_customers` row exists so the order FK insert succeeds.
     *
     * @param  array<string, mixed>  $orderRow
     */
    protected function ensureStubShopifyCustomerForOrder(int $shopifyCustomerId, array $orderRow): void
    {
        if (ShopifyCustomer::query()->where('shopify_customer_id', $shopifyCustomerId)->exists()) {
            return;
        }

        $email = null;
        foreach (['contact_email', 'email'] as $key) {
            if (! empty($orderRow[$key]) && is_string($orderRow[$key])) {
                $e = trim($orderRow[$key]);
                if ($e !== '') {
                    $email = $e;
                    break;
                }
            }
        }

        $stub = [
            'id' => $shopifyCustomerId,
            'email' => $email,
            'first_name' => null,
            'last_name' => null,
            'phone' => ! empty($orderRow['phone']) && is_string($orderRow['phone']) ? trim($orderRow['phone']) : null,
        ];

        $ship = $orderRow['shipping_address'] ?? null;
        if (is_array($ship)) {
            if (! empty($ship['first_name'])) {
                $stub['first_name'] = (string) $ship['first_name'];
            }
            if (! empty($ship['last_name'])) {
                $stub['last_name'] = (string) $ship['last_name'];
            }
            if (($stub['phone'] === null || $stub['phone'] === '') && ! empty($ship['phone'])) {
                $stub['phone'] = (string) $ship['phone'];
            }
        }

        $this->upsertCustomerRow($stub);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function upsertProductRow(array $row): ShopifyProduct
    {
        $variant = isset($row['variants'][0]) && is_array($row['variants'][0])
            ? $row['variants'][0]
            : null;
        $price = is_array($variant) && isset($variant['price']) ? $variant['price'] : null;
        $inventory = null;
        if (is_array($variant) && array_key_exists('inventory_quantity', $variant)) {
            $inventory = (int) $variant['inventory_quantity'];
        }

        return ShopifyProduct::query()->updateOrCreate(
            ['shopify_product_id' => (int) $row['id']],
            [
                'title' => $row['title'] ?? null,
                'price' => $price,
                'inventory' => $inventory,
                'raw_payload' => $row,
            ]
        );
    }
}
