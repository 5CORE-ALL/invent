<?php

namespace App\Services\MarketplaceManager;

use App\Models\AmazonOrder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SP-API Orders helpers used by Marketplace Manager:
 * restricted buyer/shipping PII (RDT) and confirmShipment.
 */
class AmazonSpOrdersClient
{
    protected string $endpoint = 'https://sellingpartnerapi-na.amazon.com';

    public function getAccessToken(): ?string
    {
        $res = Http::asForm()->timeout(30)->post('https://api.amazon.com/auth/o2/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => config('services.amazon_sp.refresh_token'),
            'client_id' => config('services.amazon_sp.client_id'),
            'client_secret' => config('services.amazon_sp.client_secret'),
        ]);

        $token = $res->json('access_token');

        return is_string($token) && $token !== '' ? $token : null;
    }

    public function marketplaceId(): string
    {
        $id = trim((string) config('services.amazon_sp.marketplace_id', 'ATVPDKIKX0DER'));

        return $id !== '' ? $id : 'ATVPDKIKX0DER';
    }

    /**
     * @return array{success: bool, order: array<string, mixed>, message: string}
     */
    public function getOrderWithPii(string $orderId): array
    {
        $orderId = trim($orderId);
        if ($orderId === '') {
            return ['success' => false, 'order' => [], 'message' => 'Amazon order id is missing.'];
        }

        $token = $this->getAccessToken();
        if ($token === null) {
            return ['success' => false, 'order' => [], 'message' => 'Amazon access token missing.'];
        }

        $path = '/orders/v0/orders/'.$orderId;
        $rdt = $this->createRestrictedDataToken($token, [[
            'method' => 'GET',
            'path' => $path,
            'dataElements' => ['buyerInfo', 'shippingAddress'],
        ]]);
        if ($rdt === null) {
            return [
                'success' => false,
                'order' => [],
                'message' => 'Could not create Amazon restricted data token for buyer/shipping address.',
            ];
        }

        $response = Http::timeout(45)->withHeaders([
            'x-amz-access-token' => $rdt,
            'accept' => 'application/json',
        ])->get($this->endpoint.$path);

        if (! $response->successful()) {
            Log::warning('AmazonSpOrdersClient: getOrderWithPii failed', [
                'order_id' => $orderId,
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 800),
            ]);

            return [
                'success' => false,
                'order' => [],
                'message' => $this->errorMessage($response) ?: 'Amazon order PII fetch failed.',
            ];
        }

        $order = $response->json('payload');
        if (! is_array($order) || $order === []) {
            return ['success' => false, 'order' => [], 'message' => 'Amazon returned an empty order payload.'];
        }

        return ['success' => true, 'order' => $order, 'message' => 'OK'];
    }

    /**
     * Best-effort tracking from SP-API (getOrder / getOrderItems / Easy Ship).
     * getOrder itself does not include a tracking field for most MFN orders.
     *
     * @return array{tracking: string, carrier: string}|null
     */
    public function lookupTrackingForOrder(string $orderId): ?array
    {
        $orderId = trim($orderId);
        if ($orderId === '' || ! preg_match('/^\d{3}-\d{7}-\d{7}$/', $orderId)) {
            return null;
        }

        foreach ([$this->getOrder($orderId), $this->getOrderItems($orderId)] as $payload) {
            if (! is_array($payload) || $payload === []) {
                continue;
            }
            $hit = AmazonOrder::trackingFromDecoded($payload);
            if (trim((string) ($hit['tracking'] ?? '')) !== '') {
                return $hit;
            }
            foreach ((array) ($payload['OrderItems'] ?? $payload['orderItems'] ?? []) as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $hit = AmazonOrder::trackingFromDecoded($item);
                if (trim((string) ($hit['tracking'] ?? '')) !== '') {
                    return $hit;
                }
            }
        }

        return $this->getEasyShipTracking($orderId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getOrder(string $orderId): ?array
    {
        $token = $this->getAccessToken();
        if ($token === null) {
            return null;
        }

        $response = Http::timeout(30)->withHeaders([
            'x-amz-access-token' => $token,
            'accept' => 'application/json',
        ])->get($this->endpoint.'/orders/v0/orders/'.$orderId);

        if (! $response->successful()) {
            Log::info('AmazonSpOrdersClient: getOrder failed', [
                'order_id' => $orderId,
                'status' => $response->status(),
            ]);

            return null;
        }

        $order = $response->json('payload');

        return is_array($order) ? $order : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getOrderItems(string $orderId): ?array
    {
        $token = $this->getAccessToken();
        if ($token === null) {
            return null;
        }

        $response = Http::timeout(30)->withHeaders([
            'x-amz-access-token' => $token,
            'accept' => 'application/json',
        ])->get($this->endpoint.'/orders/v0/orders/'.$orderId.'/orderItems');

        if (! $response->successful()) {
            Log::info('AmazonSpOrdersClient: getOrderItems failed', [
                'order_id' => $orderId,
                'status' => $response->status(),
            ]);

            return null;
        }

        $payload = $response->json('payload');

        return is_array($payload) ? $payload : null;
    }

    /**
     * @return array{tracking: string, carrier: string}|null
     */
    protected function getEasyShipTracking(string $orderId): ?array
    {
        $token = $this->getAccessToken();
        if ($token === null) {
            return null;
        }

        $response = Http::timeout(30)->withHeaders([
            'x-amz-access-token' => $token,
            'accept' => 'application/json',
        ])->get($this->endpoint.'/easyShip/2022-03-23/packages', [
            'amazonOrderId' => $orderId,
        ]);

        if (! $response->successful()) {
            return null;
        }

        $json = $response->json();
        $hit = AmazonOrder::trackingFromDecoded(is_array($json) ? $json : []);
        if (trim((string) ($hit['tracking'] ?? '')) !== '') {
            return $hit;
        }

        foreach (['packages', 'packageList', 'payload'] as $key) {
            $list = is_array($json) ? ($json[$key] ?? null) : null;
            if (! is_array($list)) {
                continue;
            }
            $items = array_is_list($list) ? $list : [$list];
            foreach ($items as $pkg) {
                if (! is_array($pkg)) {
                    continue;
                }
                $hit = AmazonOrder::trackingFromDecoded($pkg);
                if (trim((string) ($hit['tracking'] ?? '')) !== '') {
                    return $hit;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $packageDetail
     * @return array{success: bool, message: string, status?: int}
     */
    public function confirmShipment(string $orderId, array $packageDetail): array
    {
        $orderId = trim($orderId);
        if ($orderId === '') {
            return ['success' => false, 'message' => 'Amazon order id is missing.'];
        }

        $token = $this->getAccessToken();
        if ($token === null) {
            return ['success' => false, 'message' => 'Amazon access token missing.'];
        }

        $body = [
            'marketplaceId' => $this->marketplaceId(),
            'packageDetail' => $packageDetail,
        ];

        $response = Http::timeout(45)->withHeaders([
            'x-amz-access-token' => $token,
            'content-type' => 'application/json',
            'accept' => 'application/json',
        ])->post($this->endpoint.'/orders/v0/orders/'.$orderId.'/shipmentConfirmation', $body);

        if ($response->successful() || $response->status() === 204) {
            return ['success' => true, 'message' => 'Amazon shipment confirmed.', 'status' => $response->status()];
        }

        $message = $this->errorMessage($response) ?: 'Amazon confirmShipment failed.';
        Log::warning('AmazonSpOrdersClient: confirmShipment failed', [
            'order_id' => $orderId,
            'status' => $response->status(),
            'body' => substr($response->body(), 0, 800),
        ]);

        return ['success' => false, 'message' => $message, 'status' => $response->status()];
    }

    /**
     * @param  list<array{method: string, path: string, dataElements?: list<string>}>  $resources
     */
    public function createRestrictedDataToken(string $accessToken, array $resources): ?string
    {
        $response = Http::timeout(30)->withHeaders([
            'x-amz-access-token' => $accessToken,
            'content-type' => 'application/json',
            'accept' => 'application/json',
        ])->post($this->endpoint.'/tokens/2021-03-01/restrictedDataToken', [
            'restrictedResources' => $resources,
        ]);

        if (! $response->successful()) {
            Log::warning('AmazonSpOrdersClient: RDT create failed', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 800),
            ]);

            return null;
        }

        $token = $response->json('restrictedDataToken');

        return is_string($token) && $token !== '' ? $token : null;
    }

    protected function errorMessage(\Illuminate\Http\Client\Response $response): string
    {
        $errors = $response->json('errors');
        if (is_array($errors) && isset($errors[0]) && is_array($errors[0])) {
            $code = trim((string) ($errors[0]['code'] ?? ''));
            $msg = trim((string) ($errors[0]['message'] ?? ''));
            $details = trim((string) ($errors[0]['details'] ?? ''));
            $parts = array_filter([$code, $msg, $details], static fn ($p) => $p !== '');

            return implode(': ', $parts);
        }

        $body = trim($response->body());

        return $body !== '' ? $body : ('HTTP '.$response->status());
    }
}
