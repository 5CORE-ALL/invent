<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * EbayPushService
 *
 * Delegates all eBay price-push communication to an external cPanel Laravel
 * microservice instead of calling the eBay Trading API directly. This keeps
 * eBay credentials and API complexity isolated on the microservice side.
 *
 * Configuration (via config/services.php + .env):
 *   EBAY_PUSH_MICROSERVICE_URL        – base URL of the cPanel microservice
 *   EBAY_PUSH_MICROSERVICE_TOKEN      – Bearer token for Authorization header
 *   EBAY_PUSH_MICROSERVICE_TIMEOUT    – HTTP timeout in seconds (default 60)
 *   EBAY_PUSH_MICROSERVICE_RETRIES    – retry attempts on server/network failure (default 3)
 *   EBAY_PUSH_MICROSERVICE_RETRY_DELAY_MS – milliseconds between retries (default 5000)
 */
class EbayPushService
{
    /** Base URL of the cPanel microservice (no trailing slash). */
    protected string $baseUrl;

    /** Bearer token sent in every Authorization header. */
    protected string $token;

    /** Maximum seconds to wait for a response from the microservice. */
    protected int $timeout;

    /**
     * How many times to retry a request that fails due to a network error
     * or a 5xx server response (4xx client errors are NOT retried).
     */
    protected int $retries;

    /** Milliseconds to wait between consecutive retry attempts. */
    protected int $retryDelayMs;

    public function __construct()
    {
        $this->baseUrl      = rtrim(config('services.ebay_push_microservice.url', ''), '/');
        $this->token        = config('services.ebay_push_microservice.token', '');
        $this->timeout      = (int) config('services.ebay_push_microservice.timeout', 60);
        $this->retries      = (int) config('services.ebay_push_microservice.retries', 3);
        $this->retryDelayMs = (int) config('services.ebay_push_microservice.retry_delay_ms', 5000);
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Push a single SKU price to the eBay cPanel microservice.
     *
     * The microservice is responsible for communicating with eBay's Trading API
     * (ReviseFixedPriceItem / ReviseItem). This application only needs to
     * supply the business data.
     *
     * @param  array{
     *     sku:           string,
     *     price:         float,
     *     ebay_item_id?: string|null,
     *     quantity?:     int|null,
     *     title?:        string|null,
     * } $payload
     *
     * @return array{
     *     success:            bool,
     *     message:            string,
     *     accountRestricted?: bool,
     *     data?:              array,
     *     errors?:            array,
     * }
     */
    public function pushPrice(array $payload): array
    {
        $endpoint = $this->baseUrl . '/api/push-ebay-price';

        // Build a clean body — omit null / empty optional fields
        $body = $this->buildBody([
            'sku'          => $payload['sku']          ?? null,
            'price'        => $payload['price']        ?? null,
            'ebay_item_id' => $payload['ebay_item_id'] ?? null,
            'quantity'     => $payload['quantity']     ?? null,
            'title'        => $payload['title']        ?? null,
        ]);

        // Detailed pre-request log (required format)
        Log::info('Calling eBay microservice', [
            'url'     => $endpoint,
            'payload' => $body,
        ]);

        try {
            $response = $this->makeRequest($endpoint, $body);
            return $this->handleResponse($response, $body);

        } catch (RequestException $e) {
            return $this->handleRequestException($e, $endpoint, $body, 'single price push');

        } catch (\Exception $e) {
            return $this->handleGenericException($e, $endpoint, $body, 'single price push');
        }
    }

    /**
     * Push prices for multiple SKUs in one batch request to the microservice.
     *
     * The microservice endpoint is expected at: POST /api/push-ebay-price/bulk
     * with body: { "items": [ {sku, price, ebay_item_id?, quantity?, title?}, ... ] }
     *
     * @param  array<int, array{
     *     sku:           string,
     *     price:         float,
     *     ebay_item_id?: string|null,
     *     quantity?:     int|null,
     *     title?:        string|null,
     * }> $items
     *
     * @return array{
     *     success: bool,
     *     message: string,
     *     results: array,
     *     errors?: array,
     * }
     */
    public function pushBulkPrices(array $items): array
    {
        $endpoint = $this->baseUrl . '/api/push-ebay-price/bulk';

        // Sanitize each item in the batch
        $sanitizedItems = array_map(fn(array $item) => $this->buildBody([
            'sku'          => $item['sku']          ?? null,
            'price'        => $item['price']        ?? null,
            'ebay_item_id' => $item['ebay_item_id'] ?? null,
            'quantity'     => $item['quantity']     ?? null,
            'title'        => $item['title']        ?? null,
        ]), $items);

        $body = ['items' => array_values($sanitizedItems)];

        // Detailed pre-request log (required format)
        Log::info('Calling eBay microservice', [
            'url'     => $endpoint,
            'payload' => ['count' => count($items), 'skus' => array_column($sanitizedItems, 'sku')],
        ]);

        try {
            $response = $this->makeRequest($endpoint, $body);
            $result   = $this->handleResponse($response, $body);

            // Ensure a results key exists for bulk callers
            if (!isset($result['results'])) {
                $result['results'] = $result['data'] ?? [];
            }

            Log::info('[EbayPushService] Bulk price push finished', [
                'success' => $result['success'],
                'count'   => count($items),
            ]);

            return $result;

        } catch (RequestException $e) {
            $result = $this->handleRequestException($e, $endpoint, $body, 'bulk price push');
            $result['results'] = [];
            return $result;

        } catch (\Exception $e) {
            $result = $this->handleGenericException($e, $endpoint, $body, 'bulk price push');
            $result['results'] = [];
            return $result;
        }
    }

    /**
     * Normalize a microservice HTTP response into the standard result array.
     *
     * Successful responses (2xx) return:
     *   ['success' => true, 'message' => '...', 'data' => [...]]
     *
     * Error responses (4xx / 5xx) return:
     *   ['success' => false, 'message' => '...', 'errors' => [...], 'accountRestricted' => bool]
     *
     * Errors are normalized to [['code' => '...', 'message' => '...']] so the
     * caller does not need to know whether they came from eBay's raw XML format
     * or from the microservice itself.
     *
     * @param  Response  $response       Parsed HTTP response from the microservice
     * @param  array     $requestPayload Original request body (logged for debugging)
     */
    public function handleResponse(Response $response, array $requestPayload = []): array
    {
        $status = $response->status();
        $json   = $response->json() ?? [];

        // Detailed post-response log (required format)
        Log::info('eBay microservice response', [
            'status' => $status,
            'body'   => $response->body(),
        ]);

        // 2xx – success
        if ($response->successful()) {
            return [
                'success' => true,
                'message' => $json['message'] ?? 'Price pushed successfully.',
                'data'    => $json,
            ];
        }

        // 4xx / 5xx – build normalized errors array
        $rawErrors = $json['errors'] ?? [];

        if (empty($rawErrors) && isset($json['message'])) {
            // Single message error (e.g. Laravel validation)
            $rawErrors = [['code' => (string) $status, 'message' => $json['message']]];
        } elseif (empty($rawErrors)) {
            $rawErrors = [['code' => (string) $status, 'message' => 'Microservice returned HTTP ' . $status]];
        }

        // Normalize each error to {code, message} – supports both the raw eBay
        // Trading API format (ErrorCode / LongMessage) and our own microservice format.
        $normalizedErrors = array_map(function (mixed $error): array {
            if (!is_array($error)) {
                return ['code' => 'MicroserviceError', 'message' => (string) $error];
            }

            return [
                'code'    => (string) ($error['code'] ?? $error['ErrorCode'] ?? 'MicroserviceError'),
                'message' => $error['message'] ?? $error['LongMessage'] ?? $error['ShortMessage'] ?? 'Unknown error',
            ];
        }, $rawErrors);

        // Forward the accountRestricted flag when the microservice detected it
        $accountRestricted = (bool) ($json['accountRestricted'] ?? false);

        Log::error('[EbayPushService] Microservice returned error response', [
            'http_status'       => $status,
            'errors'            => $normalizedErrors,
            'accountRestricted' => $accountRestricted,
            'payload'           => $requestPayload,
        ]);

        return [
            'success'           => false,
            'message'           => $normalizedErrors[0]['message'] ?? 'Microservice error',
            'errors'            => $normalizedErrors,
            'accountRestricted' => $accountRestricted,
            'data'              => $json,
        ];
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Execute a POST request to the microservice with auth, timeout, and retry.
     *
     * Headers sent on every request:
     *   Authorization: Bearer {token}      – microservice inbound auth
     *   Content-Type:  application/json    – explicit JSON body
     *   Accept:        application/json    – expect JSON back
     *
     * Retries are attempted only for connection failures and 5xx server errors.
     * 4xx client errors are NOT retried (definitive failures).
     * All non-2xx responses after retries are exhausted throw a RequestException
     * that includes the full response body for diagnosis.
     *
     * @throws RequestException  When all retries are exhausted or a non-retryable failure occurs
     */
    protected function makeRequest(string $endpoint, array $body): Response
    {
        return Http::withToken($this->token)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ])
            ->timeout($this->timeout)
            ->retry(
                $this->retries,
                $this->retryDelayMs,
                function (\Exception $e, \Illuminate\Http\Client\PendingRequest $request): bool {
                    // Do not retry 4xx client errors — repeating a bad request always fails the same way
                    if ($e instanceof RequestException && $e->response?->clientError()) {
                        Log::warning('[EbayPushService] Non-retryable 4xx client error — aborting retries', [
                            'status'   => $e->response?->status(),
                            'body'     => $e->response?->body(),
                            'message'  => $e->getMessage(),
                        ]);
                        return false;
                    }
                    // Retry on connection errors, timeouts, and 5xx server errors
                    Log::warning('[EbayPushService] Retryable error — will retry', [
                        'error' => $e->getMessage(),
                    ]);
                    return true;
                },
                throw: true  // Throw RequestException after all retries are exhausted (includes response body)
            )
            ->post($endpoint, $body);
    }

    /**
     * Strip null and empty-string values from a payload array.
     * This prevents unnecessary fields from being sent over the wire.
     */
    protected function buildBody(array $fields): array
    {
        return array_filter($fields, fn(mixed $v): bool => $v !== null && $v !== '');
    }

    /**
     * Convert a RequestException (HTTP-level failure after retries) into a
     * standard error result, with full logging.
     */
    protected function handleRequestException(
        RequestException $e,
        string           $endpoint,
        array            $body,
        string           $context
    ): array {
        Log::error("[EbayPushService] HTTP request failed after {$this->retries} retries ({$context})", [
            'endpoint'     => $endpoint,
            'payload'      => $body,
            'http_status'  => $e->response?->status(),
            'error'        => $e->getMessage(),
            'response_body'=> $e->response?->body(),
        ]);

        return [
            'success' => false,
            'message' => 'Microservice request failed: ' . $e->getMessage(),
            'errors'  => [['code' => 'MicroserviceError', 'message' => $e->getMessage()]],
        ];
    }

    /**
     * Convert any unexpected exception into a standard error result, with full
     * logging (including stack trace for easier post-mortem debugging).
     */
    protected function handleGenericException(
        \Exception $e,
        string     $endpoint,
        array      $body,
        string     $context
    ): array {
        Log::error("[EbayPushService] Unexpected exception during {$context}", [
            'endpoint' => $endpoint,
            'payload'  => $body,
            'error'    => $e->getMessage(),
            'trace'    => $e->getTraceAsString(),
        ]);

        return [
            'success' => false,
            'message' => 'Unexpected error: ' . $e->getMessage(),
            'errors'  => [['code' => 'Exception', 'message' => $e->getMessage()]],
        ];
    }
}
