<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

$sku = $argv[1] ?? 'GSTOOL BLK';

echo "=== LIVE Macy Features & Benefits fetch for: {$sku} ===\n\n";

// 1) Buyer link from listing status / metrics
$buyerLink = null;
if (Schema::hasTable('macys_listing_statuses')) {
    $status = DB::table('macys_listing_statuses')->where('sku', $sku)->orderByDesc('updated_at')->first();
    if ($status) {
        $val = is_string($status->value ?? null) ? json_decode($status->value, true) : (array) ($status->value ?? []);
        $buyerLink = trim((string) ($val['buyer_link'] ?? ''));
    }
}
if ($buyerLink === null || $buyerLink === '') {
    foreach (['macy_metrics', 'macy_products'] as $table) {
        if (! Schema::hasTable($table)) {
            continue;
        }
        $row = DB::table($table)->where('sku', $sku)->orWhere('sku', strtolower($sku))->first();
        if ($row) {
            foreach (['listing_url', 'buyer_link', 'url', 'link'] as $col) {
                if (Schema::hasColumn($table, $col) && ! empty($row->{$col})) {
                    $buyerLink = trim((string) $row->{$col});
                    break 2;
                }
            }
        }
    }
}

echo "--- Source A: Mirakl Connect (channel_code=macys) ---\n";
$token = Http::withoutVerifying()->asForm()->post('https://auth.mirakl.net/oauth/token', [
    'grant_type' => 'client_credentials',
    'client_id' => config('services.macy.client_id'),
    'client_secret' => config('services.macy.client_secret'),
])->json()['access_token'] ?? null;

if (! $token) {
    echo "Connect auth FAILED\n\n";
} else {
    $headers = ['Accept' => 'application/json', 'channel_id' => config('services.macy.company_id')];
    $list = Http::withoutVerifying()->withToken($token)->withHeaders($headers)->timeout(60)
        ->get('https://miraklconnect.com/api/products', ['limit' => 1000, 'channel_code' => 'macys']);

    echo 'HTTP '.$list->status()."\n";
    $found = false;
    foreach (($list->json('data') ?? []) as $product) {
        if (strcasecmp((string) ($product['id'] ?? ''), $sku) !== 0) {
            continue;
        }
        $found = true;
        for ($i = 1; $i <= 5; $i++) {
            $val = '';
            foreach (($product['attributes'] ?? []) as $attr) {
                if (is_array($attr) && strcasecmp((string) ($attr['id'] ?? ''), "features_and_benefits_bullet_{$i}") === 0) {
                    $val = trim((string) ($attr['value'] ?? ''));
                }
            }
            echo "  F&B {$i}: ".($val !== '' ? $val : '(empty)')."\n";
        }
        break;
    }
    if (! $found) {
        echo "  Product NOT found in Connect list (first page)\n";
    }
    echo "\n";
}

echo "--- Source B: Macy MCM API (macysus-prod.mirakl.net) ---\n";
$mcmKey = trim((string) config('services.macy.mcm_api_key', ''));
if ($mcmKey === '') {
    echo "  Skipped — set MACY_MCM_API_KEY in .env (Shop API Key)\n\n";
} else {
    $mcmQuery = [];
    $shopId = config('services.macy.shop_id');
    if ($shopId !== null && $shopId !== '') {
        $mcmQuery['shop_id'] = (int) $shopId;
    }
    $mcm = Http::withoutVerifying()
        ->withHeaders(['Authorization' => $mcmKey, 'Accept' => 'application/json'])
        ->timeout(30)
        ->get('https://macysus-prod.mirakl.net/api/products', array_merge($mcmQuery, ['product_sku' => $sku, 'max' => 1]));
    echo 'HTTP '.$mcm->status()."\n";
    if ($mcm->successful()) {
        $body = $mcm->json();
        $products = $body['products'] ?? $body['data'] ?? [];
        if ($products === [] && isset($body[0])) {
            $products = $body;
        }
        foreach ((array) $products as $product) {
            $attrs = $product['product_attributes'] ?? $product['attributes'] ?? [];
            if (is_array($attrs)) {
                for ($i = 1; $i <= 5; $i++) {
                    $key = "features_and_benefits_bullet_{$i}";
                    $val = is_array($attrs) && isset($attrs[$key]) ? $attrs[$key] : '';
                    if ($val === '' && isset($attrs[0]['code'])) {
                        foreach ($attrs as $a) {
                            if (($a['code'] ?? '') === $key) {
                                $val = $a['value'] ?? '';
                            }
                        }
                    }
                    if ($val !== '') {
                        echo "  F&B {$i}: {$val}\n";
                    }
                }
            }
        }
        if ($products === []) {
            echo '  '.mb_substr($mcm->body(), 0, 500)."\n";
        }
    } else {
        echo '  MCM read failed. Body: '.mb_substr($mcm->body(), 0, 200)."\n";
    }
    echo "\n";
}

echo "--- Source C: Live Macy.com PDP ---\n";
if ($buyerLink) {
    echo "URL: {$buyerLink}\n";
    $page = Http::withoutVerifying()->withHeaders([
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Accept' => 'text/html,application/xhtml+xml',
    ])->timeout(45)->get($buyerLink);

    echo 'HTTP '.$page->status()."\n";
    if ($page->successful()) {
        $html = $page->body();
        $bullets = [];

        // Common Macy PDP patterns: li in features/benefits, JSON-LD, or specification bullets
        if (preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $html, $matches)) {
            foreach ($matches[1] as $li) {
                $text = trim(html_entity_decode(strip_tags($li)));
                if ($text !== '' && (strlen($text) > 30 || str_contains($text, 'Guitar') || str_contains($text, '【'))) {
                    $bullets[] = $text;
                }
            }
        }

        // Try JSON embedded product data
        if (preg_match('/"featuresAndBenefits"\s*:\s*(\[[^\]]+\])/i', $html, $jsonMatch)) {
            $arr = json_decode($jsonMatch[1], true);
            if (is_array($arr)) {
                $bullets = array_merge($bullets, array_map('strval', $arr));
            }
        }

        $bullets = array_values(array_unique(array_filter($bullets)));
        $bullets = array_slice($bullets, 0, 8);

        if ($bullets === []) {
            // Fallback: search for F&B keywords in page text
            $plain = preg_replace('/\s+/', ' ', strip_tags($html));
            foreach ([
                'Experience unmatched comfort',
                'Comfortable Guitar Stool',
                '【Comfortable for Long Sessions】',
                'Built-In Guitar Holder',
            ] as $needle) {
                if (stripos($plain, $needle) !== false) {
                    echo "  Found text on page: {$needle}\n";
                }
            }
            echo "  Could not parse structured F&B bullets from HTML (page may be JS-rendered).\n";
        } else {
            foreach ($bullets as $i => $b) {
                echo '  Bullet '.($i + 1).': '.mb_substr($b, 0, 300)."\n";
            }
        }
    }
} else {
    echo "  No buyer_link / listing_url found in DB for this SKU.\n";
    echo "  Searching Macy.com for SKU...\n";
    $search = Http::withoutVerifying()->withHeaders([
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    ])->timeout(30)->get('https://www.macys.com/shop/featured/'.rawurlencode($sku));
    echo 'Search HTTP '.$search->status()."\n";
}

echo "\nDone.\n";
