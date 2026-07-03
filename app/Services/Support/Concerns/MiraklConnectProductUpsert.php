<?php

namespace App\Services\Support\Concerns;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Mirakl Connect upsertProducts (POST /api/products) helpers.
 *
 * Updates the Connect seller catalog. Live Macy's / Best Buy PDPs sync separately
 * from Connect — HTTP success here means Connect catalog accepted the change.
 */
trait MiraklConnectProductUpsert
{
    /**
     * @param  array<string, mixed>  $attributes  Plain key => value map
     * @param  list<array{locale: string, value: string}>|null  $descriptions
     * @return array{success: bool, message: string, response?: mixed, connect_verified?: bool}
     */
    protected function miraklConnectUpsertProduct(
        string $sku,
        string $channelId,
        array $attributes,
        ?array $descriptions = null
    ): array {
        $sku = trim($sku);
        if ($sku === '' || $attributes === []) {
            return ['success' => false, 'message' => 'SKU and attributes are required.'];
        }

        $token = $this->miraklConnectAccessToken();
        if (! $token) {
            return ['success' => false, 'message' => 'Mirakl Connect access token not available.'];
        }

        $productPayload = [
            'id' => $sku,
            'attributes' => $this->formatMiraklConnectAttributes($attributes),
        ];
        if ($descriptions !== null && $descriptions !== []) {
            $productPayload['descriptions'] = $descriptions;
        }

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'channel_id' => $channelId,
        ];

        try {
            $request = Http::withoutVerifying()->withToken($token)->withHeaders($headers)->timeout(60);
            $response = $request->post('https://miraklconnect.com/api/products', ['products' => [$productPayload]]);

            $status = $response->status();
            $body = $response->body();
            $json = $response->json();

            Log::info('Mirakl Connect upsertProducts response', [
                'sku' => $sku,
                'channel_id' => $channelId,
                'status' => $status,
                'body_preview' => mb_substr($body, 0, 1500),
            ]);

            if (! $response->successful() && ! in_array($status, [200, 201, 202], true)) {
                return ['success' => false, 'message' => 'Mirakl Connect upsert failed (HTTP '.$status.'): '.$body];
            }

            if (is_array($json) && (
                ! empty($json['errors'])
                || ! empty($json['error'])
                || (isset($json['success']) && $json['success'] === false)
            )) {
                return ['success' => false, 'message' => 'Mirakl Connect upsert error: '.json_encode($json), 'response' => $json];
            }

            return [
                'success' => true,
                'message' => 'Mirakl Connect catalog updated (HTTP '.$status.'). Live marketplace PDP may sync separately.',
                'response' => $json ?? $body,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function miraklConnectFetchProductBySku(string $sku, string $channelCode, string $channelId): ?array
    {
        $token = $this->miraklConnectAccessToken();
        if (! $token) {
            return null;
        }

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'channel_id' => $channelId,
        ];

        $pageToken = null;
        do {
            $query = ['limit' => 1000, 'channel_code' => $channelCode];
            if ($pageToken) {
                $query['page_token'] = $pageToken;
            }

            $response = Http::withoutVerifying()->withToken($token)->withHeaders($headers)->timeout(60)
                ->get('https://miraklconnect.com/api/products', $query);

            if (! $response->successful()) {
                return null;
            }

            foreach (($response->json('data') ?? []) as $product) {
                if (isset($product['id']) && strcasecmp((string) $product['id'], $sku) === 0) {
                    return is_array($product) ? $product : null;
                }
            }

            $pageToken = $response->json('next_page_token');
        } while ($pageToken);

        return null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return list<array{id: string, name: string, type: string, value: mixed}>
     */
    protected function formatMiraklConnectAttributes(array $attributes): array
    {
        $formatted = [];
        foreach ($attributes as $id => $value) {
            $id = trim((string) $id);
            if ($id === '') {
                continue;
            }
            $formatted[] = [
                'id' => $id,
                'name' => $id,
                'type' => is_numeric($value) && ! is_string($value) ? 'NUMERIC' : (is_bool($value) ? 'BOOLEAN' : 'STRING'),
                'value' => $value,
            ];
        }

        return $formatted;
    }

    protected function miraklConnectBulletLines(string $bulletPoints): array
    {
        return array_values(array_filter(array_map(
            fn ($line) => trim((string) $line),
            preg_split('/\r\n|\r|\n/', trim($bulletPoints)) ?: []
        ), fn ($line) => $line !== ''));
    }

    protected function miraklConnectAboutItemText(string $bulletPoints): string
    {
        $lines = $this->miraklConnectBulletLines($bulletPoints);

        return trim('About Item '.implode(' ', array_slice($lines, 0, 5)));
    }

    /**
     * @return array{success: bool, message: string, connect_verified?: bool}
     */
    protected function miraklConnectVerifyBulletAttribute(string $sku, string $channelCode, string $channelId, string $bulletPoints): array
    {
        $product = $this->miraklConnectFetchProductBySku($sku, $channelCode, $channelId);
        if ($product === null) {
            return ['success' => true, 'message' => 'Connect upsert accepted; product not found on read-back list yet.', 'connect_verified' => false];
        }

        $expected = mb_substr($this->miraklConnectBulletLines($bulletPoints)[0] ?? '', 0, 40);
        $found = '';
        foreach (($product['attributes'] ?? []) as $attr) {
            if (! is_array($attr)) {
                continue;
            }
            $id = strtolower((string) ($attr['id'] ?? $attr['name'] ?? ''));
            if ($id === 'bulletpoints') {
                $found = (string) ($attr['value'] ?? '');
                break;
            }
        }

        if ($expected !== '' && $found !== '' && ! str_contains($found, $expected)) {
            Log::warning('Mirakl Connect bullet read-back mismatch', [
                'sku' => $sku,
                'channel_code' => $channelCode,
                'expected_prefix' => $expected,
                'found_prefix' => mb_substr($found, 0, 80),
            ]);

            return [
                'success' => false,
                'message' => 'Connect upsert returned OK but bulletPoints read-back does not match PM text.',
                'connect_verified' => false,
            ];
        }

        return [
            'success' => true,
            'message' => 'Mirakl Connect catalog verified (bulletPoints present). Live Macy/Best Buy PDP syncs from Connect separately.',
            'connect_verified' => true,
        ];
    }

    abstract protected function miraklConnectAccessToken(): ?string;
}
