<?php

namespace App\Services\MarketplaceManager;

/**
 * Shared TikTok order payload helpers for TikTok1 / TikTok2 push services.
 *
 * Prevents intermittent address sync when writers store raw_json as either
 * a JSON string or an already-decoded array (Eloquent array cast).
 */
trait ResolvesTikTokOrderRawJson
{
    /**
     * @return array<string, mixed>
     */
    protected function normalizeTikTokRawJson(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Double-encoded JSON (json_encode + Eloquent array cast).
        if (is_string($decoded)) {
            $decoded2 = json_decode($decoded, true);
            if (is_array($decoded2)) {
                return $decoded2;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $rawJson
     * @return array<string, mixed>
     */
    protected function tikTokAddressFromRaw(array $rawJson): array
    {
        $address = $rawJson['recipient_address'] ?? $rawJson['shipping_address'] ?? [];

        return is_array($address) ? $address : [];
    }

    /**
     * @param  array<string, mixed>  $address
     */
    protected function mapTikTokAddressToShopify(array $address): array
    {
        $name = trim((string) ($address['name'] ?? $address['full_name'] ?? ''));
        $parts = $name !== '' ? explode(' ', $name, 2) : ['', ''];

        $lineList = $address['address_line_list'] ?? null;
        $line1FromList = '';
        $line2FromList = '';
        if (is_array($lineList)) {
            $line1FromList = trim((string) ($lineList[0] ?? ''));
            $line2FromList = trim((string) ($lineList[1] ?? ''));
        }

        $city = trim((string) ($address['city'] ?? ''));
        $province = trim((string) (
            $address['state']
            ?? $address['region']
            ?? $address['province']
            ?? $address['province_code']
            ?? ''
        ));

        $districtInfo = $address['district_info'] ?? null;
        if (is_array($districtInfo)) {
            foreach ($districtInfo as $level) {
                if (! is_array($level)) {
                    continue;
                }
                $levelName = strtoupper((string) ($level['address_level'] ?? $level['address_level_name'] ?? ''));
                $value = trim((string) ($level['address_name'] ?? ''));
                if ($value === '') {
                    continue;
                }
                if ($city === '' && (str_contains($levelName, 'CITY') || str_contains($levelName, 'DISTRICT') || ($level['address_level'] ?? '') === 'L2')) {
                    $city = $value;
                }
                if ($province === '' && (str_contains($levelName, 'STATE') || str_contains($levelName, 'PROVINCE') || ($level['address_level'] ?? '') === 'L1')) {
                    $province = $value;
                }
            }
            if ($city === '') {
                $city = trim((string) ($districtInfo[1]['address_name'] ?? ''));
            }
            if ($province === '') {
                $province = trim((string) ($districtInfo[0]['address_name'] ?? ''));
            }
        }

        $address1 = trim((string) ($address['address_line1'] ?? $address['address_detail'] ?? ''));
        if ($address1 === '') {
            $address1 = $line1FromList;
        }
        if ($address1 === '') {
            $address1 = trim((string) ($address['full_address'] ?? ''));
        }

        $address2 = trim((string) ($address['address_line2'] ?? $address['address_line_2'] ?? ''));
        if ($address2 === '') {
            $address2 = $line2FromList;
        }

        return array_filter([
            'first_name' => $parts[0] ?? '',
            'last_name' => $parts[1] ?? $parts[0] ?? '',
            'address1' => $address1,
            'address2' => $address2,
            'city' => $city,
            'province' => $province,
            'zip' => trim((string) ($address['postal_code'] ?? $address['zipcode'] ?? $address['zip'] ?? '')),
            'country_code' => trim((string) ($address['region_code'] ?? $address['country_code'] ?? $address['country'] ?? 'US')),
            'phone' => trim((string) ($address['phone_number'] ?? $address['phone'] ?? '')),
        ], static fn ($v) => $v !== '');
    }

    /**
     * @param  array<string, mixed>|null  $response
     * @return array<string, mixed>|null
     */
    protected function extractTikTokOrderFromDetailResponse(?array $response, string $orderId): ?array
    {
        if (! is_array($response) || $orderId === '') {
            return null;
        }

        $orders = $response['orders'] ?? $response['data']['orders'] ?? null;
        if (! is_array($orders) || $orders === []) {
            // Some SDK shapes return a single order object.
            if (isset($response['id']) || isset($response['order_id'])) {
                $orders = [$response];
            } elseif (isset($response['data']) && is_array($response['data']) && (isset($response['data']['id']) || isset($response['data']['order_id']))) {
                $orders = [$response['data']];
            } else {
                return null;
            }
        }

        foreach ($orders as $order) {
            if (! is_array($order)) {
                continue;
            }
            $id = trim((string) ($order['id'] ?? $order['order_id'] ?? ''));
            if ($id === $orderId) {
                return $order;
            }
        }

        $first = $orders[0] ?? null;

        return is_array($first) ? $first : null;
    }
}
