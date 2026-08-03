<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;
use App\Models\AmazonDatasheet;
use App\Models\ProductMaster;
use App\Models\ShortTitle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ShortTitleMasterController extends Controller
{
    /** Internal-use short title hard limit. */
    public const MAX_LENGTH = 40;

    public function index(Request $request)
    {
        return view('short-title-master', [
            'mode' => $request->query('mode', ''),
            'demo' => $request->query('demo', ''),
        ]);
    }

    /**
     * GET /short-title-master-data
     */
    public function getData()
    {
        try {
            // Same ordering as /product-master: parent asc, PARENT SKU rows last within group
            $products = ProductMaster::query()
                ->orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc')
                ->select(['id', 'sku', 'parent', 'Values', 'main_image', 'image1', 'title150'])
                ->get();

            $skus = $products->pluck('sku')->map(fn ($s) => trim((string) $s))->filter()->values()->all();

            $shortBySku = ShortTitle::query()
                ->whereIn('sku', $skus)
                ->get()
                ->keyBy(fn ($row) => trim((string) $row->sku));

            $amazonTitles = $this->amazonTitlesBySku($skus);

            $rows = [];
            foreach ($products as $product) {
                $sku = trim((string) $product->sku);
                if ($sku === '') {
                    continue;
                }

                $values = $this->productValues($product);
                $saved = $shortBySku->get($sku);
                $amazonTitle = $amazonTitles[$sku]
                    ?? trim((string) ($product->title150 ?? ''));

                $rows[] = [
                    'id' => $product->id,
                    'sku' => $sku,
                    'parent' => trim((string) ($product->parent ?? '')),
                    'image' => $this->resolveProductImage($product, $values),
                    'short_title' => $saved ? trim((string) ($saved->short_title ?? '')) : '',
                    'amazon_title' => $amazonTitle,
                    'has_saved' => $saved !== null && trim((string) ($saved->short_title ?? '')) !== '',
                ];
            }

            return response()->json([
                'message' => 'Short Title Master data loaded',
                'data' => $rows,
                'status' => 200,
            ]);
        } catch (\Throwable $e) {
            Log::error('ShortTitleMaster getData failed', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Failed to load Short Title Master data.',
                'error' => $e->getMessage(),
                'status' => 500,
            ], 500);
        }
    }

    /**
     * POST /short-title-master/save
     */
    public function save(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:191',
            'short_title' => 'nullable|string|max:'.self::MAX_LENGTH,
        ]);

        $sku = trim($validated['sku']);
        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU is required.'], 422);
        }

        try {
            $shortTitle = $this->limitToWords(trim((string) ($validated['short_title'] ?? '')), self::MAX_LENGTH);
            $row = ShortTitle::updateOrCreate(
                ['sku' => $sku],
                ['short_title' => $shortTitle]
            );

            return response()->json([
                'success' => true,
                'message' => 'Short title saved.',
                'sku' => $row->sku,
                'short_title' => $row->short_title,
            ]);
        } catch (\Throwable $e) {
            Log::error('ShortTitleMaster save failed', ['error' => $e->getMessage(), 'sku' => $sku]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save short title: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /short-title-master/autopopulate
     * Fill empty short_titles from Amazon title (strip "5 Core" + SKU), max 40 chars by whole words.
     * Also shortens any existing short titles longer than 40 characters.
     * Requires selected SKUs — does not run on the full catalog.
     */
    public function autopopulate(Request $request)
    {
        $onlyMissing = (bool) $request->boolean('only_missing', true);
        $requestedSkus = collect($request->input('skus', []))
            ->map(fn ($s) => trim((string) $s))
            ->filter(fn ($s) => $s !== '' && !str_contains(strtoupper($s), 'PARENT'))
            ->unique()
            ->values()
            ->all();

        if ($requestedSkus === []) {
            return response()->json([
                'success' => false,
                'message' => 'Select at least one SKU to autopopulate.',
            ], 422);
        }

        try {
            $products = ProductMaster::query()
                ->select(['id', 'sku', 'title150'])
                ->whereIn('sku', $requestedSkus)
                ->get();

            $skus = $products->pluck('sku')->map(fn ($s) => trim((string) $s))->filter()->values()->all();
            $amazonTitles = $this->amazonTitlesBySku($skus);

            $existing = ShortTitle::query()
                ->whereIn('sku', $skus)
                ->get()
                ->keyBy(fn ($row) => trim((string) $row->sku));

            $populated = 0;
            $shortened = 0;
            $skipped = 0;
            $failed = 0;

            foreach ($products as $product) {
                $sku = trim((string) $product->sku);
                if ($sku === '') {
                    $skipped++;
                    continue;
                }

                $saved = $existing->get($sku);
                $current = $saved ? trim((string) ($saved->short_title ?? '')) : '';
                $hasSaved = $current !== '';
                $tooLong = $hasSaved && mb_strlen($current) > self::MAX_LENGTH;

                if ($onlyMissing && $hasSaved && !$tooLong) {
                    $skipped++;
                    continue;
                }

                $amazonTitle = $amazonTitles[$sku]
                    ?? trim((string) ($product->title150 ?? ''));

                $shortTitle = '';
                if ($amazonTitle !== '') {
                    $shortTitle = $this->buildShortTitleFromAmazon($amazonTitle, $sku);
                } elseif ($tooLong) {
                    // No Amazon source — shorten the existing internal title by whole words.
                    $shortTitle = $this->limitToWords($current, self::MAX_LENGTH);
                }

                if ($shortTitle === '') {
                    $skipped++;
                    continue;
                }

                try {
                    ShortTitle::updateOrCreate(
                        ['sku' => $sku],
                        [
                            'short_title' => $shortTitle,
                            'source_amazon_title' => $amazonTitle !== '' ? $amazonTitle : ($saved->source_amazon_title ?? null),
                        ]
                    );
                    if ($tooLong) {
                        $shortened++;
                    } else {
                        $populated++;
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    Log::warning('ShortTitleMaster autopopulate row failed', [
                        'sku' => $sku,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Autopopulated {$populated} short title(s). Shortened {$shortened} over-length. Skipped {$skipped}. Failed {$failed}.",
                'populated' => $populated,
                'shortened' => $shortened,
                'skipped' => $skipped,
                'failed' => $failed,
            ]);
        } catch (\Throwable $e) {
            Log::error('ShortTitleMaster autopopulate failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Autopopulate failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resolve Amazon title per SKU: listings item_name, then datasheet amazon_title.
     *
     * @param  array<int, string>  $skus
     * @return array<string, string> sku => title
     */
    private function amazonTitlesBySku(array $skus): array
    {
        $map = [];
        if ($skus === []) {
            return $map;
        }

        if (Schema::hasTable('amazon_listings_raw') && Schema::hasColumn('amazon_listings_raw', 'item_name')) {
            $latestIds = DB::table('amazon_listings_raw')
                ->select('seller_sku', DB::raw('MAX(id) as max_id'))
                ->whereIn('seller_sku', $skus)
                ->groupBy('seller_sku');

            $listings = DB::table('amazon_listings_raw as alr')
                ->joinSub($latestIds, 'latest', function ($join) {
                    $join->on('alr.seller_sku', '=', 'latest.seller_sku')
                        ->on('alr.id', '=', 'latest.max_id');
                })
                ->select(['alr.seller_sku', 'alr.item_name'])
                ->get();

            foreach ($listings as $row) {
                $sku = trim((string) $row->seller_sku);
                $title = trim((string) ($row->item_name ?? ''));
                if ($sku !== '' && $title !== '') {
                    $map[$sku] = $title;
                }
            }
        }

        if (Schema::hasTable('amazon_datsheets') && Schema::hasColumn('amazon_datsheets', 'amazon_title')) {
            $missing = array_values(array_filter($skus, fn ($s) => !isset($map[$s])));
            if ($missing !== []) {
                AmazonDatasheet::query()
                    ->whereIn('sku', $missing)
                    ->select(['sku', 'amazon_title'])
                    ->get()
                    ->each(function ($row) use (&$map) {
                        $sku = trim((string) $row->sku);
                        $title = trim((string) ($row->amazon_title ?? ''));
                        if ($sku !== '' && $title !== '' && !isset($map[$sku])) {
                            $map[$sku] = $title;
                        }
                    });
            }
        }

        return $map;
    }

    /**
     * Strip brand "5 Core" variants and the SKU from an Amazon title,
     * then keep whole leading words up to MAX_LENGTH (internal use).
     */
    public function buildShortTitleFromAmazon(string $amazonTitle, string $sku): string
    {
        $title = trim($amazonTitle);
        if ($title === '') {
            return '';
        }

        // Remove brand variants: 5 Core, 5Core, 5-Core, 5_Core, etc.
        $title = preg_replace('/\b5[\s\-_]*core\b/iu', ' ', $title) ?? $title;

        // Remove SKU if present (case-insensitive, allow flexible whitespace in SKU)
        $sku = trim($sku);
        if ($sku !== '') {
            $skuPattern = preg_quote($sku, '/');
            $skuPattern = preg_replace('/\s+/', '\\s+', $skuPattern) ?? $skuPattern;
            $title = preg_replace('/'. $skuPattern .'/iu', ' ', $title) ?? $title;

            // Also try space-stripped SKU match (e.g. "DP 200" vs "DP200")
            $skuCompact = preg_replace('/\s+/', '', $sku) ?? $sku;
            if ($skuCompact !== '' && strcasecmp($skuCompact, $sku) !== 0) {
                $compactPattern = preg_quote($skuCompact, '/');
                $title = preg_replace('/'. $compactPattern .'/iu', ' ', $title) ?? $title;
            }
        }

        // Normalize leftover punctuation/spacing
        $title = preg_replace('/\s*([|,;:\-\/])\s*/u', ' $1 ', $title) ?? $title;
        $title = preg_replace('/\s{2,}/u', ' ', $title) ?? $title;
        $title = preg_replace('/\s*([|,;:\-\/])\s*([|,;:\-\/])+/u', '$1', $title) ?? $title;
        $title = trim($title, " \t\n\r\0\x0B|,;:-/");

        return $this->limitToWords($title, self::MAX_LENGTH);
    }

    /**
     * Keep whole words from the start until max length (never mid-word cut).
     * Drops trailing filler words so the result stays usable for internal labels.
     */
    public function limitToWords(string $title, int $max = self::MAX_LENGTH): string
    {
        $title = trim(preg_replace('/\s{2,}/u', ' ', $title) ?? $title);
        if ($title === '' || mb_strlen($title) <= $max) {
            return $title;
        }

        $words = preg_split('/\s+/u', $title, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $kept = [];
        $len = 0;

        foreach ($words as $word) {
            $word = trim($word, " \t|,;:-/");
            if ($word === '') {
                continue;
            }
            $addLen = mb_strlen($word) + ($kept === [] ? 0 : 1);
            if ($len + $addLen > $max) {
                break;
            }
            $kept[] = $word;
            $len += $addLen;
        }

        // Drop weak trailing connectors so remaining words stay meaningful.
        $filler = ['for', 'and', 'with', 'w', 'the', 'a', 'an', 'of', 'to', 'in', 'on', '&', '-'];
        while ($kept !== []) {
            $last = mb_strtolower((string) end($kept));
            $last = rtrim($last, '.,;:/-');
            if (!in_array($last, $filler, true)) {
                break;
            }
            array_pop($kept);
        }

        $out = trim(implode(' ', $kept));

        // Fallback: hard cut only if a single word itself exceeds max.
        if ($out === '' && $words !== []) {
            $out = mb_substr($words[0], 0, $max);
        }

        return $out;
    }

    private function productValues(ProductMaster $product): array
    {
        $values = $product->Values;
        if (is_string($values)) {
            $decoded = json_decode($values, true);
            $values = is_array($decoded) ? $decoded : [];
        } elseif (!is_array($values)) {
            $values = [];
        }

        return $values;
    }

    private function resolveProductImage(ProductMaster $product, ?array $values = null): ?string
    {
        $candidates = [];
        $values = $values ?? $this->productValues($product);

        foreach (['image_path', 'image', 'Image', 'main_image'] as $key) {
            if (!empty($values[$key])) {
                $candidates[] = $values[$key];
                break;
            }
        }

        if (!empty($product->main_image)) {
            $candidates[] = $product->main_image;
        }
        if (!empty($product->image1)) {
            $candidates[] = $product->image1;
        }

        foreach ($candidates as $candidate) {
            $url = $this->normalizeImageUrl($candidate);
            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }

    private function normalizeImageUrl(mixed $path): ?string
    {
        $p = trim((string) ($path ?? ''));
        if ($p === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $p) || str_starts_with($p, 'data:')) {
            return $p;
        }

        return '/' . ltrim($p, '/');
    }
}
