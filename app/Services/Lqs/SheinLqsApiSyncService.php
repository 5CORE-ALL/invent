<?php

namespace App\Services\Lqs;

use App\Models\LqsMarketplaceScore;
use App\Models\ProductMaster;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Scores Shein listings (LQS 0-100) and stores the result in lqs_marketplace_scores (marketplace = 'shein').
 *
 * The Shein Open API is IP-whitelisted, so it is read by the scheduled product sync (shein_metrics.raw_data holds
 * the live seller product: images, attributes, shelf state, stock, price, dimensions). This service scores from that
 * synced data with no network calls, so a full run takes seconds. Shein does not return the title, description or
 * bullets, so those come from what this app pushes (shein_metrics copy first, then Product Master).
 *
 * @see SheinListingQualityScorer
 */
class SheinLqsApiSyncService
{
    public const MARKETPLACE = 'shein';

    public const LOCK_KEY = 'lqs-sync-shein-lock';

    public const STATUS_KEY = 'lqs-sync-shein-status';

    private const TITLE_FIELDS = ['title80', 'title75', 'title60', 'title100', 'title150'];

    private const DESCRIPTION_FIELDS = [
        'description_html', 'product_description', 'description_1500', 'description_1000',
        'description_800', 'description_600', 'description_v2_description',
    ];

    public function __construct(private readonly SheinListingQualityScorer $scorer)
    {
    }

    /** Scoring needs only local tables, so it is available whenever the synced products exist. */
    public function isConfigured(): bool
    {
        return Schema::hasTable('shein_metrics') && Schema::hasTable((new ProductMaster)->getTable());
    }

    public static function normalizeSku(string $sku): string
    {
        return strtoupper(trim(str_replace("\u{00a0}", ' ', $sku)));
    }

    /**
     * Content + health context for every SKU synced from Shein (or just $only), keyed by normalized SKU.
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

        // Newest synced row per SKU that has a Shein SKU code.
        $metrics = [];
        $query = DB::table('shein_metrics')
            ->select($this->metricColumns())
            ->whereNotNull('sku')->where('sku', '!=', '')
            ->whereNotNull('shein_sku_code')->where('shein_sku_code', '!=', '')
            ->orderBy('id');
        foreach ($query->cursor() as $row) {
            $key = self::normalizeSku((string) $row->sku);
            if ($wanted === null || isset($wanted[$key])) {
                $metrics[$key] = $row; // later ids overwrite earlier ones
            }
        }
        if ($metrics === []) {
            return [];
        }

        $masters = [];
        foreach (ProductMaster::query()
            ->select($this->masterColumns())
            ->whereNotNull('sku')->where('sku', '!=', '')
            ->whereRaw('UPPER(sku) NOT LIKE ?', ['%PARENT%'])
            ->get() as $pm) {
            $key = self::normalizeSku((string) $pm->sku);
            if (isset($metrics[$key])) {
                $masters[$key] ??= $pm;
            }
        }

        $prices = $this->bySku('shein_pricing_prices', $metrics);
        $statuses = $this->bySku('shein_listing_statuses', $metrics);

        $out = [];
        foreach ($masters as $key => $pm) {
            $out[$key] = $this->contextFor($pm, $metrics[$key], $prices[$key] ?? null, $statuses[$key] ?? null);
        }

        return $out;
    }

    /** SKUs synced from Shein that exist in Product Master (the page's "of N" denominator). */
    public function catalogSkus(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        return Cache::remember('lqs-shein-catalog-skus', 60, function () {
            $metricSkus = DB::table('shein_metrics')
                ->whereNotNull('sku')->where('sku', '!=', '')
                ->whereNotNull('shein_sku_code')->where('shein_sku_code', '!=', '')
                ->pluck('sku')
                ->map(fn ($s) => self::normalizeSku((string) $s))
                ->flip();

            return ProductMaster::query()
                ->whereNotNull('sku')->where('sku', '!=', '')
                ->whereRaw('UPPER(sku) NOT LIKE ?', ['%PARENT%'])
                ->pluck('sku')
                ->map(fn ($s) => self::normalizeSku((string) $s))
                ->filter(fn ($s) => isset($metricSkus[$s]))
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
            throw new \RuntimeException('Shein listing has no SKU.');
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
            throw new \RuntimeException('SKU '.$sku.' has not been synced from Shein yet (no shein_metrics row with a Shein SKU code). Run the Shein product sync first.');
        }

        return $this->saveListing($context);
    }

    /**
     * @return array<string, mixed>
     */
    private function contextFor(ProductMaster $pm, object $metric, ?object $price, ?object $status): array
    {
        $values = is_array($pm->Values ?? null) ? $pm->Values : [];
        $raw = json_decode((string) ($metric->raw_data ?? ''), true);
        $raw = is_array($raw) ? $raw : [];

        $title = trim((string) ($metric->product_name ?? ''));
        if ($title === '') {
            $title = $this->firstText($pm, self::TITLE_FIELDS);
        }

        $description = trim((string) ($metric->description_master ?? ''));
        if ($description === '') {
            $description = trim((string) ($metric->description ?? ''));
        }
        if ($description === '') {
            $description = $this->firstText($pm, self::DESCRIPTION_FIELDS);
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

        $images = [];
        foreach ((array) ($raw['imageList'] ?? []) as $img) {
            $url = is_array($img) ? trim((string) ($img['imageUrl'] ?? '')) : '';
            if ($url !== '') {
                $images[] = $url;
            }
        }
        if ($images === [] && trim((string) ($metric->image_url ?? '')) !== '') {
            $images[] = trim((string) $metric->image_url);
        }

        $attributes = 0;
        foreach ((array) ($raw['attributeLists'] ?? []) as $attr) {
            if (! is_array($attr)) {
                continue;
            }
            $hasValue = ! empty($attr['attributeValueMulti']) || ((int) ($attr['attributeValueId'] ?? 0)) > 0;
            foreach ((array) ($attr['attributeAdditionList'] ?? []) as $add) {
                if (is_array($add) && trim((string) ($add['additionValue'] ?? '')) !== '') {
                    $hasValue = true;
                }
            }
            if ($hasValue) {
                $attributes++;
            }
        }

        $shelf = $raw['shelfDetails'][0] ?? null;
        $onShelf = is_array($shelf) ? (bool) ($shelf['isOnShelf'] ?? false) : true; // no shelf data: trust status
        $active = strtolower(trim((string) ($metric->status ?? ''))) === 'active' && $onShelf;

        $state = is_array($status->value ?? null) ? $status->value : (json_decode((string) ($status->value ?? ''), true) ?: []);
        $listed = strtolower(trim((string) ($state['listed'] ?? ''))) === 'listed';

        $stock = $price->shein_stock ?? ($raw['goodsInventory']['usableInventory'] ?? $metric->inventory ?? 0);
        $priceValue = (float) ($price->price ?? 0);
        if ($priceValue <= 0) {
            $priceValue = (float) ($raw['currentPrices'][0]['salePrice'] ?? $raw['currentPrices'][0]['shopPrice'] ?? $metric->price ?? 0);
        }

        $dims = is_array($raw['skuDimensionsInfo'] ?? null) ? $raw['skuDimensionsInfo'] : [];

        $hasVideo = trim((string) ($metric->video_master_json ?? '')) !== '' && trim((string) ($metric->video_master_json ?? '')) !== '[]';
        for ($i = 1; ! $hasVideo && $i <= 10; $i++) {
            $hasVideo = trim((string) ($pm->{'video'.$i} ?? '')) !== '';
        }
        $hasVideo = $hasVideo || trim((string) ($pm->main_video ?? '')) !== '';

        return [
            'sku' => trim((string) $pm->sku),
            'listing_id' => trim((string) ($metric->shein_sku_code ?? '')),
            'title' => $title,
            'description' => $description,
            'bullets' => $bullets,
            'images' => $images,
            'attributes' => $attributes,
            'active' => $active,
            'listed' => $listed,
            'price' => $priceValue,
            'stock' => (int) $stock,
            'length' => $dims['length'] ?? ($values['l'] ?? 0),
            'width' => $dims['width'] ?? ($values['w'] ?? 0),
            'height' => $dims['height'] ?? ($values['h'] ?? 0),
            'weight' => $dims['weight'] ?? ($values['wt_act'] ?? 0),
            'has_brand' => trim((string) ($raw['brandCode'] ?? '')) !== '',
            'has_video' => $hasVideo,
        ];
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
    private function metricColumns(): array
    {
        $wanted = [
            'id', 'sku', 'shein_sku_code', 'product_name', 'inventory', 'price', 'status', 'description', 'image_url',
            'raw_data', 'bullet_points', 'description_master', 'video_master_json',
        ];

        return array_values(array_intersect($wanted, Schema::getColumnListing('shein_metrics')));
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
                ['id', 'sku', 'Values', 'main_video'],
                self::TITLE_FIELDS,
                self::DESCRIPTION_FIELDS,
                ['bullet1', 'bullet2', 'bullet3', 'bullet4', 'bullet5'],
                array_map(fn ($i) => 'video'.$i, range(1, 10)),
            );
            $columns = array_values(array_intersect($wanted, Schema::getColumnListing((new ProductMaster)->getTable())));
        }

        return $columns;
    }

    /**
     * Newest row per normalized SKU for the SKUs being scored.
     *
     * @param  array<string, object>  $metrics
     * @return array<string, object>
     */
    private function bySku(string $table, array $metrics): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $out = [];
        foreach (DB::table($table)->whereNotNull('sku')->where('sku', '!=', '')->orderBy('id')->cursor() as $row) {
            $key = self::normalizeSku((string) $row->sku);
            if (isset($metrics[$key])) {
                $out[$key] = $row;
            }
        }

        return $out;
    }
}
