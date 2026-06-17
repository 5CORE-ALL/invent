<?php

namespace App\Services\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * eBay Trading API helpers: listing description updates and bullet points via Item Specifics.
 */
final class EbayTradingReviseItem
{
    /**
     * @return array{success: bool, message: string}
     */
    public static function reviseItemDescription(
        string $endpoint,
        string $compatLevel,
        string $devId,
        string $appId,
        string $certId,
        string $siteId,
        string $authToken,
        string $itemId,
        string $descriptionHtml,
        string $contextLabel = 'description',
    ): array {
        $cdata = str_replace(']]>', ']]]]><![CDATA[>', $descriptionHtml);
        $tokenEsc = htmlspecialchars($authToken, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $idEsc = htmlspecialchars($itemId, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $xmlBody = '<?xml version="1.0" encoding="utf-8"?>'
            .'<ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">'
            .'<RequesterCredentials><eBayAuthToken>'.$tokenEsc.'</eBayAuthToken></RequesterCredentials>'
            .'<ErrorLanguage>en_US</ErrorLanguage><WarningLevel>High</WarningLevel>'
            .'<Item><ItemID>'.$idEsc.'</ItemID>'
            .'<Description><![CDATA['.$cdata.']]></Description>'
            .'</Item></ReviseItemRequest>';

        return self::postReviseItemXml($endpoint, $compatLevel, $devId, $appId, $certId, $siteId, $itemId, $xmlBody, $contextLabel);
    }

    /**
     * Upload a single image to eBay's EPS (Electronic Photo Service) and return the hosted URL.
     * eBay requires images to be on their CDN before they can be used in listings.
     *
     * @return array{success: bool, url?: string, message?: string}
     */
    public static function uploadImageToEps(
        string $endpoint,
        string $devId,
        string $appId,
        string $certId,
        string $authToken,
        string $imageUrl,
        string $pictureName = 'image',
    ): array {
        // Already an eBay-hosted URL — no need to re-upload
        if (preg_match('#^https?://(i|p)\.ebayimg\.com/#i', $imageUrl)) {
            return ['success' => true, 'url' => $imageUrl, 'message' => 'Already eBay-hosted.'];
        }

        // Read image binary: local file or remote URL
        $imageData = null;
        $contentType = 'image/jpeg';

        $localPath = self::resolveLocalStoragePath($imageUrl);
        if ($localPath && file_exists($localPath)) {
            $imageData = file_get_contents($localPath);
            $mime = mime_content_type($localPath);
            if ($mime && str_starts_with($mime, 'image/')) {
                $contentType = $mime;
            }
        }

        if ($imageData === null || $imageData === false) {
            // Fetch from remote URL
            try {
                $resp = Http::timeout(30)->connectTimeout(10)->get($imageUrl);
                if (! $resp->successful()) {
                    return ['success' => false, 'message' => "Image not accessible (HTTP {$resp->status()}): {$imageUrl}"];
                }
                $imageData = $resp->body();
                $ct = $resp->header('Content-Type');
                if ($ct && str_starts_with($ct, 'image/')) {
                    $contentType = explode(';', $ct)[0];
                }
            } catch (\Throwable $e) {
                return ['success' => false, 'message' => "Image download failed: {$e->getMessage()} — URL: {$imageUrl}"];
            }
        }

        if (empty($imageData)) {
            return ['success' => false, 'message' => "Image file is empty: {$imageUrl}"];
        }

        // Build UploadSiteHostedPictures XML payload
        $tokenEsc = htmlspecialchars($authToken, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $nameEsc  = htmlspecialchars($pictureName, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $xmlPayload = '<?xml version="1.0" encoding="utf-8"?>'
            .'<UploadSiteHostedPicturesRequest xmlns="urn:ebay:apis:eBLBaseComponents">'
            .'<RequesterCredentials><eBayAuthToken>'.$tokenEsc.'</eBayAuthToken></RequesterCredentials>'
            .'<ErrorLanguage>en_US</ErrorLanguage>'
            .'<PictureName>'.$nameEsc.'</PictureName>'
            .'<PictureSet>Supersize</PictureSet>'
            .'</UploadSiteHostedPicturesRequest>';

        $headers = [
            'X-EBAY-API-COMPATIBILITY-LEVEL' => '967',
            'X-EBAY-API-DEV-NAME'             => $devId,
            'X-EBAY-API-APP-NAME'             => $appId,
            'X-EBAY-API-CERT-NAME'            => $certId,
            'X-EBAY-API-CALL-NAME'            => 'UploadSiteHostedPictures',
            'X-EBAY-API-SITEID'               => '0',
        ];

        // eBay EPS upload uses multipart/form-data with XML payload + image binary
        $boundary = '----eBayEPSBoundary'.uniqid();
        $body  = "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"XML Payload\"\r\n";
        $body .= "Content-Type: text/xml;charset=utf-8\r\n\r\n";
        $body .= $xmlPayload."\r\n";
        $body .= "--{$boundary}\r\n";
        $ext   = $contentType === 'image/png' ? 'png' : ($contentType === 'image/gif' ? 'gif' : 'jpg');
        $body .= "Content-Disposition: form-data; name=\"image\"; filename=\"{$nameEsc}.{$ext}\"\r\n";
        $body .= "Content-Type: {$contentType}\r\n\r\n";
        $body .= $imageData."\r\n";
        $body .= "--{$boundary}--\r\n";

        try {
            $response = Http::withoutVerifying()
                ->connectTimeout(15)
                ->timeout(60)
                ->withHeaders($headers + ['Content-Type' => "multipart/form-data; boundary={$boundary}"])
                ->withBody($body, "multipart/form-data; boundary={$boundary}")
                ->post($endpoint);

            $respBody = $response->body();
            libxml_use_internal_errors(true);
            $xmlResp = simplexml_load_string($respBody);
            if ($xmlResp === false) {
                return ['success' => false, 'message' => 'Invalid EPS upload response: '.substr($respBody, 0, 200)];
            }

            $arr  = json_decode(json_encode($xmlResp), true);
            $ack  = $arr['Ack'] ?? 'Failure';

            if ($ack === 'Success' || $ack === 'Warning') {
                $epsUrl = $arr['SiteHostedPictureDetails']['FullURL']
                    ?? $arr['SiteHostedPictureDetails']['BaseURL']
                    ?? null;
                if ($epsUrl) {
                    return ['success' => true, 'url' => (string) $epsUrl];
                }

                return ['success' => false, 'message' => 'EPS upload succeeded but no URL returned.'];
            }

            // Parse eBay error
            $errors = $arr['Errors'] ?? [];
            if (! is_array($errors)) {
                $errors = [];
            }
            if (isset($errors['ErrorCode']) || isset($errors['ShortMessage']) || isset($errors['LongMessage'])) {
                $errors = [$errors];
            }
            $firstErr = $errors[0] ?? [];
            $errMsg = (string) ($firstErr['LongMessage'] ?? $firstErr['ShortMessage'] ?? 'EPS upload failed');
            if (isset($firstErr['ErrorCode'])) {
                $errMsg = '[eBay #'.$firstErr['ErrorCode'].'] '.$errMsg;
            }

            return ['success' => false, 'message' => $errMsg];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'EPS upload exception: '.$e->getMessage()];
        }
    }

    /**
     * Replace gallery images via PictureDetails.
     * Images are first uploaded to eBay EPS so eBay hosts them — this avoids
     * error #37 "PictureURL invalid" that occurs when eBay can't reach external URLs.
     *
     * @param  list<string>  $pictureUrls
     * @return array{success: bool, message: string}
     */
    public static function reviseItemPictureUrls(
        string $endpoint,
        string $compatLevel,
        string $devId,
        string $appId,
        string $certId,
        string $siteId,
        string $authToken,
        string $itemId,
        array $pictureUrls,
    ): array {
        $urls = [];
        foreach ($pictureUrls as $u) {
            $t = trim((string) $u);
            if ($t !== '') {
                $urls[] = $t;
            }
        }
        $urls = array_slice(array_values(array_unique($urls)), 0, 12);
        if ($urls === []) {
            return ['success' => false, 'message' => 'At least one image URL is required.'];
        }

        // Upload each image to eBay EPS to get eBay-hosted URLs.
        // eBay error #37 occurs when external URLs are not accessible from eBay's servers.
        $epsUrls    = [];
        $epsErrors  = [];
        foreach ($urls as $i => $srcUrl) {
            $name   = 'img_'.($i + 1).'_'.substr(md5($srcUrl), 0, 8);
            $result = self::uploadImageToEps($endpoint, $devId, $appId, $certId, $authToken, $srcUrl, $name);
            if ($result['success'] ?? false) {
                $epsUrls[] = $result['url'];
            } else {
                $epsErrors[] = 'Image '.($i + 1).': '.($result['message'] ?? 'upload failed');
                Log::warning('eBay EPS upload failed', ['url' => $srcUrl, 'error' => $result['message'] ?? '']);
            }
        }

        if ($epsUrls === []) {
            return ['success' => false, 'message' => 'All image uploads to eBay EPS failed. '.implode(' | ', $epsErrors)];
        }

        // Build ReviseItem with EPS-hosted URLs
        $tokenEsc = htmlspecialchars($authToken, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $idEsc    = htmlspecialchars($itemId, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents"/>');
        $creds = $xml->addChild('RequesterCredentials');
        $creds->addChild('eBayAuthToken', $tokenEsc);
        $xml->addChild('ErrorLanguage', 'en_US');
        $xml->addChild('WarningLevel', 'High');
        $itemNode = $xml->addChild('Item');
        $itemNode->addChild('ItemID', $idEsc);
        $pd = $itemNode->addChild('PictureDetails');
        foreach ($epsUrls as $u) {
            $pd->addChild('PictureURL', self::escapeXmlElementText($u));
        }

        $xmlBody = $xml->asXML();
        if ($xmlBody === false) {
            return ['success' => false, 'message' => 'Failed to build ReviseItem XML for pictures.'];
        }

        $result = self::postReviseItemXml($endpoint, $compatLevel, $devId, $appId, $certId, $siteId, $itemId, $xmlBody, 'pictures');

        // Append any partial EPS failures to the success message
        if (($result['success'] ?? false) && $epsErrors) {
            $result['message'] = ($result['message'] ?? 'eBay images updated.')
                .' Note: '.count($epsErrors).' of '.count($urls).' image(s) could not be uploaded: '.implode(' | ', $epsErrors);
        }

        return $result;
    }

    /**
     * Resolve a storage URL to a local absolute file path for local-file reads.
     * Works for both http://localhost/storage/... and https://domain.com/storage/... patterns.
     */
    private static function resolveLocalStoragePath(string $url): ?string
    {
        // Try matching /storage/ path component
        if (preg_match('#/storage/(.+)$#', $url, $m)) {
            $rel  = ltrim(str_replace('\\', '/', rawurldecode($m[1])), '/');
            $path = storage_path('app/public/'.$rel);
            if (file_exists($path)) {
                return $path;
            }
            // Fallback: public_path
            $path2 = public_path('storage/'.$rel);
            if (file_exists($path2)) {
                return $path2;
            }
        }

        return null;
    }

    /**
     * Alias for image updates used by Image Master services.
     *
     * @param  list<string>  $images
     * @return array{success: bool, message: string}
     */
    public static function reviseItemImages(
        string $endpoint,
        string $compatLevel,
        string $devId,
        string $appId,
        string $certId,
        string $siteId,
        string $authToken,
        string $itemId,
        array $images,
    ): array {
        return self::reviseItemPictureUrls(
            $endpoint,
            $compatLevel,
            $devId,
            $appId,
            $certId,
            $siteId,
            $authToken,
            $itemId,
            $images
        );
    }

    /**
     * Extract gallery PictureURL list from GetItem JSON-decoded response.
     *
     * @param  array<string, mixed>  $getItemResponse
     * @return list<string>
     */
    public static function extractPictureUrlsFromGetItem(array $getItemResponse): array
    {
        $item = $getItemResponse['Item'] ?? null;
        if (! is_array($item)) {
            return [];
        }
        $pd = $item['PictureDetails'] ?? null;
        if (! is_array($pd)) {
            return [];
        }
        $raw = $pd['PictureURL'] ?? null;
        if ($raw === null || $raw === '') {
            return [];
        }
        if (is_string($raw)) {
            $t = trim($raw);

            return $t === '' ? [] : [$t];
        }
        $out = [];
        foreach ($raw as $u) {
            if (is_string($u)) {
                $t = trim($u);
                if ($t !== '') {
                    $out[] = $t;
                }
            } elseif (is_array($u)) {
                $s = trim((string) reset($u));
                if ($s !== '') {
                    $out[] = $s;
                }
            }
        }

        return array_slice(array_values(array_unique($out)), 0, 12);
    }

    /**
     * Update Bullet Point 1–5 via Item Specifics (does not change listing Description HTML).
     * Merges with existing ItemSpecifics from GetItem so other aspects are preserved.
     *
     * @param  array<string, mixed>  $getItemResponse  json_decode(json_encode(GetItem XML), true)
     * @return array{success: bool, message: string}
     */
    public static function reviseBulletPointsViaItemSpecifics(
        string $endpoint,
        string $compatLevel,
        string $devId,
        string $appId,
        string $certId,
        string $siteId,
        string $authToken,
        array $getItemResponse,
        string $bulletPointsPlain,
    ): array {
        $item = $getItemResponse['Item'] ?? null;
        if (! is_array($item)) {
            return ['success' => false, 'message' => 'GetItem response missing Item.'];
        }
        $itemIdRaw = $item['ItemID'] ?? '';
        if (is_array($itemIdRaw)) {
            $itemIdRaw = reset($itemIdRaw);
        }
        $itemId = trim((string) $itemIdRaw);
        if ($itemId === '') {
            return ['success' => false, 'message' => 'GetItem response missing ItemID.'];
        }

        $aspectNames = config('services.ebay.bullet_aspect_names');
        if (! is_array($aspectNames) || $aspectNames === []) {
            $aspectNames = ['Bullet Point 1', 'Bullet Point 2', 'Bullet Point 3', 'Bullet Point 4', 'Bullet Point 5'];
        }

        $merged = self::buildMergedItemSpecificsForBulletUpdate($item, $bulletPointsPlain, $aspectNames);

        $tokenEsc = htmlspecialchars($authToken, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $idEsc = htmlspecialchars($itemId, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents"/>');
        $creds = $xml->addChild('RequesterCredentials');
        $creds->addChild('eBayAuthToken', $tokenEsc);
        $xml->addChild('ErrorLanguage', 'en_US');
        $xml->addChild('WarningLevel', 'High');
        $itemNode = $xml->addChild('Item');
        $itemNode->addChild('ItemID', $idEsc);
        $spec = $itemNode->addChild('ItemSpecifics');
        foreach ($merged as $row) {
            $nvl = $spec->addChild('NameValueList');
            $nvl->addChild('Name', self::escapeXmlElementText($row['name']));
            foreach ($row['values'] as $v) {
                $nvl->addChild('Value', self::escapeXmlElementText($v));
            }
        }

        $xmlBody = $xml->asXML();
        if ($xmlBody === false) {
            return ['success' => false, 'message' => 'Failed to build ReviseItem XML for Item Specifics.'];
        }

        return self::postReviseItemXml($endpoint, $compatLevel, $devId, $appId, $certId, $siteId, $itemId, $xmlBody, 'item specifics (bullet points)');
    }

    /**
     * Merge GetItem ItemSpecifics (plus any other NameValueList nodes under Item, excluding Variations),
     * preserve non-bullet specifics, then apply Bullet Point 1–5. Brand is always set to the configured
     * value (default "5 Core") so ReviseItem sends an explicit custom Brand value.
     * MPN is always set from the listing seller SKU (GetItem), with optional EBAY_MPN_FALLBACK_VALUE only if SKU is absent — never from Brand config.
     *
     * @param  array<string, mixed>  $item
     * @param  list<string>  $aspectNames
     * @return array<int, array{name: string, values: list<string>}>
     */
    private static function buildMergedItemSpecificsForBulletUpdate(array $item, string $bulletPointsPlain, array $aspectNames): array
    {
        $fromItemSpecifics = self::normalizeItemSpecificsNameValueLists($item['ItemSpecifics'] ?? null);
        $itemNoVariations = $item;
        unset($itemNoVariations['Variations']);
        $rawCollected = [];
        self::walkCollectNameValueListRows($itemNoVariations, $rawCollected);
        $fromRecursive = self::normalizeRawNameValueListRows($rawCollected);
        $existing = self::mergeItemSpecificRowsPreferFirst($fromItemSpecifics, $fromRecursive);
        $existing = self::deduplicateItemSpecificsByName($existing);
        $lines = self::splitBulletLinesFive($bulletPointsPlain);

        $merged = self::mergeBulletAspectsIntoItemSpecifics($existing, $lines, $aspectNames);
        $merged = self::deduplicateItemSpecificsByName($merged);
        $merged = self::applyRequiredTypeFallback($merged);
        $merged = self::applyForcedBrandForBulletUpdate($merged);
        $merged = self::applyForcedMpnFromSku($item, $merged);

        return self::deduplicateItemSpecificsByName($merged);
    }

    /**
     * Some categories require Type whenever any ItemSpecifics are revised.
     *
     * @param  array<int, array{name: string, values: list<string>}>  $rows
     * @return array<int, array{name: string, values: list<string>}>
     */
    private static function applyRequiredTypeFallback(array $rows): array
    {
        foreach ($rows as $row) {
            if (strtolower(trim($row['name'])) === 'type') {
                return $rows;
            }
        }

        $typeValue = config('services.ebay.type_fallback_value');
        $typeValue = is_string($typeValue) ? trim($typeValue) : '';
        if ($typeValue === '') {
            return $rows;
        }

        $rows[] = ['name' => 'Type', 'values' => [$typeValue]];

        return $rows;
    }

    /**
     * Remove any Brand row and always send Brand as a single explicit value (custom value for ReviseItem).
     *
     * @param  array<int, array{name: string, values: list<string>}>  $rows
     * @return array<int, array{name: string, values: list<string>}>
     */
    private static function applyForcedBrandForBulletUpdate(array $rows): array
    {
        $brandValue = config('services.ebay.brand_fallback_value');
        $brandValue = is_string($brandValue) ? trim($brandValue) : '';
        if ($brandValue === '') {
            $brandValue = '5 Core';
        }
        $filtered = [];
        foreach ($rows as $r) {
            if (strtolower(trim($r['name'])) === 'brand') {
                continue;
            }
            $filtered[] = $r;
        }
        $filtered[] = ['name' => 'Brand', 'values' => [$brandValue]];

        return $filtered;
    }

    /**
     * Replace any MPN-like aspect with a single MPN row: seller SKU from GetItem when present,
     * otherwise services.ebay.mpn_fallback_value (EBAY_MPN_FALLBACK_VALUE). Does not use Brand config.
     *
     * @param  array<string, mixed>  $item  GetItem Item node
     * @param  array<int, array{name: string, values: list<string>}>  $rows
     * @return array<int, array{name: string, values: list<string>}>
     */
    private static function applyForcedMpnFromSku(array $item, array $rows): array
    {
        $mpnValue = self::extractSkuFromItem($item);
        if ($mpnValue === null || $mpnValue === '') {
            $fallback = config('services.ebay.mpn_fallback_value');
            $mpnValue = is_string($fallback) ? trim($fallback) : '';
        }
        if ($mpnValue === '') {
            return $rows;
        }
        $filtered = [];
        foreach ($rows as $r) {
            if (self::isMpnLikeAspectName($r['name'])) {
                continue;
            }
            $filtered[] = $r;
        }
        $filtered[] = ['name' => 'MPN', 'values' => [$mpnValue]];

        return $filtered;
    }

    /**
     * Seller reference from GetItem: Item.SKU, else CustomLabel, else first Variation SKU.
     */
    private static function extractSkuFromItem(array $item): ?string
    {
        foreach (['SKU', 'CustomLabel'] as $key) {
            if (! isset($item[$key])) {
                continue;
            }
            $v = $item[$key];
            $s = trim((string) (is_array($v) ? reset($v) : $v));
            if ($s !== '') {
                return $s;
            }
        }
        $vars = $item['Variations']['Variation'] ?? null;
        if ($vars === null) {
            return null;
        }
        if (isset($vars['SKU'])) {
            $v = $vars['SKU'];
            $s = trim((string) (is_array($v) ? reset($v) : $v));
            if ($s !== '') {
                return $s;
            }
        }
        if (is_array($vars)) {
            foreach ($vars as $variation) {
                if (! is_array($variation) || empty($variation['SKU'])) {
                    continue;
                }
                $v = $variation['SKU'];
                $s = trim((string) (is_array($v) ? reset($v) : $v));
                if ($s !== '') {
                    return $s;
                }
            }
        }

        return null;
    }

    private static function isMpnLikeAspectName(string $name): bool
    {
        $n = strtolower(trim($name));

        return $n === 'mpn' || $n === 'manufacturer part number' || str_contains($n, 'mpn');
    }

    /**
     * @param  array<int, array{Name?: mixed, Value?: mixed}>  $rawRows
     * @return array<int, array{name: string, values: list<string>}>
     */
    private static function normalizeRawNameValueListRows(array $rawRows): array
    {
        $out = [];
        foreach ($rawRows as $row) {
            if (! is_array($row) || ! isset($row['Name'])) {
                continue;
            }
            $name = trim((string) $row['Name']);
            if ($name === '') {
                continue;
            }
            $clean = self::flattenEbayItemSpecificValues($row['Value'] ?? null);
            if ($clean === []) {
                continue;
            }
            $out[] = ['name' => $name, 'values' => $clean];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<int, array<string, mixed>>  $out
     */
    private static function walkCollectNameValueListRows(array $node, array &$out): void
    {
        if (isset($node['NameValueList'])) {
            $nvl = $node['NameValueList'];
            if (isset($nvl['Name'])) {
                $nvl = [$nvl];
            }
            if (is_array($nvl)) {
                foreach ($nvl as $row) {
                    if (is_array($row) && isset($row['Name'])) {
                        $out[] = $row;
                    }
                }
            }
        }
        foreach ($node as $v) {
            if (is_array($v)) {
                self::walkCollectNameValueListRows($v, $out);
            }
        }
    }

    /**
     * @param  array<int, array{name: string, values: list<string>}>  $primary
     * @param  array<int, array{name: string, values: list<string>}>  $secondary
     * @return array<int, array{name: string, values: list<string>}>
     */
    private static function mergeItemSpecificRowsPreferFirst(array $primary, array $secondary): array
    {
        $seen = [];
        foreach ($primary as $r) {
            $seen[strtolower(trim($r['name']))] = true;
        }
        $out = $primary;
        foreach ($secondary as $r) {
            $k = strtolower(trim($r['name']));
            if (! isset($seen[$k])) {
                $out[] = $r;
                $seen[$k] = true;
            }
        }

        return $out;
    }

    /**
     * Flatten GetItem ItemSpecifics NameValueList (handles nested Value arrays from XML→JSON).
     *
     * @return array<int, array{name: string, values: list<string>}>
     */
    public static function normalizeItemSpecificsNameValueLists(mixed $itemSpecifics): array
    {
        if (! is_array($itemSpecifics)) {
            return [];
        }
        if ($itemSpecifics === []) {
            return [];
        }
        $nvl = $itemSpecifics['NameValueList'] ?? [];
        if ($nvl === []) {
            return [];
        }
        if (isset($nvl['Name'])) {
            $nvl = [$nvl];
        }
        $out = [];
        foreach ($nvl as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['Name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $clean = self::flattenEbayItemSpecificValues($row['Value'] ?? null);
            if ($clean === []) {
                if (self::isPreservedItemSpecificName($name)) {
                    Log::warning('eBay ItemSpecific has empty Value for preserved aspect (check GetItem XML)', ['name' => $name]);
                }

                continue;
            }
            $out[] = ['name' => $name, 'values' => $clean];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private static function flattenEbayItemSpecificValues(mixed $values): array
    {
        if ($values === null) {
            return [];
        }
        if (! is_array($values)) {
            $s = trim((string) $values);

            return $s === '' ? [] : [$s];
        }
        $flat = [];
        foreach ($values as $v) {
            if (is_array($v)) {
                $flat = array_merge($flat, self::flattenEbayItemSpecificValues($v));
            } else {
                $flat[] = (string) $v;
            }
        }
        $clean = [];
        foreach ($flat as $raw) {
            $t = trim((string) $raw);
            if ($t !== '') {
                $clean[] = $t;
            } elseif ($raw === '0' || $raw === 0) {
                $clean[] = '0';
            }
        }

        return $clean;
    }

    private static function isPreservedItemSpecificName(string $name): bool
    {
        $n = strtolower(trim($name));
        $list = config('services.ebay.preserve_item_specific_names');
        if (is_array($list)) {
            foreach ($list as $p) {
                if ($n === strtolower(trim((string) $p))) {
                    return true;
                }
            }
        }

        return preg_match('/^(mpn|upc|ean|gtin|isbn|brand|part number|manufacturer part number)$/i', $name) === 1;
    }

    /**
     * @param  array<int, array{name: string, values: list<string>}>  $rows
     * @return array<int, array{name: string, values: list<string>}>
     */
    private static function deduplicateItemSpecificsByName(array $rows): array
    {
        $byKey = [];
        foreach ($rows as $row) {
            $key = strtolower(trim($row['name']));
            if (! isset($byKey[$key])) {
                $byKey[$key] = $row;

                continue;
            }
            $merged = array_merge($byKey[$key]['values'], $row['values']);
            $byKey[$key]['values'] = array_values(array_unique($merged));
        }

        return array_values($byKey);
    }

    /**
     * @return list<string> Five slots (may be empty strings)
     */
    public static function splitBulletLinesFive(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $lines = is_array($lines) ? $lines : [];
        $out = [];
        foreach ($lines as $i => $line) {
            if ($i >= 5) {
                break;
            }
            $out[] = trim((string) $line);
        }
        while (count($out) < 5) {
            $out[] = '';
        }

        return array_slice($out, 0, 5);
    }

    /**
     * @param  array<int, array{name: string, values: list<string>}>  $existing
     * @param  list<string>  $fiveLines
     * @param  list<string>  $aspectNames
     * @return array<int, array{name: string, values: list<string>}>
     */
    public static function mergeBulletAspectsIntoItemSpecifics(array $existing, array $fiveLines, array $aspectNames): array
    {
        $bulletNamesLower = array_map(fn ($n) => strtolower(trim($n)), $aspectNames);
        $filtered = [];
        foreach ($existing as $row) {
            $n = strtolower(trim($row['name']));
            if (in_array($n, $bulletNamesLower, true)) {
                continue;
            }
            if (preg_match('/^bullet\s+point\s*\d+$/i', $row['name']) === 1) {
                continue;
            }
            $filtered[] = $row;
        }
        foreach ($fiveLines as $i => $line) {
            if ($line === '' || ! isset($aspectNames[$i])) {
                continue;
            }
            $filtered[] = ['name' => $aspectNames[$i], 'values' => [self::limitItemSpecificBulletValue($line)]];
        }

        return $filtered;
    }

    private static function limitItemSpecificBulletValue(string $line): string
    {
        $max = (int) config('services.ebay.item_specific_bullet_max_length', 65);
        if ($max <= 0 || mb_strlen($line) <= $max) {
            return $line;
        }

        $cut = trim(mb_substr($line, 0, $max));
        $lastSpace = mb_strrpos($cut, ' ');
        if ($lastSpace !== false && $lastSpace >= (int) floor($max * 0.65)) {
            $cut = trim(mb_substr($cut, 0, $lastSpace));
        }

        return rtrim($cut, " \t\n\r\0\x0B.,;:-");
    }

    public static function bulletsToDescriptionHtml(string $bulletPoints): string
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($bulletPoints));
        $html = '<ul>';
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $line = preg_replace('/^[-*•\d.\)\s]+/u', '', $line);
            $html .= '<li>'.htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</li>';
        }
        $html .= '</ul>';

        return $html;
    }

    public static function bulletsToOrderedDescriptionHtml(string $bulletPoints): string
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($bulletPoints));
        $html = '<ol>';
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $html .= '<li>'.htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</li>';
        }
        $html .= '</ol>';

        return $html;
    }

    public static function replaceFirstDescriptionBulletList(string $currentDescriptionHtml, string $bulletPoints, ?array &$meta = null): string
    {
        $meta = [
            'strategy' => 'none',
            'offset' => null,
            'replaced_length' => 0,
            'description_offset' => null,
        ];
        $replacement = self::bulletsToOrderedDescriptionHtml($bulletPoints);
        $body = trim($currentDescriptionHtml);
        if ($body === '') {
            $meta['strategy'] = 'empty_description';
            return $replacement;
        }

        $updatedMarkedBlock = self::replaceMarkedShopifyBulletBlock($body, $replacement, $meta);
        if ($updatedMarkedBlock !== null) {
            return self::removeAdditionalTopListsBeforeDescription($updatedMarkedBlock, (int) ($meta['offset'] ?? 0) + strlen($replacement), $meta);
        }

        $updatedHeadingSection = self::replaceLeadingHeadingBulletSection($body, $replacement, $meta);
        if ($updatedHeadingSection !== null) {
            return self::removeAdditionalTopListsBeforeDescription($updatedHeadingSection, (int) ($meta['offset'] ?? 0) + strlen($replacement), $meta);
        }

        $updatedBracketParagraphs = self::replaceLeadingBracketBulletParagraphs($body, $replacement, $meta);
        if ($updatedBracketParagraphs !== null) {
            return self::removeAdditionalTopListsBeforeDescription($updatedBracketParagraphs, (int) ($meta['offset'] ?? 0) + strlen($replacement), $meta);
        }

        $updatedStrongLabelParagraphs = self::replaceLeadingStrongLabelBulletParagraphs($body, $replacement, $meta);
        if ($updatedStrongLabelParagraphs !== null) {
            return self::removeAdditionalTopListsBeforeDescription($updatedStrongLabelParagraphs, (int) ($meta['offset'] ?? 0) + strlen($replacement), $meta);
        }

        $updatedHighlightedParagraphs = self::replaceLeadingHighlightedFeatureParagraphs($body, $replacement, $meta);
        if ($updatedHighlightedParagraphs !== null) {
            return self::removeAdditionalTopListsBeforeDescription($updatedHighlightedParagraphs, (int) ($meta['offset'] ?? 0) + strlen($replacement), $meta);
        }

        $updatedTopParagraph = self::replaceLeadingBoldLabelBulletParagraph($body, $replacement, $meta);
        if ($updatedTopParagraph !== null) {
            return self::removeAdditionalTopListsBeforeDescription($updatedTopParagraph, (int) ($meta['offset'] ?? 0) + strlen($replacement), $meta);
        }

        $updatedSymbolParagraphs = self::replaceLeadingSymbolBulletParagraphs($body, $replacement, $meta);
        if ($updatedSymbolParagraphs !== null) {
            return self::removeAdditionalTopListsBeforeDescription($updatedSymbolParagraphs, (int) ($meta['offset'] ?? 0) + strlen($replacement), $meta);
        }

        $descriptionOffset = self::findProductDescriptionSectionOffset($body);
        $meta['description_offset'] = $descriptionOffset;

        foreach (['/<ol\b[^>]*>.*?<\/ol>/is', '/<ul\b[^>]*>.*?<\/ul>/is'] as $pattern) {
            if (! preg_match($pattern, $body, $match, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            $listOffset = (int) $match[0][1];
            if ($descriptionOffset !== null && $listOffset > $descriptionOffset) {
                continue;
            }

            $meta['strategy'] = 'top_list_before_description';
            $meta['offset'] = $listOffset;
            $meta['replaced_length'] = strlen((string) $match[0][0]);

            return substr_replace($body, $replacement, $listOffset, strlen((string) $match[0][0]));
        }

        if ($descriptionOffset !== null) {
            $meta['strategy'] = 'insert_before_description';
            $meta['offset'] = $descriptionOffset;

            return substr_replace($body, $replacement."\n", $descriptionOffset, 0);
        }

        $meta['strategy'] = 'prepend_no_description_heading';
        $meta['offset'] = 0;

        return $replacement."\n".$body;
    }

    private static function replaceMarkedShopifyBulletBlock(string $body, string $replacement, ?array &$meta = null): ?string
    {
        $patterns = [
            '/<!--\s*bullet-points-master:start\s*-->.*?<!--\s*bullet-points-master:end\s*-->/is',
            '/<div\b(?=[^>]*\bdata-bullet-points-master\s*=\s*(["\'])1\1)[^>]*>[\s\S]*?<\/div>\s*/is',
            '/<div\b[^>]*class=(["\'])(?=[^"\']*\baplus-3p-center-content\b)[^"\']*\1[^>]*>(?=[\s\S]*?About\s+Item:)(?=[\s\S]*?【)[\s\S]*?<\/div>\s*/is',
        ];

        foreach ($patterns as $pattern) {
            if (! preg_match($pattern, $body, $match, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            $offset = (int) $match[0][1];
            if (! self::isBeforeProductDescription($body, $offset)) {
                continue;
            }

            if (is_array($meta)) {
                $meta['strategy'] = 'marked_shopify_bullet_block';
                $meta['offset'] = $offset;
                $meta['replaced_length'] = strlen((string) $match[0][0]);
            }

            return substr_replace($body, $replacement, $offset, strlen((string) $match[0][0]));
        }

        return null;
    }

    private static function replaceLeadingHeadingBulletSection(string $body, string $replacement, ?array &$meta = null): ?string
    {
        if (! preg_match_all('/<(?:h[1-6]|p)\b[^>]*>.*?<\/(?:h[1-6]|p)>/is', $body, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $blocks = $matches[0];
        foreach ($blocks as $i => [$headingHtml, $headingOffset]) {
            $headingOffset = (int) $headingOffset;
            if (! self::isBeforeProductDescription($body, $headingOffset)) {
                return null;
            }
            if (! self::isBulletSectionHeadingParagraph((string) $headingHtml)) {
                continue;
            }

            $headingEnd = $headingOffset + strlen((string) $headingHtml);
            $nextBlock = $blocks[$i + 1] ?? null;
            if ($nextBlock === null) {
                return null;
            }

            $between = substr($body, $headingEnd, (int) $nextBlock[1] - $headingEnd);
            if (preg_match('/<(?:img|table)\b/i', $between)) {
                return null;
            }

            $listMatch = null;
            if (preg_match('/\G\s*<(?:ul|ol)\b[^>]*>.*?<\/(?:ul|ol)>/is', substr($body, $headingEnd), $listMatch, PREG_OFFSET_CAPTURE)) {
                $replaceOffset = $headingOffset;
                $replaceLength = strlen((string) $headingHtml) + (int) $listMatch[0][1] + strlen((string) $listMatch[0][0]);
                if (is_array($meta)) {
                    $meta['strategy'] = 'heading_list_section';
                    $meta['offset'] = $replaceOffset;
                    $meta['replaced_length'] = $replaceLength;
                }

                return substr_replace($body, $replacement, $replaceOffset, $replaceLength);
            }

            $firstBulletOffset = null;
            $lastBulletEnd = null;
            $bulletCount = 0;
            $previousEnd = $headingEnd;

            for ($j = $i + 1; $j < count($blocks); $j++) {
                [$blockHtml, $blockOffset] = $blocks[$j];
                $blockOffset = (int) $blockOffset;
                $between = substr($body, $previousEnd, $blockOffset - $previousEnd);
                if (preg_match('/<(?:div|h[1-6]|img|table|ol|ul)\b/i', $between)) {
                    break;
                }
                if (
                    ! self::looksLikeBracketBulletParagraph((string) $blockHtml)
                    && ! self::looksLikeStrongLabelBulletParagraph((string) $blockHtml)
                    && ! self::looksLikeSymbolBulletParagraph((string) $blockHtml)
                ) {
                    break;
                }

                $firstBulletOffset ??= $blockOffset;
                $lastBulletEnd = $blockOffset + strlen((string) $blockHtml);
                $bulletCount++;
                $previousEnd = $lastBulletEnd;
            }

            if ($bulletCount >= 2 && $firstBulletOffset !== null && $lastBulletEnd !== null) {
                if (is_array($meta)) {
                    $meta['strategy'] = 'heading_bullet_paragraphs';
                    $meta['offset'] = $headingOffset;
                    $meta['replaced_length'] = $lastBulletEnd - $headingOffset;
                    $meta['bullet_paragraph_count'] = $bulletCount;
                }

                return substr_replace($body, $replacement, $headingOffset, $lastBulletEnd - $headingOffset);
            }
        }

        return null;
    }

    private static function removeAdditionalTopListsBeforeDescription(string $body, int $afterOffset, ?array &$meta = null): string
    {
        $descriptionOffset = self::findProductDescriptionSectionOffset($body);
        if ($descriptionOffset === null) {
            return $body;
        }

        $removedCount = 0;
        $removedLength = 0;
        foreach (['/<ol\b[^>]*>.*?<\/ol>/is', '/<ul\b[^>]*>.*?<\/ul>/is'] as $pattern) {
            while (preg_match($pattern, $body, $match, PREG_OFFSET_CAPTURE, $afterOffset)) {
                $listHtml = (string) $match[0][0];
                $listOffset = (int) $match[0][1];
                $descriptionOffset = self::findProductDescriptionSectionOffset($body);
                if ($descriptionOffset === null || $listOffset > $descriptionOffset) {
                    break;
                }

                $body = substr_replace($body, '', $listOffset, strlen($listHtml));
                $removedCount++;
                $removedLength += strlen($listHtml);
            }
        }

        if ($removedCount > 0 && is_array($meta)) {
            $meta['removed_extra_top_list_count'] = $removedCount;
            $meta['removed_extra_top_list_length'] = $removedLength;
        }

        return $body;
    }

    private static function replaceLeadingBracketBulletParagraphs(string $body, string $replacement, ?array &$meta = null): ?string
    {
        if (! preg_match_all('/<p\b[^>]*>.*?<\/p>/is', $body, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $paragraphs = $matches[0];
        for ($i = 0; $i < count($paragraphs); $i++) {
            [$paragraphHtml, $offset] = $paragraphs[$i];
            $offset = (int) $offset;
            $beforeParagraph = substr($body, 0, $offset);
            if (preg_match('/<(?:h[1-6]|img|table|ol|ul)\b/i', $beforeParagraph)) {
                return null;
            }
            if (self::isBulletSectionHeadingParagraph((string) $paragraphHtml)) {
                continue;
            }
            if (self::looksLikeProductDescriptionHeading(self::normalizedParagraphText((string) $paragraphHtml))) {
                return null;
            }

            $labelCount = self::countBracketLabels((string) $paragraphHtml);
            if ($labelCount >= 3) {
                if (is_array($meta)) {
                    $meta['strategy'] = 'bracket_bullet_paragraph';
                    $meta['offset'] = $offset;
                    $meta['replaced_length'] = strlen((string) $paragraphHtml);
                    $meta['bracket_label_count'] = $labelCount;
                }

                return substr_replace($body, $replacement, $offset, strlen((string) $paragraphHtml));
            }

            if ($labelCount < 1 || ! self::looksLikeBracketBulletParagraph((string) $paragraphHtml)) {
                continue;
            }

            $firstBulletOffset = $offset;
            $lastBulletEnd = $offset + strlen((string) $paragraphHtml);
            $bulletCount = 1;
            $previousEnd = $lastBulletEnd;

            for ($j = $i + 1; $j < count($paragraphs); $j++) {
                [$nextParagraphHtml, $nextOffset] = $paragraphs[$j];
                $nextOffset = (int) $nextOffset;
                $between = substr($body, $previousEnd, $nextOffset - $previousEnd);
                if (preg_match('/<(?:div|h[1-6]|img|table|ol|ul)\b/i', $between)) {
                    break;
                }
                if (! self::looksLikeBracketBulletParagraph((string) $nextParagraphHtml)) {
                    break;
                }

                $lastBulletEnd = $nextOffset + strlen((string) $nextParagraphHtml);
                $bulletCount++;
                $previousEnd = $lastBulletEnd;
            }

            if ($bulletCount >= 3) {
                if (is_array($meta)) {
                    $meta['strategy'] = 'bracket_bullet_paragraphs';
                    $meta['offset'] = $firstBulletOffset;
                    $meta['replaced_length'] = $lastBulletEnd - $firstBulletOffset;
                    $meta['bullet_paragraph_count'] = $bulletCount;
                }

                return substr_replace($body, $replacement, $firstBulletOffset, $lastBulletEnd - $firstBulletOffset);
            }
        }

        return null;
    }

    private static function replaceLeadingStrongLabelBulletParagraphs(string $body, string $replacement, ?array &$meta = null): ?string
    {
        if (! preg_match_all('/<p\b[^>]*>.*?<\/p>/is', $body, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $paragraphs = $matches[0];
        $firstBulletOffset = null;
        $lastBulletEnd = null;
        $bulletCount = 0;
        $previousEnd = null;

        foreach ($paragraphs as [$paragraphHtml, $offset]) {
            $offset = (int) $offset;
            $beforeParagraph = substr($body, 0, $offset);
            if ($firstBulletOffset === null && preg_match('/<(?:h[1-6]|img|table|ol|ul|div)\b/i', $beforeParagraph)) {
                return null;
            }

            if ($previousEnd !== null) {
                $between = substr($body, $previousEnd, $offset - $previousEnd);
                if (preg_match('/<(?:div|h[1-6]|img|table|ol|ul)\b/i', $between)) {
                    break;
                }
            }

            if ($firstBulletOffset === null && self::isBulletSectionHeadingParagraph((string) $paragraphHtml)) {
                $previousEnd = $offset + strlen((string) $paragraphHtml);
                continue;
            }

            if (! self::looksLikeStrongLabelBulletParagraph((string) $paragraphHtml)) {
                break;
            }

            $firstBulletOffset ??= $offset;
            $lastBulletEnd = $offset + strlen((string) $paragraphHtml);
            $bulletCount++;
            $previousEnd = $lastBulletEnd;
        }

        if ($bulletCount < 3 || $firstBulletOffset === null || $lastBulletEnd === null) {
            return null;
        }

        if (is_array($meta)) {
            $meta['strategy'] = 'strong_label_paragraphs';
            $meta['offset'] = $firstBulletOffset;
            $meta['replaced_length'] = $lastBulletEnd - $firstBulletOffset;
            $meta['bullet_paragraph_count'] = $bulletCount;
        }

        return substr_replace($body, $replacement, $firstBulletOffset, $lastBulletEnd - $firstBulletOffset);
    }

    private static function replaceLeadingSymbolBulletParagraphs(string $body, string $replacement, ?array &$meta = null): ?string
    {
        if (! preg_match_all('/<p\b[^>]*>.*?<\/p>/is', $body, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $firstBulletOffset = null;
        $lastBulletEnd = null;
        $bulletCount = 0;
        $previousEnd = null;

        foreach ($matches[0] as [$paragraphHtml, $offset]) {
            $offset = (int) $offset;
            if ($firstBulletOffset === null && preg_match('/<(?:h[1-6]|img|table|ol|ul|div)\b/i', substr($body, 0, $offset))) {
                return null;
            }
            if ($previousEnd !== null) {
                $between = substr($body, $previousEnd, $offset - $previousEnd);
                if (preg_match('/<(?:div|h[1-6]|img|table|ol|ul)\b/i', $between)) {
                    break;
                }
            }
            if (! self::looksLikeSymbolBulletParagraph((string) $paragraphHtml)) {
                break;
            }

            $firstBulletOffset ??= $offset;
            $lastBulletEnd = $offset + strlen((string) $paragraphHtml);
            $bulletCount++;
            $previousEnd = $lastBulletEnd;
        }

        if ($bulletCount < 2 || $firstBulletOffset === null || $lastBulletEnd === null) {
            return null;
        }

        if (is_array($meta)) {
            $meta['strategy'] = 'symbol_bullet_paragraphs';
            $meta['offset'] = $firstBulletOffset;
            $meta['replaced_length'] = $lastBulletEnd - $firstBulletOffset;
            $meta['bullet_paragraph_count'] = $bulletCount;
        }

        return substr_replace($body, $replacement, $firstBulletOffset, $lastBulletEnd - $firstBulletOffset);
    }

    private static function replaceLeadingHighlightedFeatureParagraphs(string $body, string $replacement, ?array &$meta = null): ?string
    {
        if (! preg_match_all('/<p\b[^>]*>.*?<\/p>/is', $body, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $paragraphs = $matches[0];
        $headingIndex = null;
        foreach ($paragraphs as $i => [$paragraphHtml, $offset]) {
            $beforeParagraph = substr($body, 0, (int) $offset);
            if (preg_match('/<(?:img|table|ol|ul)\b/i', $beforeParagraph)) {
                return null;
            }

            if (self::isBulletSectionHeadingParagraph((string) $paragraphHtml)) {
                $headingIndex = $i;
                break;
            }
        }

        if ($headingIndex === null) {
            return null;
        }

        $firstBulletOffset = null;
        $lastBulletEnd = null;
        $bulletCount = 0;
        $previousEnd = (int) $paragraphs[$headingIndex][1] + strlen((string) $paragraphs[$headingIndex][0]);

        for ($i = $headingIndex + 1; $i < count($paragraphs); $i++) {
            [$paragraphHtml, $offset] = $paragraphs[$i];
            $offset = (int) $offset;
            $between = substr($body, $previousEnd, $offset - $previousEnd);
            if (preg_match('/<(?:div|h[1-6]|img|table|ol|ul)\b/i', $between)) {
                break;
            }

            if (! self::looksLikeSingleHighlightedFeatureParagraph((string) $paragraphHtml)) {
                break;
            }

            $firstBulletOffset ??= $offset;
            $lastBulletEnd = $offset + strlen((string) $paragraphHtml);
            $bulletCount++;
            $previousEnd = $lastBulletEnd;
        }

        if ($bulletCount < 3 || $firstBulletOffset === null || $lastBulletEnd === null) {
            return null;
        }

        if (is_array($meta)) {
            $meta['strategy'] = 'highlighted_feature_paragraphs';
            $meta['offset'] = $firstBulletOffset;
            $meta['replaced_length'] = $lastBulletEnd - $firstBulletOffset;
            $meta['bullet_paragraph_count'] = $bulletCount;
        }

        return substr_replace($body, $replacement, $firstBulletOffset, $lastBulletEnd - $firstBulletOffset);
    }

    private static function isBulletSectionHeadingParagraph(string $paragraphHtml): bool
    {
        $text = self::normalizedParagraphText($paragraphHtml);

        return in_array($text, [
            'highlighted features',
            'key features',
            'about item',
            'product highlights',
            'main features',
            'bullet points',
        ], true);
    }

    private static function looksLikeSingleHighlightedFeatureParagraph(string $paragraphHtml): bool
    {
        if (stripos($paragraphHtml, '<strong') === false) {
            return false;
        }

        $text = self::normalizedParagraphText($paragraphHtml);
        if ($text === '' || self::isBulletSectionHeadingParagraph($paragraphHtml)) {
            return false;
        }

        preg_match('/<strong\b[^>]*>(.*?)<\/strong>/is', $paragraphHtml, $strongMatch);
        $label = self::normalizedParagraphText((string) ($strongMatch[1] ?? ''));
        if ($label === '') {
            return false;
        }

        if (preg_match('/[【\[][^】\]]+[】\]]/u', $label) === 1) {
            return true;
        }

        return strlen($text) > strlen($label) + 20;
    }

    private static function looksLikeStrongLabelBulletParagraph(string $paragraphHtml): bool
    {
        if (stripos($paragraphHtml, '<strong') === false) {
            return false;
        }

        $text = self::normalizedParagraphText($paragraphHtml);
        if ($text === '' || self::isBulletSectionHeadingParagraph($paragraphHtml) || self::looksLikeProductDescriptionHeading($text)) {
            return false;
        }

        preg_match_all('/<strong\b[^>]*>(.*?)<\/strong>/is', $paragraphHtml, $strongMatches);
        $labelParts = [];
        foreach ($strongMatches[1] ?? [] as $strongHtml) {
            $labelPart = self::normalizedParagraphText((string) $strongHtml);
            if ($labelPart !== '') {
                $labelParts[] = $labelPart;
            }
        }

        if ($labelParts === []) {
            return false;
        }

        $label = trim(implode(' ', $labelParts), " \t\n\r\0\x0B:-");
        if ($label === '' || mb_strlen($text) <= mb_strlen($label) + 20) {
            return false;
        }

        return preg_match('/[a-z0-9]/u', $label) === 1;
    }

    private static function looksLikeSymbolBulletParagraph(string $paragraphHtml): bool
    {
        $text = self::normalizedParagraphText($paragraphHtml);
        if ($text === '' || self::looksLikeProductDescriptionHeading($text)) {
            return false;
        }

        return preg_match('/^(?:[•*]|-|\d+[.)])\s*\S/u', $text) === 1;
    }

    private static function looksLikeBracketBulletParagraph(string $paragraphHtml): bool
    {
        if (self::countBracketLabels($paragraphHtml) < 1) {
            return false;
        }

        $text = self::normalizedParagraphText($paragraphHtml);

        return mb_strlen($text) > 35;
    }

    private static function countBracketLabels(string $html): int
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        preg_match_all('/【[^】]{2,100}】/u', $text, $matches);

        return count($matches[0] ?? []);
    }

    private static function normalizedParagraphText(string $html): string
    {
        $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = preg_replace('/\s+/u', ' ', (string) $text);
        $text = trim((string) $text, " \t\n\r\0\x0B:-");

        return mb_strtolower($text);
    }

    private static function findProductDescriptionSectionOffset(string $body): ?int
    {
        if (! preg_match_all('/<(?:h[1-6]|p)\b[^>]*>.*?<\/(?:h[1-6]|p)>/is', $body, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        foreach ($matches[0] as [$sectionHtml, $offset]) {
            $text = self::normalizedParagraphText((string) $sectionHtml);
            if ($text === '') {
                continue;
            }

            if (self::looksLikeProductDescriptionHeading($text)) {
                return (int) $offset;
            }
        }

        return null;
    }

    private static function isBeforeProductDescription(string $body, int $offset): bool
    {
        $descriptionOffset = self::findProductDescriptionSectionOffset($body);

        return $descriptionOffset === null || $offset < $descriptionOffset;
    }

    private static function looksLikeProductDescriptionHeading(string $text): bool
    {
        if (! str_contains($text, 'description')) {
            return false;
        }

        if (str_contains($text, 'item description from the seller')) {
            return false;
        }

        if (str_contains($text, 'product description')) {
            return true;
        }

        return preg_match('/^[a-z0-9 &+\-\/]{1,80}\s+description$/u', $text) === 1;
    }

    private static function replaceLeadingBoldLabelBulletParagraph(string $body, string $replacement, ?array &$meta = null): ?string
    {
        if (! preg_match_all('/<p\b[^>]*>.*?<\/p>/is', $body, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        foreach ($matches[0] as [$paragraphHtml, $offset]) {
            $beforeParagraph = substr($body, 0, (int) $offset);
            if (preg_match('/<(?:h[1-6]|img|table)\b/i', $beforeParagraph)) {
                return null;
            }

            if (! self::looksLikeBoldLabelBulletParagraph((string) $paragraphHtml)) {
                continue;
            }

            if (is_array($meta)) {
                $meta['strategy'] = 'bold_label_paragraph';
                $meta['offset'] = (int) $offset;
                $meta['replaced_length'] = strlen((string) $paragraphHtml);
            }

            return substr_replace($body, $replacement, (int) $offset, strlen((string) $paragraphHtml));
        }

        return null;
    }

    private static function looksLikeBoldLabelBulletParagraph(string $paragraphHtml): bool
    {
        if (substr_count(strtolower($paragraphHtml), '<strong') < 3) {
            return false;
        }

        preg_match_all('/<strong\b[^>]*>(.*?)<\/strong>/is', $paragraphHtml, $strongMatches);
        $labelCount = 0;
        foreach ($strongMatches[1] ?? [] as $strongHtml) {
            $label = trim(html_entity_decode(strip_tags((string) $strongHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $label = preg_replace('/\s+/u', ' ', (string) $label);
            if ($label !== '' && preg_match('/[-:]\s*$/u', $label)) {
                $labelCount++;
            }
        }

        if ($labelCount < 3) {
            return false;
        }

        return preg_match('/<br\b/i', $paragraphHtml) === 1;
    }

    private static function escapeXmlElementText(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @return array{success: bool, message: string}
     */
    private static function postReviseItemXml(
        string $endpoint,
        string $compatLevel,
        string $devId,
        string $appId,
        string $certId,
        string $siteId,
        string $itemId,
        string $xmlBody,
        string $contextLabel,
    ): array {
        $headers = [
            'X-EBAY-API-COMPATIBILITY-LEVEL' => $compatLevel,
            'X-EBAY-API-DEV-NAME' => $devId,
            'X-EBAY-API-APP-NAME' => $appId,
            'X-EBAY-API-CERT-NAME' => $certId,
            'X-EBAY-API-CALL-NAME' => 'ReviseItem',
            'X-EBAY-API-SITEID' => $siteId,
            'Content-Type' => 'text/xml',
        ];

        try {
            $response = null;
            $lastThrowable = null;
            $attempts = 3;
            for ($attempt = 1; $attempt <= $attempts; $attempt++) {
                try {
                    $response = Http::withoutVerifying()
                        ->connectTimeout(15)
                        ->timeout(60)
                        ->withHeaders($headers)
                        ->withBody($xmlBody, 'text/xml')
                        ->post($endpoint);

                    if ($response->successful() || ! in_array($response->status(), [408, 429, 500, 502, 503, 504], true) || $attempt === $attempts) {
                        break;
                    }
                } catch (\Throwable $e) {
                    $lastThrowable = $e;
                    $msg = $e->getMessage();
                    $isTimeout = str_contains(strtolower($msg), 'timed out')
                        || str_contains(strtolower($msg), 'curl error 28')
                        || str_contains(strtolower($msg), 'operation timed out')
                        || str_contains(strtolower($msg), 'ssl connection timeout');
                    if (! $isTimeout || $attempt === $attempts) {
                        throw $e;
                    }
                }

                usleep(300000 * $attempt);
            }

            if (! $response && $lastThrowable) {
                throw $lastThrowable;
            }
            if (! $response) {
                return ['success' => false, 'message' => 'No response from eBay API.'];
            }

            $body = $response->body();
            libxml_use_internal_errors(true);
            $xmlResp = simplexml_load_string($body);

            if ($xmlResp === false) {
                Log::error('eBay ReviseItem: invalid XML response', ['itemId' => $itemId, 'context' => $contextLabel, 'body' => mb_substr($body, 0, 800)]);

                return ['success' => false, 'message' => 'Invalid eBay API response.'];
            }

            $responseArray = json_decode(json_encode($xmlResp), true);
            $ack = $responseArray['Ack'] ?? 'Failure';
            $errors = $responseArray['Errors'] ?? [];
            if (! is_array($errors)) {
                $errors = [];
            }
            // eBay returns a single <Errors> block as an associative array,
            // and multiple blocks as a numeric array of associative arrays.
            // Detect the single-error case by checking for known eBay error keys.
            if (isset($errors['ErrorCode']) || isset($errors['ShortMessage']) || isset($errors['LongMessage'])) {
                $errors = [$errors];
            }

            if ($ack === 'Success' || $ack === 'Warning') {
                Log::info('eBay ReviseItem OK', [
                    'item_id' => $itemId,
                    'context' => $contextLabel,
                    'ack' => $ack,
                    'http_status' => $response->status(),
                    'api_attempts' => $attempt,
                    'warnings' => $errors,
                ]);

                return ['success' => true, 'message' => 'eBay listing updated ('.$contextLabel.').'];
            }

            $firstErr = $errors[0] ?? [];
            $msg = (string) ($firstErr['LongMessage'] ?? $firstErr['ShortMessage'] ?? $firstErr['ErrorCode'] ?? 'Unknown eBay error');
            // Include error code for easier debugging
            if (isset($firstErr['ErrorCode']) && ! str_contains($msg, (string) $firstErr['ErrorCode'])) {
                $msg = '[eBay #'.$firstErr['ErrorCode'].'] '.$msg;
            }
            Log::warning('eBay ReviseItem failed', [
                'item_id' => $itemId,
                'context' => $contextLabel,
                'ack' => $ack,
                'http_status' => $response->status(),
                'api_attempts' => $attempt,
                'errors' => $errors,
            ]);

            return ['success' => false, 'message' => $msg];
        } catch (\Throwable $e) {
            Log::error('eBay ReviseItem exception', ['itemId' => $itemId, 'context' => $contextLabel, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
