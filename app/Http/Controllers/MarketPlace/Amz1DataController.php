<?php

namespace App\Http\Controllers\MarketPlace;

use App\Models\AmazonDataView;
use App\Models\AmazonDatasheet;
use App\Services\AmazonSpApiService;
use App\Services\MarketplaceManager\Amz1DataService;
use App\Support\Marketplace\AmazonListingCounts;
use App\Support\Marketplace\ListingCountsEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * "Amz Data": the eBay 1 Data page for Amazon. Every row is a CP Master SKU with its Amazon listing (Listed / Missing L),
 * the category tabs are Amazon product types, and the columns are the fields Amazon defines for that product type.
 * Values are read from the live listing with Pull and sent back with Push (title, and item specifics for ticked rows).
 * The price is not edited here: it stays on the Amazon pricing pages.
 */
class Amz1DataController extends Ebay1DataController
{
    private const AMAZON_URL = 'https://www.amazon.com/dp/';

    public function __construct()
    {
        Amz1DataService::ensureTables();
    }

    protected function cfg(): array
    {
        return [
            'key' => 'amz1',
            'label' => 'Amazon',
            'prefix' => 'amz1',
            'registry' => 'amazon',
            'base' => '/amz-data',
            'metric' => AmazonDatasheet::class,
            'dataView' => AmazonDataView::class,
            'service' => AmazonSpApiService::class,
            'main' => false,
            'channel' => 'amazon',
            'site' => 'Amazon',
            'page' => 'Amz Data',
            'title_name' => 'Amazon title',
            'price_name' => 'Price',
            'title_max' => 200,
            'item_url' => self::AMAZON_URL,
            'id_regex' => '^[A-Za-z0-9]{10}$',
            'col_titles' => [
                'item_id' => 'ASIN',
                'ebay_title' => 'Amazon title',
                'ebay_stock' => 'Stock',
                'ebay_l7' => 'Units L7',
                'ebay_l30' => 'Units L30',
                'ebay_l60' => 'Units L60',
                'views' => 'Sessions L30',
                'l7_views' => 'Sessions L7',
                'l1_views' => 'Sessions L1',
                'organic_clicks' => 'Organic views',
                'report_date' => 'Synced on',
                'category' => 'Product type',
            ],
            'hide' => [],
        ];
    }

    /** The UPC is not pushed to Amazon from this page (no Push UPC button, no UPC pending flag). */
    protected function hasUpcColumn(): bool
    {
        return false;
    }

    /** Every SKU of every product type shows a UPC column with the code held in CP Master. */
    protected function showsUpcColumn(): bool
    {
        return true;
    }

    protected function fillsFixedValues(): bool
    {
        // Amazon shows the values the pull saved. The eBay fixed values (MPN = the SKU ...) must not replace them.
        return false;
    }

    protected function categoryMasterColumn(): string
    {
        return 'category_amz';
    }

    // ---- Rows: CP Master SKUs and their Amazon listing ----

    /** @var array<string, array{asin: string, seller_sku: string}>|null */
    private ?array $listingMemo = null;

    /** @return array<string, array{asin: string, seller_sku: string}> */
    private function listingsMap(): array
    {
        return $this->listingMemo ??= AmazonListingCounts::listingsByNormalizedSku();
    }

    /**
     * The same rows and the same Listed / Missing L decision as the Listing Amz page (FBA copies of a SKU left out).
     */
    protected function listingRows(): Collection
    {
        $masters = ListingCountsEngine::productMasters();
        $skus = ListingCountsEngine::productSkus();
        $shopify = ListingCountsEngine::requestShopifyMap();
        $nrlSet = AmazonListingCounts::nrlSetForSkus($skus);
        $listings = $this->listingsMap();

        return $masters
            ->filter(static fn ($item) => ! AmazonListingCounts::skuLooksLikeFba((string) $item->sku))
            ->map(function ($item) use ($shopify, $nrlSet, $listings) {
                $childSku = (string) $item->sku;
                $shopifyRow = ListingCountsEngine::shopifyRow($shopify, $childSku);
                $item->INV = ListingCountsEngine::shopifyInv($shopifyRow);
                $item->L30 = $shopifyRow?->quantity ?? 0;
                $item->nr_req = AmazonListingCounts::skuIsNrl($childSku, $nrlSet) ? 'NR' : 'REQ';

                $listing = AmazonListingCounts::pickListingForProductSku($childSku, $listings);
                $asin = AmazonListingCounts::asinFromApi($listing);
                $item->asin = $asin !== '' ? $asin : null;
                $item->listing_id = $item->asin;
                $item->buyer_link = $asin !== '' ? self::AMAZON_URL.rawurlencode($asin) : null;
                $item->listed = $asin !== '' ? 'Listed' : 'Pending';
                $item->page_created = false;

                return $item;
            })
            ->values();
    }

    /** ASIN of the live Amazon listing of a SKU, '' when it has none. */
    protected function liveItemId(string $sku): string
    {
        $listing = AmazonListingCounts::pickListingForProductSku($sku, $this->listingsMap());
        $asin = strtoupper(AmazonListingCounts::asinFromApi($listing));

        return preg_match('/^[A-Z0-9]{10}$/', $asin) === 1 ? $asin : '';
    }

    /** The SKU as Amazon holds it (spacing can differ from CP Master). */
    private function sellerSkuFor(string $sku): string
    {
        $listing = AmazonListingCounts::pickListingForProductSku($sku, $this->listingsMap());
        $seller = trim((string) ($listing['seller_sku'] ?? ''));

        return $seller !== '' ? $seller : $sku;
    }

    /**
     * Latest Amazon datasheet row per SKU, in the keys the page uses (ebay_* keys hold the Amazon figures).
     *
     * @return array<string, array<string, mixed>>
     */
    protected function metricsBySku(): array
    {
        $table = $this->metricsTable();
        if (! Schema::hasTable($table)) {
            return [];
        }
        $wanted = ['sku', 'asin', 'amazon_title', 'price', 'units_ordered_l7', 'units_ordered_l30', 'units_ordered_l60',
            'sessions_l1', 'sessions_l7', 'sessions_l30', 'organic_views', 'listing_status', 'updated_at'];
        $columns = array_values(array_filter($wanted, fn ($c) => Schema::hasColumn($table, $c)));
        if (! in_array('sku', $columns, true)) {
            return [];
        }

        $byKey = [];
        $byNorm = [];
        DB::table($table)->whereNotNull('sku')->orderBy('id')->select($columns)->chunk(1000, function ($rows) use (&$byKey, &$byNorm) {
            foreach ($rows as $row) {
                $r = (array) $row;
                $asin = strtoupper(trim((string) ($r['asin'] ?? '')));
                $metric = [
                    'sku' => $r['sku'],
                    'item_id' => $asin,
                    'ebay_link' => $asin !== '' ? self::AMAZON_URL.rawurlencode($asin) : '',
                    'ebay_title' => (string) ($r['amazon_title'] ?? ''),
                    'ebay_price' => $r['price'] ?? null,
                    'ebay_stock' => null,
                    'ebay_l7' => $r['units_ordered_l7'] ?? null,
                    'ebay_l30' => $r['units_ordered_l30'] ?? null,
                    'ebay_l60' => $r['units_ordered_l60'] ?? null,
                    'views' => $r['sessions_l30'] ?? null,
                    'l7_views' => $r['sessions_l7'] ?? null,
                    'l1_views' => $r['sessions_l1'] ?? null,
                    'organic_clicks' => $r['organic_views'] ?? null,
                    'listing_status' => (string) ($r['listing_status'] ?? ''),
                    'inactive_reason' => '',
                    'report_date' => ! empty($r['updated_at']) ? substr((string) $r['updated_at'], 0, 10) : '',
                ];
                $byKey[$this->skuKey((string) $r['sku'])] = $metric;
                $norm = AmazonDatasheet::normalizeSkuForLookup((string) $r['sku']);
                if ($norm !== '') {
                    $byNorm[$norm] = $metric;
                }
            }
        });

        // The stock of the listing comes from the listings report, which also fills a missing title and price.
        $report = [];
        if (Schema::hasTable('amazon_listings_raw')) {
            $reportColumns = array_values(array_filter(['seller_sku', 'quantity', 'item_name', 'your_price'], fn ($c) => Schema::hasColumn('amazon_listings_raw', $c)));
            if (in_array('seller_sku', $reportColumns, true)) {
                foreach (DB::table('amazon_listings_raw')->whereNotNull('seller_sku')->select($reportColumns)->get() as $row) {
                    $norm = AmazonDatasheet::normalizeSkuForLookup((string) $row->seller_sku);
                    if ($norm !== '') {
                        $report[$norm] = $row;
                    }
                }
            }
        }

        $out = $byKey;
        foreach (ListingCountsEngine::productSkus() as $productSku) {
            $key = $this->skuKey((string) $productSku);
            $metric = $out[$key] ?? null;
            $reportRow = null;
            foreach (AmazonListingCounts::skuLookupKeys((string) $productSku) as $lookup) {
                $norm = AmazonDatasheet::normalizeSkuForLookup($lookup);
                if ($metric === null && isset($byNorm[$norm])) {
                    $metric = $byNorm[$norm];
                }
                if ($reportRow === null && isset($report[$norm])) {
                    $reportRow = $report[$norm];
                }
            }
            if ($metric === null && $reportRow === null) {
                continue;
            }
            $metric ??= ['sku' => $productSku, 'item_id' => '', 'ebay_link' => '', 'ebay_title' => '', 'ebay_price' => null, 'ebay_stock' => null,
                'ebay_l7' => null, 'ebay_l30' => null, 'ebay_l60' => null, 'views' => null, 'l7_views' => null, 'l1_views' => null,
                'organic_clicks' => null, 'listing_status' => '', 'inactive_reason' => '', 'report_date' => ''];
            if ($reportRow !== null) {
                if (isset($reportRow->quantity) && is_numeric($reportRow->quantity)) {
                    $metric['ebay_stock'] = $reportRow->quantity;
                }
                if (($metric['ebay_title'] ?? '') === '' && isset($reportRow->item_name)) {
                    $metric['ebay_title'] = (string) $reportRow->item_name;
                }
                if (($metric['ebay_price'] ?? null) === null && isset($reportRow->your_price) && is_numeric($reportRow->your_price)) {
                    $metric['ebay_price'] = $reportRow->your_price;
                }
            }
            $out[$key] = $metric;
        }

        return $out;
    }

    // ---- Categories are Amazon product types ----

    /** @var array<string, string>|null normalized seller SKU => product type (from the listings report) */
    private ?array $typeIndexMemo = null;

    /** @return array<string, string> */
    private function typeIndex(): array
    {
        if ($this->typeIndexMemo !== null) {
            return $this->typeIndexMemo;
        }
        $this->typeIndexMemo = [];
        if (Schema::hasTable('amazon_listings_raw') && Schema::hasColumn('amazon_listings_raw', 'product_type')) {
            $rows = DB::table('amazon_listings_raw')->whereNotNull('product_type')->where('product_type', '!=', '')
                ->whereNotNull('seller_sku')->select(['seller_sku', 'product_type'])->get();
            foreach ($rows as $row) {
                $type = strtoupper(trim((string) $row->product_type));
                $norm = AmazonDatasheet::normalizeSkuForLookup((string) $row->seller_sku);
                if ($norm !== '' && preg_match(Amz1DataService::TYPE_PATTERN, $type) === 1) {
                    $this->typeIndexMemo[$norm] = $type;
                }
            }
        }

        return $this->typeIndexMemo;
    }

    private function reportTypeFor(string $sku): string
    {
        $index = $this->typeIndex();
        foreach (AmazonListingCounts::skuLookupKeys($sku) as $lookup) {
            $norm = AmazonDatasheet::normalizeSkuForLookup($lookup);
            if ($norm !== '' && isset($index[$norm])) {
                return $index[$norm];
            }
        }

        return '';
    }

    /**
     * The category of a SKU is its Amazon product type: what a pull or Change Category saved comes first (the page
     * reads that itself), then the product type in the listings report. Categories come from Amazon only: a SKU
     * Amazon has not given a product type for yet stays under "No category" (Category Master is never used)
     * until Fetch categories or Pull reads it from Amazon.
     */
    protected function categoriesBySku(iterable $listing): array
    {
        $out = [];
        foreach ($listing as $item) {
            $sku = trim((string) ($item->sku ?? ''));
            $type = $this->reportTypeFor($sku);
            if ($type !== '') {
                $out[$this->skuKey($sku)] = $type;
            }
        }

        return $out;
    }

    /** The product type a stored category stands for ('' when it is not a product type). */
    private function typeFromCategory(string $category): string
    {
        $type = strtoupper(trim($category));

        return preg_match(Amz1DataService::TYPE_PATTERN, $type) === 1 ? $type : '';
    }

    /** @var array<string, list<string>> */
    private array $columnNamesMemo = [];

    /** @var array<string, true> product types whose fields were never loaded */
    private array $needTypes = [];

    /**
     * Every SKU of a product type shows the fields Amazon defines for it as columns, blank until its value is known.
     * Reads the stored lists only; a type without one is loaded by the page (load-attributes).
     */
    protected function withChannelColumns(array $specifics, string $category, ?array $live): array
    {
        $type = $this->typeFromCategory($category);
        if ($type === '') {
            return $specifics;
        }
        if (! isset($this->columnNamesMemo[$type])) {
            $cached = Amz1DataService::cachedColumns($type);
            if ($cached['state'] === 'none') {
                $this->needTypes[$type] = true;
            }
            $this->columnNamesMemo[$type] = array_map(static fn ($c) => (string) $c['title'], $cached['columns']);
        }
        if ($this->columnNamesMemo[$type] === []) {
            return $specifics;
        }
        $have = [];
        foreach (array_keys($specifics) as $name) {
            $have[$this->aspectKey((string) $name)] = true;
        }
        foreach ($this->columnNamesMemo[$type] as $name) {
            if (! isset($have[$this->aspectKey($name)])) {
                $specifics[$name] = '';
            }
        }

        return $specifics;
    }

    protected function rowsExtra(): array
    {
        return ['attr_pending' => array_keys($this->needTypes)];
    }

    // ---- Title from Title Master, matching columns from eBay 1 Data ----

    /**
     * The Amazon title column is the Title 170 saved in Title Master (product_master.title150), the title the
     * Title Master page pushes to Amazon. A SKU with none keeps the title of its Amazon listing.
     *
     * @return array<string, string>
     */
    protected function masterTitlesBySku(): array
    {
        if (! Schema::hasTable('product_master') || ! Schema::hasColumn('product_master', 'title150')) {
            return [];
        }
        $query = DB::table('product_master')->whereNotNull('sku')->whereNotNull('title150')->where('title150', '!=', '');
        if (Schema::hasColumn('product_master', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        $out = [];
        foreach ($query->get(['sku', 'title150']) as $row) {
            $title = trim((string) $row->title150);
            if ($title !== '') {
                $out[$this->skuKey((string) $row->sku)] = $title;
            }
        }

        return $out;
    }

    /** @var array<string, array<string, string>>|null SKU key => eBay 1 aspect key => value */
    private ?array $ebayValuesMemo = null;

    /**
     * The item specifics of every SKU as eBay 1 Data holds them, by aspect key (blank and "Does not apply" left out).
     *
     * @return array<string, array<string, string>>
     */
    private function ebayValuesBySku(): array
    {
        if ($this->ebayValuesMemo !== null) {
            return $this->ebayValuesMemo;
        }
        $this->ebayValuesMemo = [];
        if (! Schema::hasTable('ebay1_item_specifics')) {
            return $this->ebayValuesMemo;
        }
        foreach (DB::table('ebay1_item_specifics')->select(['sku', 'specifics'])->get() as $row) {
            $decoded = json_decode((string) $row->specifics, true);
            if (! is_array($decoded)) {
                continue;
            }
            $values = [];
            foreach ($decoded as $name => $value) {
                $name = (string) $name;
                $value = is_scalar($value) ? trim((string) $value) : '';
                if ($name === '' || str_starts_with($name, '__') || $this->isBlankValue($value)) {
                    continue;
                }
                // The value is kept under every key the name can be matched by (Brand = Brand Name, MPN = Part Number ...).
                foreach ($this->matchKeys($name) as $matchKey) {
                    if (! isset($values[$matchKey])) {
                        $values[$matchKey] = $value;
                    }
                }
            }
            if ($values !== []) {
                $this->ebayValuesMemo[$this->skuKey((string) $row->sku)] = $values;
            }
        }

        return $this->ebayValuesMemo;
    }

    private function isBlankValue(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['', 'does not apply', 'not applicable', '—'], true);
    }

    /**
     * A blank Amazon cell takes the value eBay 1 Data has for the column of the same name. Only the columns the SKU
     * already has are filled (never a second column for the same field), and a value Amazon holds is kept.
     */
    protected function fillSpecificsFromElsewhere(array $specifics, string $skuKey, string $sku = ''): array
    {
        $ebay = $this->ebayValuesBySku()[$skuKey] ?? [];
        // eBay 1 Data always shows these for every SKU, whatever eBay holds: MPN and Model are the SKU, the maker is 5 Core.
        $fixed = $sku !== '' ? ['mpn' => $sku, 'model' => $sku, 'manufacturer' => '5 Core'] : [];
        if ($ebay === [] && $fixed === []) {
            return $specifics;
        }
        $filled = [];
        foreach ($specifics as $name => $value) {
            $name = (string) $name;
            if ($name === '' || str_starts_with($name, '__') || ! $this->isBlankValue(is_scalar($value) ? (string) $value : '')) {
                continue;
            }
            $keys = $this->matchKeys($name);
            // The first column of a field takes the value; a repeated spelling of it stays as it is.
            if ($keys === [] || isset($filled[$keys[0]])) {
                continue;
            }
            $found = null;
            foreach ($keys as $matchKey) {
                if (isset($ebay[$matchKey])) {
                    $found = $ebay[$matchKey];
                    break;
                }
            }
            if ($found === null) {
                foreach ($keys as $matchKey) {
                    if (isset($fixed[$matchKey])) {
                        $found = $fixed[$matchKey];
                        break;
                    }
                }
            }
            if ($found !== null) {
                $filled[$keys[0]] = true;
                $specifics[$name] = $found;
            }
        }

        return $specifics;
    }

    /** Names the same field goes by on eBay and Amazon: the first of each group is the shared key. */
    private const FIELD_ALIASES = [
        'mpn' => ['manufacturerpartnumber', 'partnumber', 'mfrpartnumber', 'manufacturerpart', 'manufacturerpartno'],
        'model' => ['modelnumber', 'modelname', 'modelno'],
        'countryoforigin' => ['countryregionofmanufacture', 'countryofmanufacture', 'countryofmanufacturer', 'countryregionoforigin', 'madein'],
        'numberofitems' => ['numberinpack', 'itempackagequantity', 'unitcount', 'quantityinpack', 'packquantity', 'piececount'],
        'color' => ['colour', 'colorname', 'colorfamily'],
        'brand' => ['brandname'],
        'manufacturer' => ['manufacturername', 'maker'],
        'material' => ['materialtype', 'materialcomposition'],
        'itemweight' => ['weight', 'productweight'],
        'itemlength' => ['length', 'productlength'],
        'itemwidth' => ['width', 'productwidth'],
        'itemheight' => ['height', 'productheight'],
    ];

    /**
     * The keys a column name can be matched by, best first: its own key, the same without a leading "Item" or a
     * trailing "Name" / "Number", then the shared key of its alias group.
     *
     * @return list<string>
     */
    private function matchKeys(string $name): array
    {
        $own = $this->aspectKey($name);
        if ($own === '') {
            return [];
        }
        $keys = [$own];
        $bare = $own;
        if (strlen($bare) > 8 && str_starts_with($bare, 'item')) {
            $bare = substr($bare, 4);
            $keys[] = $bare;
        }
        foreach (['name', 'number'] as $suffix) {
            if (strlen($bare) > strlen($suffix) + 2 && str_ends_with($bare, $suffix)) {
                $keys[] = substr($bare, 0, -strlen($suffix));
            }
        }
        foreach ($keys as $candidate) {
            foreach (self::FIELD_ALIASES as $shared => $aliases) {
                if ($candidate === $shared || in_array($candidate, $aliases, true)) {
                    $keys[] = $shared;
                    break 2;
                }
            }
        }

        // Last resort: the same words in a different form ("Water resistant" = "Water Resistance Level").
        $words = preg_split('/[^a-z0-9]+/', strtolower($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stems = [];
        foreach ($words as $word) {
            if (in_array($word, ['item', 'name', 'number', 'level', 'product', 'the', 'of', 'for', 'and', 'a', 'an'], true)) {
                continue;
            }
            $stems[substr($word, 0, 5)] = true;
        }
        if (count($stems) >= 2) {
            $stemList = array_keys($stems);
            sort($stemList);
            $keys[] = 'words:'.implode('+', $stemList);
        }

        return array_values(array_unique($keys));
    }

    /**
     * Every product type known here: the listings report, the SKUs already pulled, the types already loaded.
     *
     * @return list<string>
     */
    private function knownProductTypes(): array
    {
        $types = [];
        if (Schema::hasTable('amazon_listings_raw') && Schema::hasColumn('amazon_listings_raw', 'product_type')) {
            foreach (DB::table('amazon_listings_raw')->whereNotNull('product_type')->distinct()->pluck('product_type') as $type) {
                $type = strtoupper(trim((string) $type));
                if (preg_match(Amz1DataService::TYPE_PATTERN, $type) === 1) {
                    $types[$type] = true;
                }
            }
        }
        if (Schema::hasTable($this->t('item_specifics'))) {
            foreach (DB::table($this->t('item_specifics'))->whereNotNull('category_path')->distinct()->pluck('category_path') as $path) {
                $type = $this->typeFromCategory((string) $path);
                if ($type !== '') {
                    $types[$type] = true;
                }
            }
        }
        if (Schema::hasTable(Amz1DataService::TYPE_TABLE)) {
            foreach (DB::table(Amz1DataService::TYPE_TABLE)->pluck('product_type') as $type) {
                $types[strtoupper((string) $type)] = true;
            }
        }
        $list = array_keys($types);
        sort($list);

        return $list;
    }

    /**
     * POST /amz-data/load-attributes   { category_ids: [..] }   (product types)
     * Loads (and stores) the fields of up to 3 product types, so their columns show for every SKU.
     */
    public function loadAttributes(Request $request)
    {
        $data = $request->validate([
            'category_ids' => 'required|array|min:1|max:3',
            'category_ids.*' => 'required|string|max:120',
        ]);
        @set_time_limit(300);
        $api = app(AmazonSpApiService::class);
        if (! $api->isConfigured()) {
            return response()->json(['success' => false, 'message' => 'Amazon SP-API is not connected.'], 500);
        }
        $service = new Amz1DataService($api);
        $results = [];
        foreach ($data['category_ids'] as $id) {
            $id = strtoupper(trim((string) $id));
            try {
                $loaded = $service->loadDefinition($id);
                $results[] = [
                    'id' => $id,
                    'ok' => $loaded['ok'] && $loaded['columns'] !== [],
                    'columns' => count($loaded['columns']),
                    'message' => $loaded['ok'] ? ($loaded['columns'] === [] ? 'Amazon lists no simple item-specific fields for this product type.' : '') : $loaded['message'],
                ];
            } catch (\Throwable $e) {
                Log::warning('AmzData: load attributes failed', ['product_type' => $id, 'error' => $e->getMessage()]);
                $results[] = ['id' => $id, 'ok' => false, 'columns' => 0, 'message' => $e->getMessage()];
            }
        }
        Cache::forget($this->cacheKey('column_options'));

        return response()->json(['success' => true, 'results' => $results]);
    }

    /**
     * GET /amz-data/category-field-targets
     * Every product type known here, so the All fields button can fetch each one once.
     */
    public function categoryFieldTargets()
    {
        $categories = [];
        foreach ($this->knownProductTypes() as $type) {
            $categories[] = ['id' => $type, 'path' => $type];
        }

        return response()->json(['success' => true, 'categories' => $categories]);
    }

    // ---- Pull: product type and values exactly as Amazon holds them ----

    /** @var array<string, array{category_path: string, specifics: array<string, mixed>}>|null */
    private ?array $pulledMemo = null;

    protected function pulledSpecificsBySku(): array
    {
        return $this->pulledMemo ??= parent::pulledSpecificsBySku();
    }

    /**
     * POST /amz-data/pull   { skus: [..] }
     * Reads the product type and the values of each listed SKU. A value read from Amazon replaces what the app had for
     * that field; fields Amazon holds no value for keep what the app has, and edits not pushed yet are kept.
     */
    public function pullFromAmazon(Request $request)
    {
        $data = $request->validate([
            'skus' => 'required|array|min:1|max:8',
            'skus.*' => 'required|string|max:191',
        ]);
        if (! Schema::hasTable($this->t('item_specifics'))) {
            return response()->json(['success' => false, 'message' => 'The Amz Data tables could not be created.'], 500);
        }
        @set_time_limit(300);

        $api = app(AmazonSpApiService::class);
        if (! $api->isConfigured()) {
            return response()->json(['success' => false, 'message' => 'Amazon SP-API is not connected.'], 500);
        }
        $service = new Amz1DataService($api);
        $pending = $this->pendingSpecificsBySku();

        $results = [];
        foreach ($data['skus'] as $sku) {
            $sku = trim((string) $sku);
            $asin = $this->liveItemId($sku);
            if ($asin === '') {
                $results[] = ['sku' => $sku, 'status' => 'skipped', 'message' => 'Not listed on Amazon (no ASIN).'];

                continue;
            }
            try {
                $listing = $service->fetchListing($this->sellerSkuFor($sku));
                if ($listing === null) {
                    $results[] = ['sku' => $sku, 'status' => 'failed', 'message' => $service->lastError !== '' ? $service->lastError : 'Amazon did not return the listing.'];

                    continue;
                }
                $type = $listing['product_type'];
                if (preg_match(Amz1DataService::TYPE_PATTERN, $type) !== 1) {
                    $results[] = ['sku' => $sku, 'status' => 'failed', 'message' => 'Amazon returned no product type for this listing.'];

                    continue;
                }
                $columns = $service->columnsFor($type);
                $filled = $this->storePulled($sku, $asin, $type, $columns, $listing['attributes'], $service, $pending[$this->skuKey($sku)] ?? []);
                $note = $columns === [] ? ' No fields could be loaded for this product type'.($service->lastError !== '' ? ': '.$service->lastError : '.') : '';
                $results[] = [
                    'sku' => $sku,
                    'status' => 'pulled',
                    'message' => $type.': '.$filled.' value'.($filled === 1 ? '' : 's').' read.'.$note,
                    'category' => $type,
                    'properties' => $filled,
                ];
            } catch (\Throwable $e) {
                Log::warning('AmzData: pull failed', ['sku' => $sku, 'error' => $e->getMessage()]);
                $results[] = ['sku' => $sku, 'status' => 'failed', 'message' => $e->getMessage()];
            }
        }
        $this->pulledMemo = null;
        Cache::forget($this->cacheKey('column_options'));

        return response()->json(['success' => true, 'results' => $results]);
    }

    /**
     * @param  list<array<string, mixed>>  $columns
     * @param  array<string, mixed>  $attributes
     * @param  array<string, string>  $pending  aspect key => name of the cells edited here and not pushed yet
     * @return int How many values were read from Amazon
     */
    private function storePulled(string $sku, string $asin, string $type, array $columns, array $attributes, Amz1DataService $service, array $pending): int
    {
        $stored = $this->storedSpecifics($sku);
        $filled = 0;
        foreach ($columns as $column) {
            $title = (string) $column['title'];
            $aspect = $this->aspectKey($title);
            if (isset($pending[$aspect])) {
                continue;
            }
            $text = $service->attributeText($attributes, $column);
            if ($text === '') {
                continue;
            }
            $target = $title;
            foreach (array_keys($stored) as $existing) {
                if ($this->aspectKey((string) $existing) === $aspect) {
                    $target = (string) $existing;
                    break;
                }
            }
            $stored[$target] = $text;
            $filled++;
        }

        $key = $this->skuKey($sku);
        $payload = [
            'item_id' => $asin,
            'category_path' => $type,
            'specifics' => json_encode($stored, JSON_UNESCAPED_UNICODE),
            'pulled_at' => now(),
            'updated_at' => now(),
        ];
        if (DB::table($this->t('item_specifics'))->where('sku', $key)->exists()) {
            DB::table($this->t('item_specifics'))->where('sku', $key)->update($payload);
        } else {
            DB::table($this->t('item_specifics'))->insert($payload + ['sku' => $key, 'created_at' => now()]);
        }

        return $filled;
    }

    // ---- Push: title as the app already pushes it; item specifics only for ticked rows ----

    /**
     * POST /amz-data/push
     * Title: the Amazon title update the app already uses. Item specifics are sent only when the page asks for it
     * ("specifics": true, set for ticked rows), never by Auto Push, and only for fields of the SKU's product type.
     */
    public function pushSpecifics(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1|max:10',
            'items.*.sku' => 'required|string|max:191',
            'items.*.item_id' => 'nullable|string|max:64',
            'items.*.specifics' => 'nullable|array|max:300',
            'items.*.title' => 'nullable|string|max:200',
            'items.*.price' => 'nullable|numeric',
            'specifics' => 'nullable|boolean',
            'auto' => 'nullable|boolean',
        ]);
        @set_time_limit(300);

        $sendSpecifics = $request->boolean('specifics') && ! $request->boolean('auto');
        $api = app(AmazonSpApiService::class);
        if (! $api->isConfigured()) {
            return response()->json(['success' => false, 'message' => 'Amazon SP-API is not connected.'], 500);
        }
        $service = new Amz1DataService($api);
        $results = [];

        foreach ($validated['items'] as $entry) {
            $sku = trim((string) $entry['sku']);
            if ($this->liveItemId($sku) === '') {
                $results[] = ['sku' => $sku, 'status' => 'skipped', 'message' => 'Not listed on Amazon.'];

                continue;
            }

            $edits = [];
            if ($sendSpecifics) {
                foreach ((array) ($entry['specifics'] ?? []) as $name => $value) {
                    $name = trim((string) $name);
                    $value = is_scalar($value) ? trim((string) $value) : '';
                    // The UPC column shows the CP Master code; it is not an item-specific field sent to Amazon.
                    if ($name !== '' && $value !== '' && ! str_starts_with($name, '__') && $this->aspectKey($name) !== 'upc') {
                        $edits[$name] = $value;
                    }
                }
            }
            $title = trim((string) ($entry['title'] ?? ''));
            if ($edits === [] && $title === '') {
                $results[] = ['sku' => $sku, 'status' => 'skipped', 'message' => $sendSpecifics
                    ? 'No values to send.'
                    : 'No title to send. Item specifics are sent only for ticked rows.'];

                continue;
            }

            $messages = [];
            $failed = false;

            if ($title !== '') {
                try {
                    $r = $api->updateTitle($sku, $title);
                } catch (\Throwable $e) {
                    $r = ['success' => false, 'message' => $e->getMessage()];
                }
                if ($r['success'] ?? false) {
                    $this->clearPendingTitle($sku);
                    if (Schema::hasColumn($this->metricsTable(), 'amazon_title')) {
                        DB::table($this->metricsTable())->where('sku', $sku)->update(['amazon_title' => $title]);
                    }
                    $messages[] = 'Amazon title updated.';
                } else {
                    $failed = true;
                    $messages[] = 'Title: '.(string) ($r['message'] ?? 'Amazon rejected it.');
                }
            }

            if ($edits !== []) {
                $type = $this->typeFromCategory((string) ($this->pulledSpecificsBySku()[$this->skuKey($sku)]['category_path'] ?? ''));
                if ($type === '') {
                    $type = $this->reportTypeFor($sku);
                }
                if ($type === '') {
                    $failed = true;
                    $messages[] = 'Item specifics: pull this SKU first, so its Amazon product type is known.';
                } else {
                    try {
                        $byTitle = [];
                        foreach ($service->columnsFor($type) as $column) {
                            $byTitle[$this->aspectKey((string) $column['title'])] = $column;
                        }
                        $changes = [];
                        $unknown = [];
                        foreach ($edits as $name => $value) {
                            $column = $byTitle[$this->aspectKey($name)] ?? null;
                            if ($column === null) {
                                $unknown[] = $name;

                                continue;
                            }
                            $changes[(string) $column['key']] = ['column' => $column, 'value' => $value];
                        }
                        if ($changes === []) {
                            $failed = true;
                            $messages[] = 'Item specifics: none of these columns is an Amazon field of '.$type.($unknown !== [] ? ' ('.implode(', ', array_slice($unknown, 0, 5)).')' : '').'.';
                        } else {
                            $r = $service->patchAttributes($this->sellerSkuFor($sku), $type, $changes);
                            if ($r['applied'] !== []) {
                                $this->clearPendingSpecifics($sku, $r['applied']);
                                $this->storeApplied($sku, $edits, $r['applied']);
                            }
                            if ($r['rejected'] !== [] || ! $r['success']) {
                                $failed = true;
                            }
                            $messages[] = 'Item specifics: '.$r['message'];
                            if ($unknown !== []) {
                                $messages[] = 'Not Amazon fields of '.$type.': '.implode(', ', array_slice($unknown, 0, 5)).'.';
                            }
                        }
                    } catch (\Throwable $e) {
                        Log::warning('AmzData: item specifics push failed', ['sku' => $sku, 'error' => $e->getMessage()]);
                        $failed = true;
                        $messages[] = 'Item specifics: '.$e->getMessage();
                    }
                }
            }

            $results[] = ['sku' => $sku, 'status' => $failed ? 'failed' : 'pushed', 'message' => trim(implode(' ', $messages))];
        }

        return response()->json(['success' => true, 'results' => $results]);
    }

    /**
     * @param  array<string, string>  $edits
     * @param  list<string>  $applied
     */
    private function storeApplied(string $sku, array $edits, array $applied): void
    {
        $decoded = $this->storedSpecifics($sku);
        foreach ($applied as $name) {
            $target = $name;
            foreach (array_keys($decoded) as $existing) {
                if ($this->aspectKey((string) $existing) === $this->aspectKey($name)) {
                    $target = (string) $existing;
                    break;
                }
            }
            foreach ($edits as $editName => $value) {
                if ($this->aspectKey((string) $editName) === $this->aspectKey($name)) {
                    $decoded[$target] = $value;
                }
            }
        }
        $this->writeSpecifics($sku, $decoded);
    }

    /** @return array<string, mixed> */
    private function storedSpecifics(string $sku): array
    {
        $row = DB::table($this->t('item_specifics'))->where('sku', $this->skuKey($sku))->first();
        $decoded = $row ? json_decode((string) $row->specifics, true) : [];

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, mixed> $decoded */
    private function writeSpecifics(string $sku, array $decoded): void
    {
        $payload = ['specifics' => json_encode($decoded, JSON_UNESCAPED_UNICODE), 'updated_at' => now()];
        $key = $this->skuKey($sku);
        if (DB::table($this->t('item_specifics'))->where('sku', $key)->exists()) {
            DB::table($this->t('item_specifics'))->where('sku', $key)->update($payload);
        } else {
            DB::table($this->t('item_specifics'))->insert($payload + ['sku' => $key, 'created_at' => now()]);
        }
    }

    /** The price is not edited here: it follows the Amazon pricing pages. */
    protected function saveEbayPrices(array $skus, string $value)
    {
        return response()->json([
            'success' => false,
            'message' => 'The Amazon price is not changed on this page. Use the Amazon pricing pages.',
        ], 422);
    }

    // ---- Delete column: app only (Amazon holds the list of fields of a product type) ----

    /**
     * POST /amz-data/delete-specific
     * Removes the column from the open product type tab and from the stored copy of each SKU. Nothing is deleted on
     * Amazon: the fields of a product type belong to Amazon. A later pull brings the type's own fields back.
     */
    public function deleteSpecific(Request $request)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:191',
            'names' => 'nullable|array|max:500',
            'names.*' => 'required|string|max:191',
            'category' => 'nullable|string|max:500',
            'items' => 'nullable|array|max:20',
            'items.*.sku' => 'required|string|max:191',
            'items.*.item_id' => 'nullable|string|max:64',
        ]);
        if (! Schema::hasTable($this->t('item_specifics'))) {
            return response()->json(['success' => false, 'message' => 'The Amz Data tables could not be created.'], 500);
        }

        $names = [];
        foreach (array_merge((array) ($validated['names'] ?? []), isset($validated['name']) ? [$validated['name']] : []) as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && $this->aspectKey($candidate) !== '') {
                $names[$this->aspectKey($candidate)] = $candidate;
            }
        }
        if ($names === []) {
            return response()->json(['success' => false, 'message' => 'Choose a column.'], 422);
        }
        $wantedKeys = array_keys($names);

        if (array_key_exists('category', $validated) && Schema::hasTable($this->t('extra_columns'))) {
            $hash = $this->categoryHash((string) $validated['category']);
            DB::table($this->t('extra_columns'))->where('category_hash', $hash)->whereIn('name_key', $wantedKeys)->delete();
            if (Schema::hasTable($this->t('column_sources'))) {
                DB::table($this->t('column_sources'))->where('category_hash', $hash)->whereIn('column_key', $wantedKeys)->delete();
            }
            Cache::forget($this->cacheKey('column_options'));
        }

        $results = [];
        foreach ((array) ($validated['items'] ?? []) as $entry) {
            $sku = trim((string) $entry['sku']);
            $key = $this->skuKey($sku);
            if (Schema::hasTable($this->t('pending_specifics'))) {
                DB::table($this->t('pending_specifics'))->where('sku', $key)->whereIn('name_key', $wantedKeys)->delete();
            }
            $decoded = $this->storedSpecifics($sku);
            $changed = false;
            foreach (array_keys($decoded) as $storedName) {
                if (in_array($this->aspectKey((string) $storedName), $wantedKeys, true)) {
                    unset($decoded[$storedName]);
                    $changed = true;
                }
            }
            if ($changed) {
                $this->writeSpecifics($sku, $decoded);
            }
            $results[] = ['sku' => $sku, 'status' => 'app_only', 'message' => 'Removed in the app. Amazon is not changed.'];
        }

        return response()->json(['success' => true, 'results' => $results]);
    }

    // ---- Change category: an Amazon product type, changed in the app ----

    /**
     * GET /amz-data/ebay-categories?q=   (the route name is shared with the eBay pages)
     * The product types known here, plus Amazon's own product type search when something is typed.
     */
    public function ebayCategories(Request $request)
    {
        @set_time_limit(120);
        $q = trim((string) $request->query('q', ''));
        $tokens = array_values(array_filter(preg_split('/[\s_]+/', mb_strtolower($q === '__none__' ? '' : $q)) ?: []));

        $found = [];
        foreach ($this->knownProductTypes() as $type) {
            $hay = mb_strtolower(str_replace('_', ' ', $type));
            $ok = true;
            foreach ($tokens as $token) {
                if (! str_contains($hay, $token)) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                $found[$type] = ['id' => $type, 'path' => $type];
            }
        }

        $note = '';
        if ($tokens !== []) {
            try {
                $api = app(AmazonSpApiService::class);
                if ($api->isConfigured()) {
                    $search = $api->searchProductTypes(implode(' ', $tokens));
                    foreach ((array) ($search['categories'] ?? []) as $row) {
                        $type = strtoupper(trim((string) ($row['id'] ?? '')));
                        if (preg_match(Amz1DataService::TYPE_PATTERN, $type) === 1 && ! isset($found[$type])) {
                            $found[$type] = ['id' => $type, 'path' => (string) ($row['path'] ?? $type)];
                        }
                    }
                    if (($search['success'] ?? true) === false && $found === []) {
                        $note = (string) ($search['message'] ?? '');
                    }
                }
            } catch (\Throwable $e) {
                $note = $e->getMessage();
            }
        }

        $results = array_values($found);
        usort($results, static fn ($a, $b) => strcasecmp($a['path'], $b['path']));
        $total = count($results);

        return response()->json([
            'success' => true,
            'results' => array_slice($results, 0, 200),
            'total' => $total,
            'fetched_at' => '',
            'note' => $note,
        ]);
    }

    /**
     * POST /amz-data/change-category   { skus, category_id (the product type) }
     * App only: the stored copy of each SKU takes the product type (its tab and its columns). The live Amazon listing
     * keeps its product type, and the next pull restores the one Amazon has.
     */
    public function changeCategory(Request $request)
    {
        $data = $request->validate([
            'skus' => 'required|array|min:1|max:5000',
            'skus.*' => 'required|string|max:191',
            'category_id' => 'required|string|max:120',
            'category_path' => 'nullable|string|max:500',
        ]);
        $type = strtoupper(trim((string) $data['category_id']));
        if (preg_match(Amz1DataService::TYPE_PATTERN, $type) !== 1) {
            return response()->json(['success' => false, 'message' => '"'.$data['category_id'].'" is not an Amazon product type.'], 422);
        }
        if (! Schema::hasTable($this->t('item_specifics'))) {
            return response()->json(['success' => false, 'message' => 'The Amz Data tables could not be created.'], 500);
        }

        $skus = array_values(array_unique(array_filter(array_map('trim', $data['skus']))));
        $now = now();
        $results = [];
        foreach ($skus as $sku) {
            $results[] = ['sku' => $sku, 'ebay' => 'not_live', 'message' => 'Changed in the app only. The live Amazon listing keeps its product type.'];
        }

        $rows = 0;
        DB::transaction(function () use ($skus, $type, $now, &$rows) {
            foreach (array_chunk($skus, 500) as $chunk) {
                $keys = array_map(fn ($s) => $this->skuKey($s), $chunk);
                $rows += DB::table($this->t('item_specifics'))->whereIn('sku', $keys)->update(['category_path' => $type, 'updated_at' => $now]);
                $have = DB::table($this->t('item_specifics'))->whereIn('sku', $keys)->pluck('sku')->map(fn ($s) => (string) $s)->all();
                $missing = array_values(array_diff($keys, $have));
                foreach (array_chunk($missing, 200) as $part) {
                    DB::table($this->t('item_specifics'))->insert(array_map(static fn ($key) => [
                        'sku' => $key,
                        'category_path' => $type,
                        'specifics' => json_encode((object) []),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ], $part));
                    $rows += count($part);
                }
            }
        });
        $this->pulledMemo = null;

        return response()->json([
            'success' => true,
            'skus' => count($skus),
            'failed' => 0,
            'results' => $results,
            'category_master_rows' => 0,
            'pull_rows' => $rows,
            'category_id' => $type,
            'category_path' => $type,
        ]);
    }
}
