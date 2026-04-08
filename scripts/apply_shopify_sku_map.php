<?php

/**
 * Replace ShopifySku::whereIn(...)->get()->keyBy(...) with ShopifySku::mapByProductSkus(...)
 * for consistent NBSP/unicode-safe PM ↔ shopify_skus matching.
 */

$root = dirname(__DIR__) . '/app';
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

$patterns = [
    // $shopifySkus assignment
    [
        'from' => "\$shopifySkus = ShopifySku::whereIn('sku', \$skus)->get()->keyBy('sku');",
        'to' => "\$shopifySkus = ShopifySku::mapByProductSkus(\$skus);",
    ],
    [
        'from' => "\$shopifySkus = ShopifySku::whereIn('sku', \$skus)->get()->keyBy(\"sku\");",
        'to' => "\$shopifySkus = ShopifySku::mapByProductSkus(\$skus);",
    ],
    // $shopifyData assignment
    [
        'from' => "\$shopifyData = ShopifySku::whereIn('sku', \$skus)->get()->keyBy('sku');",
        'to' => "\$shopifyData = ShopifySku::mapByProductSkus(\$skus);",
    ],
    [
        'from' => "\$shopifyData = ShopifySku::whereIn('sku', \$skus)->get()->keyBy(\"sku\");",
        'to' => "\$shopifyData = ShopifySku::mapByProductSkus(\$skus);",
    ],
    [
        'from' => "\$shopifyData = ShopifySku::whereIn(\"sku\", \$skus)->get()->keyBy(\"sku\");",
        'to' => "\$shopifyData = ShopifySku::mapByProductSkus(\$skus);",
    ],
    [
        'from' => "\$shopifyData = ShopifySku::whereIn('sku', \$baseSkus)->get()->keyBy('sku');",
        'to' => "\$shopifyData = ShopifySku::mapByProductSkus(\$baseSkus);",
    ],
    [
        'from' => "\$shopifyData = ShopifySku::whereIn('sku', \$originalSkus)->get()->keyBy('sku');",
        'to' => "\$shopifyData = ShopifySku::mapByProductSkus(\$originalSkus);",
    ],
];

$multilinePatterns = [
    [
        're' => '/\$shopifyData\s*=\s*ShopifySku::whereIn\(\'sku\',\s*\$skus\)\s*->\s*get\(\)\s*->\s*keyBy\(\'sku\'\)\s*;/s',
        'to' => '$shopifyData = ShopifySku::mapByProductSkus($skus);',
    ],
    [
        're' => '/\$shopifyData\s*=\s*ShopifySku::whereIn\(\'sku\',\s*\$baseSkus\)\s*->\s*get\(\)\s*->\s*keyBy\(\s*function\s*\(\s*\$item\s*\)\s*\{[^}]*\}\s*\)\s*;/s',
        'to' => '$shopifyData = ShopifySku::mapByProductSkus($baseSkus);',
    ],
    [
        're' => '/\$shopifyData\s*=\s*ShopifySku::whereIn\(\'sku\',\s*\$skus\)\s*->\s*select\([^)]+\)\s*->\s*get\(\)\s*->\s*keyBy\(\'sku\'\)\s*;/s',
        'to' => '$shopifyData = ShopifySku::mapByProductSkus($skus);',
    ],
];

$totalFiles = 0;
$totalRepl = 0;

foreach ($rii as $file) {
    if ($file->isDir() || $file->getExtension() !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    $c = file_get_contents($path);
    $orig = $c;

    foreach ($patterns as $p) {
        $n = substr_count($c, $p['from']);
        if ($n > 0) {
            $c = str_replace($p['from'], $p['to'], $c);
            $totalRepl += $n;
        }
    }

    foreach ($multilinePatterns as $p) {
        $c2 = preg_replace($p['re'], $p['to'], $c);
        if ($c2 !== null && $c2 !== $c) {
            $totalRepl++;
            $c = $c2;
        }
    }

    if ($c !== $orig) {
        file_put_contents($path, $c);
        $totalFiles++;
    }
}

echo "Updated {$totalFiles} files, ~{$totalRepl} replacements.\n";
