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

        return self::postReviseItemXml($endpoint, $compatLevel, $devId, $appId, $certId, $siteId, $itemId, $xmlBody, 'description');
    }

    /**
     * Replace gallery images via PictureDetails (up to 12 publicly accessible URLs).
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

        $tokenEsc = htmlspecialchars($authToken, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $idEsc = htmlspecialchars($itemId, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents"/>');
        $creds = $xml->addChild('RequesterCredentials');
        $creds->addChild('eBayAuthToken', $tokenEsc);
        $xml->addChild('ErrorLanguage', 'en_US');
        $xml->addChild('WarningLevel', 'High');
        $itemNode = $xml->addChild('Item');
        $itemNode->addChild('ItemID', $idEsc);
        $pd = $itemNode->addChild('PictureDetails');
        foreach ($urls as $u) {
            $pd->addChild('PictureURL', self::escapeXmlElementText($u));
        }

        $xmlBody = $xml->asXML();
        if ($xmlBody === false) {
            return ['success' => false, 'message' => 'Failed to build ReviseItem XML for pictures.'];
        }

        return self::postReviseItemXml($endpoint, $compatLevel, $devId, $appId, $certId, $siteId, $itemId, $xmlBody, 'pictures');
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
        $merged = self::applyForcedBrandForBulletUpdate($merged);
        $merged = self::applyForcedMpnFromSku($item, $merged);

        return self::deduplicateItemSpecificsByName($merged);
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
            $filtered[] = ['name' => $aspectNames[$i], 'values' => [$line]];
        }

        return $filtered;
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

            if ($ack === 'Success' || $ack === 'Warning') {
                Log::info('eBay ReviseItem OK', ['item_id' => $itemId, 'context' => $contextLabel]);

                return ['success' => true, 'message' => 'eBay listing updated ('.$contextLabel.').'];
            }

            $errors = $responseArray['Errors'] ?? [];
            if (! is_array($errors)) {
                $errors = [$errors];
            }
            $msg = isset($errors[0]['LongMessage']) ? $errors[0]['LongMessage'] : (isset($errors[0]['ShortMessage']) ? $errors[0]['ShortMessage'] : 'Unknown eBay error');

            return ['success' => false, 'message' => (string) $msg];
        } catch (\Throwable $e) {
            Log::error('eBay ReviseItem exception', ['itemId' => $itemId, 'context' => $contextLabel, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
