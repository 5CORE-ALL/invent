<?php

/**
 * Add Schema::hasTable guard at start of up() for migrations whose up() contains
 * exactly one Schema::create and no Schema::dropIfExists (avoids breaking drop/recreate migrations).
 *
 * Usage: php database/scripts/patch_single_schema_create_migrations.php
 */

declare(strict_types=1);

$migrationsDir = dirname(__DIR__).DIRECTORY_SEPARATOR.'migrations';
$files = glob($migrationsDir.DIRECTORY_SEPARATOR.'*.php') ?: [];
sort($files);

$patched = 0;
foreach ($files as $path) {
    $src = file_get_contents($path);
    if ($src === false) {
        continue;
    }

    if (! preg_match('/public function up\(\)\s*:\s*void\s*\{([\s\S]*)\n    \/\*\*\n     \* Reverse the migrations/m', $src, $m)
        && ! preg_match('/public function up\(\)\s*:\s*void\s*\{([\s\S]*)\n    public function down\(\)/m', $src, $m)) {
        continue;
    }

    $upBody = $m[1];
    if (str_contains($upBody, 'Schema::dropIfExists')) {
        continue;
    }

    $createCount = preg_match_all('/Schema::create\s*\(\s*[\'"]([^\'"]+)[\'"]/', $upBody, $matches);
    if ($createCount !== 1) {
        continue;
    }

    $table = $matches[1][0];
    if (preg_match('/Schema::hasTable\s*\(\s*[\'"]'.preg_quote($table, '/').'[\'"]\s*\)/', $src)) {
        continue;
    }

    $replacement = '$1        if (Schema::hasTable(\''.$table.'\')) {'."\n            return;\n        }\n\n";

    $new = preg_replace('/(public function up\(\)\s*:\s*void\s*\{\s*\n)/', $replacement, $src, 1);
    if ($new === null || $new === $src) {
        continue;
    }

    file_put_contents($path, $new);
    echo 'Patched: '.basename($path).' ('.$table.')'.PHP_EOL;
    $patched++;
}

echo 'Total patched: '.$patched.PHP_EOL;
