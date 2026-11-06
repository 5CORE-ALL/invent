<?php

namespace App\Services\Lqs;

use App\Models\LqsMarketplaceScore;
use App\Models\ProductMaster;
use App\Support\Marketplace\ChannelListingRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Scores Wayfair listings (LQS 0-100) and stores the result in lqs_marketplace_scores (marketplace = 'wayfair').
 *
 * Wayfair's Product Catalog read (attributes/insights) answers "Unauthorized supplier access" for this supplier account,
 * so there is no live listing feed to read. The score is built from this app's records of each listing instead:
 * Product Master content (what is pushed to Wayfair, overridden by wayfair_metrics when the Wayfair page saved
 * its own copy), the Partner Home listing state (wayfair_listing_statuses) and the uploaded price/stock
 * (wayfair_pricing_prices). Only SKUs that count as Listed on Wayfair are scored, with no network calls, so a
 * full run takes seconds.
 *
 * @see WayfairListingQualityScorer
 */
class WayfairLqsApiSyncService
{
    public const MARKETPLACE = 'wayfair';

    public const LOCK_KEY = 'lqs-sync-wayfair-lock';

    public const STATUS_KEY = 'lqs-sync-wayfair-status';

    private const TITLE_FIELDS = ['title80', 'title100', 'title150', 'title60', 'title75'];

    private const DESCRIPTION_FIELDS = [
        'product_description', 'description_1500', 'description_1000', 'description_800',
        'description_600', 'description_v2_description', 'description_html',
    ];

    public function __construct(private readonly WayfairListingQualityScorer $scorer)
    {
    }

    /** Scoring needs only local tables, so it is always available. */
    public function isConfigured(): bool
    {
        return Schema::hasTable((new ProductMaster)->getTable());
    }

    public static function normalizeSku(string $sku): string
    {
        return strtoupper(trim(str_replace("\u{00a0}", ' ', $sku)));
    }

    /**
     * Content + health context for every SKU listed on Wayfair (or just $only), keyed by normalized SKU.
     *
     * @param  list<string>|null  $only
     * @return array<string, array<string, mixed>>
     */
    public function listedContexts(?array $only = null): array
    {
        $query = ProductMaster::query()
            ->select($this->masterColumns())
            ->whereNotNull('sku')->where('sku', '!=', '')
            ->whereRaw('UPPER(sku) NOT LIKE ?', ['%PARENT%']);
        if ($only !== null) {
            $wanted = array_map([self::class, 'normalizeSku'], $only);
            $query->whereRaw('UPPER(TRIM(REPLACE(sku, ?, " "))) IN ('.implode(',', array_fill(0, max(1, count($wanted)), '?')).')', array_merge(["\u{00a0}"], $wanted ?: ['']));
        }
        $masters = $query->get();
        if ($masters->isEmpty()) {
            return [];
        }

        $listed = ChannelListingRegistry::listedWayfair($masters->pluck('sku')->map(fn ($s) => trim((string) $s))->all());
        $masters = $masters->filter(fn ($pm) => isset($listed[strtolower(trim((string) $pm->sku))]));
        if ($masters->isEmpty()) {
            return [];
        }

        $parents = $this->parentRows($masters);
        $metrics = $this->bySku('wayfair_metrics');
        $statuses = $this->bySku('wayfair_listing_statuses');
        $prices = $this->bySku('wayfair_pricing_prices');

        $out = [];
        foreach ($masters as $pm) {
            $sku = trim((string) $pm->sku);
            $key = self::normalizeSku($sku);
            if (isset($out[$key])) {
                continue;
            }
            $out[$key] = $this->contextFor(
                $pm,
                $parents[strtolower(trim((string) ($pm->parent ?? '')))] ?? null,
                $metrics[$key] ?? null,
                $statuses[$key] ?? null,
                $prices[$key] ?? null,
                $listed[strtolower($sku)] ?? ''
            );
        }

        return $out;
    }

    /** SKUs counted as Listed on Wayfair (the page's "of N" denominator). */
    public function catalogSkus(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        // The page polls this while a sync runs, so keep it for a minute.
        return Cache::remember('lqs-wayfair-catalog-skus', 60, function () {
            $skus = ProductMaster::query()
                ->whereNotNull('sku')->where('sku', '!=', '')
                ->whereRaw('UPPER(sku) NOT LIKE ?', ['%PARENT%'])
                ->pluck('sku')
                ->map(fn ($s) => trim((string) $s))
                ->all();

            // The registry map also holds alias keys; a SKU is listed when its own lowercase key is present.
            $listed = ChannelListingRegistry::listedWayfair($skus);

            return collect($skus)
                ->filter(fn ($s) => isset($listed[strtolower($s)]))
                ->map(fn ($s) => self::normalizeSku($s))
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
            throw new \RuntimeException('Wayfair listing has no SKU.');
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
        $contexts = $this->listedContexts([$sku]);
        $context = $contexts[self::normalizeSku($sku)] ?? null;
        if ($context === null) {
            throw new \RuntimeException('SKU '.$sku.' is not listed on Wayfair (no live listing status or uploaded price).');
        }

        return $this->saveListing($context);
    }

    /**
     * @param  object|null  $metric  wayfair_metrics row (content the Wayfair page saved)
     * @param  object|null  $status  wayfair_listing_statuses row
     * @param  object|null  $price  wayfair_pricing_prices row
     * @return array<string, mixed>
     */
    private function contextFor(ProductMaster $pm, ?ProductMaster $parent, ?object $metric, ?object $status, ?object $price, string $listingId): array
    {
        $values = is_array($pm->Values ?? null) ? $pm->Values : [];

        $title = $this->firstText($pm, self::TITLE_FIELDS) ?: ($parent ? $this->firstText($parent, self::TITLE_FIELDS) : '');

        $description = trim((string) ($metric->description_master ?? ''));
        if ($description === '') {
            $description = $this->firstText($pm, self::DESCRIPTION_FIELDS);
        }

        $bullets = $this->lines((string) ($metric->bullet_points ?? ''));
        if ($bullets === []) {
            foreach (['bullet1', 'bullet2', 'bullet3', 'bullet4', 'bullet5', 'feature1', 'feature2', 'feature3', 'feature4'] as $f) {
                $v = trim((string) ($pm->{$f} ?? ''));
                if ($v !== '' && ! in_array($v, $bullets, true)) {
                    $bullets[] = $v;
                }
            }
        }

        $images = $this->urlList((string) ($metric->image_urls ?? ''));
        if ($images === []) {
            $images = $this->masterImages($pm);
            if ($images === [] && $parent) {
                $images = $this->masterImages($parent);
            }
        }

        $hasVideo = trim((string) ($metric->video_master_json ?? '')) !== '' && trim((string) ($metric->video_master_json ?? '')) !== '[]';
        for ($i = 1; ! $hasVideo && $i <= 10; $i++) {
            $hasVideo = trim((string) ($pm->{'video'.$i} ?? '')) !== '';
        }
        $hasVideo = $hasVideo || trim((string) ($pm->main_video ?? '')) !== '';

        $state = is_array($status->value ?? null) ? $status->value : (json_decode((string) ($status->value ?? ''), true) ?: []);
        $liveFlag = strtolower(trim((string) ($state['live_inactive'] ?? '')));

        $upc = trim((string) ($values['upc'] ?? ''));
        if ($upc === '') {
            $upc = trim((string) ($pm->barcode ?? ''));
        }

        return [
            'sku' => trim((string) $pm->sku),
            'listing_id' => $this->wayfairListingId($state),
            'title' => $title,
            'description' => $description,
            'bullets' => $bullets,
            'images' => $images,
            'has_video' => $hasVideo,
            'live' => $liveFlag !== 'inactive',
            'buyer_link' => $state['buyer_link'] ?? '',
            'price' => (float) ($price->price ?? 0),
            'stock' => (int) ($price->wayfair_stock ?? 0),
            'upc' => $upc,
            'length' => $values['l'] ?? 0,
            'width' => $values['w'] ?? 0,
            'height' => $values['h'] ?? 0,
            'weight' => $values['wt_act'] ?? ($values['weight'] ?? 0),
            'origin' => $values['country_of_origin'] ?? '',
        ];
    }

    /** Wayfair's own SKU (e.g. BREQ1142) from the Partner Home link, when the status row has one. */
    private function wayfairListingId(array $state): string
    {
        $explicit = trim((string) ($state['listing_id'] ?? $state['item_id'] ?? ''));
        if ($explicit !== '') {
            return $explicit;
        }
        if (preg_match('#product-merchandising/([A-Za-z0-9]+)#', (string) ($state['seller_link'] ?? ''), $m)) {
            return $m[1];
        }

        return '';
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
                ['id', 'sku', 'parent', 'Values', 'barcode', 'main_video', 'main_image', 'main_image_brand'],
                self::TITLE_FIELDS,
                self::DESCRIPTION_FIELDS,
                ['bullet1', 'bullet2', 'bullet3', 'bullet4', 'bullet5', 'feature1', 'feature2', 'feature3', 'feature4'],
                array_map(fn ($i) => 'image'.$i, range(1, 20)),
                array_map(fn ($i) => 'video'.$i, range(1, 10)),
            );
            $columns = array_values(array_intersect($wanted, Schema::getColumnListing((new ProductMaster)->getTable())));
        }

        return $columns;
    }

    /** @param  list<string>  $fields */
    private function firstText(ProductMaster $pm, array $fields): string
    {
        foreach ($fields as $field) {
            $value = trim((string) ($pm->{$field} ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /** @return list<string> */
    private function lines(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $text) ?: []), fn ($l) => $l !== ''));
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

    /** @return list<string> */
    private function masterImages(ProductMaster $pm): array
    {
        $urls = [];
        $fields = array_merge(['main_image', 'main_image_brand'], array_map(fn ($i) => 'image'.$i, range(1, 20)));
        foreach ($fields as $field) {
            foreach (preg_split('/[|,;\s]+/', trim((string) ($pm->{$field} ?? ''))) ?: [] as $url) {
                if (preg_match('#^https?://#i', $url) && ! in_array($url, $urls, true)) {
                    $urls[] = $url;
                }
            }
        }

        return $urls;
    }

    /**
     * Parent master rows for the SKUs being scored, keyed by lowercase parent name.
     *
     * @param  \Illuminate\Support\Collection<int, ProductMaster>  $masters
     * @return array<string, ProductMaster>
     */
    private function parentRows($masters): array
    {
        $names = $masters->pluck('parent')->map(fn ($p) => trim((string) $p))->filter()->unique()->values();
        if ($names->isEmpty()) {
            return [];
        }

        $out = [];
        $rows = ProductMaster::query()
            ->select($this->masterColumns())
            ->whereRaw('UPPER(sku) LIKE ?', ['PARENT%'])
            ->where(function ($q) use ($names) {
                foreach ($names->chunk(500) as $chunk) {
                    $q->orWhereIn('parent', $chunk->all());
                }
            })
            ->get();
        foreach ($rows as $row) {
            $out[strtolower(trim((string) $row->parent))] ??= $row;
        }

        return $out;
    }

    /** @return array<string, object> newest row per normalized SKU */
    private function bySku(string $table): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $out = [];
        foreach (DB::table($table)->whereNotNull('sku')->where('sku', '!=', '')->orderBy('id')->cursor() as $row) {
            $out[self::normalizeSku((string) $row->sku)] = $row; // later ids overwrite earlier ones
        }

        return $out;
    }
}
