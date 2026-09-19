<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Pull Wayfair POs for /wayfair/daily-sales.
 *
 * Official dropship query supports fromDate (newest open/accepted orders).
 * The legacy purchaseOrders query is oldest-first and no longer reaches current days.
 */
class WayfairDailyOrderFetchService
{
    public const GRAPHQL_URL = 'https://api.wayfair.com/v1/graphql';

    public const PAGE_LIMIT = 25;

    public const PAGE_PAUSE_USEC = 3100000;

    public function __construct(private WayfairApiService $api)
    {
    }

    /**
     * @return array{ok: bool, source: string, orders: array<int, array>, errors: array}
     */
    public function fetchPurchaseOrders(Carbon $fromDate, ?string $token = null, bool $pauseBetweenPages = true): array
    {
        $token = $token ?: $this->api->getAccessTokenWithScope(null);
        $dropship = $this->fetchViaDropship($token, $fromDate, $pauseBetweenPages);
        if ($dropship['ok'] || $dropship['orders'] !== []) {
            return $dropship;
        }

        Log::warning('WayfairDailyOrderFetchService: dropship query failed, falling back to legacy purchaseOrders', [
            'errors' => $dropship['errors'],
        ]);

        return $this->fetchViaLegacyPurchaseOrders($token, $fromDate, $pauseBetweenPages);
    }

    /**
     * @return array{ok: bool, source: string, orders: array<int, array>, errors: array}
     */
    public function fetchViaDropship(string $token, Carbon $fromDate, bool $pauseBetweenPages = true): array
    {
        $collected = [];
        $seen = [];
        $errors = [];
        $ok = true;

        foreach ([false, true] as $hasResponse) {
            $cursor = $fromDate->copy()->utc()->startOfDay()->format('Y-m-d\TH:i:s\Z');
            $pages = 0;

            while ($pages < 200) {
                $pages++;
                $page = $this->queryDropshipPage($token, $cursor, $hasResponse);
                if (! $page['ok']) {
                    $ok = false;
                    $errors = array_merge($errors, $page['errors']);
                    break 2;
                }

                $orders = $page['orders'];
                $newOnPage = 0;
                $lastPoDate = $cursor;

                foreach ($orders as $po) {
                    $poNumber = trim((string) ($po['poNumber'] ?? ''));
                    if ($poNumber === '' || isset($seen[$poNumber])) {
                        if ($poNumber !== '' && ! empty($po['poDate'])) {
                            $lastPoDate = (string) $po['poDate'];
                        }
                        continue;
                    }
                    $seen[$poNumber] = true;
                    $collected[] = $po;
                    $newOnPage++;
                    if (! empty($po['poDate'])) {
                        $lastPoDate = (string) $po['poDate'];
                    }
                }

                if (count($orders) < self::PAGE_LIMIT) {
                    break;
                }

                $cursor = $this->advanceCursor($cursor, $lastPoDate, $newOnPage === 0);

                if ($pauseBetweenPages) {
                    usleep(self::PAGE_PAUSE_USEC);
                }
            }
        }

        return [
            'ok' => $ok,
            'source' => 'dropship',
            'orders' => $collected,
            'errors' => $errors,
        ];
    }

    /**
     * @return array{ok: bool, orders: array<int, array>, errors: array}
     */
    public function queryDropshipPage(string $token, string $fromDate, bool $hasResponse): array
    {
        $query = <<<'GRAPHQL'
        query GetDropshipPurchaseOrders($limit: Int32, $hasResponse: Boolean, $fromDate: IsoDateTime, $sortOrder: SortOrder) {
            getDropshipPurchaseOrders(limit: $limit, hasResponse: $hasResponse, fromDate: $fromDate, sortOrder: $sortOrder) {
                poNumber
                poDate
                estimatedShipDate
                customerName
                customerAddress1
                customerAddress2
                customerCity
                customerState
                customerPostalCode
                shippingInfo {
                    shipSpeed
                    carrierCode
                }
                packingSlipUrl
                warehouse {
                    id
                    name
                }
                products {
                    partNumber
                    quantity
                    price
                    event {
                        id
                        type
                        name
                    }
                }
                shipTo {
                    name
                    address1
                    address2
                    city
                    state
                    country
                    postalCode
                    phoneNumber
                }
            }
        }
        GRAPHQL;

        $response = $this->api->apiHttpClient()
            ->withToken($token)
            ->post(self::GRAPHQL_URL, [
                'query' => $query,
                'variables' => [
                    'limit' => self::PAGE_LIMIT,
                    'hasResponse' => $hasResponse,
                    'fromDate' => $fromDate,
                    'sortOrder' => 'ASC',
                ],
            ]);

        $json = $response->json();
        $errors = is_array($json['errors'] ?? null) ? $json['errors'] : [];
        $orders = $json['data']['getDropshipPurchaseOrders'] ?? null;

        if (! $response->successful() || $errors !== [] || ! is_array($orders)) {
            if ($errors === [] && ! $response->successful()) {
                $errors[] = ['message' => 'HTTP '.$response->status().' '.$response->body()];
            }

            return [
                'ok' => false,
                'orders' => [],
                'errors' => $errors,
            ];
        }

        return [
            'ok' => true,
            'orders' => $orders,
            'errors' => [],
        ];
    }

    /**
     * @return array{ok: bool, source: string, orders: array<int, array>, errors: array}
     */
    public function fetchViaLegacyPurchaseOrders(string $token, Carbon $fromDate, bool $pauseBetweenPages = true): array
    {
        $cutoff = $fromDate->copy()->startOfDay();
        $collected = [];
        $errors = [];
        $offset = 0;
        $emptyStreak = 0;

        while ($offset < 20000) {
            $page = $this->queryLegacyPage($token, $offset);
            if (! $page['ok']) {
                $errors = array_merge($errors, $page['errors']);
                $emptyStreak++;
                if ($emptyStreak >= 3) {
                    break;
                }
                if ($pauseBetweenPages) {
                    usleep(self::PAGE_PAUSE_USEC);
                }
                $offset += 100;
                continue;
            }

            $emptyStreak = 0;
            $orders = $page['orders'];
            if ($orders === []) {
                break;
            }

            foreach ($orders as $po) {
                $poDate = $po['poDate'] ?? null;
                if (! $poDate) {
                    continue;
                }
                if (Carbon::parse($poDate)->lt($cutoff)) {
                    continue;
                }
                $collected[] = $po;
            }

            if (count($orders) < 100) {
                break;
            }

            $offset += 100;
            if ($pauseBetweenPages) {
                usleep(self::PAGE_PAUSE_USEC);
            }
        }

        return [
            'ok' => $errors === [],
            'source' => 'legacy',
            'orders' => $collected,
            'errors' => $errors,
        ];
    }

    /**
     * @return array{ok: bool, orders: array<int, array>, errors: array}
     */
    public function queryLegacyPage(string $token, int $offset): array
    {
        $query = <<<'GRAPHQL'
        query GetPurchaseOrders($limit: Int!, $offset: Int!) {
            purchaseOrders(limit: $limit, offset: $offset) {
                poNumber
                poDate
                estimatedShipDate
                customerName
                customerAddress1
                customerAddress2
                customerCity
                customerState
                customerPostalCode
                shippingInfo {
                    shipSpeed
                    carrierCode
                }
                packingSlipUrl
                warehouse {
                    id
                    name
                }
                products {
                    partNumber
                    quantity
                    price
                    event {
                        id
                        type
                        name
                    }
                }
                shipTo {
                    name
                    address1
                    address2
                    city
                    state
                    country
                    postalCode
                    phoneNumber
                }
            }
        }
        GRAPHQL;

        $response = $this->api->apiHttpClient()
            ->withToken($token)
            ->post(self::GRAPHQL_URL, [
                'query' => $query,
                'variables' => [
                    'limit' => 100,
                    'offset' => $offset,
                ],
            ]);

        $json = $response->json();
        $errors = is_array($json['errors'] ?? null) ? $json['errors'] : [];
        $orders = $json['data']['purchaseOrders'] ?? null;

        if (! $response->successful() || $errors !== [] || ! is_array($orders)) {
            if ($errors === [] && ! $response->successful()) {
                $errors[] = ['message' => 'HTTP '.$response->status().' '.$response->body()];
            }

            return [
                'ok' => false,
                'orders' => [],
                'errors' => $errors,
            ];
        }

        return [
            'ok' => true,
            'orders' => $orders,
            'errors' => [],
        ];
    }

    public function advanceCursor(string $current, string $lastPoDate, bool $forceForward): string
    {
        try {
            $next = Carbon::parse($lastPoDate)->utc();
        } catch (\Throwable $e) {
            $next = Carbon::parse($current)->utc()->addSecond();
        }

        $cursor = Carbon::parse($current)->utc();
        if ($forceForward || $next->lte($cursor)) {
            $next = $cursor->copy()->addSecond();
        }

        return $next->format('Y-m-d\TH:i:s\Z');
    }
}
