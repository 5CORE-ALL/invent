<?php

namespace App\Services\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Resolve eBay listing ItemID for ReviseItem when ebay_metrics has no item_id yet.
 * Tries Sell Inventory API (offer by SKU), then Trading GetSellerList by SKU + time window.
 */
final class EbaySellInventoryListingResolver
{
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
     * Trading API GetSellerList (SKU + EndTime window). Max ~120 days window per eBay rules.
     *
     * @param  array<string, string>  $headers  X-EBAY-API-* headers
     * @return non-empty-string|null
     */
    public static function tryGetSellerListItemId(
        string $tradingEndpoint,
        array $headers,
        string $xmlAuthToken,
        string $sku,
    ): ?string {
        $sku = trim($sku);
        if ($sku === '' || $xmlAuthToken === '') {
            return null;
        }

        $tokenEsc = htmlspecialchars($xmlAuthToken, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $skuEsc = htmlspecialchars($sku, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $from = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('-2 days')->format('Y-m-d\TH:i:s.000\Z');
        $to = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('+118 days')->format('Y-m-d\TH:i:s.000\Z');

        $xmlBody = '<?xml version="1.0" encoding="utf-8"?>'
            .'<GetSellerListRequest xmlns="urn:ebay:apis:eBLBaseComponents">'
            .'<RequesterCredentials><eBayAuthToken>'.$tokenEsc.'</eBayAuthToken></RequesterCredentials>'
            .'<ErrorLanguage>en_US</ErrorLanguage><WarningLevel>High</WarningLevel>'
            .'<GranularityLevel>Fine</GranularityLevel><DetailLevel>ReturnAll</DetailLevel>'
            .'<SKU>'.$skuEsc.'</SKU>'
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
                Log::debug('GetSellerList parse failed', ['sku' => $sku]);

                return null;
            }
            $arr = json_decode(json_encode($xmlResp), true);
            $ack = $arr['Ack'] ?? 'Failure';
            if ($ack !== 'Success' && $ack !== 'Warning') {
                Log::debug('GetSellerList not success', ['sku' => $sku, 'ack' => $ack]);

                return null;
            }

            $items = $arr['ItemArray']['Item'] ?? null;
            if ($items === null) {
                return null;
            }
            if (isset($items['ItemID'])) {
                $id = (string) $items['ItemID'];

                return $id !== '' ? $id : null;
            }
            if (is_array($items)) {
                foreach ($items as $it) {
                    if (is_array($it) && ! empty($it['ItemID'])) {
                        return (string) $it['ItemID'];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('GetSellerList exception', ['sku' => $sku, 'error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Full resolution: Inventory API then GetSellerList.
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

        return self::tryGetSellerListItemId($tradingEndpoint, $tradingHeaders, $bearerToken, $sku);
    }
}
