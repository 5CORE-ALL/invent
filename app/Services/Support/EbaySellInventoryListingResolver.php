<?php

namespace App\Services\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Resolve eBay listing ItemID for ReviseItem when ebay_*_metrics has no item_id yet.
 * Tries Sell Inventory API, GetMyeBaySelling ActiveList, then paginated GetSellerList.
 */
final class EbaySellInventoryListingResolver
{
    private const MAX_LISTING_PAGES = 15;

    /**
     * @return non-empty-string|null
     */
    public static function resolveListingIdBySku(string $bearerToken, string $sku): ?string
    {
        $sku = trim($sku);
        if ($sku === '' || $bearerToken === '') {
            return null;
        }

        $fromInventory = self::tryInventoryOfferListingId($bearerToken, $sku);
        if ($fromInventory !== null && $fromInventory !== '') {
            return $fromInventory;
        }

        return null;
    }

    /**
     * @return non-empty-string|null
     */
    private static function tryInventoryOfferListingId(string $bearerToken, string $sku): ?string
    {
        try {
            $url = 'https://api.ebay.com/sell/inventory/v1/offer?sku='.rawurlencode($sku).'&limit=50&offset=0';
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'Bearer '.$bearerToken,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->timeout(30)
                ->get($url);

            if (! $response->successful()) {
                Log::debug('eBay Inventory offer lookup failed', [
                    'sku' => $sku,
                    'status' => $response->status(),
                    'body' => substr((string) $response->body(), 0, 500),
                ]);

                return null;
            }

            $data = $response->json();
            $offers = $data['offers'] ?? [];
            if (! is_array($offers)) {
                return null;
            }

            foreach ($offers as $offer) {
                if (! is_array($offer)) {
                    continue;
                }
                $listingId = $offer['listingId'] ?? $offer['listing']['listingId'] ?? null;
                if ($listingId !== null && $listingId !== '') {
                    return (string) $listingId;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('eBay Inventory offer lookup exception', ['sku' => $sku, 'error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * GetMyeBaySelling ActiveList — reliable for legacy + variation listings (OAuth IAF token).
     *
     * @param  array<string, string>  $headers  X-EBAY-API-* headers (without CALL-NAME)
     * @return non-empty-string|null
     */
    public static function tryGetMyeBaySellingItemId(
        string $tradingEndpoint,
        array $headers,
        string $bearerToken,
        string $sku,
    ): ?string {
        $sku = trim($sku);
        if ($sku === '' || $bearerToken === '') {
            return null;
        }

        $target = strtoupper($sku);
        $page = 1;
        $totalPages = 1;

        while ($page <= min($totalPages, self::MAX_LISTING_PAGES)) {
            $xmlBody = '<?xml version="1.0" encoding="utf-8"?>'
                .'<GetMyeBaySellingRequest xmlns="urn:ebay:apis:eBLBaseComponents">'
                .'<ActiveList><Include>true</Include>'
                .'<Pagination><EntriesPerPage>200</EntriesPerPage><PageNumber>'.$page.'</PageNumber></Pagination>'
                .'</ActiveList>'
                .'<OutputSelector>ActiveList.ItemArray.Item.ItemID</OutputSelector>'
                .'<OutputSelector>ActiveList.ItemArray.Item.SKU</OutputSelector>'
                .'<OutputSelector>ActiveList.ItemArray.Item.Variations</OutputSelector>'
                .'<OutputSelector>ActiveList.PaginationResult</OutputSelector>'
                .'</GetMyeBaySellingRequest>';

            try {
                $h = $headers;
                $h['X-EBAY-API-CALL-NAME'] = 'GetMyeBaySelling';
                $h['Content-Type'] = 'text/xml';
                $h['X-EBAY-API-IAF-TOKEN'] = $bearerToken;

                $response = Http::withoutVerifying()->withHeaders($h)->withBody($xmlBody, 'text/xml')->timeout(60)->post($tradingEndpoint);
                $body = (string) $response->body();
                libxml_use_internal_errors(true);
                $xml = simplexml_load_string($body);
                if ($xml === false) {
                    Log::debug('GetMyeBaySelling parse failed', ['sku' => $sku, 'page' => $page]);

                    return null;
                }

                $arr = json_decode(json_encode($xml), true);
                $ack = $arr['Ack'] ?? 'Failure';
                if ($ack !== 'Success' && $ack !== 'Warning') {
                    Log::debug('GetMyeBaySelling not success', [
                        'sku' => $sku,
                        'page' => $page,
                        'ack' => $ack,
                        'message' => $arr['Errors']['LongMessage'] ?? $arr['Errors'][0]['LongMessage'] ?? null,
                    ]);

                    return null;
                }

                if ($page === 1) {
                    $totalPages = max(1, (int) ($arr['ActiveList']['PaginationResult']['TotalNumberOfPages'] ?? 1));
                }

                $items = $arr['ActiveList']['ItemArray']['Item'] ?? null;
                if ($items === null) {
                    $page++;

                    continue;
                }

                if (isset($items['ItemID'])) {
                    $items = [$items];
                }

                foreach ((array) $items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $itemId = trim((string) ($item['ItemID'] ?? ''));
                    if ($itemId === '') {
                        continue;
                    }

                    $skus = [];
                    $primary = strtoupper(trim((string) ($item['SKU'] ?? '')));
                    if ($primary !== '') {
                        $skus[] = $primary;
                    }

                    $variations = $item['Variations']['Variation'] ?? null;
                    if (is_array($variations)) {
                        if (isset($variations['SKU'])) {
                            $variations = [$variations];
                        }
                        foreach ($variations as $variation) {
                            if (! is_array($variation)) {
                                continue;
                            }
                            $varSku = strtoupper(trim((string) ($variation['SKU'] ?? '')));
                            if ($varSku !== '') {
                                $skus[] = $varSku;
                            }
                        }
                    }

                    if (in_array($target, $skus, true)) {
                        Log::info('eBay listing resolved via GetMyeBaySelling', [
                            'sku' => $sku,
                            'item_id' => $itemId,
                            'page' => $page,
                        ]);

                        return $itemId;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('GetMyeBaySelling exception', ['sku' => $sku, 'page' => $page, 'error' => $e->getMessage()]);

                return null;
            }

            $page++;
        }

        return null;
    }

    /**
     * Trading API GetSellerList — paginate and match SKU in response.
     * Note: GetSellerList does NOT accept a &lt;SKU&gt; filter element (eBay rejects it).
     *
     * @param  array<string, string>  $headers  X-EBAY-API-* headers
     * @return non-empty-string|null
     */
    public static function tryGetSellerListItemId(
        string $tradingEndpoint,
        array $headers,
        string $bearerToken,
        string $sku,
    ): ?string {
        $sku = trim($sku);
        if ($sku === '' || $bearerToken === '') {
            return null;
        }

        $target = strtoupper($sku);
        $from = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('-2 days')->format('Y-m-d\TH:i:s.000\Z');
        $to = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('+118 days')->format('Y-m-d\TH:i:s.000\Z');
        $tokenEsc = htmlspecialchars($bearerToken, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $page = 1;
        $totalPages = 1;

        while ($page <= min($totalPages, self::MAX_LISTING_PAGES)) {
            $xmlBody = '<?xml version="1.0" encoding="utf-8"?>'
                .'<GetSellerListRequest xmlns="urn:ebay:apis:eBLBaseComponents">'
                .'<RequesterCredentials><eBayAuthToken>'.$tokenEsc.'</eBayAuthToken></RequesterCredentials>'
                .'<ErrorLanguage>en_US</ErrorLanguage><WarningLevel>High</WarningLevel>'
                .'<GranularityLevel>Fine</GranularityLevel><DetailLevel>ReturnAll</DetailLevel>'
                .'<Pagination><EntriesPerPage>200</EntriesPerPage><PageNumber>'.$page.'</PageNumber></Pagination>'
                .'<EndTimeFrom>'.$from.'</EndTimeFrom>'
                .'<EndTimeTo>'.$to.'</EndTimeTo>'
                .'</GetSellerListRequest>';

            try {
                $h = $headers;
                $h['X-EBAY-API-CALL-NAME'] = 'GetSellerList';
                $h['Content-Type'] = 'text/xml';

                $response = Http::withoutVerifying()->withHeaders($h)->withBody($xmlBody, 'text/xml')->timeout(45)->post($tradingEndpoint);
                $body = (string) $response->body();
                libxml_use_internal_errors(true);
                $xmlResp = simplexml_load_string($body);
                if ($xmlResp === false) {
                    Log::debug('GetSellerList parse failed', ['sku' => $sku, 'page' => $page]);

                    return null;
                }

                $arr = json_decode(json_encode($xmlResp), true);
                $ack = $arr['Ack'] ?? 'Failure';
                if ($ack !== 'Success' && $ack !== 'Warning') {
                    Log::debug('GetSellerList not success', [
                        'sku' => $sku,
                        'page' => $page,
                        'ack' => $ack,
                        'message' => $arr['Errors']['LongMessage'] ?? $arr['Errors'][0]['LongMessage'] ?? null,
                    ]);

                    return null;
                }

                if ($page === 1) {
                    $totalPages = max(1, (int) ($arr['PaginationResult']['TotalNumberOfPages'] ?? 1));
                }

                $items = $arr['ItemArray']['Item'] ?? null;
                if ($items === null) {
                    $page++;

                    continue;
                }

                if (isset($items['ItemID'])) {
                    $items = [$items];
                }

                foreach ((array) $items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $itemSku = strtoupper(trim((string) ($item['SKU'] ?? '')));
                    if ($itemSku === $target && ! empty($item['ItemID'])) {
                        Log::info('eBay listing resolved via GetSellerList', [
                            'sku' => $sku,
                            'item_id' => (string) $item['ItemID'],
                            'page' => $page,
                        ]);

                        return (string) $item['ItemID'];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('GetSellerList exception', ['sku' => $sku, 'page' => $page, 'error' => $e->getMessage()]);

                return null;
            }

            $page++;
        }

        return null;
    }

    /**
     * Full resolution: Inventory API → GetMyeBaySelling → GetSellerList.
     *
     * @param  array<string, string>  $tradingHeaders
     * @return non-empty-string|null
     */
    public static function resolveWithTradingFallback(
        string $bearerToken,
        string $tradingEndpoint,
        array $tradingHeaders,
        string $sku,
    ): ?string {
        $id = self::resolveListingIdBySku($bearerToken, $sku);
        if ($id !== null) {
            return $id;
        }

        $id = self::tryGetMyeBaySellingItemId($tradingEndpoint, $tradingHeaders, $bearerToken, $sku);
        if ($id !== null) {
            return $id;
        }

        return self::tryGetSellerListItemId($tradingEndpoint, $tradingHeaders, $bearerToken, $sku);
    }
}
