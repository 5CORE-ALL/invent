<?php

namespace App\Support\Marketplace;

use App\Services\Ebay2ApiService;
use App\Services\EbayApiService;
use App\Services\EbayThreeApiService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

/**
 * AddFixedPriceItem for eBay 1 / 2 / 3, including parent-child Variations.
 */
class ListingManagerEbayTradingPublisher
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, message: string, item_id?: string|null, raw?: string}
     */
    public static function publish(string $channelKey, array $payload): array
    {
        $key = ListingChannelCounts::normalize($channelKey);
        $ctx = self::context($key);
        if (! ($ctx['configured'] ?? false)) {
            return ['success' => false, 'message' => $ctx['label'].' API credentials are not configured.'];
        }

        return self::addFixedPriceItem($ctx, $payload);
    }

    /**
     * @return array{label: string, configured: bool, token?: string, app_id?: string, cert_id?: string, dev_id?: string, endpoint?: string, site_id?: mixed, compat?: mixed}
     */
    private static function context(string $key): array
    {
        $endpoint = (string) config('services.ebay.trading_api_endpoint', 'https://api.ebay.com/ws/api.dll');
        $siteId = config('services.ebay.site_id', 0);
        $compat = config('services.ebay.compat_level', '1189');

        if (in_array($key, ['ebay', 'ebay1', 'ebayone'], true)) {
            $svc = new EbayApiService();

            return [
                'label' => 'Ebay 1',
                'configured' => method_exists($svc, 'isConfigured') ? $svc->isConfigured() : true,
                'token' => $svc->generateBearerToken(),
                'app_id' => config('services.ebay.app_id'),
                'cert_id' => config('services.ebay.cert_id'),
                'dev_id' => config('services.ebay.dev_id'),
                'endpoint' => $endpoint,
                'site_id' => $siteId,
                'compat' => $compat,
            ];
        }

        if (in_array($key, ['ebay3', 'ebaythree'], true)) {
            $svc = new EbayThreeApiService();

            return [
                'label' => 'Ebay 3',
                'configured' => $svc->isConfigured(),
                'token' => $svc->generateBearerToken(),
                'app_id' => env('EBAY_3_APP_ID'),
                'cert_id' => env('EBAY_3_CERT_ID'),
                'dev_id' => env('EBAY_3_DEV_ID', env('EBAY_DEV_ID')),
                'endpoint' => $endpoint,
                'site_id' => $siteId,
                'compat' => $compat,
            ];
        }

        $svc = new Ebay2ApiService();

        return [
            'label' => 'Ebay 2',
            'configured' => $svc->isConfigured(),
            'token' => $svc->generateBearerToken(),
            'app_id' => config('services.ebay2.app_id'),
            'cert_id' => config('services.ebay2.cert_id'),
            'dev_id' => config('services.ebay2.dev_id', config('services.ebay.dev_id')),
            'endpoint' => $endpoint,
            'site_id' => $siteId,
            'compat' => $compat,
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, message: string, item_id?: string|null, raw?: string}
     */
    public static function addFixedPriceItem(array $ctx, array $payload): array
    {
        $label = (string) ($ctx['label'] ?? 'eBay');
        $sku = trim((string) ($payload['sku'] ?? ''));
        $title = trim((string) ($payload['title'] ?? ''));
        $description = (string) ($payload['description'] ?? '');
        $price = (float) ($payload['price'] ?? 0);
        $quantity = (int) ($payload['quantity'] ?? 0);
        $categoryId = trim((string) ($payload['primary_category_id'] ?? ''));
        $conditionId = trim((string) ($payload['condition_id'] ?? '1000')) ?: '1000';
        $images = $payload['images'] ?? [];
        if (! is_array($images)) {
            $images = [];
        }
        $images = array_values(array_filter(array_map(fn ($u) => trim((string) $u), $images)));
        $variations = self::normalizeVariations($payload['variations'] ?? []);

        if ($title === '' || $description === '' || $categoryId === '' || $images === []) {
            return ['success' => false, 'message' => 'Missing required fields for AddFixedPriceItem.'];
        }
        if ($variations === [] && $price <= 0) {
            return ['success' => false, 'message' => 'Missing required fields for AddFixedPriceItem.'];
        }

        try {
            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><AddFixedPriceItemRequest xmlns="urn:ebay:apis:eBLBaseComponents"/>');
            $credentials = $xml->addChild('RequesterCredentials');
            $credentials->addChild('eBayAuthToken', (string) ($ctx['token'] ?? ''));
            $xml->addChild('ErrorLanguage', 'en_US');
            $xml->addChild('WarningLevel', 'High');

            $item = $xml->addChild('Item');
            $item->addChild('Title', htmlspecialchars($title, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
            $item->addChild('Description', htmlspecialchars($description, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
            $item->addChild('SKU', htmlspecialchars($sku, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
            $item->addChild('Currency', 'USD');
            $item->addChild('Country', trim((string) ($payload['location_country'] ?? 'US')) ?: 'US');
            $item->addChild('ListingDuration', trim((string) ($payload['duration'] ?? 'GTC')) ?: 'GTC');
            $item->addChild('ListingType', 'FixedPriceItem');
            $item->addChild('ConditionID', $conditionId);

            if ($variations === []) {
                $item->addChild('StartPrice', number_format($price, 2, '.', ''));
                $item->addChild('Quantity', (string) max(0, $quantity));
            }

            $city = trim((string) ($payload['location_city'] ?? ''));
            $postal = trim((string) ($payload['location_postal_code'] ?? ''));
            $location = trim($city.($postal !== '' ? ' '.$postal : ''));
            if ($location !== '') {
                $item->addChild('Location', htmlspecialchars($location, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
            }
            if ($postal !== '') {
                $item->addChild('PostalCode', htmlspecialchars($postal, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
            }

            $primary = $item->addChild('PrimaryCategory');
            $primary->addChild('CategoryID', $categoryId);
            $secondaryId = trim((string) ($payload['secondary_category_id'] ?? ''));
            if ($secondaryId !== '') {
                $secondary = $item->addChild('SecondaryCategory');
                $secondary->addChild('CategoryID', $secondaryId);
            }

            $pictureDetails = $item->addChild('PictureDetails');
            foreach (array_slice($images, 0, 12) as $url) {
                $pictureDetails->addChild('PictureURL', htmlspecialchars($url, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
            }
            if (! empty($payload['gallery_plus'])) {
                $pictureDetails->addChild('GalleryType', 'Plus');
            }

            $shippingId = trim((string) ($payload['shipping_policy_id'] ?? ''));
            $paymentId = trim((string) ($payload['payment_policy_id'] ?? ''));
            $returnId = trim((string) ($payload['return_policy_id'] ?? ''));
            if ($shippingId !== '' || $paymentId !== '' || $returnId !== '') {
                $profiles = $item->addChild('SellerProfiles');
                if ($shippingId !== '') {
                    $profiles->addChild('SellerShippingProfile')->addChild('ShippingProfileID', $shippingId);
                }
                if ($paymentId !== '') {
                    $profiles->addChild('SellerPaymentProfile')->addChild('PaymentProfileID', $paymentId);
                }
                if ($returnId !== '') {
                    $profiles->addChild('SellerReturnProfile')->addChild('ReturnProfileID', $returnId);
                }
            }

            $brand = trim((string) config('listing_manager.default_brand', '5 Core Inc.')) ?: '5 Core Inc.';
            $manufacturer = trim((string) config('listing_manager.default_manufacturer', '5 Core Inc.')) ?: '5 Core Inc.';
            $mpn = $sku;
            $upc = trim((string) ($payload['upc'] ?? ''));

            $specifics = is_array($payload['item_specifics'] ?? null) ? $payload['item_specifics'] : [];
            $specifics['Brand'] = $brand;
            $specifics['Manufacturer'] = $manufacturer;
            $specifics['MPN'] = $mpn;
            if ($upc !== '') {
                $specifics['UPC'] = $upc;
            }

            if ($specifics !== []) {
                $itemSpecifics = $item->addChild('ItemSpecifics');
                foreach ($specifics as $name => $value) {
                    $name = trim((string) $name);
                    $value = trim((string) $value);
                    if ($name === '' || $value === '') {
                        continue;
                    }
                    $nvl = $itemSpecifics->addChild('NameValueList');
                    $nvl->addChild('Name', htmlspecialchars($name, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
                    $nvl->addChild('Value', htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
                }
            }

            if ($upc !== '' && $variations === []) {
                $pld = $item->addChild('ProductListingDetails');
                $pld->addChild('UPC', htmlspecialchars($upc, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
            }

            $length = (float) ($payload['package_length'] ?? 0);
            $width = (float) ($payload['package_width'] ?? 0);
            $height = (float) ($payload['package_height'] ?? 0);
            $lb = (float) ($payload['package_weight_lb'] ?? 0);
            $oz = (float) ($payload['package_weight_oz'] ?? 0);
            if ($length > 0 || $width > 0 || $height > 0 || $lb > 0 || $oz > 0) {
                $package = $item->addChild('ShippingPackageDetails');
                if ($length > 0) {
                    $package->addChild('PackageLength', (string) $length);
                }
                if ($width > 0) {
                    $package->addChild('PackageWidth', (string) $width);
                }
                if ($height > 0) {
                    $package->addChild('PackageDepth', (string) $height);
                }
                $weightMajor = (int) floor($lb);
                $weightMinor = (int) round($oz + (($lb - $weightMajor) * 16));
                if ($weightMajor > 0 || $weightMinor > 0) {
                    $package->addChild('WeightMajor', (string) $weightMajor);
                    $package->addChild('WeightMinor', (string) $weightMinor);
                }
            }

            if (! empty($payload['best_offer'])) {
                $item->addChild('BestOfferDetails')->addChild('BestOfferEnabled', 'true');
            }
            if (! empty($payload['private_listing'])) {
                $item->addChild('PrivateListing', 'true');
            }

            if ($variations !== []) {
                self::appendVariations($item, $variations);
            }

            $headers = [
                'X-EBAY-API-COMPATIBILITY-LEVEL' => (string) ($ctx['compat'] ?? '1189'),
                'X-EBAY-API-DEV-NAME' => (string) ($ctx['dev_id'] ?? ''),
                'X-EBAY-API-APP-NAME' => (string) ($ctx['app_id'] ?? ''),
                'X-EBAY-API-CERT-NAME' => (string) ($ctx['cert_id'] ?? ''),
                'X-EBAY-API-CALL-NAME' => 'AddFixedPriceItem',
                'X-EBAY-API-SITEID' => (string) ($ctx['site_id'] ?? '0'),
                'Content-Type' => 'text/xml',
            ];

            $response = Http::timeout(90)
                ->withHeaders($headers)
                ->withBody($xml->asXML(), 'text/xml')
                ->post((string) ($ctx['endpoint'] ?? 'https://api.ebay.com/ws/api.dll'));

            $body = $response->body();
            libxml_use_internal_errors(true);
            $xmlResp = simplexml_load_string($body);
            if ($xmlResp === false) {
                Log::warning($label.' AddFixedPriceItem: invalid XML', [
                    'sku' => $sku,
                    'status' => $response->status(),
                    'body' => substr($body, 0, 1500),
                ]);

                return ['success' => false, 'message' => 'Invalid XML response from eBay.', 'raw' => $body];
            }

            $data = json_decode(json_encode($xmlResp), true) ?: [];
            $ack = $data['Ack'] ?? 'Failure';
            if ($ack === 'Success' || $ack === 'Warning') {
                $itemId = trim((string) ($data['ItemID'] ?? ''));

                return [
                    'success' => true,
                    'message' => $itemId !== ''
                        ? "Published to {$label} (ItemID {$itemId})."
                        : "Published to {$label}.",
                    'item_id' => $itemId !== '' ? $itemId : null,
                    'raw' => $body,
                ];
            }

            $messages = [];
            $errors = $data['Errors'] ?? [];
            if (isset($errors['ShortMessage'])) {
                $errors = [$errors];
            }
            foreach ($errors as $err) {
                $messages[] = trim((string) ($err['LongMessage'] ?? $err['ShortMessage'] ?? ''));
            }

            return [
                'success' => false,
                'message' => $messages !== [] ? implode(' | ', $messages) : $label.' rejected AddFixedPriceItem.',
                'raw' => $body,
            ];
        } catch (\Throwable $e) {
            Log::error($label.' AddFixedPriceItem failed: '.$e->getMessage(), ['sku' => $sku]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param  mixed  $raw
     * @return list<array{sku: string, price: float, quantity: int, variation_label: string, upc?: string}>
     */
    private static function normalizeVariations(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        $seen = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku === '' || stripos($sku, 'PARENT') === 0) {
                continue;
            }
            $label = trim((string) ($row['variation_label'] ?? $sku)) ?: $sku;
            $key = strtolower($label);
            if (isset($seen[$key])) {
                $label = $sku;
            }
            $seen[strtolower($label)] = true;
            $out[] = [
                'sku' => $sku,
                'price' => (float) ($row['price'] ?? 0),
                'quantity' => (int) ($row['quantity'] ?? 0),
                'variation_label' => $label,
                'upc' => trim((string) ($row['upc'] ?? '')),
            ];
        }

        return count($out) > 1 ? $out : [];
    }

    /**
     * @param  list<array{sku: string, price: float, quantity: int, variation_label: string, upc?: string}>  $variations
     */
    private static function appendVariations(SimpleXMLElement $item, array $variations): void
    {
        $block = $item->addChild('Variations');
        $labels = [];
        foreach ($variations as $row) {
            $variation = $block->addChild('Variation');
            $variation->addChild('SKU', htmlspecialchars($row['sku'], ENT_XML1 | ENT_COMPAT, 'UTF-8'));
            $variation->addChild('StartPrice', number_format(max(0.01, $row['price']), 2, '.', ''));
            $variation->addChild('Quantity', (string) max(0, $row['quantity']));
            $vs = $variation->addChild('VariationSpecifics');
            $nvl = $vs->addChild('NameValueList');
            $nvl->addChild('Name', 'Pack');
            $nvl->addChild('Value', htmlspecialchars($row['variation_label'], ENT_XML1 | ENT_COMPAT, 'UTF-8'));
            if (($row['upc'] ?? '') !== '') {
                $pld = $variation->addChild('VariationProductListingDetails');
                $pld->addChild('UPC', htmlspecialchars($row['upc'], ENT_XML1 | ENT_COMPAT, 'UTF-8'));
            }
            $labels[] = $row['variation_label'];
        }

        $set = $block->addChild('VariationSpecificsSet');
        $setList = $set->addChild('NameValueList');
        $setList->addChild('Name', 'Pack');
        foreach (array_values(array_unique($labels)) as $label) {
            $setList->addChild('Value', htmlspecialchars($label, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
        }
    }
}
