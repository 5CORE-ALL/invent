<?php

/**
 * Mirrors official IOP SDK HTTP layout (no Laravel):
 * - Sign = HMAC-SHA256( sorted merge(api + system) ), uppercase hex, NO /rest prefix, NO secret wrapping.
 * - POST URL = https://api-sg.aliexpress.com/rest?{system+sign as query}
 * - POST body = multipart/form-data with api params only (e.g. product_list_get_request).
 *
 * php examples/aliexpress_iop_standalone.php
 */

$appKey = getenv('ALIEXPRESS_APP_KEY') ?: 'YOUR_APP_KEY';
$appSecret = getenv('ALIEXPRESS_APP_SECRET') ?: 'YOUR_APP_SECRET';
$session = getenv('ALIEXPRESS_ACCESS_TOKEN') ?: 'YOUR_SESSION_TOKEN';

$method = 'aliexpress.solution.product.list.get';
$productListJson = json_encode([
    'current_page' => 1,
    'page_size' => 5,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$api = [
    'product_list_get_request' => $productListJson,
];

$system = [
    'app_key' => $appKey,
    'format' => 'json',
    'method' => $method,
    'partner_id' => getenv('ALIEXPRESS_PARTNER_ID') ?: 'iop-sdk-php',
    'sign_method' => 'sha256',
    'simplify' => 'true',
    'session' => $session,
    'timestamp' => time().'000',
];

$forSign = array_merge($api, $system);
ksort($forSign);
$stringToSign = '';
foreach ($forSign as $k => $v) {
    $stringToSign .= $k.$v;
}
$system['sign'] = strtoupper(hash_hmac('sha256', $stringToSign, $appSecret));

$queryUrl = 'https://api-sg.aliexpress.com/rest?'.http_build_query($system, '', '&', PHP_QUERY_RFC3986);

$ch = curl_init($queryUrl);
$boundary = '-------------'.uniqid('', true);
$body = '';
foreach ($api as $name => $contents) {
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n";
    $body .= $contents."\r\n";
}
$body .= "--{$boundary}--\r\n";

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: multipart/form-data; boundary='.$boundary,
        'Content-Length: '.strlen($body),
    ],
]);

$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Sign string (first 200 chars): ".substr($stringToSign, 0, 200)."...\n";
echo "HTTP {$code}\n{$resp}\n";
