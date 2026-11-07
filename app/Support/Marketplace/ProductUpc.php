<?php

namespace App\Support\Marketplace;

use App\Models\AmazonListingRaw;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * The UPC to send to eBay for a SKU.
 * CP Master (product_master UPC field) first. When that is blank, the code Amazon holds for the SKU.
 * Only a real barcode is returned (8, 12, 13 or 14 digits with a correct check digit), never an ASIN
 * and never the internal WMS barcode.
 */
class ProductUpc
{
    public const SOURCE_CP_MASTER = 'cp_master';

    public const SOURCE_AMAZON = 'amazon';

    /**
     * Digits of a valid GTIN, or '' when the value is not one.
     * CP Master keeps UPC as a number, so a leading zero can be missing; it is put back when that makes the code valid.
     */
    public static function normalize(mixed $value): string
    {
        if (is_int($value) || is_float($value)) {
            $value = sprintf('%.0f', $value);
        }
        $text = trim((string) ($value ?? ''));
        if ($text === '' || $text === '-') {
            return '';
        }
        if (is_numeric($text) && stripos($text, 'e') !== false) {
            $text = sprintf('%.0f', (float) $text);
        }
        $text = (string) preg_replace('/[\s\-]+/', '', $text);
        if ($text === '' || ! ctype_digit($text)) {
            return '';
        }

        $candidates = [$text];
        $length = strlen($text);
        if ($length >= 9 && $length < 12) {
            $candidates[] = str_pad($text, 12, '0', STR_PAD_LEFT);
        }
        if ($length >= 6 && $length < 8) {
            $candidates[] = str_pad($text, 8, '0', STR_PAD_LEFT);
        }
        foreach ($candidates as $code) {
            if (self::validGtin($code)) {
                return $code;
            }
        }

        return '';
    }

    /**
     * Digits of a code that looks like a UPC / EAN (8 to 14 digits) without checking the check digit.
     * Used for CP Master only. 9 to 11 digits get their leading zeros back (12 digits).
     */
    public static function normalizeLenient(mixed $value): string
    {
        if (is_int($value) || is_float($value)) {
            $value = sprintf('%.0f', $value);
        }
        $text = trim((string) ($value ?? ''));
        if ($text === '' || $text === '-') {
            return '';
        }
        if (is_numeric($text) && (stripos($text, 'e') !== false || str_contains($text, '.'))) {
            $text = sprintf('%.0f', (float) $text);
        }
        $text = (string) preg_replace('/[\s\-]+/', '', $text);
        if ($text === '' || ! ctype_digit($text) || (int) ltrim($text, '0') === 0) {
            return '';
        }
        $length = strlen($text);
        if ($length >= 9 && $length < 12) {
            return str_pad($text, 12, '0', STR_PAD_LEFT);
        }

        return in_array($length, [8, 12, 13, 14], true) ? $text : '';
    }

    public static function validGtin(string $code): bool
    {
        if (! preg_match('/^\d+$/', $code) || ! in_array(strlen($code), [8, 12, 13, 14], true)) {
            return false;
        }
        $digits = array_map('intval', str_split($code));
        $check = array_pop($digits);
        $sum = 0;
        $weight = 3;
        for ($i = count($digits) - 1; $i >= 0; $i--) {
            $sum += $digits[$i] * $weight;
            $weight = $weight === 3 ? 1 : 3;
        }

        return ((10 - ($sum % 10)) % 10) === $check;
    }

    public static function skuKey(string $sku): string
    {
        return strtoupper(trim(str_replace("\u{00a0}", ' ', $sku)));
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, array{upc: string, source: string}>  keyed by the SKU as given
     */
    public static function forSkus(array $skus): array
    {
        $out = [];
        $keys = [];
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            if ($sku === '' || stripos($sku, 'PARENT ') === 0) {
                continue;
            }
            $out[$sku] = ['upc' => '', 'source' => ''];
            $keys[self::skuKey($sku)][] = $sku;
        }
        if ($out === []) {
            return $out;
        }

        foreach (self::fromCpMaster(array_keys($keys)) as $key => $upc) {
            foreach ($keys[$key] ?? [] as $sku) {
                $out[$sku] = ['upc' => $upc, 'source' => self::SOURCE_CP_MASTER];
            }
        }

        $missing = [];
        foreach ($keys as $key => $list) {
            if ($out[$list[0]]['upc'] === '') {
                $missing[] = $key;
            }
        }
        if ($missing !== []) {
            foreach (self::fromAmazon($missing) as $key => $upc) {
                foreach ($keys[$key] ?? [] as $sku) {
                    $out[$sku] = ['upc' => $upc, 'source' => self::SOURCE_AMAZON];
                }
            }
        }

        return $out;
    }

    /**
     * @return array{upc: string, source: string}
     */
    public static function forSku(string $sku): array
    {
        $sku = trim($sku);
        $all = self::forSkus([$sku]);

        return $all[$sku] ?? ['upc' => '', 'source' => ''];
    }

    /**
     * @param  list<string>  $skuKeys  upper-cased SKUs
     * @return array<string, string>  sku key => upc
     */
    private static function fromCpMaster(array $skuKeys): array
    {
        $found = [];
        try {
            if (! Schema::hasTable('product_master')) {
                return [];
            }
            $columns = ['sku'];
            $hasValues = Schema::hasColumn('product_master', 'Values');
            $hasUpc = Schema::hasColumn('product_master', 'upc');
            if ($hasValues) {
                $columns[] = 'Values';
            }
            if ($hasUpc) {
                $columns[] = 'upc';
            }
            if (count($columns) === 1) {
                return [];
            }

            // The SKU is matched in PHP, then the rows are loaded by the exact SKU.
            // Comparing UPPER(sku) in SQL mixes two collations on the server and MySQL rejects the
            // whole lookup, so every UPC came back empty.
            $wanted = array_fill_keys($skuKeys, true);
            $matchedSkus = [];
            DB::table('product_master')
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->select(['id', 'sku'])
                ->orderBy('id')
                ->chunkById(1000, function ($rows) use (&$matchedSkus, $wanted) {
                    foreach ($rows as $row) {
                        if (isset($wanted[self::skuKey((string) $row->sku)])) {
                            $matchedSkus[] = (string) $row->sku;
                        }
                    }
                });

            foreach (array_chunk($matchedSkus, 400) as $chunk) {
                $rows = DB::table('product_master')->whereIn('sku', $chunk)->get($columns);
                foreach ($rows as $row) {
                    $key = self::skuKey((string) $row->sku);
                    if (isset($found[$key])) {
                        continue;
                    }
                    $raws = [];
                    if ($hasValues) {
                        $values = $row->Values ?? null;
                        if (is_string($values)) {
                            $decoded = json_decode($values, true);
                            $values = is_array($decoded) ? $decoded : [];
                        }
                        if (is_array($values)) {
                            foreach (['upc', 'UPC', 'Upc', 'gtin', 'GTIN'] as $name) {
                                $raws[] = $values[$name] ?? null;
                            }
                        }
                    }
                    if ($hasUpc) {
                        $raws[] = $row->upc ?? null;
                    }
                    // A code with a correct check digit wins. Otherwise the CP Master code is still used as typed
                    // (digits only), because CP Master is the source of truth for the UPC.
                    $upc = '';
                    foreach ($raws as $raw) {
                        $upc = self::normalize($raw);
                        if ($upc !== '') {
                            break;
                        }
                    }
                    if ($upc === '') {
                        foreach ($raws as $raw) {
                            $upc = self::normalizeLenient($raw);
                            if ($upc !== '') {
                                break;
                            }
                        }
                    }
                    if ($upc !== '') {
                        $found[$key] = $upc;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('ProductUpc: CP Master lookup failed', ['error' => $e->getMessage()]);
        }

        return $found;
    }

    /**
     * @param  list<string>  $skuKeys  upper-cased SKUs
     * @return array<string, string>  sku key => upc
     */
    private static function fromAmazon(array $skuKeys): array
    {
        $found = [];
        try {
            if (! Schema::hasTable('amazon_listings_raw')) {
                return [];
            }
            foreach (array_chunk($skuKeys, 200) as $chunk) {
                $rows = AmazonListingRaw::query()
                    ->whereIn(DB::raw("UPPER(TRIM(REPLACE(COALESCE(seller_sku, ''), CHAR(160), ' ')))"), $chunk)
                    ->get(['seller_sku', 'external_product_id', 'raw_data']);
                foreach ($rows as $row) {
                    $key = self::skuKey((string) $row->seller_sku);
                    if (isset($found[$key])) {
                        continue;
                    }
                    $raw = is_array($row->raw_data) ? $row->raw_data : [];
                    $type = strtolower(trim((string) self::rawGet($raw, 'product-id-type', 'product_id_type', 'external_product_id_type')));
                    $idIsBarcode = $type === '' || in_array($type, ['3', '4', '5', 'upc', 'ean', 'gtin'], true);

                    $candidates = [self::rawGet($raw, 'upc', 'UPC', 'gtin', 'GTIN')];
                    if ($idIsBarcode) {
                        $candidates[] = (string) $row->external_product_id;
                        $candidates[] = self::rawGet($raw, 'product-id', 'product_id', 'external_product_id');
                    }
                    foreach ($candidates as $candidate) {
                        $upc = self::normalize($candidate);
                        if ($upc !== '') {
                            $found[$key] = $upc;
                            break;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('ProductUpc: Amazon lookup failed', ['error' => $e->getMessage()]);
        }

        return $found;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private static function rawGet(array $raw, string ...$keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $raw) && is_scalar($raw[$key]) && trim((string) $raw[$key]) !== '') {
                return trim((string) $raw[$key]);
            }
            foreach ($raw as $k => $v) {
                if (is_scalar($v) && strcasecmp(ltrim((string) $k, "\xEF\xBB\xBF"), $key) === 0 && trim((string) $v) !== '') {
                    return trim((string) $v);
                }
            }
        }

        return '';
    }
}
