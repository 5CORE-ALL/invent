<?php

$storeUrl = trim((string) env('SHOPIFY_STORE_URL', ''));
$storeDomain = preg_replace('#^https?://#i', '', $storeUrl) ?? $storeUrl;
$storeDomain = rtrim($storeDomain, '/');
$apiVersion = trim((string) env('SHOPIFY_API_VERSION', '2024-01'), '/');

return [
    'store_url' => $storeDomain,
    'api_version' => $apiVersion,
    'access_token' => env('SHOPIFY_ACCESS_TOKEN'),
    'base_url' => $storeDomain !== '' ? 'https://'.$storeDomain.'/admin/api/'.$apiVersion.'/' : null,
    // Local Windows environments can set false to bypass missing CA bundle issues.
    'ssl_verify' => filter_var(env('SHOPIFY_SSL_VERIFY', true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true,
];

