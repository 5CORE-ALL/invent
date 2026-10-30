<?php

namespace App\Support\Marketplace;

use App\Services\Ebay2ApiService;
use App\Services\EbayApiService;
use App\Services\EbayThreeApiService;
use App\Services\Support\EbayTradingReviseItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use SimpleXMLElement;

/**
 * AddFixedPriceItem for eBay 1 / 2 / 3, including parent-child Variations.
 */
class ListingManagerEbayTradingPublisher
{
    /** eBay's exemption value: we never send real UPCs, but some categories require the field. */
    private const NO_UPC = 'Does not apply';

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

        $payload = self::ensureNumericCategory($payload);
        $categoryId = trim((string) ($payload['primary_category_id'] ?? ''));
        if (! preg_match('/^\d+$/', $categoryId)) {
            return [
                'success' => false,
                'message' => 'eBay category must be a numeric leaf category. The saved category is not an eBay category ID. Search and select an eBay category, then publish again.',
            ];
        }

        return self::addFixedPriceItem($ctx, $payload);
    }

    /**
     * eBay Trading CategoryID is a numeric leaf. A Reverb UUID or other marketplace
     * id is replaced with the best eBay taxonomy match for the listing title.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function ensureNumericCategory(array $payload): array
    {
        $id = trim((string) ($payload['primary_category_id'] ?? ''));
        $secondary = trim((string) ($payload['secondary_category_id'] ?? ''));
        if ($secondary !== '' && ! preg_match('/^\d+$/', $secondary)) {
            $payload['secondary_category_id'] = '';
        }
        if (preg_match('/^\d+$/', $id)) {
            return $payload;
        }

        $resolved = self::suggestNumericCategory($payload);
        if ($resolved['id'] === '') {
            $payload['primary_category_id'] = '';

            return $payload;
        }
        $payload['primary_category_id'] = $resolved['id'];
        if ($resolved['path'] !== '') {
            $payload['primary_category_path'] = $resolved['path'];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{id: string, path: string}
     */
    private static function suggestNumericCategory(array $payload): array
    {
        $empty = ['id' => '', 'path' => ''];
        $ebay = new Ebay2ApiService();
        if (! $ebay->isConfigured()) {
            return $empty;
        }

        $queries = [];
        $title = trim((string) preg_replace('/^\s*5\s*core\s+/i', '', (string) ($payload['title'] ?? '')));
        if ($title !== '') {
            $queries[] = mb_substr($title, 0, 80);
        }
        $path = trim((string) ($payload['primary_category_path'] ?? ''));
        if ($path !== '') {
            $leaf = trim((string) preg_replace('/^.*(?:\/|>|\|)/', '', str_replace(' / ', '/', $path)));
            if ($leaf !== '' && ! in_array($leaf, $queries, true)) {
                $queries[] = $leaf;
            }
        }

        foreach ($queries as $query) {
            try {
                $result = $ebay->getCategorySuggestions($query);
            } catch (\Throwable) {
                continue;
            }
            foreach ($result['categories'] ?? [] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $id = trim((string) ($row['id'] ?? ''));
                if (! preg_match('/^\d+$/', $id)) {
                    continue;
                }

                return [
                    'id' => $id,
                    'path' => trim((string) ($row['path'] ?? '')),
                ];
            }
        }

        return $empty;
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
                'channel' => 'ebay',
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
                'channel' => 'ebaythree',
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
     * eBay rejects listings with active content: "Your listing cannot contain javascript (".cookie",
     * "cookie(", "replace(", IFRAME, META, or includes), cookies or base href." Product copy and A+ HTML
     * pulled from Shopify / Amazon can carry script tags, event handlers or text such as "replace(s)".
     */
    public static function sanitizeDescription(string $html): string
    {
        if ($html === '') {
            return $html;
        }
        // Whole elements that eBay does not allow, with their content.
        $html = preg_replace('~<\s*(script|iframe|object|embed|applet|noscript|frameset|frame)\b[^>]*>.*?<\s*/\s*\1\s*>~is', '', $html) ?? $html;
        // Any remaining opening / closing / self-closing tags of the same kind, plus meta, base, link, form fields.
        $html = preg_replace('~<\s*/?\s*(script|iframe|object|embed|applet|noscript|frameset|frame|meta|base|link|form|input|button|textarea|select)\b[^>]*>~i', '', $html) ?? $html;
        // Inline handlers (onclick=, onerror= ...) and javascript:/vbscript: links.
        $html = preg_replace('~\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)~i', '', $html) ?? $html;
        $html = preg_replace('~(href|src|action)\s*=\s*(["\'])\s*(javascript|vbscript):[^"\']*\2~i', '$1=$2#$2', $html) ?? $html;
        // Text that looks like script to eBay's filter ("replace(s)", "document.cookie", ".includes(").
        $html = preg_replace('~\.\s*cookie~i', ' cookie', $html) ?? $html;
        $html = preg_replace('~\b(cookie|replace|includes|eval|unescape)\s*\(~i', '$1 (', $html) ?? $html;
        $html = preg_replace('~document\s*\.\s*(write|writeln|location)~i', 'document $1', $html) ?? $html;

        return trim($html);
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
        $description = self::sanitizeDescription((string) ($payload['description'] ?? ''));
        $price = (float) ($payload['price'] ?? 0);
        $quantity = (int) ($payload['quantity'] ?? 0);
        $categoryId = trim((string) ($payload['primary_category_id'] ?? ''));
        $conditionId = trim((string) ($payload['condition_id'] ?? '1000')) ?: '1000';
        $images = $payload['images'] ?? [];
        if (! is_array($images)) {
            $images = [];
        }
        $images = array_values(array_filter(array_map(fn ($u) => trim((string) $u), $images)));
        $payload = self::stripUpcFromPayload($payload);
        if (($ctx['channel'] ?? '') === 'ebaythree') {
            $payload = self::applyEbay3Policies($payload);
        } elseif (($ctx['channel'] ?? '') === 'ebay') {
            $payload = self::applyEbay1Policies($payload);
        }
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
            $parentSku = trim((string) ($payload['parent_sku'] ?? ''));
            $item->addChild('SKU', htmlspecialchars($variations !== [] && $parentSku !== '' ? $parentSku : $sku, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
            $item->addChild('Currency', 'USD');
            $item->addChild('Country', trim((string) ($payload['location_country'] ?? 'US')) ?: 'US');
            $item->addChild('ListingDuration', trim((string) ($payload['duration'] ?? 'GTC')) ?: 'GTC');
            $item->addChild('ListingType', 'FixedPriceItem');
            $item->addChild('ConditionID', $conditionId);
            if (($ctx['channel'] ?? '') === 'ebaythree') {
                $item->addChild('DispatchTimeMax', '3');
            }

            if ($variations === []) {
                $item->addChild('StartPrice', number_format($price, 2, '.', ''));
                $item->addChild('Quantity', (string) max(0, $quantity));
                $item->addChild('ProductListingDetails')->addChild('UPC', self::NO_UPC);
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
            $shippingName = trim((string) ($payload['shipping_policy_name'] ?? ''));
            $paymentName = trim((string) ($payload['payment_policy_name'] ?? ''));
            $returnName = trim((string) ($payload['return_policy_name'] ?? ''));
            if ($shippingId !== '' || $paymentId !== '' || $returnId !== '' || $shippingName !== '' || $paymentName !== '' || $returnName !== '') {
                $profiles = $item->addChild('SellerProfiles');
                if ($shippingId !== '') {
                    $profiles->addChild('SellerShippingProfile')->addChild('ShippingProfileID', $shippingId);
                } elseif ($shippingName !== '') {
                    $profiles->addChild('SellerShippingProfile')->addChild(
                        'ShippingProfileName',
                        htmlspecialchars($shippingName, ENT_XML1 | ENT_COMPAT, 'UTF-8')
                    );
                }
                if ($paymentId !== '') {
                    $profiles->addChild('SellerPaymentProfile')->addChild('PaymentProfileID', $paymentId);
                } elseif ($paymentName !== '') {
                    $profiles->addChild('SellerPaymentProfile')->addChild(
                        'PaymentProfileName',
                        htmlspecialchars($paymentName, ENT_XML1 | ENT_COMPAT, 'UTF-8')
                    );
                }
                if ($returnId !== '') {
                    $profiles->addChild('SellerReturnProfile')->addChild('ReturnProfileID', $returnId);
                } elseif ($returnName !== '') {
                    $profiles->addChild('SellerReturnProfile')->addChild(
                        'ReturnProfileName',
                        htmlspecialchars($returnName, ENT_XML1 | ENT_COMPAT, 'UTF-8')
                    );
                }
            } elseif (($ctx['channel'] ?? '') === 'ebaythree') {
                self::appendEbay3FallbackShippingAndReturns($item);
            }

            $resolved = self::resolveSpecifics($ctx, $payload, $variations, true);
            $specifics = $resolved['specifics'];
            $variationAspect = $resolved['variation_aspect'];
            $defaulted = $resolved['defaulted'];

            if ($specifics !== []) {
                $itemSpecifics = $item->addChild('ItemSpecifics');
                foreach ($specifics as $name => $value) {
                    $name = trim((string) $name);
                    $value = trim((string) $value);
                    if ($name === '' || $value === '' || strcasecmp($name, 'UPC') === 0) {
                        continue;
                    }
                    $nvl = $itemSpecifics->addChild('NameValueList');
                    $nvl->addChild('Name', htmlspecialchars($name, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
                    $nvl->addChild('Value', htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
                }
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
                self::appendVariations($item, $variations, $variationAspect);
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
                $note = '';
                if ($defaulted !== []) {
                    $note = ' Defaulted: '.implode(', ', array_map(
                        static fn ($n, $v) => $n.'='.$v,
                        array_keys($defaulted),
                        $defaulted
                    )).'.';
                }

                return [
                    'success' => true,
                    'message' => ($itemId !== ''
                        ? "Published to {$label} (ItemID {$itemId})."
                        : "Published to {$label}.").$note,
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
     * Required item specifics still missing after masters / Amazon / Shopify (shown in the publish window).
     *
     * @param  array<string, mixed>  $payload  sku, title, description, primary_category_id, variations, item_specifics
     * @return array{aspects_loaded: bool, variation_aspect: string, missing: list<array{name: string, values: list<string>, free_text: bool, suggested: string}>}
     */
    public static function previewRequiredSpecifics(string $channelKey, array $payload): array
    {
        $ctx = self::context(ListingChannelCounts::normalize($channelKey));
        if (! ($ctx['configured'] ?? false)) {
            return ['aspects_loaded' => false, 'variation_aspect' => '', 'missing' => []];
        }
        $variations = self::normalizeVariations($payload['variations'] ?? []);
        $resolved = self::resolveSpecifics($ctx, self::stripUpcFromPayload($payload), $variations, false);

        return [
            'aspects_loaded' => $resolved['aspects_loaded'],
            'variation_aspect' => $resolved['variation_aspect'],
            'missing' => $resolved['unresolved'],
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $payload
     * @param  list<array{sku: string}>  $variations
     * @return array{specifics: array<string, mixed>, variation_aspect: string, defaulted: array<string, string>, unresolved: list<array<string, mixed>>, aspects_loaded: bool}
     */
    private static function resolveSpecifics(array $ctx, array $payload, array $variations, bool $useDefaults): array
    {
        $sku = trim((string) ($payload['sku'] ?? ''));
        $specifics = is_array($payload['item_specifics'] ?? null) ? $payload['item_specifics'] : [];
        $specifics['Brand'] = trim((string) config('listing_manager.default_brand', '5 Core')) ?: '5 Core';
        $specifics['Manufacturer'] = trim((string) config('listing_manager.default_manufacturer', '5 Core')) ?: '5 Core';
        $specifics['MPN'] = $sku;
        $specifics = self::ensureRequiredItemSpecifics($specifics, $payload);
        $specifics = self::withoutUpcKeys($specifics);

        $aspects = EbayCategoryAspects::forCategory(
            (string) ($payload['primary_category_id'] ?? ''),
            (string) ($ctx['token'] ?? ''),
            (string) ($ctx['app_id'] ?? ''),
            (string) ($ctx['cert_id'] ?? '')
        );
        $variationAspect = $variations !== []
            ? EbayCategoryAspects::variationAspect($aspects, self::variationAspectName($variations))
            : '';
        $aspectNames = array_map(static fn ($a) => strtolower($a['name']), $aspects);
        if (in_array('model', $aspectNames, true) && strcasecmp($variationAspect, 'Model') !== 0 && trim((string) ($specifics['Model'] ?? '')) === '') {
            $specifics['Model'] = $sku;
        }
        $skusForLookup = array_values(array_unique(array_filter(array_merge([$sku], array_column($variations, 'sku')))));
        [$specifics, $defaulted, $unresolved] = EbayCategoryAspects::fillRequired(
            $specifics,
            $aspects,
            $skusForLookup,
            trim((string) ($payload['title'] ?? '')).' '.strip_tags((string) ($payload['description'] ?? '')),
            $variationAspect,
            $useDefaults
        );
        if ($variationAspect !== '') {
            foreach (array_keys($specifics) as $name) {
                if (strcasecmp(trim((string) $name), $variationAspect) === 0) {
                    unset($specifics[$name]);
                }
            }
        }

        return [
            'specifics' => $specifics,
            'variation_aspect' => $variationAspect,
            'defaulted' => $defaulted,
            'unresolved' => $unresolved,
            'aspects_loaded' => $aspects !== [],
        ];
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
            $images = is_array($row['images'] ?? null) ? $row['images'] : [];
            $out[] = [
                'sku' => $sku,
                'price' => (float) ($row['price'] ?? 0),
                'quantity' => (int) ($row['quantity'] ?? 0),
                'variation_label' => $label,
                'variation_type' => trim((string) ($row['variation_type'] ?? '')),
                'images' => array_values(array_filter(array_map(static fn ($u) => trim((string) $u), $images), static fn ($u) => preg_match('#^https?://#i', $u) === 1)),
                'upc' => '',
            ];
        }

        return count($out) > 1 ? $out : [];
    }

    /**
     * @param  list<array{sku: string, price: float, quantity: int, variation_label: string, upc?: string}>  $variations
     */
    private static function appendVariations(SimpleXMLElement $item, array $variations, string $aspect = ''): void
    {
        $block = $item->addChild('Variations');
        $aspect = $aspect !== '' ? $aspect : self::variationAspectName($variations);
        $labels = [];
        foreach ($variations as $row) {
            $variation = $block->addChild('Variation');
            $variation->addChild('SKU', htmlspecialchars($row['sku'], ENT_XML1 | ENT_COMPAT, 'UTF-8'));
            $variation->addChild('StartPrice', number_format(max(0.01, $row['price']), 2, '.', ''));
            $variation->addChild('Quantity', (string) max(0, $row['quantity']));
            $vs = $variation->addChild('VariationSpecifics');
            $nvl = $vs->addChild('NameValueList');
            $nvl->addChild('Name', $aspect);
            $nvl->addChild('Value', htmlspecialchars($row['variation_label'], ENT_XML1 | ENT_COMPAT, 'UTF-8'));
            $variation->addChild('VariationProductListingDetails')->addChild('UPC', self::NO_UPC);
            $labels[] = $row['variation_label'];
        }

        $withImages = array_filter($variations, static fn ($row) => ! empty($row['images']));
        if ($withImages !== []) {
            $pictures = $block->addChild('Pictures');
            $pictures->addChild('VariationSpecificName', htmlspecialchars($aspect, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
            foreach ($withImages as $row) {
                $set = $pictures->addChild('VariationSpecificPictureSet');
                $set->addChild('VariationSpecificValue', htmlspecialchars($row['variation_label'], ENT_XML1 | ENT_COMPAT, 'UTF-8'));
                foreach (array_slice(array_values(array_unique($row['images'])), 0, 12) as $url) {
                    $set->addChild('PictureURL', htmlspecialchars($url, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
                }
            }
        }

        $set = $block->addChild('VariationSpecificsSet');
        $setList = $set->addChild('NameValueList');
        $setList->addChild('Name', $aspect);
        foreach (array_values(array_unique($labels)) as $label) {
            $setList->addChild('Value', htmlspecialchars($label, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
        }
    }

    /**
     * @param  list<array{variation_label?: string}>  $variations
     */
    private static function variationAspectName(array $variations): string
    {
        $types = [];
        foreach ($variations as $row) {
            $type = trim((string) ($row['variation_type'] ?? ''));
            if ($type !== '') {
                $types[$type] = ($types[$type] ?? 0) + 1;
            }
        }
        if ($types !== []) {
            arsort($types);

            return (string) array_key_first($types);
        }

        $packish = 0;
        foreach ($variations as $row) {
            if (preg_match('/\d+\s*(?:pcs?|pieces?|pack|pk)\b/i', (string) ($row['variation_label'] ?? ''))) {
                $packish++;
            }
        }

        return $packish === count($variations) && $packish > 0 ? 'Pack' : 'Model';
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getItem(string $channelKey, string $itemId): ?array
    {
        $itemId = trim($itemId);
        if ($itemId === '') {
            return null;
        }
        $ctx = self::context(ListingChannelCounts::normalize($channelKey));
        if (! ($ctx['configured'] ?? false)) {
            return null;
        }

        try {
            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><GetItemRequest xmlns="urn:ebay:apis:eBLBaseComponents"/>');
            $xml->addChild('RequesterCredentials')->addChild('eBayAuthToken', (string) ($ctx['token'] ?? ''));
            $xml->addChild('ItemID', $itemId);
            $xml->addChild('DetailLevel', 'ReturnAll');
            $xml->addChild('IncludeItemSpecifics', 'true');

            $response = Http::timeout(60)
                ->withHeaders(self::tradingHeaders($ctx, 'GetItem'))
                ->withBody($xml->asXML(), 'text/xml')
                ->post((string) ($ctx['endpoint'] ?? 'https://api.ebay.com/ws/api.dll'));

            $xmlResp = simplexml_load_string($response->body());
            if ($xmlResp === false) {
                return null;
            }
            $data = json_decode(json_encode($xmlResp), true) ?: [];
            $ack = $data['Ack'] ?? 'Failure';

            return ($ack === 'Success' || $ack === 'Warning') ? $data : null;
        } catch (\Throwable $e) {
            Log::warning(($ctx['label'] ?? 'eBay').' GetItem failed: '.$e->getMessage(), ['item_id' => $itemId]);

            return null;
        }
    }

    public static function itemHasVariations(string $channelKey, string $itemId): bool
    {
        $data = self::getItem($channelKey, $itemId);
        $variations = $data['Item']['Variations']['Variation'] ?? null;

        return is_array($variations) && $variations !== [];
    }

    /**
     * Add new child variations to a live eBay variation listing (ReviseFixedPriceItem).
     * Only the new Variation rows are sent; the listing's existing variations stay untouched.
     * The variation-values set is rebuilt as existing values + new values, and the picture
     * set (when the listing already uses variation pictures) is re-sent with the new values appended.
     *
     * @param  list<string>  $existingValues  values already in the listing's VariationSpecificsSet
     * @param  list<array{sku: string, price: float, quantity: int, variation_label: string, images?: list<string>}>  $newVariations
     * @param  list<array{value: string, urls: list<string>}>  $existingPictureSets
     * @return array{success: bool, message: string}
     */
    public static function addVariationsToItem(
        string $channelKey,
        string $itemId,
        string $aspect,
        array $existingValues,
        array $newVariations,
        array $existingPictureSets = [],
        string $pictureAspect = '',
        bool $epsHosted = false
    ): array {
        $itemId = trim($itemId);
        if ($itemId === '' || $aspect === '' || $newVariations === []) {
            return ['success' => false, 'message' => 'Missing eBay item, variation aspect or new variations.'];
        }
        $ctx = self::context(ListingChannelCounts::normalize($channelKey));
        if (! ($ctx['configured'] ?? false)) {
            return ['success' => false, 'message' => ($ctx['label'] ?? 'eBay').' API credentials are not configured.'];
        }
        $esc = static fn (string $v): string => htmlspecialchars($v, ENT_XML1 | ENT_COMPAT, 'UTF-8');

        // eBay refuses a listing that mixes eBay-hosted (EPS) and self-hosted pictures. When the live
        // listing uses EPS pictures, copy our new images onto eBay's servers first and use those URLs.
        if ($epsHosted) {
            foreach ($newVariations as $i => $row) {
                $hosted = [];
                $lastError = '';
                foreach (array_slice(array_values(array_unique($row['images'] ?? [])), 0, 12) as $url) {
                    $eps = self::uploadPictureToEps($ctx, (string) $url, $lastError);
                    if ($eps !== null) {
                        $hosted[] = $eps;
                    }
                }
                if ($hosted === []) {
                    return ['success' => false, 'message' => 'Could not copy the Image Master photos of '.$row['sku'].' to eBay'
                        .($lastError !== '' ? ': '.$lastError : '.')
                        .' Nothing was added.'];
                }
                $newVariations[$i]['images'] = $hosted;
            }
        }

        try {
            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><ReviseFixedPriceItemRequest xmlns="urn:ebay:apis:eBLBaseComponents"/>');
            $xml->addChild('RequesterCredentials')->addChild('eBayAuthToken', (string) ($ctx['token'] ?? ''));
            $xml->addChild('ErrorLanguage', 'en_US');
            $xml->addChild('WarningLevel', 'High');
            $item = $xml->addChild('Item');
            $item->addChild('ItemID', $itemId);
            $block = $item->addChild('Variations');

            $values = array_values($existingValues);
            foreach ($newVariations as $row) {
                $variation = $block->addChild('Variation');
                $variation->addChild('SKU', $esc((string) $row['sku']));
                $variation->addChild('StartPrice', number_format(max(0.01, (float) $row['price']), 2, '.', ''));
                $variation->addChild('Quantity', (string) max(0, (int) $row['quantity']));
                $nvl = $variation->addChild('VariationSpecifics')->addChild('NameValueList');
                $nvl->addChild('Name', $esc($aspect));
                $nvl->addChild('Value', $esc((string) $row['variation_label']));
                $variation->addChild('VariationProductListingDetails')->addChild('UPC', self::NO_UPC);
                $values[] = (string) $row['variation_label'];
            }

            $newSets = array_values(array_filter($newVariations, static fn ($row) => ! empty($row['images'])));
            // The new variations' photos must always be sent, even when the live listing had no
            // variation pictures yet (then the variation aspect itself carries the pictures).
            if ($pictureAspect === '') {
                $pictureAspect = $aspect;
            }
            if (($existingPictureSets !== [] || $newSets !== []) && $pictureAspect !== '') {
                $pictures = $block->addChild('Pictures');
                $pictures->addChild('VariationSpecificName', $esc($pictureAspect));
                foreach ($existingPictureSets as $existing) {
                    $set = $pictures->addChild('VariationSpecificPictureSet');
                    $set->addChild('VariationSpecificValue', $esc((string) $existing['value']));
                    foreach ($existing['urls'] as $url) {
                        $set->addChild('PictureURL', $esc((string) $url));
                    }
                }
                foreach ($newSets as $row) {
                    $set = $pictures->addChild('VariationSpecificPictureSet');
                    $set->addChild('VariationSpecificValue', $esc((string) $row['variation_label']));
                    foreach (array_slice(array_values(array_unique($row['images'])), 0, 12) as $url) {
                        $set->addChild('PictureURL', $esc((string) $url));
                    }
                }
            }

            $setList = $block->addChild('VariationSpecificsSet')->addChild('NameValueList');
            $setList->addChild('Name', $esc($aspect));
            foreach (array_values(array_unique($values)) as $value) {
                $setList->addChild('Value', $esc($value));
            }

            $response = Http::timeout(90)
                ->withHeaders(self::tradingHeaders($ctx, 'ReviseFixedPriceItem'))
                ->withBody($xml->asXML(), 'text/xml')
                ->post((string) ($ctx['endpoint'] ?? 'https://api.ebay.com/ws/api.dll'));

            libxml_use_internal_errors(true);
            $xmlResp = simplexml_load_string($response->body());
            if ($xmlResp === false) {
                return ['success' => false, 'message' => 'Invalid XML response from eBay.'];
            }
            $data = json_decode(json_encode($xmlResp), true) ?: [];
            $ack = $data['Ack'] ?? 'Failure';
            if ($ack === 'Success' || $ack === 'Warning') {
                return ['success' => true, 'message' => 'Added '.count($newVariations).' variation(s) to eBay item '.$itemId.'.'];
            }

            $errors = $data['Errors'] ?? [];
            $errors = isset($errors['LongMessage']) || isset($errors['ShortMessage']) ? [$errors] : $errors;
            $messages = [];
            foreach ((array) $errors as $error) {
                if (! is_array($error) || (($error['SeverityCode'] ?? 'Error') !== 'Error')) {
                    continue;
                }
                $messages[] = trim((string) ($error['LongMessage'] ?? $error['ShortMessage'] ?? ''));
            }
            $message = trim(implode(' ', array_filter($messages))) ?: 'eBay rejected ReviseFixedPriceItem.';
            Log::warning(($ctx['label'] ?? 'eBay').' ReviseFixedPriceItem (add variations): '.$message, ['item_id' => $itemId]);

            return ['success' => false, 'message' => $message];
        } catch (\Throwable $e) {
            Log::warning(($ctx['label'] ?? 'eBay').' ReviseFixedPriceItem (add variations) failed: '.$e->getMessage(), ['item_id' => $itemId]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Copy an image onto eBay Picture Services and return the eBay-hosted URL.
     * The photo is downloaded here and sent as a file. Asking eBay to fetch a Shopify
     * link itself fails, which is what blocked variation photos.
     *
     * @param  array<string, mixed>  $ctx
     */
    private static function uploadPictureToEps(array $ctx, string $url, string &$error = ''): ?string
    {
        $url = trim($url);
        if ($url === '') {
            $error = 'Image Master photo URL is empty.';

            return null;
        }
        if (preg_match('#ebayimg\.com|ebaystatic\.com#i', $url)) {
            return $url;
        }

        // eBay's Trading UploadSiteHostedPictures answers HTTP 404 for this account, so the picture
        // goes through eBay's Media API first (it returns an i.ebayimg.com address).
        $mediaError = '';
        $media = self::uploadPictureViaMediaApi($ctx, $url, $mediaError);
        if ($media !== null) {
            return $media;
        }

        $result = EbayTradingReviseItem::uploadImageToEps(
            (string) ($ctx['endpoint'] ?? 'https://api.ebay.com/ws/api.dll'),
            (string) ($ctx['dev_id'] ?? ''),
            (string) ($ctx['app_id'] ?? ''),
            (string) ($ctx['cert_id'] ?? ''),
            (string) ($ctx['token'] ?? ''),
            $url,
            'image'
        );
        if (! empty($result['success']) && trim((string) ($result['url'] ?? '')) !== '') {
            return trim((string) $result['url']);
        }

        $fileError = trim((string) ($result['message'] ?? 'eBay did not accept the photo.'));

        $error = 'Media API: '.$mediaError.' | Trading API: '.$fileError;
        Log::warning(($ctx['label'] ?? 'eBay').' picture upload failed', [
            'url' => $url,
            'error' => $error,
        ]);

        return null;
    }

    /**
     * eBay Media API: copy a picture onto eBay's servers (needs the sell.inventory OAuth scope).
     * First asks eBay to fetch the link, then uploads the downloaded file.
     *
     * @param  array<string, mixed>  $ctx
     */
    private static function uploadPictureViaMediaApi(array $ctx, string $url, string &$error): ?string
    {
        $token = trim((string) ($ctx['token'] ?? ''));
        if ($token === '') {
            $error = 'no OAuth token.';

            return null;
        }
        $base = 'https://apim.ebay.com/commerce/media/v1_beta/image';
        $auth = ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
        $notes = [];

        $read = static function ($response) use ($auth, $base): string {
            $body = $response->json();
            $found = is_array($body) ? trim((string) ($body['imageUrl'] ?? '')) : '';
            if ($found !== '') {
                return $found;
            }
            $location = trim((string) $response->header('Location'));
            if ($location !== '') {
                $get = Http::timeout(30)->withHeaders($auth)->get($location);
                $data = $get->json();

                return is_array($data) ? trim((string) ($data['imageUrl'] ?? '')) : '';
            }

            return '';
        };

        $summary = static function ($response): string {
            $text = trim(preg_replace('/\s+/', ' ', strip_tags(substr((string) $response->body(), 0, 220))) ?? '');

            return 'HTTP '.$response->status().($text !== '' ? ' '.$text : '');
        };

        try {
            $response = Http::timeout(60)->withHeaders($auth)->asJson()
                ->post($base.'/create_image_from_url', ['imageUrl' => $url]);
            if ($response->successful()) {
                $hosted = $read($response);
                if ($hosted !== '') {
                    return $hosted;
                }
            }
            $notes[] = 'by link '.$summary($response);
        } catch (\Throwable $e) {
            $notes[] = 'by link '.$e->getMessage();
        }

        try {
            $file = Http::timeout(30)->connectTimeout(10)->get($url);
            if ($file->successful() && $file->body() !== '') {
                $mime = strtolower(trim(explode(';', (string) $file->header('Content-Type'))[0]));
                $ext = str_contains($mime, 'png') ? 'png' : 'jpg';
                $response = Http::timeout(90)->withHeaders($auth)
                    ->attach('image', $file->body(), 'photo.'.$ext)
                    ->post($base.'/create_image_from_file');
                if ($response->successful()) {
                    $hosted = $read($response);
                    if ($hosted !== '') {
                        return $hosted;
                    }
                }
                $notes[] = 'by file '.$summary($response);
            } else {
                $notes[] = 'photo download HTTP '.$file->status();
            }
        } catch (\Throwable $e) {
            $notes[] = 'by file '.$e->getMessage();
        }

        $error = implode('; ', $notes);

        return null;
    }

    /**
     * @return array{success: bool, message: string}
     */
    public static function endItem(string $channelKey, string $itemId, string $reason = 'NotAvailable'): array
    {
        $itemId = trim($itemId);
        if ($itemId === '') {
            return ['success' => false, 'message' => 'Missing ItemID.'];
        }
        $ctx = self::context(ListingChannelCounts::normalize($channelKey));
        if (! ($ctx['configured'] ?? false)) {
            return ['success' => false, 'message' => ($ctx['label'] ?? 'eBay').' API credentials are not configured.'];
        }

        $allowed = ['Incorrect', 'LostOrBroken', 'NotAvailable', 'OtherListingError', 'Sold'];
        if (! in_array($reason, $allowed, true)) {
            $reason = 'NotAvailable';
        }

        try {
            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><EndItemRequest xmlns="urn:ebay:apis:eBLBaseComponents"/>');
            $xml->addChild('RequesterCredentials')->addChild('eBayAuthToken', (string) ($ctx['token'] ?? ''));
            $xml->addChild('ItemID', $itemId);
            $xml->addChild('EndingReason', $reason);

            $response = Http::timeout(60)
                ->withHeaders(self::tradingHeaders($ctx, 'EndItem'))
                ->withBody($xml->asXML(), 'text/xml')
                ->post((string) ($ctx['endpoint'] ?? 'https://api.ebay.com/ws/api.dll'));

            $xmlResp = simplexml_load_string($response->body());
            $data = $xmlResp !== false ? (json_decode(json_encode($xmlResp), true) ?: []) : [];
            $ack = $data['Ack'] ?? 'Failure';
            if ($ack === 'Success' || $ack === 'Warning') {
                return ['success' => true, 'message' => 'Ended eBay item '.$itemId.'.'];
            }

            $msg = trim((string) ($data['Errors']['LongMessage'] ?? $data['Errors']['ShortMessage'] ?? 'EndItem failed.'));
            Log::warning(($ctx['label'] ?? 'eBay').' EndItem: '.$msg, ['item_id' => $itemId]);

            return ['success' => false, 'message' => $msg];
        } catch (\Throwable $e) {
            Log::warning(($ctx['label'] ?? 'eBay').' EndItem failed: '.$e->getMessage(), ['item_id' => $itemId]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array<string, string>
     */
    private static function tradingHeaders(array $ctx, string $call): array
    {
        return [
            'X-EBAY-API-COMPATIBILITY-LEVEL' => (string) ($ctx['compat'] ?? '1189'),
            'X-EBAY-API-DEV-NAME' => (string) ($ctx['dev_id'] ?? ''),
            'X-EBAY-API-APP-NAME' => (string) ($ctx['app_id'] ?? ''),
            'X-EBAY-API-CERT-NAME' => (string) ($ctx['cert_id'] ?? ''),
            'X-EBAY-API-CALL-NAME' => $call,
            'X-EBAY-API-SITEID' => (string) ($ctx['site_id'] ?? '0'),
            'Content-Type' => 'text/xml',
        ];
    }

    /**
     * Ebay 1 must use this store's business policies, not Ebay 2 IDs.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function applyEbay1Policies(array $payload): array
    {
        try {
            $svc = new EbayApiService();
            if ($svc->isConfigured()) {
                $resolved = $svc->policyIdsForPayload($payload);
                $payload['shipping_policy_id'] = $resolved['shipping'];
                $payload['payment_policy_id'] = $resolved['payment'];
                $payload['return_policy_id'] = $resolved['return'];
            }
        } catch (\Throwable $e) {
            Log::warning('Ebay 1 policy resolve failed: '.$e->getMessage());
        }
        $defaults = EbaySellAccountPolicies::defaultsForChannel('ebay');
        if (trim((string) ($payload['shipping_policy_id'] ?? '')) === '') {
            $payload['shipping_policy_name'] = trim((string) ($defaults['shipping_policy_name'] ?? 'As Per Weight')) ?: 'As Per Weight';
        }
        if (trim((string) ($payload['payment_policy_id'] ?? '')) === '') {
            $payload['payment_policy_name'] = trim((string) ($defaults['payment_policy_name'] ?? 'eBay Managed Payments')) ?: 'eBay Managed Payments';
        }
        if (trim((string) ($payload['return_policy_id'] ?? '')) === '') {
            $payload['return_policy_name'] = trim((string) ($defaults['return_policy_name'] ?? '30 days money back')) ?: '30 days money back';
        }

        return $payload;
    }

    /**
     * Ebay 3 must use this store's business policies, not Ebay 2 IDs, and cannot enable Best Offer.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function applyEbay3Policies(array $payload): array
    {
        $payload['best_offer'] = false;
        try {
            $svc = new EbayThreeApiService();
            if (! $svc->isConfigured()) {
                return $payload;
            }
            $resolved = $svc->policyIdsForPayload($payload);
            $payload['shipping_policy_id'] = $resolved['shipping'];
            $payload['payment_policy_id'] = $resolved['payment'];
            $payload['return_policy_id'] = $resolved['return'];
        } catch (\Throwable $e) {
            Log::warning('Ebay 3 policy resolve failed: '.$e->getMessage());
        }

        return $payload;
    }

    private static function appendEbay3FallbackShippingAndReturns(SimpleXMLElement $item): void
    {
        $shipping = $item->addChild('ShippingDetails');
        $shipping->addChild('ShippingType', 'Flat');
        $option = $shipping->addChild('ShippingServiceOptions');
        $option->addChild('ShippingServicePriority', '1');
        $option->addChild('ShippingService', 'USPSPriority');
        $option->addChild('ShippingServiceCost', '0.00');
        $option->addChild('ShippingServiceAdditionalCost', '0.00');

        $returns = $item->addChild('ReturnPolicy');
        $returns->addChild('ReturnsAcceptedOption', 'ReturnsAccepted');
        $returns->addChild('RefundOption', 'MoneyBack');
        $returns->addChild('ReturnsWithinOption', 'Days_30');
        $returns->addChild('ShippingCostPaidByOption', 'Buyer');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function stripUpcFromPayload(array $payload): array
    {
        $payload['upc'] = '';
        if (isset($payload['item_specifics']) && is_array($payload['item_specifics'])) {
            $payload['item_specifics'] = self::withoutUpcKeys($payload['item_specifics']);
        }
        if (isset($payload['variations']) && is_array($payload['variations'])) {
            foreach ($payload['variations'] as $i => $row) {
                if (is_array($row)) {
                    $payload['variations'][$i]['upc'] = '';
                }
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $specifics
     * @return array<string, mixed>
     */
    public static function withoutUpcKeys(array $specifics): array
    {
        foreach (array_keys($specifics) as $name) {
            if (strcasecmp(trim((string) $name), 'UPC') === 0) {
                unset($specifics[$name]);
            }
        }

        return $specifics;
    }

    /**
     * eBay categories reject AddFixedPriceItem when Type is omitted.
     *
     * @param  array<string, mixed>  $specifics
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function ensureRequiredItemSpecifics(array $specifics, array $payload = []): array
    {
        $hasType = false;
        foreach ($specifics as $name => $value) {
            if (strcasecmp(trim((string) $name), 'Type') === 0 && trim((string) $value) !== '') {
                $hasType = true;
                break;
            }
        }
        if (! $hasType) {
            $type = self::resolveTypeValue($payload, $specifics);
            if ($type !== '') {
                $specifics['Type'] = $type;
            }
        }

        return $specifics;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $specifics
     */
    public static function resolveTypeValue(array $payload, array $specifics = []): string
    {
        foreach (['Type', 'type', 'product_type'] as $key) {
            $value = trim((string) ($specifics[$key] ?? $payload[$key] ?? ''));
            if ($value !== '' && ! preg_match('/^\d+$/', $value)) {
                return $value;
            }
        }

        $haystack = trim(implode(' ', array_filter([
            (string) ($payload['title'] ?? ''),
            (string) ($payload['primary_category_path'] ?? ''),
            (string) ($payload['category'] ?? ''),
            (string) ($payload['category_name'] ?? ''),
        ])));
        $inferred = self::inferTypeFromText($haystack);
        if ($inferred !== '') {
            return $inferred;
        }

        $sku = trim((string) ($payload['sku'] ?? ''));
        if ($sku !== '' && Schema::hasTable('product_master')) {
            $row = DB::table('product_master')->where('sku', $sku)->first();
            if ($row) {
                foreach (['category', 'group'] as $col) {
                    $value = trim((string) ($row->{$col} ?? ''));
                    if ($value === '') {
                        continue;
                    }
                    $fromMaster = self::inferTypeFromText($value);
                    if ($fromMaster !== '') {
                        return $fromMaster;
                    }
                    if (! preg_match('/^\d+$/', $value)) {
                        return $value;
                    }
                }
            }
        }

        return trim((string) config('services.ebay.type_fallback_value', ''));
    }

    public static function inferTypeFromText(string $text): string
    {
        $t = strtolower($text);
        if ($t === '') {
            return '';
        }

        $rules = [
            'light stand' => 'Light Stand',
            'lighting stand' => 'Light Stand',
            'speaker stand' => 'Speaker Stand',
            'keyboard stand' => 'Keyboard Stand',
            'guitar stand' => 'Guitar Stand',
            'mic stand' => 'Microphone Stand',
            'microphone stand' => 'Microphone Stand',
            'amp stand' => 'Amplifier Stand',
            'tripod' => 'Tripod',
            'capo' => 'Capo',
            'guitar speaker' => 'Guitar Speaker',
            'subwoofer' => 'Subwoofer',
            'tweeter' => 'Tweeter',
            'speaker' => 'Speaker',
            'amplifier' => 'Amplifier',
            'mixer' => 'Mixer',
            'cable' => 'Cable',
            'connector' => 'Audio Connector',
            'microphone' => 'Microphone',
            'headset' => 'Headset',
            'headphone' => 'Headphones',
        ];

        foreach ($rules as $needle => $type) {
            if (str_contains($t, $needle)) {
                return $type;
            }
        }

        return '';
    }
}
