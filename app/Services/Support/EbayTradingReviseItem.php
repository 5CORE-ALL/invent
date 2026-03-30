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

        $existing = self::normalizeItemSpecificsNameValueLists($item['ItemSpecifics'] ?? null);
        $existing = self::deduplicateItemSpecificsByName($existing);
        $lines = self::splitBulletLinesFive($bulletPointsPlain);
        $merged = self::mergeBulletAspectsIntoItemSpecifics($existing, $lines, $aspectNames);
        $merged = self::deduplicateItemSpecificsByName($merged);

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
        $html = '<div class="bp-master-bullets"><ul>';
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $line = preg_replace('/^[-*•\d.\)\s]+/u', '', $line);
            $html .= '<li>'.htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</li>';
        }
        $html .= '</ul></div>';

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
            $response = Http::withoutVerifying()
                ->withHeaders($headers)
                ->withBody($xmlBody, 'text/xml')
                ->post($endpoint);

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
