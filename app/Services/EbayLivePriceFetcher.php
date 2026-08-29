<?php

namespace App\Services;

use App\Support\Marketplace\EbayCompetitorVariationMatcher;
use App\Support\Marketplace\EbayShippingCostParser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EbayLivePriceFetcher
{
    /** US zip so calculated / EIS shipping is resolved instead of treated as FREE. */
    private const US_SHIP_ZIP = '10001';

    /** @var array<string, list<array<string, mixed>>> */
    private array $variationCache = [];

    /** @var array<string, array{cost: float, known: bool}> */
    private array $listingShippingCache = [];

    public function getApiKey(): ?string
    {
        $key = config('services.serpapi.key');

        return $key ?: null;
    }

    public function extractListingIdFromUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        if (preg_match('/\/itm\/(\d+)/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function extractVariationIdFromUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        if (preg_match('/[?&]var=(\d+)/i', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Resolve the eBay listing ID to use for live price lookup.
     * Prefer the numeric ID from /itm/{id} in the product link over stored item_id (which may be epid).
     */
    public function resolveListingId(?string $productLink, ?string $storedItemId = null): ?string
    {
        $fromLink = $this->extractListingIdFromUrl($productLink);
        if ($fromLink) {
            return $fromLink;
        }

        if ($storedItemId && preg_match('/^\d+$/', (string) $storedItemId)) {
            return (string) $storedItemId;
        }

        return null;
    }

    /**
     * Fetch the current Buy-It-Now price for an eBay listing.
     * When $sku has a pack size (e.g. "MS DBL 4PCS") and the listing is a
     * variation, use that variation's BIN — not the unselected default price.
     *
     * @return array{listing_id: string, price: float, shipping_cost: float, total_price: float, title: ?string, link: ?string, image: ?string, variation_label?: ?string}|null
     */
    public function fetchByListingId(string $listingId, ?string $sku = null, ?string $productLink = null): ?array
    {
        $variationId = $this->extractVariationIdFromUrl($productLink);

        if ($variationId || EbayCompetitorVariationMatcher::wantsVariationMatch($sku)) {
            $matched = EbayCompetitorVariationMatcher::pick(
                $this->fetchVariations($listingId),
                $sku,
                $variationId
            );

            if ($matched && (float) ($matched['price'] ?? 0) > 0) {
                $live = $this->liveFromVariation($listingId, $matched, $sku);
                if ($live) {
                    $live = $this->ensureLiveShipping($listingId, $live);
                    Log::info('EbayLivePriceFetcher: using matched variation price', [
                        'listing_id' => $listingId,
                        'sku' => $sku,
                        'variation_label' => $live['variation_label'] ?? null,
                        'price' => $live['price'],
                        'shipping_cost' => $live['shipping_cost'],
                    ]);

                    return $live;
                }
            }
        }

        return $this->ensureLiveShipping($listingId, $this->fetchSerpApiProduct($listingId));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchVariations(string $listingId): array
    {
        if (array_key_exists($listingId, $this->variationCache)) {
            return $this->variationCache[$listingId];
        }

        $variations = $this->fetchVariationsFromBrowseApi($listingId);
        if (! $this->hasUsefulVariationLabels($variations)) {
            $shopping = $this->fetchVariationsFromShoppingApi($listingId);
            if ($shopping !== []) {
                $variations = $shopping;
            } elseif ($variations === []) {
                $variations = $this->fetchVariationsFromListingHtml($listingId);
            }
        }

        $this->variationCache[$listingId] = $this->withListingShipping($variations, $listingId);

        return $this->variationCache[$listingId];
    }

    /**
     * @param  array<string, mixed>  $variation
     * @return array{listing_id: string, price: float, shipping_cost: float, total_price: float, title: ?string, link: ?string, image: ?string, variation_label?: ?string}|null
     */
    public function liveFromVariation(string $listingId, array $variation, ?string $sku = null): ?array
    {
        $price = round((float) ($variation['price'] ?? 0), 2);
        if ($price <= 0) {
            return null;
        }

        $shipping = round((float) ($variation['shipping_cost'] ?? 0), 2);
        $shippingKnown = (bool) ($variation['shipping_known'] ?? ($shipping > 0));
        $label = EbayCompetitorVariationMatcher::shortLabel($variation, $sku);
        $title = is_string($variation['title'] ?? null) ? trim((string) $variation['title']) : '';
        if ($title !== '' && $label !== '' && stripos($title, $label) === false) {
            $title = $title.' ['.$label.']';
        } elseif ($title === '') {
            $title = $label !== '' ? $label : '';
        }

        return [
            'listing_id' => $listingId,
            'price' => $price,
            'shipping_cost' => $shipping,
            'shipping_known' => $shippingKnown,
            'total_price' => round($price + $shipping, 2),
            'title' => $title !== '' ? $title : null,
            'link' => $variation['link'] ?? $this->variationLink($listingId, $variation),
            'image' => $variation['image'] ?? null,
            'variation_label' => $label !== '' ? $label : null,
        ];
    }

    /**
     * Fetch the current Buy-It-Now price for an eBay listing via SerpApi.
     *
     * @return array{listing_id: string, price: float, shipping_cost: float, total_price: float, title: ?string, link: ?string, image: ?string}|null
     */
    private function fetchSerpApiProduct(string $listingId): ?array
    {
        $apiKey = $this->getApiKey();
        if (!$apiKey) {
            return null;
        }

        try {
            $response = Http::timeout(30)->get('https://serpapi.com/search', [
                'engine' => 'ebay_product',
                'ebay_domain' => 'ebay.com',
                'product_id' => $listingId,
                'shipping_country' => 'US',
                'api_key' => $apiKey,
            ]);

            if (!$response->successful()) {
                Log::warning('EbayLivePriceFetcher: SerpApi HTTP error', [
                    'listing_id' => $listingId,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $data = $response->json();
            if (!empty($data['error'])) {
                return null;
            }

            $product = $data['product_results'] ?? $data;
            $price = $this->extractPrice($product);
            if ($price === null) {
                return null;
            }

            $shipping = EbayShippingCostParser::fromProduct(is_array($product) ? $product : []);
            $link = $product['product_link'] ?? $product['link'] ?? "https://www.ebay.com/itm/{$listingId}";
            $image = $this->extractImage($product);

            return [
                'listing_id' => $listingId,
                'price' => round($price, 2),
                'shipping_cost' => round($shipping['cost'], 2),
                'shipping_known' => $shipping['known'],
                'total_price' => round($price + $shipping['cost'], 2),
                'title' => $product['title'] ?? null,
                'link' => $link,
                'image' => $image,
            ];
        } catch (\Throwable $e) {
            Log::warning('EbayLivePriceFetcher: fetch failed', [
                'listing_id' => $listingId,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchVariationsFromBrowseApi(string $listingId): array
    {
        $token = $this->getBrowseAccessToken();
        if (!$token) {
            return [];
        }

        try {
            $response = Http::withToken($token)
                ->withHeaders($this->browseHeaders())
                ->timeout(25)
                ->get('https://api.ebay.com/buy/browse/v1/item/get_items_by_item_group', [
                    'item_group_id' => $listingId,
                ]);

            if (!$response->successful()) {
                return [];
            }

            $items = $response->json('items') ?? [];
            if (!is_array($items) || $items === []) {
                return [];
            }

            $variations = [];
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $price = $this->numericPrice($item['price']['value'] ?? $item['price'] ?? null);
                if ($price === null) {
                    continue;
                }

                $aspects = $item['localizedAspects'] ?? [];
                $labels = [];
                if (is_array($aspects)) {
                    foreach ($aspects as $aspect) {
                        $value = trim((string) ($aspect['value'] ?? ''));
                        if ($value === '') {
                            continue;
                        }
                        $name = (string) ($aspect['name'] ?? '');
                        if (preg_match('/pack|quantity|qty|count|pieces/i', $name) && preg_match('/^\d+$/', $value)) {
                            $labels[] = $value.'PCS';
                        } else {
                            $labels[] = $value;
                        }
                    }
                }

                $shipping = $this->shippingFromBrowseItem($item);

                $itemId = (string) ($item['itemId'] ?? '');
                $varId = null;
                if (preg_match('/\|(\d+)$/', $itemId, $match)) {
                    $varId = $match[1];
                }

                $variations[] = [
                    'id' => $varId ?? $itemId,
                    'item_id' => $itemId,
                    'label' => implode(' / ', $labels),
                    'price' => $price,
                    'shipping_cost' => $shipping['cost'],
                    'shipping_known' => $shipping['known'],
                    'title' => $item['title'] ?? null,
                    'image' => $item['image']['imageUrl'] ?? null,
                    'link' => $item['itemWebUrl'] ?? $this->variationLink($listingId, ['id' => $varId]),
                ];
            }

            return $variations;
        } catch (\Throwable $e) {
            Log::warning('EbayLivePriceFetcher: Browse variation fetch failed', [
                'listing_id' => $listingId,
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchVariationsFromShoppingApi(string $listingId): array
    {
        $appId = config('services.ebay.app_id') ?: config('services.ebay2.app_id') ?: config('services.ebay3.app_id');
        if (!$appId) {
            return [];
        }

        try {
            $response = Http::timeout(25)->get('https://open.api.ebay.com/shopping', [
                'callname' => 'GetSingleItem',
                'responseencoding' => 'JSON',
                'appid' => $appId,
                'siteid' => 0,
                'version' => 1157,
                'ItemID' => $listingId,
                'IncludeSelector' => 'Variations,Details,ShippingCosts',
            ]);

            if (!$response->successful()) {
                return [];
            }

            $item = $response->json('Item') ?? [];
            if (!is_array($item)) {
                return [];
            }

            $raw = $item['Variations']['Variation'] ?? [];
            if ($raw === [] || $raw === null) {
                return [];
            }
            if (isset($raw['StartPrice']) || isset($raw['VariationSpecifics'])) {
                $raw = [$raw];
            }

            $shippingParsed = EbayShippingCostParser::fromMoney(
                $item['ShippingCostSummary']['ShippingServiceCost']['Value']
                    ?? $item['ShippingCostSummary']['ShippingServiceCost']
                    ?? null
            );
            if (! $shippingParsed['known'] && isset($item['ShippingCostSummary']['ShippingType'])) {
                $type = (string) $item['ShippingCostSummary']['ShippingType'];
                if (stripos($type, 'free') !== false) {
                    $shippingParsed = ['cost' => 0.0, 'known' => true];
                }
            }
            $shipping = $shippingParsed['cost'];

            $title = $item['Title'] ?? null;
            $image = $item['PictureURL'][0] ?? (is_string($item['PictureURL'] ?? null) ? $item['PictureURL'] : null);
            $variations = [];

            foreach ($raw as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $price = $this->numericPrice($row['StartPrice']['Value'] ?? $row['StartPrice'] ?? null);
                if ($price === null) {
                    continue;
                }

                $specifics = $row['VariationSpecifics']['NameValueList'] ?? [];
                if (isset($specifics['Name'])) {
                    $specifics = [$specifics];
                }
                $labels = [];
                foreach ((array) $specifics as $specific) {
                    $value = $specific['Value'] ?? null;
                    if (is_array($value)) {
                        $value = implode(', ', $value);
                    }
                    $value = trim((string) $value);
                    if ($value !== '') {
                        $labels[] = $value;
                    }
                }

                $variations[] = [
                    'id' => (string) ($row['VariationSpecifics']['NameValueList']['Value'] ?? ''),
                    'item_id' => (string) ($row['SKU'] ?? ''),
                    'label' => implode(' / ', $labels),
                    'price' => $price,
                    'shipping_cost' => $shipping,
                    'shipping_known' => $shippingParsed['known'] || $shipping > 0,
                    'title' => $title,
                    'image' => is_string($image) ? $image : null,
                    'link' => "https://www.ebay.com/itm/{$listingId}",
                ];
            }

            return $variations;
        } catch (\Throwable $e) {
            Log::warning('EbayLivePriceFetcher: Shopping variation fetch failed', [
                'listing_id' => $listingId,
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchVariationsFromListingHtml(string $listingId): array
    {
        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; InventLmp/1.0)',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ])
                ->get("https://www.ebay.com/itm/{$listingId}", [
                    '_stpos' => self::US_SHIP_ZIP,
                ]);

            if (!$response->successful()) {
                return [];
            }

            $html = $response->body();
            $msku = $this->extractMskuObject($html);
            if ($msku === []) {
                return [];
            }

            return $this->withListingShipping(
                $this->variationsFromMsku($listingId, $msku),
                $listingId,
                $html
            );
        } catch (\Throwable $e) {
            Log::warning('EbayLivePriceFetcher: HTML variation fetch failed', [
                'listing_id' => $listingId,
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function extractMskuObject(string $html): array
    {
        $needle = '"variationsMap"';
        $pos = strpos($html, $needle);
        if ($pos === false) {
            return [];
        }

        $start = $pos;
        $depth = 0;
        for ($i = $pos; $i >= 0; $i--) {
            $char = $html[$i];
            if ($char === '}') {
                $depth++;
            } elseif ($char === '{') {
                if ($depth === 0) {
                    $start = $i;
                    break;
                }
                $depth--;
            }
        }

        $json = $this->extractJsonObjectAt($html, $start);
        if ($json === null) {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) && isset($decoded['variationsMap']) ? $decoded : [];
    }

    private function extractJsonObjectAt(string $html, int $start): ?string
    {
        if (!isset($html[$start]) || $html[$start] !== '{') {
            return null;
        }

        $depth = 0;
        $inString = false;
        $escape = false;
        $length = strlen($html);
        for ($i = $start; $i < $length; $i++) {
            $char = $html[$i];
            if ($inString) {
                if ($escape) {
                    $escape = false;
                } elseif ($char === '\\') {
                    $escape = true;
                } elseif ($char === '"') {
                    $inString = false;
                }
                continue;
            }
            if ($char === '"') {
                $inString = true;
                continue;
            }
            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($html, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $msku
     * @return list<array<string, mixed>>
     */
    private function variationsFromMsku(string $listingId, array $msku): array
    {
        $variationsMap = $msku['variationsMap'] ?? [];
        $menuItemMap = $msku['menuItemMap'] ?? [];
        $combos = $msku['variationCombinations'] ?? [];
        $selectMenus = $msku['selectMenus'] ?? [];
        if (!is_array($variationsMap) || $variationsMap === []) {
            return [];
        }

        $variations = [];
        $comboEntries = is_array($combos) && $combos !== [] ? $combos : ['_' => null];

        foreach ($comboEntries as $comboKey => $comboId) {
            $variantId = $comboId !== null ? (string) $comboId : (string) $comboKey;
            $variant = $variationsMap[$variantId] ?? $variationsMap[(string) $comboId] ?? null;
            if (!is_array($variant) && is_array($variationsMap[$comboKey] ?? null)) {
                $variant = $variationsMap[$comboKey];
                $variantId = (string) $comboKey;
            }
            if (!is_array($variant)) {
                continue;
            }

            $price = $this->priceFromMskuVariant($variant);
            if ($price === null) {
                continue;
            }

            $labels = [];
            if (is_string($comboKey) && $comboKey !== '_' && is_array($menuItemMap)) {
                foreach (preg_split('/_/', $comboKey) ?: [] as $menuId) {
                    $menuItem = $menuItemMap[(string) $menuId] ?? $menuItemMap[(int) $menuId] ?? null;
                    if (!is_array($menuItem)) {
                        continue;
                    }
                    $name = trim((string) ($menuItem['displayName'] ?? $menuItem['valueName'] ?? ''));
                    if ($name !== '') {
                        $labels[] = $name;
                    }
                }
            }
            if ($labels === [] && is_array($selectMenus)) {
                foreach ($selectMenus as $menu) {
                    $name = trim((string) ($menu['selectedValueDisplayName'] ?? ''));
                    if ($name !== '') {
                        $labels[] = $name;
                    }
                }
            }

            $variations[] = [
                'id' => $variantId,
                'item_id' => $variantId,
                'label' => implode(' / ', $labels),
                'price' => $price,
                'shipping_cost' => 0.0,
                'title' => null,
                'image' => null,
                'link' => $this->variationLink($listingId, ['id' => $variantId]),
            ];
        }

        if ($variations === [] && is_array($variationsMap)) {
            foreach ($variationsMap as $variantId => $variant) {
                if (!is_array($variant)) {
                    continue;
                }
                $price = $this->priceFromMskuVariant($variant);
                if ($price === null) {
                    continue;
                }
                $variations[] = [
                    'id' => (string) $variantId,
                    'item_id' => (string) $variantId,
                    'label' => (string) ($variant['displayName'] ?? $variantId),
                    'price' => $price,
                    'shipping_cost' => 0.0,
                    'title' => null,
                    'image' => null,
                    'link' => $this->variationLink($listingId, ['id' => (string) $variantId]),
                ];
            }
        }

        return $variations;
    }

    /**
     * @param  array<string, mixed>  $variant
     */
    private function priceFromMskuVariant(array $variant): ?float
    {
        $bin = $variant['binModel']['price'] ?? $variant['price'] ?? null;
        if (is_array($bin)) {
            if (isset($bin['amount'])) {
                return $this->numericPrice($bin['amount']);
            }
            if (isset($bin['value'])) {
                return $this->numericPrice($bin['value']);
            }
            $text = $bin['textSpans'][0]['text'] ?? $bin['text'] ?? null;
            if (is_string($text)) {
                return $this->numericPrice($text);
            }
        }

        return $this->numericPrice($bin);
    }

    /**
     * @param  array<string, mixed>  $variation
     */
    private function variationLink(string $listingId, array $variation): string
    {
        $varId = trim((string) ($variation['id'] ?? ''));
        if ($varId !== '' && preg_match('/^\d+$/', $varId)) {
            return "https://www.ebay.com/itm/{$listingId}?var={$varId}";
        }

        return "https://www.ebay.com/itm/{$listingId}";
    }

    private function getBrowseAccessToken(): ?string
    {
        $pairs = [
            [config('services.ebay.app_id'), config('services.ebay.cert_id')],
            [config('services.ebay2.app_id'), config('services.ebay2.cert_id')],
            [config('services.ebay3.app_id'), config('services.ebay3.cert_id')],
        ];

        foreach ($pairs as [$clientId, $clientSecret]) {
            $clientId = trim((string) $clientId);
            $clientSecret = trim((string) $clientSecret);
            if ($clientId === '' || $clientSecret === '') {
                continue;
            }

            $cacheKey = 'ebay_lmp_browse_token_'.md5($clientId);
            $cached = Cache::get($cacheKey);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }

            try {
                $response = Http::asForm()
                    ->withBasicAuth($clientId, $clientSecret)
                    ->timeout(20)
                    ->post('https://api.ebay.com/identity/v1/oauth2/token', [
                        'grant_type' => 'client_credentials',
                        'scope' => 'https://api.ebay.com/oauth/api_scope',
                    ]);

                if ($response->failed()) {
                    continue;
                }

                $token = trim((string) ($response->json('access_token') ?? ''));
                $expiresIn = (int) ($response->json('expires_in') ?? 7200);
                if ($token === '') {
                    continue;
                }

                Cache::put($cacheKey, $token, now()->addSeconds(max(60, $expiresIn - 60)));

                return $token;
            } catch (\Throwable $e) {
                Log::warning('EbayLivePriceFetcher: Browse token failed', [
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $variations
     */
    private function hasUsefulVariationLabels(array $variations): bool
    {
        foreach ($variations as $variation) {
            if (EbayCompetitorVariationMatcher::labelHasDiscriminant((string) ($variation['label'] ?? ''))) {
                return true;
            }
        }

        return false;
    }

    private function numericPrice(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return $value > 0 ? round((float) $value, 2) : null;
        }
        if (!is_string($value)) {
            return null;
        }
        if (preg_match('/[\d,.]+/', $value, $matches)) {
            $amount = (float) str_replace(',', '', $matches[0]);

            return $amount > 0 ? round($amount, 2) : null;
        }

        return null;
    }

    private function extractPrice(array $product): ?float
    {
        if (isset($product['buy']['buy_it_now']['price']['amount'])) {
            return (float) $product['buy']['buy_it_now']['price']['amount'];
        }

        if (isset($product['price']['value'])) {
            return (float) $product['price']['value'];
        }

        if (isset($product['price']['extracted'])) {
            return (float) $product['price']['extracted'];
        }

        if (!empty($product['price']) && is_string($product['price']) && preg_match('/[\d,.]+/', $product['price'], $matches)) {
            return (float) str_replace(',', '', $matches[0]);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $live
     * @return array<string, mixed>|null
     */
    private function ensureLiveShipping(string $listingId, ?array $live): ?array
    {
        if ($live === null) {
            return null;
        }

        $shipping = (float) ($live['shipping_cost'] ?? 0);
        if ($shipping > 0) {
            return $live;
        }

        // SerpApi often reports FREE when calculated shipping needs a US zip.
        // Ask Browse/Shopping with a destination before accepting $0.
        $fetched = $this->fetchListingShipping($listingId);
        if ($fetched['cost'] > 0) {
            $live['shipping_cost'] = $fetched['cost'];
            $live['shipping_known'] = true;
            $live['total_price'] = round((float) ($live['price'] ?? 0) + $fetched['cost'], 2);

            return $live;
        }

        if ($fetched['known']) {
            $live['shipping_known'] = true;
            $live['shipping_cost'] = 0.0;
            $live['total_price'] = round((float) ($live['price'] ?? 0), 2);
        }

        return $live;
    }

    /**
     * @param  list<array<string, mixed>>  $variations
     * @return list<array<string, mixed>>
     */
    private function withListingShipping(array $variations, string $listingId, ?string $html = null): array
    {
        $needs = false;
        foreach ($variations as $variation) {
            if ((float) ($variation['shipping_cost'] ?? 0) <= 0 && empty($variation['shipping_known'])) {
                $needs = true;
                break;
            }
        }
        if (! $needs) {
            return $variations;
        }

        $parsed = $this->fetchListingShipping($listingId);
        if (! $parsed['known'] && $html) {
            $parsed = EbayShippingCostParser::fromHtml($html);
        }
        if (! $parsed['known'] && $parsed['cost'] <= 0) {
            return $variations;
        }

        foreach ($variations as $index => $variation) {
            if ((float) ($variation['shipping_cost'] ?? 0) > 0 || ! empty($variation['shipping_known'])) {
                continue;
            }
            $variations[$index]['shipping_cost'] = $parsed['cost'];
            $variations[$index]['shipping_known'] = $parsed['known'];
        }

        return $variations;
    }

    /**
     * @return array{cost: float, known: bool}
     */
    private function fetchListingShipping(string $listingId): array
    {
        if (isset($this->listingShippingCache[$listingId])) {
            return $this->listingShippingCache[$listingId];
        }

        $parsed = $this->fetchShippingFromBrowseItem($listingId);
        if (! $parsed['known']) {
            $parsed = $this->fetchShippingFromShoppingApi($listingId);
        }

        $this->listingShippingCache[$listingId] = $parsed;

        return $parsed;
    }

    /**
     * @return array{cost: float, known: bool}
     */
    private function fetchShippingFromBrowseItem(string $listingId): array
    {
        $token = $this->getBrowseAccessToken();
        if (! $token) {
            return ['cost' => 0.0, 'known' => false];
        }

        try {
            $response = Http::withToken($token)
                ->withHeaders($this->browseHeaders())
                ->timeout(25)
                ->get('https://api.ebay.com/buy/browse/v1/item/get_item_by_legacy_id', [
                    'legacy_item_id' => $listingId,
                ]);

            if (! $response->successful()) {
                return ['cost' => 0.0, 'known' => false];
            }

            $item = $response->json();

            return is_array($item) ? $this->shippingFromBrowseItem($item) : ['cost' => 0.0, 'known' => false];
        } catch (\Throwable $e) {
            Log::warning('EbayLivePriceFetcher: Browse item shipping failed', [
                'listing_id' => $listingId,
                'message' => $e->getMessage(),
            ]);

            return ['cost' => 0.0, 'known' => false];
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{cost: float, known: bool}
     */
    private function shippingFromBrowseItem(array $item): array
    {
        $options = $item['shippingOptions'] ?? [];
        if (! is_array($options) || $options === []) {
            return ['cost' => 0.0, 'known' => false];
        }

        $bestPaid = null;
        $sawFree = false;
        foreach ($options as $option) {
            if (! is_array($option)) {
                continue;
            }
            $parsed = EbayShippingCostParser::fromOption($option);
            if (! $parsed['known']) {
                continue;
            }
            if ($parsed['cost'] > 0) {
                if ($bestPaid === null || $parsed['cost'] < $bestPaid) {
                    $bestPaid = $parsed['cost'];
                }
            } else {
                $sawFree = true;
            }
        }

        if ($bestPaid !== null) {
            return ['cost' => $bestPaid, 'known' => true];
        }
        if ($sawFree) {
            return ['cost' => 0.0, 'known' => true];
        }

        return ['cost' => 0.0, 'known' => false];
    }

    /**
     * @return array{cost: float, known: bool}
     */
    private function fetchShippingFromShoppingApi(string $listingId): array
    {
        $appId = config('services.ebay.app_id') ?: config('services.ebay2.app_id') ?: config('services.ebay3.app_id');
        if (! $appId) {
            return ['cost' => 0.0, 'known' => false];
        }

        try {
            $response = Http::timeout(25)->get('https://open.api.ebay.com/shopping', [
                'callname' => 'GetShippingCosts',
                'responseencoding' => 'JSON',
                'appid' => $appId,
                'siteid' => 0,
                'version' => 1157,
                'ItemID' => $listingId,
                'DestinationCountryCode' => 'US',
                'DestinationPostalCode' => self::US_SHIP_ZIP,
                'IncludeDetails' => 'true',
            ]);

            if (! $response->successful()) {
                return ['cost' => 0.0, 'known' => false];
            }

            $data = $response->json() ?? [];
            $summary = is_array($data) ? ($data['ShippingCostSummary'] ?? []) : [];
            $parsed = EbayShippingCostParser::fromMoney(
                $summary['ShippingServiceCost']['Value']
                    ?? $summary['ShippingServiceCost']
                    ?? null
            );
            if ($parsed['known']) {
                return $parsed;
            }

            if (isset($summary['ShippingType']) && stripos((string) $summary['ShippingType'], 'free') !== false) {
                return ['cost' => 0.0, 'known' => true];
            }

            return ['cost' => 0.0, 'known' => false];
        } catch (\Throwable $e) {
            Log::warning('EbayLivePriceFetcher: Shopping shipping failed', [
                'listing_id' => $listingId,
                'message' => $e->getMessage(),
            ]);

            return ['cost' => 0.0, 'known' => false];
        }
    }

    /**
     * @return array<string, string>
     */
    private function browseHeaders(): array
    {
        return [
            'X-EBAY-C-MARKETPLACE-ID' => 'EBAY_US',
            'X-EBAY-C-ENDUSERCTX' => 'contextualLocation=country%3DUS%2Czip%3D'.self::US_SHIP_ZIP,
        ];
    }

    private function extractImage(array $product): ?string
    {
        if (!empty($product['thumbnail']) && is_string($product['thumbnail'])) {
            return $product['thumbnail'];
        }

        if (!empty($product['image']) && is_string($product['image'])) {
            return $product['image'];
        }

        if (!empty($product['images'][0]) && is_string($product['images'][0])) {
            return $product['images'][0];
        }

        if (empty($product['media']) || !is_array($product['media'])) {
            return null;
        }

        foreach ($product['media'] as $mediaItem) {
            if (($mediaItem['type'] ?? '') !== 'image') {
                continue;
            }

            $variants = $mediaItem['image'] ?? [];
            if (!is_array($variants) || empty($variants)) {
                continue;
            }

            $bestLink = null;
            $bestWidth = 0;

            foreach ($variants as $variant) {
                $link = $variant['link'] ?? null;
                if (!$link) {
                    continue;
                }

                $width = (int) ($variant['size']['width'] ?? 0);
                if ($width === 500) {
                    return $link;
                }

                if ($width > $bestWidth) {
                    $bestLink = $link;
                    $bestWidth = $width;
                }
            }

            if ($bestLink) {
                return $bestLink;
            }
        }

        return null;
    }
}
