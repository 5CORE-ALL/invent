<?php

namespace App\Support\Marketplace;

use App\Models\AmazonListingRaw;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * eBay Taxonomy aspects per category: which item specifics are required, which may be
 * variation specifics, and filling missing required ones from Product Master / Amazon / Shopify.
 */
class EbayCategoryAspects
{
    private const VARIATION_PREFERENCE = ['Model', 'Pack', 'Number in Pack', 'Style', 'Type', 'Color', 'Size', 'Impedance', 'Configuration', 'Version', 'Item Length'];

    private const KNOWN_DEFAULTS = [
        'connectivity' => 'Wired',
        'wirelesstechnology' => 'Not Applicable',
        'countryregionofmanufacture' => 'China',
        'californiaprop65warning' => 'No',
    ];

    /**
     * @return list<array{name: string, required: bool, variations: bool, free_text: bool, values: list<string>}>
     */
    public static function forCategory(string $categoryId, string $token): array
    {
        $categoryId = trim($categoryId);
        if ($categoryId === '' || $token === '') {
            return [];
        }
        $cacheKey = 'ebay_category_aspects_v1:'.$categoryId;
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $res = Http::timeout(30)
                ->withToken($token)
                ->acceptJson()
                ->get('https://api.ebay.com/commerce/taxonomy/v1/category_tree/0/get_item_aspects_for_category', [
                    'category_id' => $categoryId,
                ]);
            if (! $res->successful()) {
                Log::warning('eBay taxonomy aspects failed', ['category' => $categoryId, 'status' => $res->status(), 'body' => substr($res->body(), 0, 500)]);

                return [];
            }
            $out = [];
            foreach ((array) ($res->json('aspects') ?? []) as $a) {
                $name = trim((string) ($a['localizedAspectName'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $c = (array) ($a['aspectConstraint'] ?? []);
                $out[] = [
                    'name' => $name,
                    'required' => (bool) ($c['aspectRequired'] ?? false),
                    'variations' => (bool) ($c['aspectEnabledForVariations'] ?? false),
                    'free_text' => strtoupper((string) ($c['aspectMode'] ?? 'FREE_TEXT')) !== 'SELECTION_ONLY',
                    'values' => array_values(array_filter(array_map(
                        static fn ($v) => trim((string) ($v['localizedValue'] ?? '')),
                        (array) ($a['aspectValues'] ?? [])
                    ))),
                ];
            }
            Cache::put($cacheKey, $out, now()->addDays(7));

            return $out;
        } catch (\Throwable $e) {
            Log::warning('eBay taxonomy aspects error', ['category' => $categoryId, 'error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Keep the preferred variation name when the category allows it, else the best allowed one.
     *
     * @param  list<array{name: string, required: bool, variations: bool, free_text: bool, values: list<string>}>  $aspects
     */
    public static function variationAspect(array $aspects, string $preferred): string
    {
        $enabled = array_values(array_filter($aspects, static fn ($a) => $a['variations']));
        if ($enabled === []) {
            return $preferred;
        }
        foreach ($enabled as $a) {
            if (strcasecmp($a['name'], $preferred) === 0) {
                return $a['name'];
            }
        }
        $passes = [
            static fn ($a) => $a['free_text'] && ! $a['required'],
            static fn ($a) => $a['free_text'],
            static fn ($a) => true,
        ];
        foreach ($passes as $ok) {
            foreach (self::VARIATION_PREFERENCE as $want) {
                foreach ($enabled as $a) {
                    if (strcasecmp($a['name'], $want) === 0 && $ok($a)) {
                        return $a['name'];
                    }
                }
            }
            foreach ($enabled as $a) {
                if ($ok($a)) {
                    return $a['name'];
                }
            }
        }

        return $preferred;
    }

    /**
     * Fill missing required aspects. Returns [specifics, defaulted names => value].
     *
     * @param  array<string, mixed>  $specifics
     * @param  list<array{name: string, required: bool, variations: bool, free_text: bool, values: list<string>}>  $aspects
     * @param  list<string>  $skus
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    public static function fillRequired(array $specifics, array $aspects, array $skus, string $text, string $skipAspect = ''): array
    {
        $have = [];
        foreach ($specifics as $k => $v) {
            if (trim((string) $v) !== '') {
                $have[self::key((string) $k)] = true;
            }
        }

        $sources = null;
        $defaulted = [];
        foreach ($aspects as $aspect) {
            $k = self::key($aspect['name']);
            if (! $aspect['required'] || isset($have[$k]) || $k === 'upc' || ($skipAspect !== '' && strcasecmp($aspect['name'], $skipAspect) === 0)) {
                continue;
            }
            $sources ??= self::sourceValues($skus);
            $haystack = strtolower(strip_tags($text.' '.implode(' ', $sources)));

            $value = self::fromSources($aspect, $sources);
            if ($value === '') {
                $value = self::fromText($aspect, $haystack);
            }
            if ($value === '') {
                $value = self::fallback($aspect);
                if ($value !== '') {
                    $defaulted[$aspect['name']] = $value;
                }
            }
            if ($value !== '') {
                $specifics[$aspect['name']] = $value;
            }
        }

        return [$specifics, $defaulted];
    }

    private static function key(string $name): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($name)) ?? '';
    }

    /**
     * @param  array{name: string, free_text: bool, values: list<string>}  $aspect
     * @param  array<string, string>  $sources
     */
    private static function fromSources(array $aspect, array $sources): string
    {
        $want = self::key($aspect['name']);
        $best = '';
        foreach ($sources as $k => $v) {
            if ($k === $want) {
                $best = $v;
                break;
            }
            if ($best === '' && strlen($want) >= 5 && strlen($k) >= 5 && (str_contains($k, $want) || str_contains($want, $k))) {
                $best = $v;
            }
        }
        if ($best === '') {
            return '';
        }

        return self::conform($aspect, $best);
    }

    /**
     * @param  array{free_text: bool, values: list<string>}  $aspect
     */
    private static function conform(array $aspect, string $value): string
    {
        $value = trim(strip_tags($value));
        if ($value === '' || mb_strlen($value) > 65) {
            return $aspect['free_text'] && $value !== '' ? mb_substr($value, 0, 65) : '';
        }
        if ($aspect['values'] === []) {
            return $value;
        }
        foreach ($aspect['values'] as $allowed) {
            if (strcasecmp($allowed, $value) === 0) {
                return $allowed;
            }
        }
        foreach ($aspect['values'] as $allowed) {
            if (stripos($value, $allowed) !== false || stripos($allowed, $value) !== false) {
                return $allowed;
            }
        }

        return $aspect['free_text'] ? $value : '';
    }

    /**
     * @param  array{values: list<string>}  $aspect
     */
    private static function fromText(array $aspect, string $haystack): string
    {
        $values = $aspect['values'];
        usort($values, static fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        foreach ($values as $v) {
            if (mb_strlen($v) >= 3 && preg_match('/\b'.preg_quote(strtolower($v), '/').'\b/u', $haystack)) {
                return $v;
            }
        }

        return '';
    }

    /**
     * @param  array{name: string, free_text: bool, values: list<string>}  $aspect
     */
    private static function fallback(array $aspect): string
    {
        $k = self::key($aspect['name']);
        if (isset(self::KNOWN_DEFAULTS[$k])) {
            $v = self::conform($aspect, self::KNOWN_DEFAULTS[$k]);
            if ($v !== '') {
                return $v;
            }
        }
        foreach ($aspect['values'] as $v) {
            if (in_array(strtolower($v), ['does not apply', 'not applicable', 'n/a', 'unbranded'], true)) {
                return $v;
            }
        }
        if ($aspect['free_text']) {
            return 'Does Not Apply';
        }

        return $aspect['values'][0] ?? '';
    }

    /**
     * Normalized attribute key => value from Product Master, Amazon listing and Shopify 5 Core.
     *
     * @param  list<string>  $skus
     * @return array<string, string>
     */
    private static function sourceValues(array $skus): array
    {
        $out = [];
        $put = static function (string $k, mixed $v) use (&$out): void {
            $k = self::key($k);
            $v = is_scalar($v) ? trim((string) $v) : '';
            if ($k !== '' && $v !== '' && ! isset($out[$k]) && mb_strlen($v) <= 5000) {
                $out[$k] = $v;
            }
        };
        $flatten = static function (mixed $data, string $prefix = '') use (&$flatten, $put): void {
            if (! is_array($data)) {
                return;
            }
            foreach ($data as $k => $v) {
                $name = is_int($k) ? $prefix : (string) $k;
                if (is_array($v)) {
                    if (isset($v['value']) && is_scalar($v['value'])) {
                        $put($name, $v['value']);
                    } else {
                        $flatten($v, $name);
                    }
                } else {
                    $put($name, $v);
                }
            }
        };

        foreach ($skus as $sku) {
            $sku = trim($sku);
            if ($sku === '') {
                continue;
            }
            try {
                if (Schema::hasTable('product_master')) {
                    $pm = DB::table('product_master')->where('sku', $sku)->first();
                    if ($pm) {
                        $row = (array) $pm;
                        $values = $row['Values'] ?? $row['values'] ?? null;
                        unset($row['Values'], $row['values']);
                        $flatten($row);
                        $flatten(is_string($values) ? (json_decode($values, true) ?: []) : (array) $values);
                    }
                }
            } catch (\Throwable) {
            }
            try {
                $listing = AmazonListingRaw::query()->where('seller_sku', $sku)->first();
                if ($listing) {
                    $flatten(is_array($listing->raw_data) ? $listing->raw_data : []);
                    $flatten($listing->getAttributes());
                }
            } catch (\Throwable) {
            }
            try {
                if (Schema::hasTable('shopify_catalog_variants')) {
                    $p = DB::table('shopify_catalog_variants as v')
                        ->join('shopify_catalog_products as p', 'p.id', '=', 'v.shopify_catalog_product_id')
                        ->where('v.store', 'main')
                        ->whereRaw('UPPER(TRIM(v.sku)) = ?', [strtoupper($sku)])
                        ->first(['p.product_type', 'p.vendor', 'p.body_html', 'v.variant_title']);
                    if ($p) {
                        $put('product_type', $p->product_type);
                        $put('shopify_body', strip_tags((string) $p->body_html));
                        $put('variant_title', $p->variant_title);
                    }
                }
            } catch (\Throwable) {
            }
        }

        return $out;
    }
}
