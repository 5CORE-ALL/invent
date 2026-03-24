<?php

namespace App\Services\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Shared Trading API ReviseItem call to update listing HTML description (bullet points).
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
                Log::error('eBay ReviseItem (description): invalid XML response', ['itemId' => $itemId, 'body' => mb_substr($body, 0, 800)]);

                return ['success' => false, 'message' => 'Invalid eBay API response.'];
            }

            $responseArray = json_decode(json_encode($xmlResp), true);
            $ack = $responseArray['Ack'] ?? 'Failure';

            if ($ack === 'Success' || $ack === 'Warning') {
                Log::info('eBay listing description (bullets) updated', ['item_id' => $itemId]);

                return ['success' => true, 'message' => 'Bullet points updated on eBay listing.'];
            }

            $errors = $responseArray['Errors'] ?? [];
            if (! is_array($errors)) {
                $errors = [$errors];
            }
            $msg = isset($errors[0]['LongMessage']) ? $errors[0]['LongMessage'] : (isset($errors[0]['ShortMessage']) ? $errors[0]['ShortMessage'] : 'Unknown eBay error');

            return ['success' => false, 'message' => (string) $msg];
        } catch (\Throwable $e) {
            Log::error('eBay ReviseItem (description) exception', ['itemId' => $itemId, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
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
}
