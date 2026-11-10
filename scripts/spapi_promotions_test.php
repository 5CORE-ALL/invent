<?php
/**
 * Standalone diagnostic for the Amazon SP-API Promotions API v2025-12-01.
 * READ-ONLY (only GET requests). Does not touch application code and never prints secrets.
 *
 * Endpoints (from the official model promotions_2025-12-01.json):
 *   GET /promotions/2025-12-01/promotions                                   searchPromotions
 *   GET /promotions/2025-12-01/promotions/{promotionId}                     getPromotion
 *   GET /promotions/2025-12-01/promotions/{promotionId}/selections/{selId}  getSelection
 *
 * Auth (official "Connect to the SP-API" guide): LWA access token in `x-amz-access-token`
 * + `user-agent` header. AWS SigV4 signing is NOT required.
 *
 * Usage:
 *   php scripts/spapi_promotions_test.php [--marketplace=A21TJRUUN4KGV]
 *                                         [--endpoint=https://sellingpartnerapi-eu.amazon.com]
 *                                         [--prefix=SPAPI_EU]   # env var prefix for creds (falls back to SPAPI)
 *                                         [--env=/path/to/.env]
 *
 * Credentials (looked up as <PREFIX>_CLIENT_ID / _CLIENT_SECRET / _REFRESH_TOKEN, then SPAPI_*):
 *   Process env vars take priority over the .env file.
 */

const API_VERSION = '2025-12-01';
const LWA_URL = 'https://api.amazon.com/auth/o2/token';
const DEFAULT_ENDPOINT = 'https://sellingpartnerapi-eu.amazon.com';
const INDIA_MARKETPLACE = 'A21TJRUUN4KGV';

$opts = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z]+)=(.*)$/', $a, $m)) {
        $opts[$m[1]] = $m[2];
    }
}
$root = dirname(__DIR__);
$envFile = $opts['env'] ?? $root . '/.env';
$endpoint = rtrim($opts['endpoint'] ?? DEFAULT_ENDPOINT, '/');
$marketplace = $opts['marketplace'] ?? INDIA_MARKETPLACE;
$prefix = $opts['prefix'] ?? 'SPAPI_EU';

// ---------- helpers ----------
function loadEnv(string $file): array
{
    $out = [];
    if (!is_readable($file)) {
        return $out;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        if ($v !== '' && ($v[0] === '"' || $v[0] === "'")) {
            $v = trim($v, $v[0]);
        } else {
            $v = trim(preg_replace('/\s+#.*$/', '', $v));
        }
        if (!isset($out[$k]) || $out[$k] === '') {
            $out[$k] = $v;
        }
    }
    return $out;
}

function cfg(array $env, string $name): string
{
    $v = getenv($name);
    return $v !== false && $v !== '' ? $v : ($env[$name] ?? '');
}

/** Mask anything token/secret-shaped in text before it is printed. */
function redact(string $s): string
{
    $s = preg_replace('/Atz[ar]\|[A-Za-z0-9_\-\.\|=\/+]+/', 'Atz*|[REDACTED]', $s);
    $s = preg_replace('/amzn1\.(application-oa2-client|oa2-cs)\.[A-Za-z0-9\-]+/', 'amzn1.$1.[REDACTED]', $s);
    $s = preg_replace('/("(?:access_token|refresh_token|client_secret)"\s*:\s*")[^"]+"/', '$1[REDACTED]"', $s);
    return $s;
}

function http(string $method, string $url, array $headers, ?string $body = null): array
{
    $ch = curl_init($url);
    $respHeaders = [];
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_HEADERFUNCTION => function ($c, $h) use (&$respHeaders) {
            if (str_contains($h, ':')) {
                [$k, $v] = explode(':', $h, 2);
                $respHeaders[strtolower(trim($k))] = trim($v);
            }
            return strlen($h);
        },
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    unset($ch);
    return ['status' => $status, 'body' => (string) $raw, 'headers' => $respHeaders, 'curl_error' => $err];
}

function qs(array $params): string
{
    $parts = [];
    foreach ($params as $k => $v) {
        if ($v === null || $v === '') {
            continue;
        }
        // arrays are comma-delimited (collectionFormat: csv in the model); keep commas literal
        $parts[] = rawurlencode($k) . '=' . str_replace('%2C', ',', rawurlencode(is_array($v) ? implode(',', $v) : (string) $v));
    }
    return $parts ? '?' . implode('&', $parts) : '';
}

function line(string $c = '='): void
{
    echo str_repeat($c, 78) . "\n";
}

$results = [];
function record(string $op, string $verdict, string $note): void
{
    global $results;
    $results[] = [$op, $verdict, $note];
    echo "\n>>> {$op}: {$verdict} — {$note}\n";
}

function show(array $r, int $max = 2500): void
{
    echo "HTTP status : " . ($r['status'] ?: 'NO RESPONSE') . "\n";
    if ($r['curl_error']) {
        echo "cURL error  : {$r['curl_error']}\n";
    }
    foreach (['x-amzn-requestid', 'x-amzn-ratelimit-limit', 'x-amzn-errortype'] as $h) {
        if (isset($r['headers'][$h])) {
            echo str_pad($h, 24) . ": {$r['headers'][$h]}\n";
        }
    }
    $j = json_decode($r['body'], true);
    $txt = $j !== null ? json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $r['body'];
    $txt = redact($txt);
    if (strlen($txt) > $max) {
        $txt = substr($txt, 0, $max) . "\n... [truncated, " . strlen($txt) . " bytes total]";
    }
    echo "Response body:\n" . ($txt === '' ? '(empty)' : $txt) . "\n";
}

function errorCodes(array $r): string
{
    $j = json_decode($r['body'], true);
    $codes = [];
    foreach (($j['errors'] ?? []) as $e) {
        $codes[] = ($e['code'] ?? '?') . ': ' . ($e['message'] ?? '');
    }
    if (!$codes && isset($j['error'])) {
        $codes[] = $j['error'] . ': ' . ($j['error_description'] ?? '');
    }
    return $codes ? implode(' | ', $codes) : '';
}

/** Perform a GET against SP-API with a single 429 retry. */
function spapiGet(string $endpoint, string $path, array $query, string $token): array
{
    $url = $endpoint . $path . qs($query);
    $headers = [
        'x-amz-access-token: ' . $token,
        'x-amz-date: ' . gmdate('Ymd\THis\Z'),
        'user-agent: 5CoreInvent-PromotionsDiag/1.0 (Language=PHP/' . PHP_VERSION . '; Platform=' . PHP_OS . ')',
        'accept: application/json',
    ];
    echo "REQUEST     : GET {$path}" . qs($query) . "\n";
    $r = http('GET', $url, $headers);
    if ($r['status'] === 429) {
        echo "(429 throttled — usage plan is 0.1 rps; waiting 12s and retrying once)\n";
        sleep(12);
        $r = http('GET', $url, $headers);
    }
    return $r;
}

// ---------- 0. context ----------
$env = loadEnv($envFile);
line();
echo "SP-API Promotions API v" . API_VERSION . " — diagnostic\n";
line();
echo "Language/runtime : PHP " . PHP_VERSION . " (project: Laravel, see composer.json)\n";
echo "Endpoint         : {$endpoint}\n";
echo "Marketplace ID   : {$marketplace}" . ($marketplace === INDIA_MARKETPLACE ? ' (India, IN)' : '') . "\n";
echo "Cred prefix      : {$prefix}_* (fallback SPAPI_*)\n";
echo "Env file         : " . (is_readable($envFile) ? $envFile : 'NOT FOUND') . "\n";

// ---------- 1. credentials check (presence only) ----------
echo "\n--- Step 1: credential configuration (presence only, values never printed) ---\n";
$need = ['CLIENT_ID', 'CLIENT_SECRET', 'REFRESH_TOKEN'];
$creds = [];
$usedPrefix = [];
foreach ($need as $n) {
    $v = cfg($env, "{$prefix}_{$n}");
    $src = "{$prefix}_{$n}";
    if ($v === '') {
        $v = cfg($env, "SPAPI_{$n}");
        $src = "SPAPI_{$n}";
    }
    $creds[$n] = $v;
    echo str_pad($n, 14) . ': ' . ($v !== '' ? "present (from {$src}, length " . strlen($v) . ")" : 'MISSING') . "\n";
}
$awsKey = cfg($env, 'AWS_ACCESS_KEY_ID');
echo str_pad('AWS keys', 14) . ': ' . ($awsKey !== '' ? 'present in .env' : 'absent') . " (not needed — SigV4 not required by SP-API)\n";
echo "Configured SPAPI_ENDPOINT in .env : " . (cfg($env, 'SPAPI_ENDPOINT') ?: '(unset)') . "\n";
echo "Configured SPAPI_REGION in .env   : " . (cfg($env, 'SPAPI_REGION') ?: '(unset)') . "\n";
echo "Configured SPAPI_MARKETPLACE_ID   : " . (cfg($env, 'SPAPI_MARKETPLACE_ID') ?: '(unset)') . "\n";

if (in_array('', $creds, true)) {
    record('Credentials', 'FAIL', 'Missing required: LWA client_id, client_secret and a seller-authorized refresh_token.');
    goto summary;
}
record('Credentials', 'PASS', 'client_id, client_secret, refresh_token all present.');

// ---------- 2. endpoint reachability (unauthenticated) ----------
echo "\n--- Step 2: endpoint reachability (no token; expect 403 'missing auth' = host is reachable) ---\n";
$r = http('GET', $endpoint . '/promotions/' . API_VERSION . '/promotions' . qs(['marketplaceIds' => $marketplace]), [
    'user-agent: 5CoreInvent-PromotionsDiag/1.0 (Language=PHP)',
]);
show($r, 600);
$reachable = $r['status'] > 0;
record('Endpoint reachability', $reachable ? 'PASS' : 'FAIL',
    $reachable ? "Host answered HTTP {$r['status']} (a 403/401 here without a token is expected)." : 'No HTTP response (DNS/network/TLS failure).');

// ---------- 3. LWA token ----------
echo "\n--- Step 3: LWA access token (POST " . LWA_URL . ") ---\n";
$r = http('POST', LWA_URL, ['Content-Type: application/x-www-form-urlencoded;charset=UTF-8'], http_build_query([
    'grant_type' => 'refresh_token',
    'refresh_token' => $creds['REFRESH_TOKEN'],
    'client_id' => $creds['CLIENT_ID'],
    'client_secret' => $creds['CLIENT_SECRET'],
]));
$j = json_decode($r['body'], true);
echo "HTTP status : {$r['status']}\n";
if (!empty($j['access_token'])) {
    $token = $j['access_token'];
    echo "access_token: obtained (length " . strlen($token) . ", NOT printed)\n";
    echo "token_type  : " . ($j['token_type'] ?? '?') . "\nexpires_in  : " . ($j['expires_in'] ?? '?') . "s\n";
    record('LWA token generation', 'PASS', 'Access token issued by api.amazon.com.');
} else {
    echo "Error       : " . (errorCodes($r) ?: redact($r['body'])) . "\n";
    record('LWA token generation', 'FAIL', errorCodes($r) ?: 'No access_token in response.');
    goto summary;
}

// ---------- 4. searchPromotions (COUPON) ----------
$base = '/promotions/' . API_VERSION . '/promotions';
echo "\n--- Step 4: searchPromotions (promotionTypes=COUPON) ---\n";
$r = spapiGet($endpoint, $base, ['marketplaceIds' => $marketplace, 'promotionTypes' => 'COUPON', 'limit' => 20, 'revision' => 'ANY'], $token);
show($r);
$j = json_decode($r['body'], true);
$couponId = null;
$couponSel = null;
if ($r['status'] === 200) {
    $n = $j['totalResults'] ?? count($j['promotions'] ?? []);
    foreach (($j['promotions'] ?? []) as $p) {
        $couponId = $couponId ?? ($p['promotionId'] ?? null);
        if (($p['promotionId'] ?? null) === $couponId) {
            $couponSel = $p['selection'] ?? null;
        }
    }
    record('searchPromotions (COUPON)', 'PASS', "HTTP 200; totalResults={$n}" . ($n == 0 ? ' (call works; account has no coupons in this marketplace)' : ''));
} else {
    record('searchPromotions (COUPON)', 'FAIL', "HTTP {$r['status']} " . errorCodes($r));
}
$searchStatus = $r['status'];
sleep(2);

// ---------- 5. searchPromotions (all types) ----------
echo "\n--- Step 5: searchPromotions (no type filter, limit=5) — shows whether ANY promotion data is readable ---\n";
$r = spapiGet($endpoint, $base, ['marketplaceIds' => $marketplace, 'limit' => 5], $token);
show($r, 1800);
$j = json_decode($r['body'], true);
if ($r['status'] === 200) {
    $types = array_count_values(array_map(fn ($p) => $p['promotionType'] ?? '(none)', $j['promotions'] ?? []));
    record('searchPromotions (all types)', 'PASS', "HTTP 200; totalResults=" . ($j['totalResults'] ?? '?') . '; types on page: ' . json_encode($types));
} else {
    record('searchPromotions (all types)', 'FAIL', "HTTP {$r['status']} " . errorCodes($r));
}
sleep(2);

// ---------- 6. getPromotion ----------
echo "\n--- Step 6: getPromotion ---\n";
if ($couponId) {
    $r = spapiGet($endpoint, "{$base}/" . rawurlencode($couponId), ['includedData' => 'ISSUES,SELECTION,CUSTOMER_SEGMENTS'], $token);
    show($r);
    record('getPromotion', $r['status'] === 200 ? 'PASS' : 'FAIL', "HTTP {$r['status']} " . errorCodes($r));
} else {
    echo "No coupon promotionId available from searchPromotions.\n";
    record('getPromotion', 'SKIPPED', 'No coupon promotionId to fetch (search returned none or failed).');
}
sleep(2);

// ---------- 7. getSelection ----------
echo "\n--- Step 7: getSelection ---\n";
$selId = $couponSel['selectionId'] ?? null;
$revId = $couponSel['revisionId'] ?? null;
$selType = $couponSel['type'] ?? null;
if ($couponId && $selId !== null && $revId !== null && $selType === 'ITEMS') {
    $r = spapiGet($endpoint, "{$base}/" . rawurlencode($couponId) . '/selections/' . rawurlencode((string) $selId), ['revisionId' => $revId, 'limit' => 10, 'includedData' => 'ISSUES'], $token);
    show($r);
    record('getSelection', $r['status'] === 200 ? 'PASS' : 'FAIL', "HTTP {$r['status']} " . errorCodes($r));
} else {
    echo "Not applicable (needs a promotion whose selection.type = ITEMS; got " . json_encode($selType) . ").\n";
    record('getSelection', 'SKIPPED', 'No coupon with ITEMS selection available (getSelection does not support CATALOG selections).');
}

// ---------- summary ----------
summary:
echo "\n";
line();
echo "SUMMARY\n";
line();
foreach ($results as [$op, $v, $note]) {
    echo str_pad($v, 8) . ' ' . str_pad($op, 32) . ' ' . $note . "\n";
}
if (isset($searchStatus)) {
    echo "\nAuthentication succeeded : " . (collect_auth($results) ? 'YES' : 'NO') . "\n";
    echo "Endpoint accessible      : " . ($searchStatus > 0 ? 'YES (HTTP ' . $searchStatus . ')' : 'NO') . "\n";
    $perm = $searchStatus === 200 ? 'YES (200 OK)' : ($searchStatus === 403 ? 'NO (403 — app not authorized for this region/role)' : 'UNDETERMINED (HTTP ' . $searchStatus . ')');
    echo "Permission to coupon data: {$perm}\n";
}

function collect_auth(array $results): bool
{
    foreach ($results as [$op, $v]) {
        if ($op === 'LWA token generation') {
            return $v === 'PASS';
        }
    }
    return false;
}
