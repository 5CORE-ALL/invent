<?php

namespace App\Services\MarketplaceManager;

/**
 * Shared TikTok order payload helpers for TikTok1 / TikTok2 push + fetch.
 *
 * List-order APIs often omit or mask recipient_address; Get Order Detail has
 * the real ship-to. Writers must preserve a complete address across upserts.
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

    protected function unmaskTikTokValue(mixed $value): string
    {
        if (is_array($value)) {
            return '';
        }

        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }
        if (preg_match('/^\*+$/', $text) === 1) {
            return '';
        }
        if (in_array(strtolower($text), ['null', 'n/a', 'na', 'none', 'unknown', 'redacted'], true)) {
            return '';
        }

        return $text;
    }

    /**
     * @param  array<string, mixed>  $rawJson
     * @return array<string, mixed>
     */
    protected function tikTokAddressFromRaw(array $rawJson): array
    {
        $candidates = [
            $rawJson['recipient_address'] ?? null,
            $rawJson['shipping_address'] ?? null,
            $rawJson['recipient_address_info'] ?? null,
            is_array($rawJson['data'] ?? null) ? ($rawJson['data']['recipient_address'] ?? null) : null,
        ];

        foreach ($candidates as $address) {
            if (is_string($address) && trim($address) !== '') {
                $decoded = json_decode($address, true);
                $address = is_array($decoded) ? $decoded : null;
            }
            if (is_array($address) && $address !== []) {
                return $address;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $address
     */
    protected function tikTokPhoneFromAddress(array $address): string
    {
        $phone = $address['phone_number'] ?? $address['phone'] ?? $address['mobile'] ?? '';
        if (is_array($phone)) {
            $country = $this->unmaskTikTokValue($phone['country_code'] ?? $phone['calling_code'] ?? '');
            $local = $this->unmaskTikTokValue($phone['phone_number'] ?? $phone['local_number'] ?? $phone['number'] ?? '');
            $phone = trim($country.$local);
        }

        return $this->unmaskTikTokValue($phone);
    }

    /**
     * @param  array<string, mixed>  $address
     * @return array<string, mixed>
     */
    protected function mapTikTokAddressToShopify(array $address): array
    {
        $first = $this->unmaskTikTokValue($address['first_name'] ?? $address['firstName'] ?? '');
        $last = $this->unmaskTikTokValue($address['last_name'] ?? $address['lastName'] ?? '');
        $name = $this->unmaskTikTokValue($address['name'] ?? $address['full_name'] ?? $address['recipient'] ?? '');
        if ($first === '' && $name !== '') {
            $parts = explode(' ', $name, 2);
            $first = $parts[0] ?? '';
            $last = $last !== '' ? $last : ($parts[1] ?? '');
        }
        if ($last === '') {
            $last = $first;
        }

        $linesFromList = $this->tikTokStreetLinesFromList($address['address_line_list'] ?? null);

        $city = $this->unmaskTikTokValue($address['city'] ?? $address['city_name'] ?? '');
        $province = $this->unmaskTikTokValue(
            $address['state']
            ?? $address['region']
            ?? $address['province']
            ?? $address['province_code']
            ?? $address['state_code']
            ?? ''
        );
        $zip = $this->unmaskTikTokValue($address['postal_code'] ?? $address['zipcode'] ?? $address['zip'] ?? $address['postcode'] ?? '');
        $countryCode = $this->unmaskTikTokValue($address['region_code'] ?? $address['country_code'] ?? $address['country'] ?? '');

        $districtInfo = $address['district_info'] ?? null;
        if (is_array($districtInfo)) {
            foreach ($districtInfo as $level) {
                if (! is_array($level)) {
                    continue;
                }
                $levelName = strtoupper((string) ($level['address_level_name'] ?? $level['address_level'] ?? ''));
                $levelCode = strtoupper((string) ($level['address_level'] ?? ''));
                $value = $this->unmaskTikTokValue($level['address_name'] ?? $level['name'] ?? '');
                if ($value === '') {
                    continue;
                }
                if ($city === '' && (str_contains($levelName, 'CITY') || str_contains($levelName, 'TOWN') || $levelCode === 'L2')) {
                    $city = $value;
                }
                if ($province === '' && (str_contains($levelName, 'STATE') || str_contains($levelName, 'PROVINCE') || $levelCode === 'L1')) {
                    $province = $value;
                }
                if ($countryCode === '' && (str_contains($levelName, 'COUNTRY') || $levelCode === 'L0')) {
                    $countryCode = $value;
                }
                if ($city === '' && str_contains($levelName, 'DISTRICT') && $levelCode === 'L3') {
                    // Keep district as last-resort city, after a dedicated city pass.
                }
            }
            if ($city === '') {
                $city = $this->unmaskTikTokValue($districtInfo[1]['address_name'] ?? '');
            }
            if ($province === '') {
                $province = $this->unmaskTikTokValue($districtInfo[0]['address_name'] ?? '');
            }
            if ($city === '') {
                foreach ($districtInfo as $level) {
                    if (! is_array($level)) {
                        continue;
                    }
                    $levelName = strtoupper((string) ($level['address_level_name'] ?? ''));
                    if (str_contains($levelName, 'DISTRICT') || str_contains($levelName, 'COUNTY')) {
                        $city = $this->unmaskTikTokValue($level['address_name'] ?? '');
                        if ($city !== '') {
                            break;
                        }
                    }
                }
            }
        }

        $address1 = $this->unmaskTikTokValue(
            $address['address_line1']
            ?? $address['address_line_1']
            ?? $address['address_detail']
            ?? $address['addr']
            ?? ''
        );
        if ($address1 === '') {
            $address1 = $linesFromList[0] ?? '';
        }
        $fullAddress = $this->unmaskTikTokValue($address['full_address'] ?? '');
        if ($address1 === '') {
            $address1 = $fullAddress;
        }

        $address2 = $this->unmaskTikTokValue(
            $address['address_line2']
            ?? $address['address_line_2']
            ?? ''
        );
        if ($address2 === '') {
            $address2 = $linesFromList[1] ?? '';
        }
        $extra = array_filter([
            $this->unmaskTikTokValue($address['address_line3'] ?? $address['address_line_3'] ?? ''),
            $this->unmaskTikTokValue($address['address_line4'] ?? $address['address_line_4'] ?? ''),
            $linesFromList[2] ?? '',
        ]);
        if ($extra !== []) {
            $address2 = trim($address2.' '.implode(', ', $extra));
        }

        [$countryCode, $countryName] = $this->normalizeTikTokCountry($countryCode);

        return array_filter([
            'first_name' => $first,
            'last_name' => $last,
            'address1' => $address1,
            'address2' => $address2,
            'city' => $city,
            'province' => $province,
            'zip' => $zip,
            'country_code' => $countryCode !== '' ? $countryCode : 'US',
            'country' => $countryName,
            'phone' => $this->tikTokPhoneFromAddress($address),
        ], static fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @param  mixed  $lineList
     * @return list<string>
     */
    protected function tikTokStreetLinesFromList(mixed $lineList): array
    {
        if (! is_array($lineList)) {
            return [];
        }

        $out = [];
        foreach ($lineList as $entry) {
            if (is_string($entry) || is_numeric($entry)) {
                $text = $this->unmaskTikTokValue($entry);
            } elseif (is_array($entry)) {
                $text = $this->unmaskTikTokValue(
                    $entry['address_line']
                    ?? $entry['address_name']
                    ?? $entry['value']
                    ?? $entry['text']
                    ?? ''
                );
            } else {
                $text = '';
            }
            if ($text !== '') {
                $out[] = $text;
            }
        }

        return $out;
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function normalizeTikTokCountry(string $codeOrName): array
    {
        $value = strtoupper(trim($codeOrName));
        $names = [
            'US' => 'United States',
            'USA' => 'United States',
            'UNITED STATES' => 'United States',
            'CA' => 'Canada',
            'CANADA' => 'Canada',
            'GB' => 'United Kingdom',
            'UK' => 'United Kingdom',
            'UNITED KINGDOM' => 'United Kingdom',
            'AU' => 'Australia',
            'AUSTRALIA' => 'Australia',
            'MX' => 'Mexico',
            'MEXICO' => 'Mexico',
        ];
        $codes = [
            'UNITED STATES' => 'US',
            'USA' => 'US',
            'CANADA' => 'CA',
            'UNITED KINGDOM' => 'GB',
            'UK' => 'GB',
            'AUSTRALIA' => 'AU',
            'MEXICO' => 'MX',
        ];

        if (isset($names[$value]) && strlen($value) <= 3) {
            $code = $value === 'USA' ? 'US' : ($value === 'UK' ? 'GB' : $value);

            return [$code, $names[$value]];
        }
        if (isset($codes[$value])) {
            return [$codes[$value], $names[$value] ?? $codeOrName];
        }
        if (strlen($value) === 2) {
            return [$value, $names[$value] ?? ''];
        }

        return ['', $this->unmaskTikTokValue($codeOrName)];
    }

    /**
     * @param  array<string, mixed>  $shopifyAddress
     */
    protected function tikTokShopifyAddressIsComplete(array $shopifyAddress): bool
    {
        return $this->unmaskTikTokValue($shopifyAddress['address1'] ?? '') !== ''
            && $this->unmaskTikTokValue($shopifyAddress['city'] ?? '') !== ''
            && $this->unmaskTikTokValue($shopifyAddress['zip'] ?? '') !== '';
    }

    /**
     * Keep a previously stored full recipient_address when a list-API refresh
     * comes back masked or empty so later Shopify address sync still has data.
     *
     * @param  array<string, mixed>  $incoming
     * @param  array<string, mixed>  $existingRaw
     * @return array<string, mixed>
     */
    protected function mergePreservedTikTokRecipientAddress(array $incoming, array $existingRaw): array
    {
        if ($existingRaw === []) {
            return $incoming;
        }

        $incomingAddr = $this->tikTokAddressFromRaw($incoming);
        $existingAddr = $this->tikTokAddressFromRaw($existingRaw);
        $incomingMapped = $incomingAddr !== [] ? $this->mapTikTokAddressToShopify($incomingAddr) : [];
        $existingMapped = $existingAddr !== [] ? $this->mapTikTokAddressToShopify($existingAddr) : [];

        if ($this->tikTokShopifyAddressIsComplete($existingMapped)
            && ! $this->tikTokShopifyAddressIsComplete($incomingMapped)
        ) {
            $incoming['recipient_address'] = $existingAddr;
        }

        if (empty($incoming['buyer_email']) && ! empty($existingRaw['buyer_email'])) {
            $incoming['buyer_email'] = $existingRaw['buyer_email'];
        }

        return $incoming;
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

    /**
     * @param  array<string, mixed>  $rawJson
     * @param  array<string, mixed>  $shipping
     * @return array{0: string, 1: array<string, mixed>, 2: bool}
     */
    protected function tikTokShopifyCustomerAndEmail(string $orderId, array $rawJson, array $shipping, string $prefix = 'tiktok'): array
    {
        $email = $this->unmaskTikTokValue($rawJson['buyer_email'] ?? $rawJson['email'] ?? ($shipping['email'] ?? ''));
        $placeholder = false;
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $domain = (string) env('TIKTOK_SHOPIFY_PLACEHOLDER_EMAIL_DOMAIN', 'import.5coremanagement.com');
            $slug = preg_replace('/[^a-zA-Z0-9]/', '', $orderId) ?: 'order';
            $email = $prefix.'-'.$slug.'@'.$domain;
            $placeholder = true;
        }

        $customer = array_filter([
            'first_name' => $shipping['first_name'] ?? '',
            'last_name' => $shipping['last_name'] ?? '',
            'email' => $email,
            'phone' => $shipping['phone'] ?? '',
        ], static fn ($v) => $v !== null && $v !== '');

        return [$email, $customer, $placeholder];
    }
}
