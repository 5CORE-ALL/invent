<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$sku = $argv[1] ?? 'CM MOB 2M';
$token = App\Services\ReverbApiService::getReverbBearerToken();

$p = App\Models\ReverbProduct::query()
    ->where('sku', $sku)
    ->orWhere('sku', strtoupper($sku))
    ->first();
$lid = $p?->reverb_listing_id;

$pm = App\Models\ProductMaster::query()
    ->where('sku', $sku)
    ->orWhere('sku', strtoupper($sku))
    ->first();

echo "=== SKU: {$sku} | listing: {$lid} ===\n\n";

echo "PRODUCT MASTER BULLETS:\n";
for ($i = 1; $i <= 5; $i++) {
    $b = trim((string) ($pm?->{'bullet'.$i} ?? ''));
    echo "  {$i}. ".($b !== '' ? $b : '(empty)')."\n";
}

if (! $token || ! $lid) {
    echo "\nNo token or listing.\n";
    exit(1);
}

$r = Illuminate\Support\Facades\Http::withoutVerifying()
    ->timeout(30)
    ->withHeaders([
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/hal+json',
        'Accept-Version' => '3.0',
    ])
    ->get('https://api.reverb.com/api/listings/'.$lid);

$listing = $r->json()['listing'] ?? [];
$desc = (string) ($listing['description'] ?? '');
$state = $listing['state'] ?? 'n/a';
$inv = $listing['inventory'] ?? 'n/a';

echo "\nREVERB STATE: ".(is_array($state) ? json_encode($state) : $state)." | inventory: ".(is_array($inv) ? json_encode($inv) : $inv)."\n";

// Extract HF block bullets from live description
$hfBullets = [];
if (preg_match('/<p>\s*<strong>\s*Highlighted\s+Features\s*<\/strong>\s*<\/p>(.*?)(?=<p>\s*<strong>(?!.*Highlighted)|<h[1-6]|$)/is', $desc, $m)) {
    preg_match_all('/<p>\s*<strong>([^<]+)<\/strong>\s*([^<]*)/i', $m[1], $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
        $label = trim(html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        $rest = trim(html_entity_decode(strip_tags($match[2]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        $label = rtrim($label, '-:–— ');
        $hfBullets[] = trim($label.($rest !== '' ? ' - '.$rest : ''));
    }
}

echo "\nLIVE REVERB 'Highlighted Features' BULLETS (top block we push):\n";
foreach ($hfBullets as $i => $b) {
    echo '  '.($i + 1).'. '.$b."\n";
}

// Check for other bullet-like sections user might be looking at
echo "\nOTHER BULLET SECTIONS IN DESCRIPTION:\n";
$sections = ['Lavalier Microphone Features', 'Key Features', 'About Item', '【'];
foreach ($sections as $needle) {
    if (str_contains($desc, $needle)) {
        $pos = mb_stripos($desc, $needle);
        echo "  FOUND '{$needle}' at pos {$pos}: ".mb_substr(strip_tags($desc), $pos, 120)."...\n";
    }
}

echo "\nFULL DESCRIPTION SECTION HEADINGS (plain text):\n";
$plain = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $desc)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
foreach (preg_split('/\n+/', $plain) as $line) {
    $line = trim($line);
    if ($line === '') {
        continue;
    }
    if (preg_match('/^(Highlighted Features|Key Features|About Item|.*Features.*|.*Specifications.*|LONG CABLE|ENHANCED NOISE)/i', $line)) {
        echo "  > {$line}\n";
    }
}
for ($i = 1; $i <= 5; $i++) {
    $pmB = trim((string) ($pm?->{'bullet'.$i} ?? ''));
    $liveB = $hfBullets[$i - 1] ?? '';
    if ($pmB === '' && $liveB === '') {
        continue;
    }
    $match = $pmB !== '' && $liveB !== '' && (
        strcasecmp($pmB, $liveB) === 0
        || str_contains(mb_strtolower($liveB), mb_strtolower(mb_substr($pmB, 0, 40)))
    );
    echo "  bullet{$i}: ".($match ? 'MATCH' : 'MISMATCH')."\n";
    if (! $match && $pmB !== '') {
        echo "    PM:   {$pmB}\n";
        echo "    Live: ".($liveB !== '' ? $liveB : '(missing)')."\n";
    }
}
