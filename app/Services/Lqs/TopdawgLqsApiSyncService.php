<?php

namespace App\Services\Lqs;

use App\Models\LqsMarketplaceScore;
use App\Models\ProductMaster;
use App\Models\TopDawgProduct;
use App\Support\Marketplace\ChannelListingRegistry;
use App\Support\Marketplace\ListingCountsEngine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Scores TopDawg listings (LQS 0-100) and stores the result in lqs_marketplace_scores (marketplace = 'topdawg').
 *
 * TopDawg's catalog list API is capped at 25,000 calls a day, so scoring makes no API calls. It reads
 * topdawg_products, which the scheduled product sync fills from the live catalog (title, price, MSRP, stock, TDID,
 * state). TopDawg's list does not give the description, bullets or photo set, so those come from what this app
 * pushes (topdawg_metrics first, then Product Master).
 *
 * @see TopdawgListingQualityScorer
 */
class TopdawgLqsApiSyncService
{
    public const MARKETPLACE = 'topdawg';

    public const LOCK_KEY = 'lqs-sync-topdawg-lock';

    public const STATUS_KEY = 'lqs-sync-topdawg-status';

    private const TITLE_FIELDS = ['title100', 'title150', 'title80', 'title75', 'title60'];

    private const DESCRIPTION_FIELDS = [
        'product_description', 'description_1500', 'description_1000', 'description_800',
        'description_600', 'description_v2_description', 'description_html',
    ];

    public function __construct(private readonly TopdawgListingQualityScorer $scorer)
    {
    }

    /** Scoring needs only local tables. */
    public function isConfigured(): bool
    {
        return Schema::hasTable('topdawg_products') && Schema::hasTable((new ProductMaster)->getTable());
    }

    public static function normalizeSku(string $sku): string
    {
        return strtoupper(trim(str_replace("\u{00a0}", ' ', $sku)));
    }

    /**
     * Content + health context for every live TopDawg listing (or just $only), keyed by normalized SKU.
     *
     * @param  list<string>|null  $only
     * @return array<string, array<string, mixed>>
     */
    public function listedContexts(?array $only = null): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $wanted = $only === null ? null : array_flip(array_map([self::class, 'normalizeSku'], $only));

        // Newest synced catalog row per SKU that is a real listing (not a placeholder from a failed create).
        $products = [];
        foreach (TopDawgProduct::query()->whereNotNull('sku')->where('sku', '!=', '')->orderBy('id')->cursor() as $row) {
            if (! $row->countsAsLiveCatalogListing()) {
                continue;
            }
            $key = self::normalizeSku((string) $row->sku);
            if ($wanted === null || isset($wanted[$key])) {
                $products[$key] = $row; // later ids overwrite earlier ones
            }
        }
        if ($products === []) {
            return [];
        }

        $masters = [];
        foreach (ProductMaster::query()
            ->select($this->masterColumns())
            ->whereNotNull('sku')->where('sku', '!=', '')
            ->whereRaw('UPPER(sku) NOT LIKE ?', ['%PARENT%'])
            ->get() as $pm) {
            $key = self::normalizeSku((string) $pm->sku);
            if (isset($products[$key])) {
                $masters[$key] ??= $pm;
            }
        }

        $metrics = $this->metricsBySku($products);

        $out = [];
        foreach ($masters as $key => $pm) {
            $out[$key] = $this->contextFor($pm, $products[$key], $metrics[$key] ?? null);
        }

        return $out;
    }

    /** SKUs live on TopDawg that exist in Product Master (the page's "of N" denominator). */
    public function catalogSkus(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        return Cache::remember('lqs-topdawg-catalog-skus', 60, function () {
            $live = [];
            foreach (TopDawgProduct::query()->whereNotNull('sku')->where('sku', '!=', '')->cursor() as $row) {
                if ($row->countsAsLiveCatalogListing()) {
                    $live[self::normalizeSku((string) $row->sku)] = true;
                }
            }

            return ProductMaster::query()
                ->whereNotNull('sku')->where('sku', '!=', '')
                ->whereRaw('UPPER(sku) NOT LIKE ?', ['%PARENT%'])
                ->pluck('sku')
                ->map(fn ($s) => self::normalizeSku((string) $s))
                ->filter(fn ($s) => isset($live[$s]))
                ->unique()
                ->values()
                ->all();
        });
    }

    /**
     * @param  array<string, mixed>  $context  one entry of listedContexts()
     * @return array{score: float, facts: array<string, mixed>, checks: list<array<string, mixed>>, findings: string, suggestions: string}
     */
    public function assess(array $context): array
    {
        return $this->scorer->score($context);
    }

    /**
     * Score a listing and save it.
     *
     * @param  array<string, mixed>  $context
     * @return array{sku: string, lqs: float, findings: string, suggestions: string, checks: list<array<string, mixed>>}
     */
    public function saveListing(array $context): array
    {
        $key = self::normalizeSku((string) ($context['sku'] ?? ''));
        if ($key === '') {
            throw new \RuntimeException('TopDawg listing has no SKU.');
        }

        $result = $this->assess($context);
        $row = ['lqs' => $result['score']];
        if (($context['listing_id'] ?? '') !== '') {
            $row['listing_id'] = (string) $context['listing_id'];
        }
        if ($result['facts']['price'] > 0) {
            $row['price'] = $result['facts']['price'];
        }
        if (Schema::hasColumn('lqs_marketplace_scores', 'audit_findings')) {
            $row['audit_findings'] = $result['findings'];
            $row['audit_suggestions'] = $result['suggestions'];
        }

        LqsMarketplaceScore::updateOrCreate(['marketplace' => self::MARKETPLACE, 'sku' => $key], $row);

        return [
            'sku' => $key,
            'lqs' => $result['score'],
            'findings' => $result['findings'],
            'suggestions' => $result['suggestions'],
            'checks' => $result['checks'],
        ];
    }

    /** @return array{sku: string, lqs: float, findings: string, suggestions: string, checks: list<array<string, mixed>>} */
    public function syncSku(string $sku): array
    {
        $context = $this->listedContexts([$sku])[self::normalizeSku($sku)] ?? null;
        if ($context === null) {
            throw new \RuntimeException('SKU '.$sku.' is not a live TopDawg listing (no synced topdawg_products row with a real TDID).');
        }

        return $this->saveListing($context);
    }

    /**
     * @return array<string, mixed>
     */
    private function contextFor(ProductMaster $pm, TopDawgProduct $product, ?object $metric): array
    {
        $values = is_array($pm->Values ?? null) ? $pm->Values : [];

        $title = trim((string) ($product->product_title ?? ''));
        if ($title === '') {
            $title = trim((string) ($metric->title ?? ''));
        }
        if ($title === '') {
            foreach (self::TITLE_FIELDS as $field) {
                $title = trim((string) ($pm->{$field} ?? ''));
                if ($title !== '') {
                    break;
                }
            }
        }

        $description = trim((string) ($metric->description_master ?? ''));
        if ($description === '') {
            foreach (self::DESCRIPTION_FIELDS as $field) {
                $description = trim((string) ($pm->{$field} ?? ''));
                if ($description !== '') {
                    break;
                }
            }
        }

        $bullets = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) ($metric->bullet_points ?? '')) ?: []), fn ($l) => $l !== ''));
        if ($bullets === []) {
            foreach (['bullet1', 'bullet2', 'bullet3', 'bullet4', 'bullet5'] as $f) {
                $v = trim((string) ($pm->{$f} ?? ''));
                if ($v !== '' && ! in_array($v, $bullets, true)) {
                    $bullets[] = $v;
                }
            }
        }

        $images = $this->urlList((string) ($metric->image_urls ?? ''));
        if ($images === []) {
            foreach (array_merge(['main_image', 'main_image_brand'], array_map(fn ($i) => 'image'.$i, range(1, 12))) as $field) {
                foreach (preg_split('/[|,;\s]+/', trim((string) ($pm->{$field} ?? ''))) ?: [] as $url) {
                    if (preg_match('#^https?://#i', $url) && ! in_array($url, $images, true)) {
                        $images[] = $url;
                    }
                }
            }
        }
        $live = trim((string) ($product->image_src ?? ''));
        if ($live !== '' && ! in_array($live, $images, true)) {
            array_unshift($images, $live); // the live main photo always counts
        }

        $sku = trim((string) $product->sku);
        $listingId = trim((string) ($product->topdawg_listing_id ?? ''));
        $tdid = trim((string) ($product->tdid ?? ''));
        $liveId = ChannelListingRegistry::isLiveTopDawgListingId($listingId, $sku) ? $listingId : (ChannelListingRegistry::isLiveTopDawgListingId($tdid, $sku) ? $tdid : '');

        $state = strtolower(trim((string) ($product->listing_state ?? '')));
        $published = ! ListingCountsEngine::isPendingOrReviewListingState($state)
            && ! in_array($state, ['inactive', 'disabled', 'deactivated', 'archived', 'suspended', 'no', 'false', '0'], true);

        return [
            'sku' => trim((string) $pm->sku),
            'listing_id' => $liveId,
            'title' => $title,
            'description' => $description,
            'bullets' => $bullets,
            'images' => $images,
            'price' => (float) ($product->price ?? 0),
            'msrp' => (float) ($product->msrp ?? 0),
            'stock' => (int) ($product->remaining_inventory ?? 0),
            'published' => $published,
            'has_live_id' => $liveId !== '',
            'upc' => trim((string) ($values['upc'] ?? '')) ?: trim((string) ($pm->barcode ?? '')),
            'length' => $values['l'] ?? 0,
            'width' => $values['w'] ?? 0,
            'height' => $values['h'] ?? 0,
            'weight' => $values['wt_act'] ?? ($values['weight'] ?? 0),
            'origin' => $values['country_of_origin'] ?? '',
        ];
    }

    /** @return list<string> */
    private function urlList(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        $items = is_array($decoded) ? $decoded : (preg_split('/[\s,|;]+/', $raw) ?: []);

        return array_values(array_filter(array_map(fn ($u) => is_string($u) ? trim($u) : '', $items), fn ($u) => preg_match('#^https?://#i', $u) === 1));
    }

    /**
     * Only the columns scoring reads: product_master rows carry very large A+/HTML columns that would exhaust memory.
     *
     * @return list<string>
     */
    private function masterColumns(): array
    {
        static $columns = null;
        if ($columns === null) {
            $wanted = array_merge(
                ['id', 'sku', 'Values', 'barcode', 'main_image', 'main_image_brand'],
                self::TITLE_FIELDS,
                self::DESCRIPTION_FIELDS,
                ['bullet1', 'bullet2', 'bullet3', 'bullet4', 'bullet5'],
                array_map(fn ($i) => 'image'.$i, range(1, 12)),
            );
            $columns = array_values(array_intersect($wanted, Schema::getColumnListing((new ProductMaster)->getTable())));
        }

        return $columns;
    }

    /**
     * Newest topdawg_metrics row (content this app pushed) per SKU being scored.
     *
     * @param  array<string, object>  $products
     * @return array<string, object>
     */
    private function metricsBySku(array $products): array
    {
        if (! Schema::hasTable('topdawg_metrics')) {
            return [];
        }

        $out = [];
        foreach (DB::table('topdawg_metrics')->whereNotNull('sku')->where('sku', '!=', '')->orderBy('id')->cursor() as $row) {
            $key = self::normalizeSku((string) $row->sku);
            if (isset($products[$key])) {
                $out[$key] = $row;
            }
        }

        return $out;
    }
}
