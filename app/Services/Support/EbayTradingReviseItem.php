<?php

namespace App\Services\Support;

use App\Services\MarketplaceManager\EbayLiveListingMapper;
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
     * Package size (inches) and weight (lb + oz) of a live listing. eBay has no "height": the third side is
     * PackageDepth. GTC / fixed-price listings need ReviseFixedPriceItem, so the other call is only a fallback.
     *
     * @return array{success: bool, message: string}
     */
    public static function reviseShippingPackage(
        string $endpoint,
        string $compatLevel,
        string $devId,
        string $appId,
        string $certId,
        string $siteId,
        string $authToken,
        string $itemId,
        float $length,
        float $width,
        float $height,
        float $weightLb,
    ): array {
        $itemId = trim($itemId);
        if ($itemId === '') {
            return ['success' => false, 'message' => 'Item ID is required.'];
        }
        $hasDims = $length > 0 && $width > 0 && $height > 0;
        $hasWeight = $weightLb > 0;
        if (! $hasDims && ! $hasWeight) {
            return ['success' => false, 'message' => 'No dimensions or weight to send.'];
        }

        $package = '<MeasurementUnit>English</MeasurementUnit>';
        if ($hasDims) {
            $package .= '<PackageDepth unit="inches">'.round($height, 2).'</PackageDepth>'
                .'<PackageLength unit="inches">'.round($length, 2).'</PackageLength>'
                .'<PackageWidth unit="inches">'.round($width, 2).'</PackageWidth>';
        }
        if ($hasWeight) {
            $totalOz = (int) round($weightLb * 16);
            $package .= '<WeightMajor unit="lbs">'.intdiv($totalOz, 16).'</WeightMajor>'
                .'<WeightMinor unit="oz">'.($totalOz % 16).'</WeightMinor>';
        }

        $build = static function (string $root) use ($authToken, $itemId, $package): string {
            return '<?xml version="1.0" encoding="utf-8"?>'
                .'<'.$root.' xmlns="urn:ebay:apis:eBLBaseComponents">'
                .'<RequesterCredentials><eBayAuthToken>'.htmlspecialchars($authToken, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</eBayAuthToken></RequesterCredentials>'
                .'<ErrorLanguage>en_US</ErrorLanguage><WarningLevel>High</WarningLevel>'
                .'<Item><ItemID>'.htmlspecialchars($itemId, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</ItemID>'
                .'<ShippingPackageDetails>'.$package.'</ShippingPackageDetails>'
                .'</Item></'.$root.'>';
        };

        $result = self::postReviseItemXml($endpoint, $compatLevel, $devId, $appId, $certId, $siteId, $itemId, $build('ReviseFixedPriceItemRequest'), 'package dimensions and weight', 'ReviseFixedPriceItem');
        if (! ($result['success'] ?? false) && self::isWrongListingTypeError((string) ($result['message'] ?? ''))) {
            $result = self::postReviseItemXml($endpoint, $compatLevel, $devId, $appId, $certId, $siteId, $itemId, $build('ReviseItemRequest'), 'package dimensions and weight', 'ReviseItem');
        }

        return $result;
    }

    /**
     * After ReviseItem acks, re-read the listing so a description eBay accepted but did not apply is
     * reported instead of shown as "updated". GetItem failures keep the original result.
     *
     * @param  array{success?: bool, message?: string}  $result
     * @param  array<string, mixed>|null  $getItem
     * @return array{success: bool, message: string}
     */
    public static function verifyDescriptionApplied(array $result, ?array $getItem, string $sentHtml, string $itemId, string $label): array
    {
        if (! ($result['success'] ?? false)) {
            return $result + ['success' => false, 'message' => 'Update failed.'];
        }
        $live = is_array($getItem) ? ($getItem['Item']['Description'] ?? null) : null;
        if (! is_string($live) || trim($live) === '') {
            return ['success' => true, 'message' => $label.' description updated on eBay item '.$itemId.'.'] + $result;
        }

        $plain = static fn (string $html): string => mb_strtolower(trim(preg_replace('/\s+/u', ' ',
            html_entity_decode(strip_tags(preg_replace('/<(style|script)\b[^>]*>.*?<\/\1>/is', ' ', $html) ?? $html), ENT_QUOTES | ENT_HTML5, 'UTF-8')
        ) ?? ''));
        $sent = $plain($sentHtml);
        $needle = mb_substr($sent, 0, 80);
        if ($needle === '' || str_contains($plain($live), $needle)) {
            return ['success' => true, 'message' => $label.' description updated on eBay item '.$itemId.'.'] + $result;
        }

        Log::warning('eBay description revise acked but listing unchanged', ['item_id' => $itemId, 'label' => $label, 'sent_start' => $needle]);

        return [
            'success' => false,
            'message' => 'eBay accepted the description for item '.$itemId.' but the listing still shows a different description. '
                .'Check that this SKU belongs to item '.$itemId.' in '.$label.'.',
        ];
    }

    /**
     * Turn every picture inside a description into an address eBay can always show.
     * Pictures that only load on our own pages (relative "/cdn/shop/..." paths, "//host/..." links, http links, links
     * with spaces or "+", pictures pasted as base64) look broken once they sit inside an eBay listing. Each one is
     * made absolute and copied onto eBay's own picture host (i.ebayimg.com). A picture that cannot be copied keeps
     * its cleaned-up https link and is reported in "failed".
     *
     * @return array{html: string, hosted: int, failed: list<string>}
     */
    public static function hostDescriptionImages(
        string $html,
        string $endpoint,
        string $devId,
        string $appId,
        string $certId,
        string $authToken,
    ): array {
        $out = ['html' => $html, 'hosted' => 0, 'failed' => []];
        if ($html === '' || stripos($html, '<img') === false) {
            return $out;
        }

        // Pictures pasted as base64 become stored files first, so they have an address to copy from.
        $html = \App\Support\Marketplace\AplusInlineImages::externalize($html);

        $cache = [];
        $converted = preg_replace_callback('#<img\b[^>]*>#i', static function (array $m) use (&$cache, &$out, $endpoint, $devId, $appId, $certId, $authToken): string {
            $tag = $m[0];
            $src = '';
            foreach (['src', 'data-src', 'data-original'] as $attr) {
                if (preg_match('#\b'.$attr.'\s*=\s*(?:"([^"]*)"|\'([^\']*)\')#i', $tag, $a)) {
                    $candidate = trim(html_entity_decode($a[1] !== '' ? $a[1] : ($a[2] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    if ($candidate !== '') {
                        $src = $candidate;
                        break;
                    }
                }
            }
            if ($src === '' && preg_match('#\bsrcset\s*=\s*(?:"([^"]*)"|\'([^\']*)\')#i', $tag, $a)) {
                $first = trim(explode(',', html_entity_decode($a[1] !== '' ? $a[1] : ($a[2] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'))[0]);
                $src = trim(preg_split('/\s+/', $first)[0] ?? '');
            }
            if ($src === '') {
                return $tag;
            }

            $url = self::absoluteImageUrl($src);
            if ($url === null) {
                $out['failed'][] = mb_substr($src, 0, 120);

                return $tag;
            }

            if (! array_key_exists($url, $cache)) {
                $name = pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_FILENAME);
                $up = self::uploadImageToEps($endpoint, $devId, $appId, $certId, $authToken, $url, $name !== '' ? mb_substr(rawurldecode($name), 0, 60) : 'image', self::driveFilePath($url));
                if (($up['success'] ?? false) && ! empty($up['url'])) {
                    $cache[$url] = preg_replace('#^http://#i', 'https://', (string) $up['url']);
                    $out['hosted']++;
                } else {
                    $cache[$url] = null;
                    $out['failed'][] = mb_substr($url, 0, 120).' ('.mb_substr((string) ($up['message'] ?? 'upload failed'), 0, 120).')';
                }
            }
            $final = $cache[$url] ?? $url;

            // Drop the old address attributes, then write the working one.
            $tag = preg_replace('#\s(?:src|srcset|data-src|data-original|sizes)\s*=\s*(?:"[^"]*"|\'[^\']*\')#i', '', $tag) ?? $tag;

            return preg_replace('#<img\b#i', '<img src="'.htmlspecialchars($final, ENT_QUOTES, 'UTF-8').'"', $tag, 1) ?? $tag;
        }, $html);

        $out['html'] = is_string($converted) ? $converted : $html;

        return $out;
    }

    /**
     * Files in our own Drive ("/drive/file/{uuid}") are private: opening that address needs a login, so neither eBay nor
     * a server-side download can read it. Return the file's place on this server instead.
     */
    private static function driveFilePath(string $url): ?string
    {
        if (! preg_match('#/drive/file/([0-9a-fA-F-]{20,40})(?:[/?\#]|$)#', $url, $m)) {
            return null;
        }
        try {
            $item = \App\Models\DriveItem::where('uuid', $m[1])->first();
            if (! $item || $item->isFolder()) {
                return null;
            }

            return app(\App\Services\Drive\DriveService::class)->absolutePath($item->disk, $item->path);
        } catch (\Throwable $e) {
            Log::warning('Drive picture lookup failed', ['uuid' => $m[1], 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** An absolute, correctly encoded https/http address for a picture link, or null when it cannot be made one. */
    private static function absoluteImageUrl(string $src): ?string
    {
        $src = trim($src);
        if ($src === '' || stripos($src, 'data:') === 0 || stripos($src, 'blob:') === 0 || stripos($src, 'cid:') === 0) {
            return null;
        }
        if (stripos($src, 'shopify://shop_images/') === 0) {
            $src = '/cdn/shop/files/'.substr($src, strlen('shopify://shop_images/'));
        }
        if (str_starts_with($src, '//')) {
            $src = 'https:'.$src;
        } elseif (! preg_match('#^https?://#i', $src)) {
            if (! str_starts_with($src, '/')) {
                $src = '/'.$src;
            }
            if (str_starts_with($src, '/storage/')) {
                $base = rtrim((string) config('app.url'), '/');
            } else {
                $host = trim((string) (config('services.shopify.store_url') ?: config('services.shopify.domain')));
                $host = rtrim(preg_replace('#^https?://#i', '', $host) ?? $host, '/');
                $base = $host !== '' ? 'https://'.$host : rtrim((string) config('app.url'), '/');
            }
            if ($base === '') {
                return null;
            }
            $src = $base.$src;
        }

        // Encode only what a link cannot carry (spaces, quotes, non-ASCII); keep "+" and existing %XX as they are.
        $src = preg_replace_callback('#[^A-Za-z0-9\-._~:/?\#\[\]@!$&\'()*+,;=%]#u', static fn (array $c): string => rawurlencode($c[0]), $src) ?? $src;
        $src = preg_replace('#%(?![0-9A-Fa-f]{2})#', '%25', $src) ?? $src;

        return preg_match('#^https?://[^/\s]+#i', $src) ? $src : null;
    }

    /**
     * Listing Description body for a push: the caller's full HTML is kept intact (only active content
     * eBay rejects is removed). Images already inside that HTML stay; the product gallery is not added.
     */
    public static function buildListingDescriptionHtml(string $description, string $identifier, ?string $skuHint = null): string
    {
        $description = trim($description);
        if ($description === '') {
            return '';
        }

        if (! preg_match('/<[^>]+>/', $description)) {
            return DescriptionWithImagesFormatter::buildHtmlWithImages($description, $identifier, $skuHint, 'Product Image', 12)['html'];
        }

        $html = $description;
        if (preg_match('/<body\b[^>]*>(.*)<\/body>/is', $html, $m)) {
            $html = trim($m[1]);
        }
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/<iframe\b[^>]*>.*?<\/iframe>/is', '', $html) ?? $html;
        $html = trim($html);

        if (! preg_match('/\A<div\b[^>]*\bclass\s*=\s*["\'][^"\']*\bproduct-description\b/i', $html)) {
            $html = '<div class="product-description">'.$html.'</div>';
        }

        return $html;
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
        ?string $localFile = null,
    ): array {
        // Already an eBay-hosted URL — no need to re-upload
        if (preg_match('#^https?://(i|p)\.ebayimg\.com/#i', $imageUrl)) {
            return ['success' => true, 'url' => $imageUrl, 'message' => 'Already eBay-hosted.'];
        }

        // Read image binary: local file or remote URL
        $imageData = null;
        $contentType = 'image/jpeg';

        // A file the caller already located on this server (e.g. a private Drive file that needs a login to download).
        $localPath = ($localFile !== null && is_file($localFile)) ? $localFile : self::resolveLocalStoragePath($imageUrl);
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

        // Build UploadSiteHostedPictures XML payload.
        // An OAuth token belongs in X-EBAY-API-IAF-TOKEN. Putting it inside the multipart
        // body makes eBay's gateway answer "Resource not found" and no photo is stored.
        $tokenEsc = htmlspecialchars($authToken, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $nameEsc  = htmlspecialchars($pictureName, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $oauth = str_starts_with($authToken, 'v^');

        // eBay's Trading UploadSiteHostedPictures answers HTTP 404 for these OAuth accounts.
        // The Media API accepts the same token and returns an i.ebayimg.com address, so use it first.
        $mediaNote = '';
        if ($oauth) {
            $mediaUrl = self::uploadImageViaMediaApi($authToken, $imageData, $contentType, $mediaNote);
            if ($mediaUrl !== null) {
                return ['success' => true, 'url' => $mediaUrl];
            }
        }

        $xmlPayload = '<?xml version="1.0" encoding="utf-8"?>'
            .'<UploadSiteHostedPicturesRequest xmlns="urn:ebay:apis:eBLBaseComponents">';
        if (! $oauth) {
            $xmlPayload .= '<RequesterCredentials><eBayAuthToken>'.$tokenEsc.'</eBayAuthToken></RequesterCredentials>';
        }
        $xmlPayload .= '<ErrorLanguage>en_US</ErrorLanguage>'
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
        if ($oauth) {
            $headers['X-EBAY-API-IAF-TOKEN'] = $authToken;
        }

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
                $host = (string) (parse_url($endpoint, PHP_URL_HOST) ?? $endpoint);
                $detail = trim(preg_replace('/\s+/', ' ', strip_tags(substr($respBody, 0, 300))) ?? '');

                return ['success' => false, 'message' => 'Invalid EPS upload response (HTTP '.$response->status().' from '.$host
                    .', '.strlen($imageData).' bytes sent as '.$contentType.'): '.($detail !== '' ? $detail : 'empty reply')
                    .($mediaNote !== '' ? ' | Media API: '.$mediaNote : '')];
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

            return ['success' => false, 'message' => $errMsg.($mediaNote !== '' ? ' | Media API: '.$mediaNote : '')];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'EPS upload exception: '.$e->getMessage()];
        }
    }

    /**
     * eBay Media API: copy an image onto eBay's servers (needs the sell.inventory OAuth scope).
     */
    private static function uploadImageViaMediaApi(string $token, string $imageData, string $contentType, string &$note): ?string
    {
        $base = 'https://apim.ebay.com/commerce/media/v1_beta/image';
        $auth = ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
        try {
            $ext = str_contains($contentType, 'png') ? 'png' : (str_contains($contentType, 'gif') ? 'gif' : 'jpg');
            $response = Http::timeout(90)->withHeaders($auth)
                ->attach('image', $imageData, 'photo.'.$ext, ['Content-Type' => $contentType])
                ->post($base.'/create_image_from_file');
            if ($response->successful()) {
                $json = $response->json();
                $found = is_array($json) ? trim((string) ($json['imageUrl'] ?? '')) : '';
                if ($found === '') {
                    $location = trim((string) $response->header('Location'));
                    if ($location !== '') {
                        $data = Http::timeout(30)->withHeaders($auth)->get($location)->json();
                        $found = is_array($data) ? trim((string) ($data['imageUrl'] ?? '')) : '';
                    }
                }
                if ($found !== '') {
                    return $found;
                }
            }
            $text = trim(preg_replace('/\s+/', ' ', strip_tags(substr((string) $response->body(), 0, 220))) ?? '');
            $note = 'HTTP '.$response->status().($text !== '' ? ' '.$text : '');
        } catch (\Throwable $e) {
            $note = $e->getMessage();
        }

        return null;
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
        $pd->addChild('GalleryType', 'Gallery');
        $pd->addChild('GalleryURL', self::escapeXmlElementText($epsUrls[0]));
        foreach ($epsUrls as $u) {
            $pd->addChild('PictureURL', self::escapeXmlElementText($u));
        }

        $xmlBody = $xml->asXML();
        if ($xmlBody === false) {
            return ['success' => false, 'message' => 'Failed to build ReviseItem XML for pictures.'];
        }

        $result = self::postReviseItemXml($endpoint, $compatLevel, $devId, $appId, $certId, $siteId, $itemId, $xmlBody, 'pictures');

        // Append any partial EPS failures to the success message
        if ($result['success'] ?? false) {
            $result['normalized_urls'] = $epsUrls;
            if ($epsErrors) {
                $result['message'] = ($result['message'] ?? 'eBay images updated.')
                    .' Note: '.count($epsErrors).' of '.count($urls).' image(s) could not be uploaded: '.implode(' | ', $epsErrors);
            }
        }

        return $result;
    }

    /**
     * Attach product video via Trading API VideoDetails (typically one URL; YouTube/Vimeo or hosted MP4).
     *
     * @param  list<string>  $videoUrls
     * @return array{success: bool, message: string, normalized_urls?: list<string>}
     */
    public static function reviseItemVideos(
        string $endpoint,
        string $compatLevel,
        string $devId,
        string $appId,
        string $certId,
        string $siteId,
        string $authToken,
        string $itemId,
        array $videoUrls,
    ): array {
        $urls = [];
        foreach ($videoUrls as $u) {
            $t = trim((string) $u);
            if ($t !== '' && preg_match('#^https?://#i', $t)) {
                $urls[] = $t;
            }
        }
        $urls = array_slice(array_values(array_unique($urls)), 0, 3);
        if ($urls === []) {
            return ['success' => false, 'message' => 'At least one video URL is required.'];
        }

        $primary = $urls[0];
        $tokenEsc = htmlspecialchars($authToken, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $idEsc = htmlspecialchars($itemId, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $urlEsc = self::escapeXmlElementText($primary);

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents"/>');
        $creds = $xml->addChild('RequesterCredentials');
        $creds->addChild('eBayAuthToken', $tokenEsc);
        $xml->addChild('ErrorLanguage', 'en_US');
        $xml->addChild('WarningLevel', 'High');
        $itemNode = $xml->addChild('Item');
        $itemNode->addChild('ItemID', $idEsc);
        $vd = $itemNode->addChild('VideoDetails');
        $vi = $vd->addChild('VideoItem');
        $vi->addChild('VideoURL', $urlEsc);

        $xmlBody = $xml->asXML();
        if ($xmlBody === false) {
            return ['success' => false, 'message' => 'Failed to build ReviseItem XML for video.'];
        }

        $result = self::postReviseItemXml($endpoint, $compatLevel, $devId, $appId, $certId, $siteId, $itemId, $xmlBody, 'video');
        if ($result['success'] ?? false) {
            $result['normalized_urls'] = [$primary];
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
     * True when GetItem contains at least one Variations.Variation node.
     *
     * @param  array<string, mixed>  $getItemResponse
     */
    public static function listingHasVariations(array $getItemResponse): bool
    {
        $item = self::itemFromGetItem($getItemResponse);
        $vars = $item['Variations']['Variation'] ?? null;

        return is_array($vars) && $vars !== [];
    }

    /**
     * Plan a SKU-only VariationSpecificPictureSet update. Does not touch parent PictureDetails.
     *
     * @param  array<string, mixed>  $getItemResponse
     * @param  list<string>  $newImageUrls
     * @return array{
     *   ok: bool,
     *   message?: string,
     *   not_variation?: bool,
     *   item_id?: string,
     *   name?: string,
     *   variation_value?: string,
     *   sets?: list<array{value: string, urls: list<string>}>,
     *   new_urls?: list<string>
     * }
     */
    public static function variationPicturePlan(array $getItemResponse, string $sku, array $newImageUrls): array
    {
        $sku = trim($sku);
        $urls = [];
        foreach ($newImageUrls as $u) {
            $t = trim((string) $u);
            if ($t !== '') {
                $urls[] = $t;
            }
        }
        $urls = array_values(array_unique($urls));
        if ($sku === '' || $urls === []) {
            return ['ok' => false, 'message' => 'SKU and image URL are required.'];
        }

        $item = self::itemFromGetItem($getItemResponse);
        $itemId = self::xmlText($item['ItemID'] ?? '');
        if ($itemId === '') {
            return ['ok' => false, 'message' => 'GetItem response missing ItemID.'];
        }

        if (! self::listingHasVariations($item)) {
            return [
                'ok' => false,
                'not_variation' => true,
                'item_id' => $itemId,
                'message' => 'Listing has no variations.',
            ];
        }

        $variation = self::findVariationForSku($item, $sku);
        if ($variation === null) {
            return [
                'ok' => false,
                'item_id' => $itemId,
                'message' => 'SKU was not found as a child variation on this eBay listing. Parent image was not changed.',
            ];
        }

        $specifics = EbayLiveListingMapper::nameValueMap($variation['VariationSpecifics']['NameValueList'] ?? null);
        $state = self::extractVariationPictureState($item);
        $name = $state['name'];
        if ($name === '') {
            foreach (['Color', 'Colour', 'color'] as $pref) {
                if (isset($specifics[$pref]) && trim((string) $specifics[$pref]) !== '') {
                    $name = $pref;
                    break;
                }
            }
            if ($name === '' && $specifics !== []) {
                $name = (string) array_key_first($specifics);
            }
        }
        if ($name === '') {
            return [
                'ok' => false,
                'item_id' => $itemId,
                'message' => 'Could not determine the variation picture aspect (Color) for this SKU.',
            ];
        }

        $value = '';
        foreach ($specifics as $aspect => $aspectValue) {
            if (strcasecmp((string) $aspect, $name) === 0) {
                $name = (string) $aspect;
                $value = trim((string) $aspectValue);
                break;
            }
        }
        if ($value === '') {
            return [
                'ok' => false,
                'item_id' => $itemId,
                'message' => 'This SKU has no "'.$name.'" value, so its variation picture cannot be updated.',
            ];
        }

        $sets = $state['sets'];
        foreach (self::allVariationValuesForName($item, $name) as $otherValue) {
            $exists = false;
            foreach ($sets as $set) {
                if (strcasecmp((string) ($set['value'] ?? ''), $otherValue) === 0) {
                    $exists = true;
                    break;
                }
            }
            if (! $exists) {
                $sets[] = ['value' => $otherValue, 'urls' => []];
            }
        }

        return [
            'ok' => true,
            'item_id' => $itemId,
            'name' => $name,
            'variation_value' => $value,
            'sets' => $sets,
            'new_urls' => $urls,
        ];
    }

    /**
     * Replace PictureURL list for one VariationSpecificValue and keep every sibling set.
     *
     * @param  list<array{value: string, urls: list<string>}>  $sets
     * @param  list<string>  $newUrls
     * @return list<array{value: string, urls: list<string>}>
     */
    public static function mergeVariationPictureSets(array $sets, string $value, array $newUrls): array
    {
        $value = trim($value);
        $newUrls = array_values(array_filter(array_map('trim', $newUrls), fn ($u) => $u !== ''));
        $found = false;
        $out = [];
        foreach ($sets as $set) {
            $setValue = trim((string) ($set['value'] ?? ''));
            $urls = is_array($set['urls'] ?? null) ? $set['urls'] : [];
            if ($setValue !== '' && strcasecmp($setValue, $value) === 0) {
                $kept = [];
                foreach ($urls as $u) {
                    $t = trim((string) $u);
                    if ($t === '' || in_array($t, $newUrls, true)) {
                        continue;
                    }
                    $kept[] = $t;
                }
                $out[] = ['value' => $setValue, 'urls' => array_slice(array_merge($newUrls, $kept), 0, 12)];
                $found = true;
            } else {
                $out[] = ['value' => $setValue, 'urls' => array_values(array_filter(array_map('trim', $urls), fn ($u) => $u !== ''))];
            }
        }
        if (! $found && $value !== '') {
            $out[] = ['value' => $value, 'urls' => array_slice($newUrls, 0, 12)];
        }

        return $out;
    }

    /**
     * Update only this SKU's variation picture via ReviseFixedPriceItem.
     * Does not send Item.PictureDetails (parent gallery stays unchanged).
     *
     * @param  array<string, mixed>  $getItemResponse
     * @param  list<string>  $imageUrls
     * @return array{success: bool, message: string, is_variation?: bool, variation_value?: string, item_id?: string}
     */
    public static function reviseVariationSpecificPictures(
        string $endpoint,
        string $compatLevel,
        string $devId,
        string $appId,
        string $certId,
        string $siteId,
        string $authToken,
        array $getItemResponse,
        string $sku,
        array $imageUrls,
        bool $replaceSet = false,
    ): array {
        $plan = self::variationPicturePlan($getItemResponse, $sku, $imageUrls);
        if (! ($plan['ok'] ?? false)) {
            return [
                'success' => false,
                'message' => $plan['message'] ?? 'Could not plan variation picture update.',
                'not_variation' => (bool) ($plan['not_variation'] ?? false),
                'item_id' => $plan['item_id'] ?? null,
            ];
        }

        $epsUrls = [];
        $epsErrors = [];
        foreach ($plan['new_urls'] as $i => $srcUrl) {
            $name = 'var_'.($i + 1).'_'.substr(md5($srcUrl), 0, 8);
            $result = self::uploadImageToEps($endpoint, $devId, $appId, $certId, $authToken, $srcUrl, $name);
            if ($result['success'] ?? false) {
                $epsUrls[] = $result['url'];
            } else {
                $epsErrors[] = 'Image '.($i + 1).': '.($result['message'] ?? 'upload failed');
                Log::warning('eBay EPS upload failed (variation picture)', ['url' => $srcUrl, 'error' => $result['message'] ?? '']);
            }
        }
        if ($epsUrls === []) {
            return ['success' => false, 'message' => 'All image uploads to eBay EPS failed. '.implode(' | ', $epsErrors)];
        }

        $sets = $plan['sets'];
        if ($replaceSet) {
            foreach ($sets as $i => $set) {
                if (strcasecmp(trim((string) ($set['value'] ?? '')), $plan['variation_value']) === 0) {
                    $sets[$i]['urls'] = [];
                }
            }
        }
        $sets = self::mergeVariationPictureSets($sets, $plan['variation_value'], $epsUrls);
        $xmlBody = self::buildReviseVariationPicturesRequestXml(
            $authToken,
            $plan['item_id'],
            $plan['name'],
            $sets,
            'ReviseFixedPriceItem'
        );
        $result = self::postTradingCall(
            $endpoint,
            $compatLevel,
            $devId,
            $appId,
            $certId,
            $siteId,
            $plan['item_id'],
            $xmlBody,
            'ReviseFixedPriceItem',
            'variation pictures'
        );

        if (! ($result['success'] ?? false) && self::isWrongListingTypeError((string) ($result['message'] ?? ''))) {
            $xmlBody = self::buildReviseVariationPicturesRequestXml(
                $authToken,
                $plan['item_id'],
                $plan['name'],
                $sets,
                'ReviseItem'
            );
            $result = self::postTradingCall(
                $endpoint,
                $compatLevel,
                $devId,
                $appId,
                $certId,
                $siteId,
                $plan['item_id'],
                $xmlBody,
                'ReviseItem',
                'variation pictures'
            );
        }

        if ($result['success'] ?? false) {
            $result['message'] = 'Updated '.$sku.' variation image ('.$plan['name'].': '.$plan['variation_value'].'). Parent and sibling pictures were not replaced.';
            if ($epsErrors) {
                $result['message'] .= ' Note: '.count($epsErrors).' image(s) could not be uploaded.';
            }
            $result['is_variation'] = true;
            $result['variation_value'] = $plan['variation_value'];
            $result['variation_name'] = $plan['name'];
            $result['item_id'] = $plan['item_id'];
            $result['normalized_urls'] = $epsUrls;
        }

        return $result;
    }

    /**
     * @param  list<array{value: string, urls: list<string>}>  $sets
     */
    public static function buildReviseVariationPicturesRequestXml(
        string $authToken,
        string $itemId,
        string $variationSpecificName,
        array $sets,
        string $callName = 'ReviseFixedPriceItem',
    ): string {
        $root = $callName === 'ReviseItem' ? 'ReviseItemRequest' : 'ReviseFixedPriceItemRequest';
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><'.$root.' xmlns="urn:ebay:apis:eBLBaseComponents"/>');
        $creds = $xml->addChild('RequesterCredentials');
        $creds->addChild('eBayAuthToken', self::escapeXmlElementText($authToken));
        $xml->addChild('ErrorLanguage', 'en_US');
        $xml->addChild('WarningLevel', 'High');
        $itemNode = $xml->addChild('Item');
        $itemNode->addChild('ItemID', self::escapeXmlElementText($itemId));
        $variations = $itemNode->addChild('Variations');
        $pictures = $variations->addChild('Pictures');
        $pictures->addChild('VariationSpecificName', self::escapeXmlElementText($variationSpecificName));
        foreach ($sets as $set) {
            $value = trim((string) ($set['value'] ?? ''));
            if ($value === '') {
                continue;
            }
            $setNode = $pictures->addChild('VariationSpecificPictureSet');
            $setNode->addChild('VariationSpecificValue', self::escapeXmlElementText($value));
            $urls = is_array($set['urls'] ?? null) ? $set['urls'] : [];
            foreach (array_slice($urls, 0, 12) as $url) {
                $t = trim((string) $url);
                if ($t !== '') {
                    $setNode->addChild('PictureURL', self::escapeXmlElementText($t));
                }
            }
        }

        $xmlBody = $xml->asXML();

        return $xmlBody === false ? '' : $xmlBody;
    }

    /**
     * @param  array<string, mixed>  $itemOrGetItem
     * @return array{name: string, sets: list<array{value: string, urls: list<string>}>}
     */
    public static function extractVariationPictureState(array $itemOrGetItem): array
    {
        $item = self::itemFromGetItem($itemOrGetItem);
        $pictures = $item['Variations']['Pictures'] ?? null;
        if (! is_array($pictures)) {
            return ['name' => '', 'sets' => []];
        }

        $name = self::xmlText($pictures['VariationSpecificName'] ?? '');
        $rawSets = $pictures['VariationSpecificPictureSet'] ?? [];
        if (isset($rawSets['VariationSpecificValue']) || isset($rawSets['PictureURL'])) {
            $rawSets = [$rawSets];
        }
        $sets = [];
        if (is_array($rawSets)) {
            foreach ($rawSets as $set) {
                if (! is_array($set)) {
                    continue;
                }
                $value = self::xmlText($set['VariationSpecificValue'] ?? '');
                if ($value === '') {
                    continue;
                }
                $sets[] = [
                    'value' => $value,
                    'urls' => self::extractPictureUrlsFromGetItem(['Item' => ['PictureDetails' => ['PictureURL' => $set['PictureURL'] ?? []]]]),
                ];
            }
        }

        return ['name' => $name, 'sets' => $sets];
    }

    /**
     * @param  array<string, mixed>  $itemOrGetItem
     * @return array<string, mixed>|null
     */
    public static function findVariationForSku(array $itemOrGetItem, string $sku): ?array
    {
        $item = self::itemFromGetItem($itemOrGetItem);
        $vars = $item['Variations']['Variation'] ?? null;
        if (! is_array($vars) || $vars === []) {
            return null;
        }
        if (isset($vars['SKU']) || isset($vars['Quantity']) || isset($vars['VariationSpecifics'])) {
            $vars = [$vars];
        }
        foreach ($vars as $variation) {
            if (! is_array($variation)) {
                continue;
            }
            $vSku = self::xmlText($variation['SKU'] ?? '');
            if ($vSku !== '' && EbayLiveListingMapper::skuEquals($vSku, $sku)) {
                return $variation;
            }
        }

        return null;
    }

    /**
     * The aspect(s) a live multi-variation listing varies by, with their values, e.g. ['Color' => ['Black', 'Red']].
     *
     * @param  array<string, mixed>  $itemOrGetItem
     * @return array<string, list<string>>
     */
    public static function variationAspects(array $itemOrGetItem): array
    {
        $item = self::itemFromGetItem($itemOrGetItem);
        $list = $item['Variations']['VariationSpecificsSet']['NameValueList'] ?? null;
        if (! is_array($list) || $list === []) {
            return [];
        }
        if (isset($list['Name'])) {
            $list = [$list];
        }
        $out = [];
        foreach ($list as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim(self::xmlText($row['Name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $values = $row['Value'] ?? [];
            $values = is_array($values) ? $values : [$values];
            $out[$name] = array_values(array_filter(array_map(static fn ($v) => trim(self::xmlText($v)), $values), static fn ($v) => $v !== ''));
        }

        return $out;
    }

    /**
     * The child variations of a live listing: each variation's SKU and the value it has per aspect,
     * e.g. [['sku' => 'AB 1', 'specifics' => ['Color' => 'Black']]].
     *
     * @param  array<string, mixed>  $itemOrGetItem
     * @return list<array{sku: string, specifics: array<string, string>}>
     */
    public static function variationSpecificsList(array $itemOrGetItem): array
    {
        $item = self::itemFromGetItem($itemOrGetItem);
        $vars = $item['Variations']['Variation'] ?? [];
        if (isset($vars['SKU']) || isset($vars['VariationSpecifics'])) {
            $vars = [$vars];
        }
        $out = [];
        foreach ((array) $vars as $variation) {
            if (! is_array($variation)) {
                continue;
            }
            $sku = trim(self::xmlText($variation['SKU'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $specifics = [];
            foreach (EbayLiveListingMapper::nameValueMap($variation['VariationSpecifics']['NameValueList'] ?? null) as $name => $value) {
                $specifics[(string) $name] = trim((string) $value);
            }
            $out[] = ['sku' => $sku, 'specifics' => $specifics];
        }

        return $out;
    }

    /**
     * Rename the variation aspect of a live listing (for example Color to Quantity). eBay needs the new name on
     * every variation, in the variation set and on the variation pictures in the same call, so all of them are
     * re-sent with their current values. Only a listing that varies by one aspect is renamed.
     *
     * @param  array<string, mixed>  $getItemResponse
     * @return array{success: bool, message: string}
     */
    public static function renameVariationAspect(
        string $endpoint,
        string $compatLevel,
        string $devId,
        string $appId,
        string $certId,
        string $siteId,
        string $authToken,
        array $getItemResponse,
        string $newName,
    ): array {
        $newName = trim($newName);
        $item = self::itemFromGetItem($getItemResponse);
        $itemId = trim(self::xmlText($item['ItemID'] ?? ''));
        $aspects = self::variationAspects($item);
        if ($itemId === '' || $newName === '') {
            return ['success' => false, 'message' => 'Missing eBay item or the new variation type.'];
        }
        if (count($aspects) !== 1) {
            return ['success' => false, 'message' => $aspects === []
                ? 'This eBay listing has no variations to rename.'
                : 'This eBay listing varies by '.implode(' and ', array_keys($aspects)).'. Only a listing with one variation aspect can be renamed from here.'];
        }
        $oldName = (string) array_key_first($aspects);
        if (self::aspectKey($oldName) === self::aspectKey($newName)) {
            return ['success' => true, 'message' => 'Variation type is already "'.$oldName.'" on eBay.'];
        }

        $vars = $item['Variations']['Variation'] ?? [];
        if (isset($vars['SKU']) || isset($vars['VariationSpecifics'])) {
            $vars = [$vars];
        }
        $esc = static fn (string $v): string => self::escapeXmlElementText($v);

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><ReviseFixedPriceItemRequest xmlns="urn:ebay:apis:eBLBaseComponents"/>');
        $xml->addChild('RequesterCredentials')->addChild('eBayAuthToken', $esc($authToken));
        $xml->addChild('ErrorLanguage', 'en_US');
        $xml->addChild('WarningLevel', 'High');
        $itemNode = $xml->addChild('Item');
        $itemNode->addChild('ItemID', $esc($itemId));
        // eBay refuses an aspect that is both a variation aspect and an item specific. A plain item specific with
        // the new name (an earlier push may have added one) is dropped in the same call, other specifics are kept.
        $itemRows = self::normalizeItemSpecificsNameValueLists($item['ItemSpecifics'] ?? null);
        $keptRows = array_values(array_filter($itemRows, static fn ($r) => self::aspectKey((string) ($r['name'] ?? '')) !== self::aspectKey($newName)));
        if (count($keptRows) !== count($itemRows) && $keptRows !== []) {
            $spec = $itemNode->addChild('ItemSpecifics');
            foreach ($keptRows as $r) {
                $nvl = $spec->addChild('NameValueList');
                $nvl->addChild('Name', $esc((string) $r['name']));
                foreach ((array) ($r['values'] ?? []) as $v) {
                    if (trim((string) $v) !== '') {
                        $nvl->addChild('Value', $esc(trim((string) $v)));
                    }
                }
            }
        }
        $block = $itemNode->addChild('Variations');
        $sent = 0;
        foreach ((array) $vars as $variation) {
            if (! is_array($variation)) {
                continue;
            }
            $vSku = trim(self::xmlText($variation['SKU'] ?? ''));
            $map = EbayLiveListingMapper::nameValueMap($variation['VariationSpecifics']['NameValueList'] ?? null);
            $value = '';
            foreach ($map as $name => $v) {
                if (self::aspectKey((string) $name) === self::aspectKey($oldName)) {
                    $value = trim((string) $v);
                    break;
                }
            }
            if ($vSku === '' || $value === '') {
                return ['success' => false, 'message' => 'A variation on this eBay listing has no SKU or no '.$oldName.' value, so the aspect was not renamed.'];
            }
            // eBay treats a renamed variation like a new one, so it also needs its price and quantity. They are
            // sent back exactly as the live listing holds them (Quantity is the listed total, sales included).
            $price = trim(self::xmlText($variation['StartPrice'] ?? ''));
            $qty = trim(self::xmlText($variation['Quantity'] ?? ''));
            if (! is_numeric($price) || (float) $price <= 0 || ! is_numeric($qty)) {
                return ['success' => false, 'message' => 'Could not read the live price or quantity of variation '.$vSku.' from eBay, so the aspect was not renamed.'];
            }
            $node = $block->addChild('Variation');
            $node->addChild('SKU', $esc($vSku));
            $node->addChild('StartPrice', number_format((float) $price, 2, '.', ''));
            $node->addChild('Quantity', (string) max(0, (int) $qty));
            $nvl = $node->addChild('VariationSpecifics')->addChild('NameValueList');
            $nvl->addChild('Name', $esc($newName));
            $nvl->addChild('Value', $esc($value));
            $sent++;
        }
        if ($sent === 0) {
            return ['success' => false, 'message' => 'This eBay listing has no variations to rename.'];
        }

        $pics = self::extractVariationPictureState($item);
        if ($pics['sets'] !== []) {
            $pictures = $block->addChild('Pictures');
            $pictures->addChild('VariationSpecificName', $esc($newName));
            foreach ($pics['sets'] as $set) {
                $setNode = $pictures->addChild('VariationSpecificPictureSet');
                $setNode->addChild('VariationSpecificValue', $esc((string) $set['value']));
                foreach (array_slice((array) $set['urls'], 0, 12) as $url) {
                    $setNode->addChild('PictureURL', $esc((string) $url));
                }
            }
        }

        $setList = $block->addChild('VariationSpecificsSet')->addChild('NameValueList');
        $setList->addChild('Name', $esc($newName));
        foreach ($aspects[$oldName] as $value) {
            $setList->addChild('Value', $esc($value));
        }

        $xmlBody = $xml->asXML();
        if ($xmlBody === false) {
            return ['success' => false, 'message' => 'Failed to build the variation rename request.'];
        }
        $res = self::postReviseItemXml($endpoint, $compatLevel, $devId, $appId, $certId, $siteId, $itemId, $xmlBody, 'variation type', 'ReviseFixedPriceItem');
        if ($res['success']) {
            $res['message'] = 'Variation type renamed from "'.$oldName.'" to "'.$newName.'" on '.$sent.' variation(s). '.$res['message'];
        } else {
            // The exact request is logged (token removed) so a refusal can be compared with what eBay holds.
            Log::warning('eBay variation aspect rename refused', [
                'itemId' => $itemId,
                'from' => $oldName,
                'to' => $newName,
                'error' => $res['message'],
                'variationCount' => $sent,
                'pictureSets' => count($pics['sets']),
                'pictureAspect' => $pics['name'],
                'request' => preg_replace('#<eBayAuthToken>.*?</eBayAuthToken>#s', '<eBayAuthToken>x</eBayAuthToken>', $xmlBody),
            ]);
            $res['message'] = 'eBay would not rename the variation type "'.$oldName.'" to "'.$newName.'": '.$res['message'];
        }

        return $res;
    }

    /**
     * Add a new variation aspect (for example Size) to a live multi-variation listing. The existing aspects stay as
     * they are; every variation is re-sent with its SKU, price and quantity exactly as eBay holds them, its current
     * values, and one more value for the new aspect taken from $valuesBySku (eBay variation SKU => value). The set
     * is rebuilt from the values the variations use. Nothing is sent when a variation has no value for the new aspect.
     *
     * @param  array<string, mixed>  $getItemResponse
     * @param  array<string, string>  $valuesBySku  exact eBay variation SKU => value of the new aspect
     * @return array{success: bool, message: string, aspects?: array<string, list<string>>}
     */
    public static function addVariationAspect(
        string $endpoint,
        string $compatLevel,
        string $devId,
        string $appId,
        string $certId,
        string $siteId,
        string $authToken,
        array $getItemResponse,
        string $newName,
        array $valuesBySku,
    ): array {
        $newName = trim($newName);
        $item = self::itemFromGetItem($getItemResponse);
        $itemId = trim(self::xmlText($item['ItemID'] ?? ''));
        $aspects = self::variationAspects($item);
        if ($itemId === '' || $newName === '') {
            return ['success' => false, 'message' => 'Missing eBay item or the new variation type.'];
        }
        if ($aspects === []) {
            return ['success' => false, 'message' => 'This eBay listing has no variations to add a variation type to.'];
        }
        foreach (array_keys($aspects) as $name) {
            if (self::aspectKey((string) $name) === self::aspectKey($newName)) {
                return ['success' => false, 'message' => 'This eBay listing already varies by "'.$name.'". Nothing to add.'];
            }
        }
        if (count($aspects) >= 5) {
            return ['success' => false, 'message' => 'This eBay listing already varies by '.count($aspects).' aspects ('.implode(', ', array_keys($aspects)).'). eBay allows at most 5.'];
        }

        $vars = $item['Variations']['Variation'] ?? [];
        if (isset($vars['SKU']) || isset($vars['VariationSpecifics'])) {
            $vars = [$vars];
        }
        $esc = static fn (string $v): string => self::escapeXmlElementText($v);

        // Every variation needs a value for the new aspect: stop before sending anything if one has none.
        $missing = [];
        foreach ((array) $vars as $variation) {
            if (! is_array($variation)) {
                continue;
            }
            $vSku = trim(self::xmlText($variation['SKU'] ?? ''));
            if ($vSku === '' || trim((string) ($valuesBySku[$vSku] ?? '')) === '') {
                $missing[] = $vSku !== '' ? $vSku : '(no SKU)';
            }
        }
        if ($missing !== []) {
            return ['success' => false, 'message' => count($missing).' variation(s) have no "'.$newName.'" value in Variation Master (Variation Name), so nothing was added: '
                .implode(', ', array_slice($missing, 0, 10)).(count($missing) > 10 ? ', ...' : '').'.'];
        }

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><ReviseFixedPriceItemRequest xmlns="urn:ebay:apis:eBLBaseComponents"/>');
        $xml->addChild('RequesterCredentials')->addChild('eBayAuthToken', $esc($authToken));
        $xml->addChild('ErrorLanguage', 'en_US');
        $xml->addChild('WarningLevel', 'High');
        $itemNode = $xml->addChild('Item');
        $itemNode->addChild('ItemID', $esc($itemId));
        // eBay refuses an aspect that is both a variation aspect and an item specific, so a plain item specific
        // with the new name is dropped in the same call; the other specifics are kept.
        $itemRows = self::normalizeItemSpecificsNameValueLists($item['ItemSpecifics'] ?? null);
        $keptRows = array_values(array_filter($itemRows, static fn ($r) => self::aspectKey((string) ($r['name'] ?? '')) !== self::aspectKey($newName)));
        if (count($keptRows) !== count($itemRows) && $keptRows !== []) {
            $spec = $itemNode->addChild('ItemSpecifics');
            foreach ($keptRows as $r) {
                $nvl = $spec->addChild('NameValueList');
                $nvl->addChild('Name', $esc((string) $r['name']));
                foreach ((array) ($r['values'] ?? []) as $v) {
                    if (trim((string) $v) !== '') {
                        $nvl->addChild('Value', $esc(trim((string) $v)));
                    }
                }
            }
        }

        $block = $itemNode->addChild('Variations');
        $sent = 0;
        $used = []; // aspect name => [value => true]
        $newValues = [];
        foreach ((array) $vars as $variation) {
            if (! is_array($variation)) {
                continue;
            }
            $vSku = trim(self::xmlText($variation['SKU'] ?? ''));
            $map = EbayLiveListingMapper::nameValueMap($variation['VariationSpecifics']['NameValueList'] ?? null);
            if ($map === []) {
                return ['success' => false, 'message' => 'Variation '.$vSku.' has no variation values on eBay, so nothing was added.'];
            }
            $price = trim(self::xmlText($variation['StartPrice'] ?? ''));
            $qty = trim(self::xmlText($variation['Quantity'] ?? ''));
            if (! is_numeric($price) || (float) $price <= 0 || ! is_numeric($qty)) {
                return ['success' => false, 'message' => 'Could not read the live price or quantity of variation '.$vSku.' from eBay, so nothing was added.'];
            }
            $node = $block->addChild('Variation');
            $node->addChild('SKU', $esc($vSku));
            $node->addChild('StartPrice', number_format((float) $price, 2, '.', ''));
            $node->addChild('Quantity', (string) max(0, (int) $qty));
            $specifics = $node->addChild('VariationSpecifics');
            foreach ($map as $name => $value) {
                $used[(string) $name][(string) $value] = true;
                $nvl = $specifics->addChild('NameValueList');
                $nvl->addChild('Name', $esc((string) $name));
                $nvl->addChild('Value', $esc((string) $value));
            }
            $newValue = trim((string) $valuesBySku[$vSku]);
            $newValues[$newValue] = true;
            $nvl = $specifics->addChild('NameValueList');
            $nvl->addChild('Name', $esc($newName));
            $nvl->addChild('Value', $esc($newValue));
            $sent++;
        }
        if ($sent === 0) {
            return ['success' => false, 'message' => 'This eBay listing has no variations to add a variation type to.'];
        }

        // Pictures stay keyed by the aspect they already use.
        $pics = self::extractVariationPictureState($item);
        if ($pics['sets'] !== []) {
            $pictures = $block->addChild('Pictures');
            $pictures->addChild('VariationSpecificName', $esc($pics['name']));
            foreach ($pics['sets'] as $set) {
                $setNode = $pictures->addChild('VariationSpecificPictureSet');
                $setNode->addChild('VariationSpecificValue', $esc((string) $set['value']));
                foreach (array_slice((array) $set['urls'], 0, 12) as $url) {
                    $setNode->addChild('PictureURL', $esc((string) $url));
                }
            }
        }

        $afterAspects = [];
        $setNode = $block->addChild('VariationSpecificsSet');
        foreach ($aspects as $name => $values) {
            $inUse = $used[(string) $name] ?? [];
            $list = [];
            foreach ($values as $value) {
                if (isset($inUse[$value])) {
                    $list[$value] = true;
                }
            }
            foreach (array_keys($inUse) as $value) {
                $list[(string) $value] = true;
            }
            $nvl = $setNode->addChild('NameValueList');
            $nvl->addChild('Name', $esc((string) $name));
            foreach (array_keys($list) as $value) {
                $nvl->addChild('Value', $esc((string) $value));
            }
            $afterAspects[(string) $name] = array_map('strval', array_keys($list));
        }
        $nvl = $setNode->addChild('NameValueList');
        $nvl->addChild('Name', $esc($newName));
        foreach (array_keys($newValues) as $value) {
            $nvl->addChild('Value', $esc((string) $value));
        }
        $afterAspects[$newName] = array_map('strval', array_keys($newValues));

        $xmlBody = $xml->asXML();
        if ($xmlBody === false) {
            return ['success' => false, 'message' => 'Failed to build the add variation type request.'];
        }
        $res = self::postReviseItemXml($endpoint, $compatLevel, $devId, $appId, $certId, $siteId, $itemId, $xmlBody, 'variation type', 'ReviseFixedPriceItem');
        if ($res['success']) {
            $res['message'] = 'Added variation type "'.$newName.'" ('.count($newValues).' value(s)) to '.$sent.' variation(s). '.$res['message'];
            $res['aspects'] = $afterAspects;
        } else {
            Log::warning('eBay add variation aspect refused', [
                'itemId' => $itemId,
                'aspect' => $newName,
                'error' => $res['message'],
                'variationCount' => $sent,
                'request' => preg_replace('#<eBayAuthToken>.*?</eBayAuthToken>#s', '<eBayAuthToken>x</eBayAuthToken>', $xmlBody),
            ]);
            $res['message'] = 'eBay would not add the variation type "'.$newName.'": '.$res['message'];
        }

        return $res;
    }

    /**
     * Change the variation type of a live listing that varies by one type (for example Color to Size) by adding the
     * variations under the new type first and then deleting the old ones, in one request: every variation is added
     * again with the same SKU, price, quantity still available, UPC/EAN/ISBN and pictures, its value under the new type
     * taken from $valuesBySku, and the old variation (old type) is deleted. When eBay refuses, nothing changes.
     *
     * @param  array<string, mixed>  $getItemResponse
     * @param  array<string, string>  $valuesBySku  exact eBay variation SKU => value under the new type
     * @return array{success: bool, message: string, aspects?: array<string, list<string>>, specifics_by_sku?: array<string, array<string, string>>}
     */
    public static function replaceVariationAspect(
        string $endpoint,
        string $compatLevel,
        string $devId,
        string $appId,
        string $certId,
        string $siteId,
        string $authToken,
        array $getItemResponse,
        string $newName,
        array $valuesBySku,
    ): array {
        $newName = trim($newName);
        $item = self::itemFromGetItem($getItemResponse);
        $itemId = trim(self::xmlText($item['ItemID'] ?? ''));
        $aspects = self::variationAspects($item);
        if ($itemId === '' || $newName === '') {
            return ['success' => false, 'message' => 'Missing eBay item or the new variation type.'];
        }
        if ($aspects === []) {
            return ['success' => false, 'message' => 'This eBay listing has no variations to change the type of.'];
        }
        if (count($aspects) !== 1) {
            return ['success' => false, 'message' => 'This eBay listing varies by '.implode(' and ', array_keys($aspects)).'. Only a listing with one variation type can have its type replaced from here.'];
        }
        $oldName = (string) array_key_first($aspects);
        if (self::aspectKey($oldName) === self::aspectKey($newName)) {
            return ['success' => false, 'message' => 'This eBay listing already varies by "'.$oldName.'". Nothing to add.'];
        }

        $vars = $item['Variations']['Variation'] ?? [];
        if (isset($vars['SKU']) || isset($vars['VariationSpecifics'])) {
            $vars = [$vars];
        }
        $esc = static fn (string $v): string => self::escapeXmlElementText($v);

        // The variations as eBay holds them now, each with its value under the new type.
        $records = [];
        $missing = [];
        $oldValueOf = []; // new value => old value (for the pictures)
        $seen = [];
        foreach ((array) $vars as $variation) {
            if (! is_array($variation)) {
                continue;
            }
            $vSku = trim(self::xmlText($variation['SKU'] ?? ''));
            $map = EbayLiveListingMapper::nameValueMap($variation['VariationSpecifics']['NameValueList'] ?? null);
            $oldValue = '';
            foreach ($map as $n => $v) {
                if (self::aspectKey((string) $n) === self::aspectKey($oldName)) {
                    $oldValue = trim((string) $v);
                }
            }
            if ($vSku === '' || $oldValue === '') {
                return ['success' => false, 'message' => 'A variation on this eBay listing has no SKU or no '.$oldName.' value, so nothing was changed.'];
            }
            $newValue = trim((string) ($valuesBySku[$vSku] ?? ''));
            if ($newValue === '') {
                $missing[] = $vSku;

                continue;
            }
            if (isset($seen[mb_strtolower($newValue)])) {
                return ['success' => false, 'message' => 'Two variations would both get the '.$newName.' value "'.$newValue.'" ('.$seen[mb_strtolower($newValue)].' and '.$vSku.'). Give each SKU its own Variation Name.'];
            }
            $seen[mb_strtolower($newValue)] = $vSku;
            $price = trim(self::xmlText($variation['StartPrice'] ?? ''));
            $qty = trim(self::xmlText($variation['Quantity'] ?? ''));
            if (! is_numeric($price) || (float) $price <= 0 || ! is_numeric($qty)) {
                return ['success' => false, 'message' => 'Could not read the live price or quantity of variation '.$vSku.' from eBay, so nothing was changed.'];
            }
            $sold = (int) trim(self::xmlText($variation['SellingStatus']['QuantitySold'] ?? '0'));
            $details = [];
            foreach (['UPC', 'EAN', 'ISBN'] as $key) {
                $code = trim(self::xmlText($variation['VariationProductListingDetails'][$key] ?? ''));
                if ($code !== '') {
                    $details[$key] = $code;
                }
            }
            $oldValueOf[$newValue] = $oldValue;
            $records[] = [
                'sku' => $vSku, 'oldSpecifics' => $map, 'oldValue' => $oldValue, 'newValue' => $newValue,
                'price' => number_format((float) $price, 2, '.', ''), 'qty' => max(0, (int) $qty - max(0, $sold)), 'details' => $details,
            ];
        }
        // Every variation needs a value under the new type: stop before sending anything if one has none.
        if ($missing !== []) {
            return ['success' => false, 'message' => count($missing).' variation(s) have no "'.$newName.'" value in Variation Master (Variation Name), so nothing was changed: '
                .implode(', ', array_slice($missing, 0, 10)).(count($missing) > 10 ? ', ...' : '').'.'];
        }
        if ($records === []) {
            return ['success' => false, 'message' => 'This eBay listing has no variations to change the type of.'];
        }

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><ReviseFixedPriceItemRequest xmlns="urn:ebay:apis:eBLBaseComponents"/>');
        $xml->addChild('RequesterCredentials')->addChild('eBayAuthToken', $esc($authToken));
        $xml->addChild('ErrorLanguage', 'en_US');
        $xml->addChild('WarningLevel', 'High');
        $itemNode = $xml->addChild('Item');
        $itemNode->addChild('ItemID', $esc($itemId));
        // A plain item specific called like the new type would clash with it as a variation type: drop it, keep the rest.
        $itemRows = self::normalizeItemSpecificsNameValueLists($item['ItemSpecifics'] ?? null);
        $keptRows = array_values(array_filter($itemRows, static fn ($r) => self::aspectKey((string) ($r['name'] ?? '')) !== self::aspectKey($newName)));
        if (count($keptRows) !== count($itemRows) && $keptRows !== []) {
            $spec = $itemNode->addChild('ItemSpecifics');
            foreach ($keptRows as $r) {
                $nvl = $spec->addChild('NameValueList');
                $nvl->addChild('Name', $esc((string) $r['name']));
                foreach ((array) ($r['values'] ?? []) as $v) {
                    if (trim((string) $v) !== '') {
                        $nvl->addChild('Value', $esc(trim((string) $v)));
                    }
                }
            }
        }

        $block = $itemNode->addChild('Variations');
        // 1. The new variations first ...
        foreach ($records as $rec) {
            $node = $block->addChild('Variation');
            $node->addChild('SKU', $esc($rec['sku']));
            $node->addChild('StartPrice', $rec['price']);
            $node->addChild('Quantity', (string) $rec['qty']);
            if ($rec['details'] !== []) {
                $details = $node->addChild('VariationProductListingDetails');
                foreach ($rec['details'] as $k => $code) {
                    $details->addChild($k, $esc($code));
                }
            }
            $nvl = $node->addChild('VariationSpecifics')->addChild('NameValueList');
            $nvl->addChild('Name', $esc($newName));
            $nvl->addChild('Value', $esc($rec['newValue']));
        }
        // 2. ... then the old ones are deleted.
        foreach ($records as $rec) {
            $node = $block->addChild('Variation');
            $node->addChild('Delete', 'true');
            $node->addChild('SKU', $esc($rec['sku']));
            $specifics = $node->addChild('VariationSpecifics');
            foreach ($rec['oldSpecifics'] as $n => $v) {
                $nvl = $specifics->addChild('NameValueList');
                $nvl->addChild('Name', $esc((string) $n));
                $nvl->addChild('Value', $esc((string) $v));
            }
        }

        // Pictures: each new value gets the pictures of the value it replaces.
        $pics = self::extractVariationPictureState($item);
        $picByOld = [];
        foreach ($pics['sets'] as $set) {
            $picByOld[(string) $set['value']] = (array) $set['urls'];
        }
        $picSets = [];
        if ($pics['name'] !== '' && self::aspectKey($pics['name']) === self::aspectKey($oldName)) {
            foreach ($records as $rec) {
                $urls = $picByOld[$rec['oldValue']] ?? [];
                if ($urls !== []) {
                    $picSets[$rec['newValue']] = $urls;
                }
            }
        }
        if ($picSets !== []) {
            $pictures = $block->addChild('Pictures');
            $pictures->addChild('VariationSpecificName', $esc($newName));
            foreach ($picSets as $value => $urls) {
                $setNode = $pictures->addChild('VariationSpecificPictureSet');
                $setNode->addChild('VariationSpecificValue', $esc((string) $value));
                foreach (array_slice($urls, 0, 12) as $url) {
                    $setNode->addChild('PictureURL', $esc((string) $url));
                }
            }
        }

        $newValues = array_values(array_unique(array_column($records, 'newValue')));
        $setList = $block->addChild('VariationSpecificsSet')->addChild('NameValueList');
        $setList->addChild('Name', $esc($newName));
        foreach ($newValues as $value) {
            $setList->addChild('Value', $esc($value));
        }

        $xmlBody = $xml->asXML();
        if ($xmlBody === false) {
            return ['success' => false, 'message' => 'Failed to build the variation type request.'];
        }
        $res = self::postReviseItemXml($endpoint, $compatLevel, $devId, $appId, $certId, $siteId, $itemId, $xmlBody, 'variation type', 'ReviseFixedPriceItem');
        if ($res['success']) {
            $specificsBySku = [];
            foreach ($records as $rec) {
                $specificsBySku[$rec['sku']] = [$newName => $rec['newValue']];
            }
            $res['message'] = 'Variation type changed from "'.$oldName.'" to "'.$newName.'": '.count($records).' variation(s) added under "'.$newName
                .'" first and the old "'.$oldName.'" variations deleted (same SKU, price, quantity, UPC and pictures). '.$res['message'];
            $res['aspects'] = [$newName => $newValues];
            $res['specifics_by_sku'] = $specificsBySku;
        } else {
            Log::warning('eBay variation type replace refused', [
                'itemId' => $itemId,
                'from' => $oldName,
                'to' => $newName,
                'error' => $res['message'],
                'variationCount' => count($records),
                'request' => preg_replace('#<eBayAuthToken>.*?</eBayAuthToken>#s', '<eBayAuthToken>x</eBayAuthToken>', $xmlBody),
            ]);
            $res['message'] = 'eBay would not change the variation type "'.$oldName.'" to "'.$newName.'": '.$res['message'].' Nothing was changed on the listing.';
        }

        return $res;
    }

    /**
     * Give variations a new value name (for example Color: "BLUE 1Pc" becomes "Blue") by deleting the old variation and
     * adding a new one with the same SKU, price, quantity, UPC/EAN/ISBN and the same pictures, instead of renaming the
     * old variation in place (eBay refuses that with 21916664). One request does both; when eBay refuses that because
     * the SKU is used twice, each variation is deleted and re-added in two requests and the old one is put back if
     * the add fails.
     *
     * @param  array<string, mixed>  $getItemResponse
     * @param  list<array{aspect: string, old: string, new: string}>  $replacements
     * @return array{success: bool, message: string, applied: list<array{aspect: string, old: string, new: string}>}
     */
    public static function replaceVariationValues(
        string $endpoint,
        string $compatLevel,
        string $devId,
        string $appId,
        string $certId,
        string $siteId,
        string $authToken,
        array $getItemResponse,
        array $replacements,
    ): array {
        $fail = static fn (string $m, array $applied = []): array => ['success' => false, 'message' => $m, 'applied' => $applied];
        $item = self::itemFromGetItem($getItemResponse);
        $itemId = trim(self::xmlText($item['ItemID'] ?? ''));
        $aspects = self::variationAspects($item);
        if ($itemId === '') {
            return $fail('Missing eBay item.');
        }
        if ($aspects === []) {
            return $fail('This eBay listing has no variations.');
        }

        // liveAspectName => [oldValue => newValue]
        $plan = [];
        foreach ($replacements as $r) {
            $aspectIn = trim((string) ($r['aspect'] ?? ''));
            $old = trim((string) ($r['old'] ?? ''));
            $new = trim((string) ($r['new'] ?? ''));
            if ($old === '' || $old === $new) {
                continue;
            }
            $live = null;
            foreach (array_keys($aspects) as $name) {
                if (self::aspectKey((string) $name) === self::aspectKey($aspectIn)) {
                    $live = (string) $name;
                    break;
                }
            }
            if ($live === null) {
                return $fail('This eBay listing no longer varies by "'.$aspectIn.'" (it now varies by '.implode(' and ', array_keys($aspects)).'). Pull the listing again.');
            }
            if ($new === '') {
                return $fail('The new name for "'.$old.'" is empty.');
            }
            if (! in_array($old, $aspects[$live], true)) {
                return $fail('The value "'.$old.'" is no longer on the eBay listing under '.$live.'. Pull the listing again.');
            }
            $plan[$live][$old] = $new;
        }
        if ($plan === []) {
            return ['success' => true, 'message' => 'No variation names to change.', 'applied' => []];
        }
        // Two values must not end up with the same name: eBay would see duplicate variations.
        foreach ($plan as $live => $map) {
            $final = [];
            foreach ($aspects[$live] as $value) {
                $key = mb_strtolower($map[$value] ?? $value);
                if (isset($final[$key])) {
                    return $fail('Two '.$live.' values would both be named "'.($map[$value] ?? $value).'". Give each value its own name.');
                }
                $final[$key] = true;
            }
        }

        // The variations as eBay holds them now: SKU, price, quantity still available, UPC/EAN/ISBN and values.
        $vars = $item['Variations']['Variation'] ?? [];
        if (isset($vars['SKU']) || isset($vars['VariationSpecifics'])) {
            $vars = [$vars];
        }
        $records = [];
        foreach ((array) $vars as $i => $variation) {
            if (! is_array($variation)) {
                continue;
            }
            $vSku = trim(self::xmlText($variation['SKU'] ?? ''));
            $specifics = [];
            foreach (EbayLiveListingMapper::nameValueMap($variation['VariationSpecifics']['NameValueList'] ?? null) as $n => $v) {
                $specifics[(string) $n] = trim((string) $v);
            }
            if ($vSku === '' || $specifics === []) {
                return $fail('A variation on this eBay listing has no SKU or no variation values, so nothing was changed.');
            }
            $price = trim(self::xmlText($variation['StartPrice'] ?? ''));
            $qty = trim(self::xmlText($variation['Quantity'] ?? ''));
            if (! is_numeric($price) || (float) $price <= 0 || ! is_numeric($qty)) {
                return $fail('Could not read the live price or quantity of variation '.$vSku.' from eBay, so nothing was changed.');
            }
            $sold = (int) trim(self::xmlText($variation['SellingStatus']['QuantitySold'] ?? '0'));
            $details = [];
            foreach (['UPC', 'EAN', 'ISBN'] as $key) {
                $code = trim(self::xmlText($variation['VariationProductListingDetails'][$key] ?? ''));
                if ($code !== '') {
                    $details[$key] = $code;
                }
            }
            $records[] = ['i' => (int) $i, 'sku' => $vSku, 'specifics' => $specifics, 'price' => number_format((float) $price, 2, '.', ''), 'qty' => max(0, (int) $qty - max(0, $sold)), 'details' => $details];
        }

        $specOf = static function (array $specifics, string $aspectName): string {
            foreach ($specifics as $n => $v) {
                if (self::aspectKey((string) $n) === self::aspectKey($aspectName)) {
                    return (string) $v;
                }
            }

            return '';
        };

        // Variations that carry a value to be replaced, with the replacement they get.
        $pairs = [];
        foreach ($records as $rec) {
            $newSpecifics = $rec['specifics'];
            $changes = [];
            foreach ($rec['specifics'] as $n => $v) {
                foreach ($plan as $live => $map) {
                    if (self::aspectKey((string) $live) === self::aspectKey((string) $n) && isset($map[$v])) {
                        $newSpecifics[$n] = $map[$v];
                        $changes[] = ['aspect' => (string) $live, 'old' => (string) $v, 'new' => $map[$v]];
                    }
                }
            }
            if ($changes !== []) {
                $pairs[] = ['old' => $rec, 'new' => ['specifics' => $newSpecifics] + $rec, 'changes' => $changes];
            }
        }
        if ($pairs === []) {
            return ['success' => true, 'message' => 'No variation uses the names to be replaced.', 'applied' => []];
        }

        // Pictures: each value's set; a new value gets the pictures of the value it replaces.
        $pics = self::extractVariationPictureState($item);
        $picByValue = [];
        foreach ($pics['sets'] as $set) {
            $picByValue[(string) $set['value']] = (array) $set['urls'];
        }
        $picCopy = []; // new value => old value
        if ($pics['name'] !== '') {
            foreach ($pairs as $p) {
                $oldValue = $specOf($p['old']['specifics'], $pics['name']);
                $newValue = $specOf($p['new']['specifics'], $pics['name']);
                if ($oldValue !== '' && $newValue !== '' && $oldValue !== $newValue) {
                    $picCopy[$newValue] = $oldValue;
                }
            }
        }

        $esc = static fn (string $v): string => self::escapeXmlElementText($v);
        $addSpecifics = static function (\SimpleXMLElement $node, array $specifics) use ($esc): void {
            $block = $node->addChild('VariationSpecifics');
            foreach ($specifics as $n => $v) {
                $nvl = $block->addChild('NameValueList');
                $nvl->addChild('Name', $esc((string) $n));
                $nvl->addChild('Value', $esc((string) $v));
            }
        };

        // One ReviseFixedPriceItem: delete $deleteRecs, add $addRecs; $present is what the listing holds afterwards, which
        // fixes the variation set and the picture sets (a value nobody uses any more is dropped).
        $send = function (array $deleteRecs, array $addRecs, array $present) use ($aspects, $pics, $picByValue, $picCopy, $specOf, $esc, $addSpecifics, $itemId, $endpoint, $compatLevel, $devId, $appId, $certId, $siteId, $authToken): array {
            $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><ReviseFixedPriceItemRequest xmlns="urn:ebay:apis:eBLBaseComponents"/>');
            $xml->addChild('RequesterCredentials')->addChild('eBayAuthToken', $esc($authToken));
            $xml->addChild('ErrorLanguage', 'en_US');
            $xml->addChild('WarningLevel', 'High');
            $itemNode = $xml->addChild('Item');
            $itemNode->addChild('ItemID', $esc($itemId));
            $block = $itemNode->addChild('Variations');
            foreach ($deleteRecs as $rec) {
                $node = $block->addChild('Variation');
                $node->addChild('Delete', 'true');
                $node->addChild('SKU', $esc($rec['sku']));
                $addSpecifics($node, $rec['specifics']);
            }
            foreach ($addRecs as $rec) {
                $node = $block->addChild('Variation');
                $node->addChild('SKU', $esc($rec['sku']));
                $node->addChild('StartPrice', $rec['price']);
                $node->addChild('Quantity', (string) $rec['qty']);
                if ($rec['details'] !== []) {
                    $details = $node->addChild('VariationProductListingDetails');
                    foreach ($rec['details'] as $k => $code) {
                        $details->addChild($k, $esc($code));
                    }
                }
                $addSpecifics($node, $rec['specifics']);
            }

            $used = []; // aspect => [value => true] over what stays on the listing
            foreach ($present as $rec) {
                foreach ($rec['specifics'] as $n => $v) {
                    $used[(string) $n][(string) $v] = true;
                }
            }

            if ($pics['name'] !== '') {
                $sets = [];
                foreach (array_keys($used[$pics['name']] ?? []) as $value) {
                    $urls = $picByValue[$value] ?? (isset($picCopy[$value]) ? ($picByValue[$picCopy[$value]] ?? []) : []);
                    if ($urls !== []) {
                        $sets[(string) $value] = $urls;
                    }
                }
                if ($sets !== []) {
                    $pictures = $block->addChild('Pictures');
                    $pictures->addChild('VariationSpecificName', $esc($pics['name']));
                    foreach ($sets as $value => $urls) {
                        $setNode = $pictures->addChild('VariationSpecificPictureSet');
                        $setNode->addChild('VariationSpecificValue', $esc((string) $value));
                        foreach (array_slice($urls, 0, 12) as $url) {
                            $setNode->addChild('PictureURL', $esc((string) $url));
                        }
                    }
                }
            }

            $setNode = $block->addChild('VariationSpecificsSet');
            foreach ($aspects as $name => $values) {
                $inUse = $used[(string) $name] ?? [];
                $list = [];
                foreach ($values as $value) {
                    if (isset($inUse[$value])) {
                        $list[$value] = true;
                    }
                }
                foreach (array_keys($inUse) as $value) {
                    $list[(string) $value] = true;
                }
                $nvl = $setNode->addChild('NameValueList');
                $nvl->addChild('Name', $esc((string) $name));
                foreach (array_keys($list) as $value) {
                    $nvl->addChild('Value', $esc((string) $value));
                }
            }

            $xmlBody = $xml->asXML();
            if ($xmlBody === false) {
                return ['success' => false, 'message' => 'Failed to build the request.'];
            }
            $res = self::postReviseItemXml($endpoint, $compatLevel, $devId, $appId, $certId, $siteId, $itemId, $xmlBody, 'variation names', 'ReviseFixedPriceItem');
            if (! $res['success']) {
                Log::warning('eBay variation replace refused', [
                    'itemId' => $itemId,
                    'deleted' => array_column($deleteRecs, 'sku'),
                    'added' => array_column($addRecs, 'sku'),
                    'error' => $res['message'],
                    'request' => preg_replace('#<eBayAuthToken>.*?</eBayAuthToken>#s', '<eBayAuthToken>x</eBayAuthToken>', $xmlBody),
                ]);
            }

            return $res;
        };

        $allChanges = [];
        foreach ($pairs as $p) {
            foreach ($p['changes'] as $c) {
                $allChanges[$c['aspect']."\0".$c['old']] = $c;
            }
        }
        $replacedIndexes = array_map(static fn ($p) => $p['old']['i'], $pairs);
        $skus = array_map(static fn ($p) => $p['old']['sku'], $pairs);
        $kept = array_values(array_filter($records, static fn ($r) => ! in_array($r['i'], $replacedIndexes, true)));

        // One request: delete every old variation and add its replacement.
        $res = $send(array_column($pairs, 'old'), array_column($pairs, 'new'), array_merge($kept, array_column($pairs, 'new')));
        if ($res['success']) {
            return ['success' => true, 'applied' => array_values($allChanges),
                'message' => count($pairs).' variation(s) deleted and added again with the new name, same SKU, price, quantity, UPC and pictures ('.implode(', ', array_slice($skus, 0, 8)).(count($skus) > 8 ? ', ...' : '').'). '.$res['message']];
        }

        $firstError = $res['message'];
        $skuClash = (bool) preg_match('/\bSKU\b/i', $firstError) && (bool) preg_match('/duplicate|already|unique|exist|same/i', $firstError);
        if (! $skuClash) {
            return $fail('eBay would not replace the variation(s): '.$firstError);
        }

        // eBay does not take the same SKU twice in one request: delete, then add, one variation at a time.
        $present = $records;
        $done = [];
        foreach ($pairs as $p) {
            $old = $p['old'];
            $new = $p['new'];
            if (count($present) <= 1) {
                return $fail('eBay would not replace '.$old['sku'].' in one request, and it is the only variation on the listing, so it cannot be deleted first: '.$firstError, array_values($done));
            }
            $without = array_values(array_filter($present, static fn ($r) => $r['i'] !== $old['i']));
            $d = $send([$old], [], $without);
            if (! $d['success']) {
                return $fail('eBay would not delete the old variation '.$old['sku'].': '.$d['message'].(count($done) ? ' '.count($done).' variation(s) were already replaced.' : ''), array_values($done));
            }
            $withNew = array_merge($without, [$new]);
            $a = $send([], [$new], $withNew);
            if (! $a['success']) {
                $back = $send([], [$old], array_merge($without, [$old]));

                return $fail('eBay deleted '.$old['sku'].' but would not add it with the new name: '.$a['message'].' '
                    .($back['success'] ? 'The old variation was put back as it was.' : 'Putting the old variation back also failed ('.$back['message'].'): add '.$old['sku'].' again by hand.'), array_values($done));
            }
            $present = $withNew;
            foreach ($p['changes'] as $c) {
                $done[$c['aspect']."\0".$c['old']] = $c;
            }
        }

        return ['success' => true, 'applied' => array_values($allChanges),
            'message' => count($pairs).' variation(s) replaced one at a time (eBay would not take the same SKU twice in one request).'];
    }

    /**
     * Rename values of the variation aspect(s) of a live multi-variation listing (for example Color: "Black" to
     * "Jet Black"). Every variation is re-sent with its SKU, price and quantity exactly as eBay holds them, with the
     * renamed value in its VariationSpecifics, in the VariationSpecificsSet and on the variation picture sets, so
     * nothing else on the listing changes. The aspect names themselves are not touched.
     *
     * @param  array<string, mixed>  $getItemResponse
     * @param  list<array{aspect: string, old: string, new: string}>  $renames
     * @return array{success: bool, message: string}
     */
    public static function renameVariationValues(
        string $endpoint,
        string $compatLevel,
        string $devId,
        string $appId,
        string $certId,
        string $siteId,
        string $authToken,
        array $getItemResponse,
        array $renames,
    ): array {
        $item = self::itemFromGetItem($getItemResponse);
        $itemId = trim(self::xmlText($item['ItemID'] ?? ''));
        $aspects = self::variationAspects($item);
        if ($itemId === '') {
            return ['success' => false, 'message' => 'Missing eBay item.'];
        }
        if ($aspects === []) {
            return ['success' => false, 'message' => 'This eBay listing has no variations to rename values on.'];
        }

        // liveAspectName => [oldValue => newValue]
        $plan = [];
        foreach ($renames as $r) {
            $aspectIn = trim((string) ($r['aspect'] ?? ''));
            $old = trim((string) ($r['old'] ?? ''));
            $new = trim((string) ($r['new'] ?? ''));
            if ($old === '' || $old === $new) {
                continue;
            }
            $live = null;
            foreach (array_keys($aspects) as $name) {
                if (self::aspectKey((string) $name) === self::aspectKey($aspectIn)) {
                    $live = (string) $name;
                    break;
                }
            }
            if ($live === null) {
                return ['success' => false, 'message' => 'This eBay listing no longer varies by "'.$aspectIn.'" (it now varies by '.implode(' and ', array_keys($aspects)).'). Import the listing again.'];
            }
            if ($new === '') {
                return ['success' => false, 'message' => 'The new name for "'.$old.'" is empty.'];
            }
            if (! in_array($old, $aspects[$live], true)) {
                return ['success' => false, 'message' => 'The value "'.$old.'" is no longer on the eBay listing under '.$live.'. Import the listing again.'];
            }
            $plan[$live][$old] = $new;
        }
        if ($plan === []) {
            return ['success' => true, 'message' => 'No value changes to send.'];
        }

        // Two values must not end up with the same name: eBay would see duplicate variations.
        foreach ($plan as $live => $map) {
            $final = [];
            foreach ($aspects[$live] as $value) {
                $after = $map[$value] ?? $value;
                $key = mb_strtolower($after);
                if (isset($final[$key])) {
                    return ['success' => false, 'message' => 'Two '.$live.' values would both be named "'.$after.'". Give each value its own name.'];
                }
                $final[$key] = true;
            }
        }
        $vars = $item['Variations']['Variation'] ?? [];
        if (isset($vars['SKU']) || isset($vars['VariationSpecifics'])) {
            $vars = [$vars];
        }
        $esc = static fn (string $v): string => self::escapeXmlElementText($v);
        $pics = self::extractVariationPictureState($item);

        // Value shown on eBay for an aspect once the renames in $applyPlan are applied.
        $mapWith = static function (array $applyPlan, string $aspectName, string $value): string {
            foreach ($applyPlan as $live => $map) {
                if (self::aspectKey((string) $live) === self::aspectKey($aspectName)) {
                    return $map[$value] ?? $value;
                }
            }

            return $value;
        };

        // Builds and sends one ReviseFixedPriceItem with $applyPlan applied to every variation, the variation
        // pictures and the variation set. The set is rebuilt from the values the variations really use, so it can
        // never list a value no variation has (or miss one a variation has): eBay refuses that with 21916664.
        $send = function (array $applyPlan) use ($item, $itemId, $vars, $esc, $pics, $mapWith, $aspects, $endpoint, $compatLevel, $devId, $appId, $certId, $siteId, $authToken): array {
            $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><ReviseFixedPriceItemRequest xmlns="urn:ebay:apis:eBLBaseComponents"/>');
            $xml->addChild('RequesterCredentials')->addChild('eBayAuthToken', $esc($authToken));
            $xml->addChild('ErrorLanguage', 'en_US');
            $xml->addChild('WarningLevel', 'High');
            $itemNode = $xml->addChild('Item');
            $itemNode->addChild('ItemID', $esc($itemId));
            $block = $itemNode->addChild('Variations');
            $sent = 0;
            $used = []; // aspect name => [value => true], after the renames
            foreach ((array) $vars as $variation) {
                if (! is_array($variation)) {
                    continue;
                }
                $vSku = trim(self::xmlText($variation['SKU'] ?? ''));
                $map = EbayLiveListingMapper::nameValueMap($variation['VariationSpecifics']['NameValueList'] ?? null);
                if ($vSku === '' || $map === []) {
                    return ['success' => false, 'message' => 'A variation on this eBay listing has no SKU or no variation values, so nothing was renamed.', 'sent' => 0];
                }
                $price = trim(self::xmlText($variation['StartPrice'] ?? ''));
                $qty = trim(self::xmlText($variation['Quantity'] ?? ''));
                if (! is_numeric($price) || (float) $price <= 0 || ! is_numeric($qty)) {
                    return ['success' => false, 'message' => 'Could not read the live price or quantity of variation '.$vSku.' from eBay, so nothing was renamed.', 'sent' => 0];
                }
                $node = $block->addChild('Variation');
                $node->addChild('SKU', $esc($vSku));
                $node->addChild('StartPrice', number_format((float) $price, 2, '.', ''));
                $node->addChild('Quantity', (string) max(0, (int) $qty));
                $specifics = $node->addChild('VariationSpecifics');
                foreach ($map as $name => $value) {
                    $after = $mapWith($applyPlan, (string) $name, (string) $value);
                    $used[(string) $name][$after] = true;
                    $nvl = $specifics->addChild('NameValueList');
                    $nvl->addChild('Name', $esc((string) $name));
                    $nvl->addChild('Value', $esc($after));
                }
                $sent++;
            }
            if ($sent === 0) {
                return ['success' => false, 'message' => 'This eBay listing has no variations to rename values on.', 'sent' => 0];
            }

            if ($pics['sets'] !== []) {
                $pictures = $block->addChild('Pictures');
                $pictures->addChild('VariationSpecificName', $esc($pics['name']));
                foreach ($pics['sets'] as $set) {
                    $setNode = $pictures->addChild('VariationSpecificPictureSet');
                    $setNode->addChild('VariationSpecificValue', $esc($mapWith($applyPlan, (string) $pics['name'], (string) $set['value'])));
                    foreach (array_slice((array) $set['urls'], 0, 12) as $url) {
                        $setNode->addChild('PictureURL', $esc((string) $url));
                    }
                }
            }

            $setNode = $block->addChild('VariationSpecificsSet');
            $written = [];
            foreach ($aspects as $name => $values) {
                $inUse = $used[(string) $name] ?? [];
                $list = [];
                foreach ($values as $value) {
                    $after = $mapWith($applyPlan, (string) $name, (string) $value);
                    if (isset($inUse[$after]) && ! isset($list[$after])) {
                        $list[$after] = true;
                    }
                }
                foreach (array_keys($inUse) as $after) {
                    $list[$after] = true;
                }
                $written[(string) $name] = true;
                $nvl = $setNode->addChild('NameValueList');
                $nvl->addChild('Name', $esc((string) $name));
                foreach (array_keys($list) as $after) {
                    $nvl->addChild('Value', $esc((string) $after));
                }
            }
            // An aspect the variations use that the listing's set did not list at all.
            foreach ($used as $name => $inUse) {
                if (isset($written[(string) $name])) {
                    continue;
                }
                $nvl = $setNode->addChild('NameValueList');
                $nvl->addChild('Name', $esc((string) $name));
                foreach (array_keys($inUse) as $after) {
                    $nvl->addChild('Value', $esc((string) $after));
                }
            }

            $xmlBody = $xml->asXML();
            if ($xmlBody === false) {
                return ['success' => false, 'message' => 'Failed to build the variation value rename request.', 'sent' => $sent];
            }
            $res = self::postReviseItemXml($endpoint, $compatLevel, $devId, $appId, $certId, $siteId, $itemId, $xmlBody, 'variation values', 'ReviseFixedPriceItem');
            $res['sent'] = $sent;
            if (! $res['success']) {
                // The exact request is logged (token removed) so a refusal can be compared with what eBay holds.
                Log::warning('eBay variation value rename refused', [
                    'itemId' => $itemId,
                    'plan' => $applyPlan,
                    'error' => $res['message'],
                    'variationCount' => $sent,
                    'pictureAspect' => $pics['name'],
                    'request' => preg_replace('#<eBayAuthToken>.*?</eBayAuthToken>#s', '<eBayAuthToken>x</eBayAuthToken>', $xmlBody),
                ]);
            }

            return $res;
        };

        // Every requested rename, in order, as [aspect, old, new].
        $steps = [];
        foreach ($plan as $live => $map) {
            foreach ($map as $old => $new) {
                $steps[] = ['aspect' => (string) $live, 'old' => (string) $old, 'new' => (string) $new];
            }
        }

        $res = $send($plan);
        if ($res['success']) {
            return ['success' => true, 'message' => count($steps).' variation value(s) renamed across '.$res['sent'].' variation(s). '.$res['message'], 'applied' => $steps];
        }

        // eBay rejects a request that renames many values at once as not matching the variations it holds
        // (21916664). One value at a time keeps every other variation identical to the live listing, so each
        // call changes a single value. A call that fails stops the run, and the ones before it stay applied.
        $firstError = $res['message'];
        $mismatch = str_contains($firstError, '21916664') || stripos($firstError, 'does not match with the variation specifics') !== false;
        if (! $mismatch || count($steps) < 2) {
            return ['success' => false, 'message' => 'eBay would not rename the variation values: '.$firstError, 'applied' => []];
        }

        $applied = [];
        $cumulative = [];
        foreach ($steps as $step) {
            $cumulative[$step['aspect']][$step['old']] = $step['new'];
            $one = $send($cumulative);
            if (! $one['success']) {
                $done = count($applied);
                $message = 'eBay would not rename "'.$step['old'].'" to "'.$step['new'].'": '.$one['message'];
                if ($done > 0) {
                    $message = $done.' of '.count($steps).' value(s) were renamed before this one failed. '.$message;
                }

                return ['success' => false, 'message' => $message, 'applied' => $applied];
            }
            $applied[] = $step;
        }

        return ['success' => true, 'message' => count($steps).' variation value(s) renamed one at a time (eBay would not take them in one request).', 'applied' => $applied];
    }

    /**
     * @param  array<string, mixed>  $getItemResponse
     * @return array<string, mixed>
     */
    public static function itemFromGetItem(array $getItemResponse): array
    {
        $item = $getItemResponse['Item'] ?? null;

        return is_array($item) ? $item : $getItemResponse;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    private static function allVariationValuesForName(array $item, string $name): array
    {
        $vars = $item['Variations']['Variation'] ?? null;
        if (! is_array($vars) || $vars === []) {
            return [];
        }
        if (isset($vars['SKU']) || isset($vars['VariationSpecifics'])) {
            $vars = [$vars];
        }
        $out = [];
        $seen = [];
        foreach ($vars as $variation) {
            if (! is_array($variation)) {
                continue;
            }
            $map = EbayLiveListingMapper::nameValueMap($variation['VariationSpecifics']['NameValueList'] ?? null);
            foreach ($map as $aspect => $value) {
                if (strcasecmp((string) $aspect, $name) !== 0) {
                    continue;
                }
                $value = trim((string) $value);
                $key = strtolower($value);
                if ($value === '' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = $value;
            }
        }

        return $out;
    }

    private static function xmlText(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (is_array($value)) {
            if (isset($value[0]) && ! is_array($value[0])) {
                return trim((string) $value[0]);
            }
            if (isset($value['#text'])) {
                return trim((string) $value['#text']);
            }
            $first = reset($value);

            return is_scalar($first) ? trim((string) $first) : '';
        }

        return trim((string) $value);
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
     * Write technical specifications that differ from the live listing.
     * Blank values are never sent. Matching values are left unchanged.
     * A name that already belongs to this SKU's variation is revised on that variation.
     *
     * @param  list<array{name: string, value: string}>  $overrides
     * @return array{success: bool, message: string}
     */
    public static function reviseTechnicalSpecifications(
        string $endpoint,
        string $compatLevel,
        string $devId,
        string $appId,
        string $certId,
        string $siteId,
        string $authToken,
        array $getItemResponse,
        string $sku,
        array $overrides,
    ): array {
        $item = self::itemFromGetItem($getItemResponse);
        $itemId = trim(self::xmlText($item['ItemID'] ?? ''));
        if ($itemId === '') {
            return ['success' => false, 'message' => 'GetItem response missing ItemID.'];
        }

        $clean = [];
        foreach ($overrides as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));
            if ($name !== '' && $value !== '') {
                $clean[] = ['name' => $name, 'value' => $value];
            }
        }
        if ($clean === []) {
            return ['success' => true, 'message' => 'No specifications to send. Blank values were left unchanged on eBay.'];
        }

        $variation = self::findVariationForSku($getItemResponse, $sku);
        $variationMap = $variation
            ? EbayLiveListingMapper::nameValueMap($variation['VariationSpecifics']['NameValueList'] ?? null)
            : [];
        $variationKeys = [];
        foreach (array_keys($variationMap) as $name) {
            $variationKeys[self::aspectKey($name)] = $name;
        }

        $itemRows = self::normalizeItemSpecificsNameValueLists($item['ItemSpecifics'] ?? null);
        $itemChanged = false;
        $variationChanged = false;
        $changedCount = 0;
        foreach ($clean as $row) {
            $key = self::aspectKey($row['name']);
            if (isset($variationKeys[$key])) {
                $currentName = $variationKeys[$key];
                $current = trim((string) ($variationMap[$currentName] ?? ''));
                if (strcasecmp($current, $row['value']) === 0) {
                    continue;
                }
                $variationMap[$currentName] = $row['value'];
                $variationChanged = true;
                $changedCount++;

                continue;
            }
            $replaced = false;
            foreach ($itemRows as &$existing) {
                if (self::aspectKey($existing['name']) !== $key) {
                    continue;
                }
                $replaced = true;
                $current = trim(implode(', ', array_map(static fn ($value) => trim((string) $value), $existing['values'] ?? [])));
                if (strcasecmp($current, $row['value']) === 0) {
                    break;
                }
                $existing['name'] = $row['name'];
                $existing['values'] = [$row['value']];
                $itemChanged = true;
                $changedCount++;
                break;
            }
            unset($existing);
            if (! $replaced) {
                $itemRows[] = ['name' => $row['name'], 'values' => [$row['value']]];
                $itemChanged = true;
                $changedCount++;
            }
        }

        if (! $itemChanged && ! $variationChanged) {
            return ['success' => true, 'message' => 'No values changed. Blank and matching values were not sent to eBay.'];
        }

        $messages = [];
        if ($itemChanged) {
            $result = self::postSpecificRows($endpoint, $compatLevel, $devId, $appId, $certId, $siteId, $authToken, $itemId, $itemRows, 'ReviseItem', 'ReviseItemRequest', null, null, false, $getItemResponse);
            $messages[] = $result['message'];
            if (! $result['success']) {
                return ['success' => false, 'message' => implode(' ', $messages)];
            }
        }
        if ($variationChanged) {
            $rows = [];
            foreach ($variationMap as $name => $value) {
                if (trim((string) $value) !== '') {
                    $rows[] = ['name' => (string) $name, 'values' => [(string) $value]];
                }
            }
            $result = self::postSpecificRows($endpoint, $compatLevel, $devId, $appId, $certId, $siteId, $authToken, $itemId, $rows, 'ReviseFixedPriceItem', 'ReviseFixedPriceItemRequest', $sku, 'VariationSpecifics', false, $getItemResponse);
            $messages[] = $result['message'];
            if (! $result['success']) {
                return ['success' => false, 'message' => implode(' ', $messages)];
            }
        }

        $summary = 'Sent '.$changedCount.' changed value'.($changedCount === 1 ? '' : 's').'. Blank values were not sent.';

        return ['success' => true, 'message' => $summary.' '.implode(' ', $messages)];
    }

    /**
     * Set the UPC on a live listing. The UPC lives in ProductListingDetails (or, for a variation SKU,
     * VariationProductListingDetails), not in the item specifics. Nothing is sent when eBay already has it.
     *
     * @param  array<string, mixed>  $getItemResponse
     * @return array{success: bool, message: string}
     */
    public static function reviseUpc(
        string $endpoint,
        string $compatLevel,
        string $devId,
        string $appId,
        string $certId,
        string $siteId,
        string $authToken,
        array $getItemResponse,
        string $sku,
        string $upc,
    ): array {
        $upc = trim($upc);
        if ($upc === '') {
            return ['success' => true, 'message' => 'No UPC to send.'];
        }
        $item = self::itemFromGetItem($getItemResponse);
        $itemId = trim(self::xmlText($item['ItemID'] ?? ''));
        if ($itemId === '') {
            return ['success' => false, 'message' => 'GetItem response missing ItemID.'];
        }

        $variation = self::findVariationForSku($getItemResponse, $sku);
        if ($variation !== null) {
            $details = is_array($variation['VariationProductListingDetails'] ?? null) ? $variation['VariationProductListingDetails'] : [];
        } else {
            $details = is_array($item['ProductListingDetails'] ?? null) ? $item['ProductListingDetails'] : [];
        }
        $current = preg_replace('/\D+/', '', self::xmlText($details['UPC'] ?? '')) ?? '';
        if ($current === $upc) {
            // The listing details have it. A "UPC" item specific that still says "Does not apply" is what the eBay
            // listing page shows, so that one is brought in line too.
            $specificNote = self::syncUpcItemSpecific($endpoint, $compatLevel, $devId, $appId, $certId, $siteId, $authToken, $getItemResponse, $sku, $upc);

            return ['success' => true, 'message' => $specificNote === '' ? 'UPC '.$upc.' is already on the listing.' : 'UPC '.$upc.' '.$specificNote];
        }

        $root = $variation !== null ? 'ReviseFixedPriceItemRequest' : 'ReviseItemRequest';
        $callName = $variation !== null ? 'ReviseFixedPriceItem' : 'ReviseItem';
        $buildXml = function (?array $shippingProfile, bool $fixPackage = false) use ($root, $authToken, $itemId, $variation, $sku, $upc, $getItemResponse): string|false {
            $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><'.$root.' xmlns="urn:ebay:apis:eBLBaseComponents"/>');
            $creds = $xml->addChild('RequesterCredentials');
            $creds->addChild('eBayAuthToken', $authToken);
            $xml->addChild('ErrorLanguage', 'en_US');
            $xml->addChild('WarningLevel', 'High');
            $itemNode = $xml->addChild('Item');
            $itemNode->addChild('ItemID', $itemId);
            if ($shippingProfile !== null) {
                self::addShippingProfile($xml, $itemNode, $shippingProfile);
            }
            if ($fixPackage) {
                self::addPackageDetails($itemNode, $getItemResponse);
            }
            if ($variation !== null) {
                $node = $itemNode->addChild('Variations')->addChild('Variation');
                $node->addChild('SKU', htmlspecialchars($sku, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
                $node->addChild('VariationProductListingDetails')->addChild('UPC', $upc);
            } else {
                $itemNode->addChild('ProductListingDetails')->addChild('UPC', $upc);
            }

            return $xml->asXML();
        };
        $xmlBody = $buildXml(null);
        if ($xmlBody === false) {
            return ['success' => false, 'message' => 'Failed to build '.$callName.' XML for UPC.'];
        }

        $result = self::postReviseItemXml($endpoint, $compatLevel, $devId, $appId, $certId, $siteId, $itemId, $xmlBody, 'UPC', $callName);
        // eBay checks the whole listing on every revise and reports one problem at a time. When it says the shipping
        // service is missing (21915409) send the listing's own shipping policy along; when it then asks for a valid
        // package type (#739) send the package details too. At most two retries, each adding what eBay asked for.
        $profile = null;
        $fixPackage = false;
        for ($retry = 0; $retry < 2 && ! ($result['success'] ?? false); $retry++) {
            $message = (string) ($result['message'] ?? '');
            $nextProfile = $profile;
            $nextPackage = $fixPackage;
            if ($profile === null && self::isMissingShippingError($message)) {
                $nextProfile = self::shippingRetryProfile($getItemResponse);
            }
            if (! $fixPackage && self::isPackageTypeError($message)) {
                $nextPackage = true;
            }
            if ($nextProfile === $profile && $nextPackage === $fixPackage) {
                break;
            }
            $profile = $nextProfile;
            $fixPackage = $nextPackage;
            $retryBody = $buildXml($profile, $fixPackage);
            if ($retryBody === false) {
                break;
            }
            $result = self::postReviseItemXml($endpoint, $compatLevel, $devId, $appId, $certId, $siteId, $itemId, $retryBody, 'UPC (with listing shipping details)', $callName);
        }

        if (! ($result['success'] ?? false)) {
            return ['success' => false, 'message' => (string) ($result['message'] ?? 'UPC update failed.')];
        }
        $specificNote = self::syncUpcItemSpecific($endpoint, $compatLevel, $devId, $appId, $certId, $siteId, $authToken, $getItemResponse, $sku, $upc);

        return ['success' => true, 'message' => 'UPC '.$upc.' sent. '.($result['message'] ?? '').($specificNote !== '' ? ' '.$specificNote : '')];
    }

    /**
     * When the listing carries a "UPC" item specific (often "Does not apply") that is not this code, set it to the code.
     * Best effort: the UPC in the listing details is already saved, so a refusal here is only reported.
     * Returns a short note, or '' when there was nothing to change.
     *
     * @param  array<string, mixed>  $getItemResponse
     */
    private static function syncUpcItemSpecific(
        string $endpoint,
        string $compatLevel,
        string $devId,
        string $appId,
        string $certId,
        string $siteId,
        string $authToken,
        array $getItemResponse,
        string $sku,
        string $upc,
    ): string {
        $item = self::itemFromGetItem($getItemResponse);
        $specName = null;
        foreach (self::normalizeItemSpecificsNameValueLists($item['ItemSpecifics'] ?? null) as $row) {
            if (self::aspectKey((string) ($row['name'] ?? '')) !== 'upc') {
                continue;
            }
            $value = trim(implode(', ', array_map(static fn ($v) => trim((string) $v), $row['values'] ?? [])));
            if ((preg_replace('/\D+/', '', $value) ?? '') !== $upc) {
                $specName = (string) $row['name'];
            }
            break;
        }
        if ($specName === null) {
            return '';
        }
        try {
            $sync = self::reviseTechnicalSpecifications($endpoint, $compatLevel, $devId, $appId, $certId, $siteId, $authToken, $getItemResponse, $sku, [['name' => $specName, 'value' => $upc]]);
        } catch (\Throwable $e) {
            return 'UPC item specific not changed: '.$e->getMessage();
        }

        return ($sync['success'] ?? false)
            ? 'UPC item specific "'.$specName.'" set.'
            : 'UPC item specific "'.$specName.'" not changed: '.(string) ($sync['message'] ?? '');
    }

    /**
     * Remove one item specific from a live listing. ReviseItem replaces the whole ItemSpecifics block,
     * so every other specific is sent back unchanged. Nothing is sent when the listing does not have it.
     *
     * @param  array<string, mixed>  $getItemResponse
     * @return array{success: bool, removed: bool, message: string}
     */
    public static function removeItemSpecific(
        string $endpoint,
        string $compatLevel,
        string $devId,
        string $appId,
        string $certId,
        string $siteId,
        string $authToken,
        array $getItemResponse,
        string|array $name,
        bool $exactNames = false,
    ): array {
        $item = self::itemFromGetItem($getItemResponse);
        $itemId = trim(self::xmlText($item['ItemID'] ?? ''));
        if ($itemId === '') {
            return ['success' => false, 'removed' => false, 'message' => 'GetItem response missing ItemID.'];
        }

        // One name or several: a single ReviseItem drops all of them.
        // $exactNames: only a specific spelled exactly like the name goes (case and spacing aside). "Type-" and "Type:"
        // must not take the real "Type" with them, which eBay requires in many categories.
        $nameKey = static fn (string $n): string => $exactNames
            ? mb_strtolower(trim(preg_replace('/\s+/u', ' ', $n) ?? $n))
            : self::aspectKey($n);
        $wanted = [];
        foreach ((array) $name as $candidate) {
            $wanted[$nameKey((string) $candidate)] = true;
        }
        $kept = [];
        $found = false;
        foreach (self::normalizeItemSpecificsNameValueLists($item['ItemSpecifics'] ?? null) as $row) {
            if (isset($wanted[$nameKey((string) ($row['name'] ?? ''))])) {
                $found = true;

                continue;
            }
            $kept[] = $row;
        }
        if (! $found) {
            return ['success' => true, 'removed' => false, 'message' => 'Not on the eBay listing.'];
        }

        $result = self::postSpecificRows($endpoint, $compatLevel, $devId, $appId, $certId, $siteId, $authToken, $itemId, $kept, 'ReviseItem', 'ReviseItemRequest', null, null);
        // A listing with no valid shipping service rejects every revise, including one that only
        // removes item specifics. Retry once with the eBay 1 shipping policy so the delete can land.
        if (! ($result['success'] ?? false) && self::isMissingShippingError((string) ($result['message'] ?? ''))) {
            $result = self::postSpecificRows($endpoint, $compatLevel, $devId, $appId, $certId, $siteId, $authToken, $itemId, $kept, 'ReviseItem', 'ReviseItemRequest', null, null, true);
        }

        return [
            'success' => (bool) ($result['success'] ?? false),
            'removed' => (bool) ($result['success'] ?? false),
            'message' => (string) ($result['message'] ?? ''),
        ];
    }

    /**
     * The shipping policy the listing already uses (from GetItem), else the configured default. Used to retry a revise
     * that eBay refused with "add at least one valid shipping service".
     *
     * @param  array<string, mixed>  $getItemResponse
     * @return array{id: string, name: string}|null
     */
    private static function shippingRetryProfile(array $getItemResponse): ?array
    {
        $item = self::itemFromGetItem($getItemResponse);
        $own = $item['SellerProfiles']['SellerShippingProfile'] ?? null;
        if (is_array($own)) {
            $id = trim(self::xmlText($own['ShippingProfileID'] ?? ''));
            $name = trim(self::xmlText($own['ShippingProfileName'] ?? ''));
            if ($id !== '' || $name !== '') {
                return ['id' => $id, 'name' => $name];
            }
        }

        $id = trim((string) config('listing_manager.ebay1_defaults.shipping_policy_id', ''));
        $name = trim((string) config('listing_manager.ebay1_defaults.shipping_policy_name', 'As Per Weight'));

        return ($id !== '' || $name !== '') ? ['id' => $id, 'name' => $name] : null;
    }

    /** @param  array{id: string, name: string}  $profile */
    private static function addShippingProfile(\SimpleXMLElement $xml, \SimpleXMLElement $itemNode, array $profile): void
    {
        $node = $itemNode->addChild('SellerProfiles')->addChild('SellerShippingProfile');
        if (($profile['id'] ?? '') !== '') {
            $node->addChild('ShippingProfileID', $profile['id']);
        } else {
            $node->addChild('ShippingProfileName', htmlspecialchars((string) $profile['name'], ENT_XML1 | ENT_COMPAT, 'UTF-8'));
        }
        // Only the policy is sent. eBay rejects "Item.ShippingDetails" as a DeletedField path (#10201), so the old
        // shipping block is left alone and the policy supplies the service on the revise.
    }

    /**
     * Send the package block of the listing again with a valid package type. eBay (#739) refuses a revise of a listing
     * whose package type is blank or retired; the sizes and weight already on the listing are sent back unchanged.
     *
     * @param  array<string, mixed>  $getItemResponse
     */
    private static function addPackageDetails(\SimpleXMLElement $itemNode, array $getItemResponse): void
    {
        $item = self::itemFromGetItem($getItemResponse);
        $current = is_array($item['ShippingPackageDetails'] ?? null) ? $item['ShippingPackageDetails'] : [];

        $system = 'English';
        foreach (['PackageDepth', 'PackageLength', 'PackageWidth', 'WeightMajor'] as $field) {
            $found = is_array($current[$field] ?? null) ? ($current[$field]['@attributes']['measurementSystem'] ?? null) : null;
            if (is_string($found) && strcasecmp($found, 'Metric') === 0) {
                $system = 'Metric';
                break;
            }
        }

        $package = $itemNode->addChild('ShippingPackageDetails');
        $package->addChild('MeasurementUnit', $system);
        foreach (['PackageDepth', 'PackageLength', 'PackageWidth'] as $field) {
            $value = self::xmlText($current[$field] ?? '');
            if (is_numeric($value) && (float) $value > 0) {
                $package->addChild($field, $value);
            }
        }
        $type = self::xmlText($current['ShippingPackage'] ?? '');
        if ($type === '' || strcasecmp($type, 'None') === 0) {
            $type = 'PackageThickEnvelope';
        }
        $package->addChild('ShippingPackage', htmlspecialchars($type, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
        $major = self::xmlText($current['WeightMajor'] ?? '');
        $minor = self::xmlText($current['WeightMinor'] ?? '');
        if ((is_numeric($major) && (float) $major > 0) || (is_numeric($minor) && (float) $minor > 0)) {
            $package->addChild('WeightMajor', is_numeric($major) ? $major : '0');
            $package->addChild('WeightMinor', is_numeric($minor) ? $minor : '0');
        }
    }

    private static function isPackageTypeError(string $message): bool
    {
        return (bool) preg_match('/shipping package|package type|#739\b|\b739\b/i', $message);
    }

    private static function isMissingShippingError(string $message): bool
    {
        return (bool) preg_match('/shipping service|21915409/i', $message);
    }

    /**
     * @param  list<array{name: string, values: list<string>}>  $rows
     * @return array{success: bool, message: string}
     */
    private static function postSpecificRows(
        string $endpoint,
        string $compatLevel,
        string $devId,
        string $appId,
        string $certId,
        string $siteId,
        string $authToken,
        string $itemId,
        array $rows,
        string $callName,
        string $root,
        ?string $variationSku,
        ?string $specificsNode,
        bool $ensureShippingPolicy = false,
        ?array $getItemResponse = null,
        ?array $shippingProfile = null,
        bool $fixPackage = false,
    ): array {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><'.$root.' xmlns="urn:ebay:apis:eBLBaseComponents"/>');
        $creds = $xml->addChild('RequesterCredentials');
        $creds->addChild('eBayAuthToken', $authToken);
        $xml->addChild('ErrorLanguage', 'en_US');
        $xml->addChild('WarningLevel', 'High');
        $itemNode = $xml->addChild('Item');
        $itemNode->addChild('ItemID', $itemId);
        if ($ensureShippingPolicy) {
            $policyName = trim((string) config('listing_manager.ebay1_defaults.shipping_policy_name', 'As Per Weight'));
            $policyId = trim((string) config('listing_manager.ebay1_defaults.shipping_policy_id', ''));
            if ($policyName !== '' || $policyId !== '') {
                $profile = $itemNode->addChild('SellerProfiles')->addChild('SellerShippingProfile');
                if ($policyId !== '') {
                    $profile->addChild('ShippingProfileID', $policyId);
                } else {
                    $profile->addChild('ShippingProfileName', htmlspecialchars($policyName, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
                }
                // The old shipping block is not deleted: eBay rejects "Item.ShippingDetails" as a DeletedField path (#10201).
            }
        }
        if ($shippingProfile !== null && ! $ensureShippingPolicy) {
            self::addShippingProfile($xml, $itemNode, $shippingProfile);
        }
        if ($fixPackage && $getItemResponse !== null) {
            self::addPackageDetails($itemNode, $getItemResponse);
        }
        $specParent = $itemNode;
        if ($variationSku !== null && $specificsNode !== null) {
            $variation = $itemNode->addChild('Variations')->addChild('Variation');
            $variation->addChild('SKU', $variationSku);
            $specParent = $variation;
        }
        $spec = $specParent->addChild($specificsNode ?? 'ItemSpecifics');
        foreach ($rows as $row) {
            $values = array_values(array_filter(array_map(static fn ($v) => trim((string) $v), $row['values'] ?? []), static fn ($v) => $v !== ''));
            if ($values === [] || trim((string) ($row['name'] ?? '')) === '') {
                continue;
            }
            $nvl = $spec->addChild('NameValueList');
            $nvl->addChild('Name', (string) $row['name']);
            foreach ($values as $value) {
                $nvl->addChild('Value', $value);
            }
        }
        $xmlBody = $xml->asXML();
        if ($xmlBody === false) {
            return ['success' => false, 'message' => 'Failed to build '.$callName.' XML.'];
        }

        $result = self::postReviseItemXml($endpoint, $compatLevel, $devId, $appId, $certId, $siteId, $itemId, $xmlBody, 'technical specifications', $callName);
        // eBay reports one listing problem at a time. Missing shipping service: send the listing's shipping policy along.
        // Invalid package type (#739): send the package block too. Each flag is added once, so this ends after two retries.
        if (! ($result['success'] ?? false) && $getItemResponse !== null && ! $ensureShippingPolicy) {
            $message = (string) ($result['message'] ?? '');
            $nextProfile = $shippingProfile;
            $nextPackage = $fixPackage;
            if ($shippingProfile === null && self::isMissingShippingError($message)) {
                $nextProfile = self::shippingRetryProfile($getItemResponse);
            }
            if (! $fixPackage && self::isPackageTypeError($message)) {
                $nextPackage = true;
            }
            if ($nextProfile !== $shippingProfile || $nextPackage !== $fixPackage) {
                return self::postSpecificRows($endpoint, $compatLevel, $devId, $appId, $certId, $siteId, $authToken, $itemId, $rows, $callName, $root, $variationSku, $specificsNode, false, $getItemResponse, $nextProfile, $nextPackage);
            }
        }

        return $result;
    }

    private static function aspectKey(string $name): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', $name) ?? $name);
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
        $merged = self::applyRequiredAmplifierTypeFallback($merged);
        $merged = self::applyRequiredNamedFallback(
            $merged,
            'Form Factor',
            (string) config('services.ebay.form_factor_fallback_value', 'Handheld')
        );
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
     * Guitar/amp categories (e.g. eBay3) require Amplifier Type when revising item specifics.
     *
     * @param  array<int, array{name: string, values: list<string>}>  $rows
     * @return array<int, array{name: string, values: list<string>}>
     */
    private static function applyRequiredAmplifierTypeFallback(array $rows): array
    {
        foreach ($rows as $row) {
            if (strtolower(trim($row['name'])) === 'amplifier type') {
                return $rows;
            }
        }

        $value = config('services.ebay.amplifier_type_fallback_value');
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return $rows;
        }

        $rows[] = ['name' => 'Amplifier Type', 'values' => [$value]];

        return $rows;
    }

    /**
     * Some categories reject a bullet revise until a named item specific is present.
     *
     * @param  array<int, array{name: string, values: list<string>}>  $rows
     * @return array<int, array{name: string, values: list<string>}>
     */
    private static function applyRequiredNamedFallback(array $rows, string $name, string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return $rows;
        }
        $want = strtolower(trim($name));
        foreach ($rows as $row) {
            if (strtolower(trim($row['name'])) === $want) {
                return $rows;
            }
        }
        $rows[] = ['name' => $name, 'values' => [$value]];

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
     * Update a live listing title. GTC / fixed-price listings must use
     * ReviseFixedPriceItem — ReviseItem can Ack Success and still leave Title unchanged.
     *
     * @return array{success: bool, message: string}
     */
    public static function reviseListingTitle(
        string $endpoint,
        string $compatLevel,
        string $devId,
        string $appId,
        string $certId,
        string $siteId,
        string $authToken,
        string $itemId,
        string $title,
        string $sku = '',
    ): array {
        $itemId = trim($itemId);
        $title = mb_substr(trim($title), 0, 80);
        $sku = trim($sku);
        if ($itemId === '' || $title === '') {
            return ['success' => false, 'message' => 'Item ID and title are required.'];
        }

        $fixed = self::postTitleReviseCall(
            $endpoint,
            $compatLevel,
            $devId,
            $appId,
            $certId,
            $siteId,
            $authToken,
            $itemId,
            $title,
            'ReviseFixedPriceItem',
            $sku,
        );

        // Seller Hub / Inventory listings can Ack Success on Trading while the
        // visible title stays on the inventory item. Inventory is source of truth when it exists.
        if ($sku !== '' && $authToken !== '') {
            $inventory = self::updateSellInventoryTitle($authToken, $sku, $title);
            if ($inventory['exists'] ?? false) {
                if ($inventory['success'] ?? false) {
                    return ['success' => true, 'message' => 'Title updated on eBay item '.$itemId.' (Seller Hub inventory for SKU '.$sku.').'];
                }

                return [
                    'success' => false,
                    'message' => (string) ($inventory['message'] ?? 'eBay Inventory title update failed.'),
                ];
            }
        }

        if ($fixed['success']) {
            // eBay can answer Success and leave the listing title as it was (for example a listing that already
            // has sales). Read the listing back so "updated" is only reported when the title really changed.
            $live = self::fetchLiveTitle($endpoint, $compatLevel, $devId, $appId, $certId, $siteId, $authToken, $itemId);
            $norm = static fn (string $t): string => mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
            if ($live !== null && $live !== '' && $norm($live) !== $norm($title)) {
                Log::warning('eBay title revise acked but listing title unchanged', ['item_id' => $itemId, 'sent' => $title, 'live' => $live]);

                return [
                    'success' => false,
                    'message' => 'eBay accepted the title for item '.$itemId.' but the listing still shows "'.$live.'". '
                        .'eBay does not always allow a title change on a listing that already has sales; change it in Seller Hub if so.',
                ];
            }

            return $fixed;
        }

        $fixedMsg = (string) ($fixed['message'] ?? '');
        $tryReviseItem = self::isUsageLimitError($fixedMsg) || self::isWrongListingTypeError($fixedMsg);
        if (! $tryReviseItem) {
            return $fixed;
        }

        $reviseItem = self::postTitleReviseCall(
            $endpoint,
            $compatLevel,
            $devId,
            $appId,
            $certId,
            $siteId,
            $authToken,
            $itemId,
            $title,
            'ReviseItem',
            $sku,
        );
        if ($reviseItem['success']) {
            return $reviseItem;
        }

        if (self::isUsageLimitError($fixedMsg) || self::isUsageLimitError((string) ($reviseItem['message'] ?? ''))) {
            return [
                'success' => false,
                'message' => 'eBay API usage limit reached on this account. Wait for the daily Trading API quota to reset, then try again. '.$fixedMsg,
            ];
        }

        return $reviseItem['message'] !== '' ? $reviseItem : $fixed;
    }

    /**
     * The title eBay currently shows for an item (GetItem), or null when it cannot be read.
     */
    private static function fetchLiveTitle(
        string $endpoint,
        string $compatLevel,
        string $devId,
        string $appId,
        string $certId,
        string $siteId,
        string $authToken,
        string $itemId,
    ): ?string {
        try {
            $xmlBody = '<?xml version="1.0" encoding="utf-8"?>'
                .'<GetItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">'
                .'<RequesterCredentials><eBayAuthToken>'.self::escapeXmlElementText($authToken).'</eBayAuthToken></RequesterCredentials>'
                .'<ItemID>'.self::escapeXmlElementText($itemId).'</ItemID>'
                .'<OutputSelector>Title</OutputSelector>'
                .'</GetItemRequest>';
            $response = Http::withoutVerifying()
                ->connectTimeout(10)
                ->timeout(30)
                ->withHeaders([
                    'X-EBAY-API-COMPATIBILITY-LEVEL' => $compatLevel,
                    'X-EBAY-API-DEV-NAME' => $devId,
                    'X-EBAY-API-APP-NAME' => $appId,
                    'X-EBAY-API-CERT-NAME' => $certId,
                    'X-EBAY-API-CALL-NAME' => 'GetItem',
                    'X-EBAY-API-SITEID' => $siteId,
                    'Content-Type' => 'text/xml',
                ])
                ->withBody($xmlBody, 'text/xml')
                ->post($endpoint);
            if (! $response->successful()) {
                return null;
            }
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($response->body());
            if ($xml === false) {
                return null;
            }
            $data = json_decode(json_encode($xml), true);
            $ack = (string) ($data['Ack'] ?? '');
            $title = $data['Item']['Title'] ?? null;

            return ($ack === 'Success' || $ack === 'Warning') && is_string($title) ? trim($title) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Seller Hub listings store the visible title on the Sell Inventory item.
     *
     * @return array{success: bool, exists: bool, message: string}
     */
    public static function updateSellInventoryTitle(string $bearerToken, string $sku, string $title): array
    {
        $sku = trim($sku);
        $title = mb_substr(trim($title), 0, 80);
        if ($sku === '' || $bearerToken === '' || $title === '') {
            return ['success' => false, 'exists' => false, 'message' => 'SKU and title are required.'];
        }

        $url = 'https://api.ebay.com/sell/inventory/v1/inventory_item/'.rawurlencode($sku);
        $headers = [
            'Authorization' => 'Bearer '.$bearerToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Content-Language' => 'en-US',
        ];

        try {
            $get = Http::withoutVerifying()
                ->withHeaders($headers)
                ->timeout(30)
                ->get($url);

            if ($get->status() === 404) {
                return ['success' => false, 'exists' => false, 'message' => 'No eBay Inventory item for this SKU.'];
            }
            if (! $get->successful()) {
                Log::warning('eBay Inventory GET failed', [
                    'sku' => $sku,
                    'status' => $get->status(),
                    'body' => mb_substr($get->body(), 0, 500),
                ]);

                return [
                    'success' => false,
                    'exists' => false,
                    'message' => 'eBay Inventory lookup failed (HTTP '.$get->status().').',
                ];
            }

            $item = $get->json();
            if (! is_array($item)) {
                return ['success' => false, 'exists' => true, 'message' => 'eBay Inventory item response was invalid.'];
            }

            unset($item['sku'], $item['locale']);
            if (! isset($item['product']) || ! is_array($item['product'])) {
                $item['product'] = [];
            }
            $item['product']['title'] = $title;

            $put = Http::withoutVerifying()
                ->withHeaders($headers)
                ->timeout(45)
                ->withBody((string) json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 'application/json')
                ->put($url);

            if ($put->successful() || $put->status() === 204) {
                Log::info('eBay Inventory title updated', ['sku' => $sku]);

                return ['success' => true, 'exists' => true, 'message' => 'Inventory title updated.'];
            }

            $msg = mb_substr($put->body(), 0, 400);
            Log::warning('eBay Inventory title PUT failed', [
                'sku' => $sku,
                'status' => $put->status(),
                'body' => $msg,
            ]);

            return [
                'success' => false,
                'exists' => true,
                'message' => 'eBay Inventory title update failed: '.$msg,
            ];
        } catch (\Throwable $e) {
            Log::warning('eBay Inventory title update exception', ['sku' => $sku, 'error' => $e->getMessage()]);

            return ['success' => false, 'exists' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Move a live fixed-price listing to another eBay leaf category.
     * ReviseFixedPriceItem with only ItemID + PrimaryCategory/CategoryID.
     *
     * @return array{success: bool, message: string}
     */
    public static function reviseListingCategory(
        string $endpoint,
        string $compatLevel,
        string $devId,
        string $appId,
        string $certId,
        string $siteId,
        string $authToken,
        string $itemId,
        string $categoryId,
    ): array {
        $itemId = trim($itemId);
        $categoryId = trim($categoryId);
        if ($itemId === '' || ! preg_match('/^\d+$/', $categoryId)) {
            return ['success' => false, 'message' => 'Item ID and a numeric eBay category ID are required.'];
        }

        $xmlBody = '<?xml version="1.0" encoding="utf-8"?>'
            .'<ReviseFixedPriceItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">'
            .'<RequesterCredentials><eBayAuthToken>'.self::escapeXmlElementText($authToken).'</eBayAuthToken></RequesterCredentials>'
            .'<ErrorLanguage>en_US</ErrorLanguage><WarningLevel>High</WarningLevel>'
            .'<Item><ItemID>'.self::escapeXmlElementText($itemId).'</ItemID>'
            .'<PrimaryCategory><CategoryID>'.$categoryId.'</CategoryID></PrimaryCategory>'
            .'</Item></ReviseFixedPriceItemRequest>';

        $result = self::postTradingCall(
            $endpoint,
            $compatLevel,
            $devId,
            $appId,
            $certId,
            $siteId,
            $itemId,
            $xmlBody,
            'ReviseFixedPriceItem',
            'category',
        );

        return [
            'success' => (bool) ($result['success'] ?? false),
            'message' => ($result['success'] ?? false)
                ? 'Category changed to '.$categoryId.' on eBay item '.$itemId.'.'
                : (string) ($result['message'] ?? 'eBay rejected the category change.'),
        ];
    }

    /**
     * @return array{success: bool, message: string}
     */
    private static function postTitleReviseCall(
        string $endpoint,
        string $compatLevel,
        string $devId,
        string $appId,
        string $certId,
        string $siteId,
        string $authToken,
        string $itemId,
        string $title,
        string $callName,
        string $sku = '',
    ): array {
        $root = $callName === 'ReviseFixedPriceItem' ? 'ReviseFixedPriceItemRequest' : 'ReviseItemRequest';
        $tokenEsc = htmlspecialchars($authToken, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $idEsc = htmlspecialchars($itemId, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $titleCdata = str_replace(']]>', ']]]]><![CDATA[>', $title);
        $xmlBody = '<?xml version="1.0" encoding="utf-8"?>'
            .'<'.$root.' xmlns="urn:ebay:apis:eBLBaseComponents">'
            .'<RequesterCredentials><eBayAuthToken>'.$tokenEsc.'</eBayAuthToken></RequesterCredentials>'
            .'<ErrorLanguage>en_US</ErrorLanguage><WarningLevel>High</WarningLevel>'
            .'<Item><ItemID>'.$idEsc.'</ItemID>'
            .'<Title><![CDATA['.$titleCdata.']]></Title>'
            .'</Item></'.$root.'>';

        $result = self::postTradingCall(
            $endpoint,
            $compatLevel,
            $devId,
            $appId,
            $certId,
            $siteId,
            $itemId,
            $xmlBody,
            $callName,
            'title',
        );

        if ($result['success'] && self::titleWasIgnored($result['warnings'] ?? [])) {
            return [
                'success' => false,
                'message' => 'eBay accepted the request but did not change the title. Try ReviseFixedPriceItem for GTC listings.',
            ];
        }

        return [
            'success' => (bool) ($result['success'] ?? false),
            'message' => (string) ($result['message'] ?? ''),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $warnings
     */
    private static function titleWasIgnored(array $warnings): bool
    {
        foreach ($warnings as $warning) {
            $code = (string) ($warning['ErrorCode'] ?? '');
            $text = strtolower((string) ($warning['LongMessage'] ?? $warning['ShortMessage'] ?? ''));
            if ($code === '21917023' || str_contains($text, 'title was ignored') || str_contains($text, 'title has not been modified')) {
                return true;
            }
        }

        return false;
    }

    private static function isUsageLimitError(string $message): bool
    {
        $m = strtolower($message);

        return str_contains($m, '#518')
            || str_contains($m, 'errorcode 518')
            || str_contains($m, 'exceeded usage limit')
            || str_contains($m, 'exceeded the usage limit');
    }

    private static function isWrongListingTypeError(string $message): bool
    {
        $m = strtolower($message);

        return str_contains($m, 'listing type')
            || str_contains($m, 'fixedpriceitem')
            || str_contains($m, 'reviseitem cannot')
            || str_contains($m, '21916270')
            || str_contains($m, '21916264');
    }

    /**
     * @return array{success: bool, message: string, warnings?: list<array<string, mixed>>}
     */
    private static function postTradingCall(
        string $endpoint,
        string $compatLevel,
        string $devId,
        string $appId,
        string $certId,
        string $siteId,
        string $itemId,
        string $xmlBody,
        string $callName,
        string $contextLabel,
    ): array {
        $headers = [
            'X-EBAY-API-COMPATIBILITY-LEVEL' => $compatLevel,
            'X-EBAY-API-DEV-NAME' => $devId,
            'X-EBAY-API-APP-NAME' => $appId,
            'X-EBAY-API-CERT-NAME' => $certId,
            'X-EBAY-API-CALL-NAME' => $callName,
            'X-EBAY-API-SITEID' => $siteId,
            'Content-Type' => 'text/xml',
        ];

        try {
            $response = null;
            $lastThrowable = null;
            $attempts = 3;
            $attempt = 1;
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
                Log::error('eBay '.$callName.': invalid XML response', ['itemId' => $itemId, 'context' => $contextLabel, 'body' => mb_substr($body, 0, 800)]);

                return ['success' => false, 'message' => 'Invalid eBay API response.'];
            }

            $responseArray = json_decode(json_encode($xmlResp), true);
            $ack = $responseArray['Ack'] ?? 'Failure';
            $errors = $responseArray['Errors'] ?? [];
            if (! is_array($errors)) {
                $errors = [];
            }
            if (isset($errors['ErrorCode']) || isset($errors['ShortMessage']) || isset($errors['LongMessage'])) {
                $errors = [$errors];
            }

            if ($ack === 'Success' || $ack === 'Warning') {
                Log::info('eBay '.$callName.' OK', [
                    'item_id' => $itemId,
                    'context' => $contextLabel,
                    'ack' => $ack,
                    'http_status' => $response->status(),
                    'api_attempts' => $attempt,
                    'warnings' => $errors,
                ]);

                return [
                    'success' => true,
                    'message' => 'Title updated successfully (eBay item '.$itemId.').',
                    'warnings' => $errors,
                ];
            }

            $firstErr = $errors[0] ?? [];
            $msg = (string) ($firstErr['LongMessage'] ?? $firstErr['ShortMessage'] ?? $firstErr['ErrorCode'] ?? 'Unknown eBay error');
            if (isset($firstErr['ErrorCode']) && ! str_contains($msg, (string) $firstErr['ErrorCode'])) {
                $msg = '[eBay #'.$firstErr['ErrorCode'].'] '.$msg;
            }
            Log::warning('eBay '.$callName.' failed', [
                'item_id' => $itemId,
                'context' => $contextLabel,
                'ack' => $ack,
                'http_status' => $response->status(),
                'api_attempts' => $attempt,
                'errors' => $errors,
            ]);

            return ['success' => false, 'message' => $msg, 'warnings' => $errors];
        } catch (\Throwable $e) {
            Log::error('eBay '.$callName.' exception', ['itemId' => $itemId, 'context' => $contextLabel, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
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
        string $callName = 'ReviseItem',
    ): array {
        $headers = [
            'X-EBAY-API-COMPATIBILITY-LEVEL' => $compatLevel,
            'X-EBAY-API-DEV-NAME' => $devId,
            'X-EBAY-API-APP-NAME' => $appId,
            'X-EBAY-API-CERT-NAME' => $certId,
            'X-EBAY-API-CALL-NAME' => $callName,
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

                return ['success' => true, 'message' => 'eBay listing updated ('.$contextLabel.') on item '.$itemId.'.'];
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
