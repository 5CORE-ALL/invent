<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProductMaster\TechnicalSpecificationsController;
use App\Models\EbayDataView;
use App\Models\EbayMetric;
use App\Support\Marketplace\EbayListingEnded;
use App\Models\ProductMaster;
use App\Services\EbayApiService;
use App\Services\Support\EbayTradingReviseItem;
use App\Support\Marketplace\AutomatedListingPage;
use App\Support\Marketplace\EbayCategoryTree;
use App\Support\Marketplace\EbaySpricePush;
use App\Support\Marketplace\ProductUpc;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * "Ebay1 Data": every eBay 1 listing row (the same Listed / Missing L decision as Listing eBay),
 * with the eBay 1 metrics of the SKU, shown category wise in tabs.
 */
class Ebay1DataController extends Controller
{
    /**
     * The eBay account this page works on. eBay 2 and eBay 3 extend this controller and only override this:
     * their own tables (ebay2_item_specifics ...), metrics, listing channel and eBay API service.
     * eBay 1 is the main page: what is changed there is repeated on the eBay 2 and eBay 3 pages.
     *
     * @return array{key: string, label: string, prefix: string, registry: string, base: string, metric: class-string, dataView: class-string, service: class-string, main: bool}
     */
    protected function cfg(): array
    {
        return [
            'key' => 'ebay1',
            'label' => 'eBay 1',
            'prefix' => 'ebay1',
            'registry' => 'ebay',
            'base' => '/ebay1-data',
            'metric' => EbayMetric::class,
            'dataView' => EbayDataView::class,
            'service' => EbayApiService::class,
            'main' => true,
        ];
    }

    /** Table of this channel, e.g. t('item_specifics') = ebay1_item_specifics / ebay2_item_specifics. */
    protected function t(string $name): string
    {
        return $this->cfg()['prefix'].'_'.$name;
    }

    protected function cacheKey(string $name): string
    {
        return $this->cfg()['prefix'].'data.'.$name;
    }

    protected function metricsTable(): string
    {
        return (new ($this->cfg()['metric']))->getTable();
    }

    /** eBay item ID of the live listing of a SKU on this account, '' when it has none. */
    protected function liveItemId(string $sku): string
    {
        $metric = EbayListingEnded::preferredRow($this->cfg()['metric'], $sku);
        $itemId = trim((string) ($metric->item_id ?? ''));
        if ($metric === null
            || $itemId === ''
            || str_starts_with($itemId, 'UNSOLD-')
            || strcasecmp($itemId, $sku) === 0
            || EbayListingEnded::isEnded($metric->listing_status ?? null)
            || EbayListingEnded::isNotListed($metric->listing_status ?? null)) {
            return '';
        }

        return $itemId;
    }

    public function index(Request $request)
    {
        $cfg = $this->cfg();

        return view('market-places.ebay1-data', [
            'mode' => $request->query('mode', ''),
            'demo' => $request->query('demo', ''),
            'ebayCfg' => $cfg,
        ]);
    }

    /**
     * GET /ebay1-data/rows
     */
    public function rows(Request $request)
    {
        try {
            @set_time_limit(300);
            @ini_set('memory_limit', '512M');

            $listing = AutomatedListingPage::rows($this->cfg()['registry'], false, false);
            $metrics = $this->metricsBySku();
            $pulled = $this->pulledSpecificsBySku();
            $categories = $this->categoriesBySku($listing);
            $extras = $this->extraColumnsByCategory();
            $sources = $this->columnSourcesByCategory();
            $techSpecs = ($extras === [] && $sources === []) ? [] : $this->technicalSpecValuesBySku();

            // UPC column: the code held in CP Master (product_master), as on Technical Specifications.
            $cpUpc = ProductUpc::forSkus($listing->map(static fn ($item) => trim((string) ($item->sku ?? '')))->filter()->values()->all());

            // Item specifics edited by hand and not pushed yet, and the stored value of every SKU before autofill:
            // the cells of both kinds are what Auto Push sends.
            $pendingByKey = $this->pendingSpecificsBySku();
            $origByIndex = [];

            $data = [];
            foreach ($listing as $item) {
                $sku = trim((string) ($item->sku ?? ''));
                if ($this->isParentRow($sku, (string) ($item->parent ?? ''))) {
                    continue;
                }
                $key = $this->skuKey($sku);
                $metric = $metrics[$key] ?? [];
                $live = $pulled[$key] ?? null;
                // The live eBay category of a pulled listing wins over the sheet categories.
                $category = ($live['category_path'] ?? '') !== '' ? $live['category_path'] : ($categories[$key] ?? '');

                // Columns added by hand to this category tab: the value comes from Technical Specifications.
                $specifics = $live['specifics'] ?? [];
                foreach ($extras[$this->categoryHash($category)] ?? [] as $extraKey => $extraName) {
                    $already = false;
                    foreach (array_keys($specifics) as $existingName) {
                        if ($this->aspectKey((string) $existingName) === $extraKey) {
                            $already = true;
                            break;
                        }
                    }
                    if (! $already) {
                        $specifics[$extraName] = (string) ($techSpecs[$key][$extraKey] ?? '');
                    }
                }

                // Source Data: the column always shows the current Technical Specifications value of its source column.
                // A blank source value leaves what the column already had.
                foreach ($sources[$this->categoryHash($category)] ?? [] as $columnKey => $map) {
                    $target = $map['column_name'];
                    foreach (array_keys($specifics) as $existingName) {
                        if ($this->aspectKey((string) $existingName) === $columnKey) {
                            $target = (string) $existingName;
                            break;
                        }
                    }
                    $value = (string) ($techSpecs[$key][$map['source_key']] ?? '');
                    if ($value !== '') {
                        $specifics[$target] = $value;
                    } elseif (! array_key_exists($target, $specifics)) {
                        $specifics[$target] = '';
                    }
                }

                // A title typed in Edit Column waits here until Push. It wins over the metrics title,
                // and it is not an item specific, so it must not become its own column.
                $pendingTitle = trim((string) ($specifics['__ebay_title'] ?? ''));
                $pendingPrice = is_numeric($specifics['__ebay_price'] ?? null) ? round((float) $specifics['__ebay_price'], 2) : null;
                unset($specifics['__ebay_title'], $specifics['__ebay_price']);

                // Brand is always 5 Core, and Durability Guarantee is always 30, as on Technical Specifications.
                foreach (array_keys($specifics) as $specName) {
                    $aspect = $this->aspectKey((string) $specName);
                    if ($aspect === 'brand') {
                        $specifics[$specName] = '5 Core';
                    } elseif ($aspect === 'durabilityguarantee') {
                        $specifics[$specName] = '30';
                    }
                }

                // UPC is the CP Master code, else the barcode Amazon holds for the SKU (the same order Technical
                // Specifications and Push use). It replaces "Does not apply", "Not Applicable" and a blank cell, and every
                // SKU with a UPC gets the column. A SKU with no UPC anywhere leaves the cell as it was.
                $upc = (string) ($cpUpc[$sku]['upc'] ?? '');
                if ($upc !== '') {
                    $upcName = null;
                    foreach (array_keys($specifics) as $specName) {
                        if ($this->aspectKey((string) $specName) === 'upc') {
                            $upcName = (string) $specName;
                            break;
                        }
                    }
                    $specifics[$upcName ?? 'UPC'] = $upc;
                }

                $itemId = trim((string) ($item->listing_id ?? ''));
                if ($itemId === '' && ! empty($metric['item_id']) && ! in_array((string) ($metric['listing_status'] ?? ''), ['inactive', 'ended', 'unsold', 'completed', 'sold', 'missing', 'not_listed'], true)) {
                    $itemId = trim((string) $metric['item_id']);
                }

                // The stored copy follows eBay, so it is what autofill is compared with. A SKU with no stored copy
                // (null) is never auto pushed from autofill.
                $origKeys = null;
                if ($live !== null) {
                    $origKeys = [];
                    foreach (($live['specifics'] ?? []) as $origName => $origValue) {
                        if (str_starts_with((string) $origName, '__')) {
                            continue;
                        }
                        $origKeys[$this->aspectKey((string) $origName)] = is_scalar($origValue) ? trim((string) $origValue) : '';
                    }
                }
                $origByIndex[count($data)] = $origKeys;

                $data[] = [
                    'parent' => (string) ($item->parent ?? ''),
                    'sku' => $sku,
                    'inv' => (float) ($item->INV ?? 0),
                    'sold_l30' => (float) ($item->L30 ?? 0),
                    'nr_req' => (string) ($item->nr_req ?? 'REQ'),
                    'listed' => (string) ($item->listed ?? 'Pending'),
                    'item_id' => $itemId,
                    'buyer_link' => (string) ($item->buyer_link ?? ($metric['ebay_link'] ?? '')),
                    'ebay_title' => $pendingTitle !== '' ? $pendingTitle : (string) ($metric['ebay_title'] ?? ''),
                    'title_pending' => $pendingTitle !== '',
                    'ebay_price' => $pendingPrice !== null ? $pendingPrice : $this->num($metric['ebay_price'] ?? null),
                    'price_pending' => $pendingPrice !== null,
                    'ebay_stock' => $this->num($metric['ebay_stock'] ?? null),
                    'ebay_l7' => $this->num($metric['ebay_l7'] ?? null),
                    'ebay_l30' => $this->num($metric['ebay_l30'] ?? null),
                    'ebay_l60' => $this->num($metric['ebay_l60'] ?? null),
                    'views' => $this->num($metric['views'] ?? null),
                    'l7_views' => $this->num($metric['l7_views'] ?? null),
                    'l1_views' => $this->num($metric['l1_views'] ?? null),
                    'organic_clicks' => $this->num($metric['organic_clicks'] ?? null),
                    'listing_status' => (string) ($metric['listing_status'] ?? ''),
                    'inactive_reason' => (string) ($metric['inactive_reason'] ?? ''),
                    'report_date' => (string) ($metric['report_date'] ?? ''),
                    'category' => $category,
                    'specs_pulled' => $live !== null,
                    'specifics' => $specifics,
                ];
            }

            // Autofill rules, applied to every SKU of a category that has the column, missing listings included
            // (a missing listing has no pulled copy, so it would otherwise show these cells blank):
            //  - MPN and Model always carry the exact SKU of their own row, whatever eBay or an edit had there.
            //  - Brand is always 5 Core and Durability Guarantee is always 30.
            //  - Manufacturer (5 Core), Condition (New) and Country of Origin (China) are always applied to a
            //    missing listing. A listed row keeps what it has and only gets them when the cell is blank.
            $skuColumns = ['mpn' => 'MPN', 'model' => 'Model'];
            $alwaysColumns = ['brand' => '5 Core', 'durabilityguarantee' => '30'];
            $missingColumns = ['manufacturer' => '5 Core', 'condition' => 'New', 'countryoforigin' => 'China'];
            $present = [];
            foreach ($data as $entry) {
                foreach (array_keys($entry['specifics']) as $specName) {
                    $k = $this->aspectKey((string) $specName);
                    if (isset($skuColumns[$k]) || isset($alwaysColumns[$k]) || isset($missingColumns[$k])) {
                        $present[$this->categoryHash((string) $entry['category'])][$k] = (string) $specName;
                    }
                }
            }
            $isBlank = static function (string $value): bool {
                return in_array(strtolower(trim($value)), ['', 'does not apply', 'not applicable'], true);
            };
            foreach ($data as $i => $entry) {
                $hash = $this->categoryHash((string) $entry['category']);
                $isMissingListing = ! in_array((string) $entry['listed'], ['Listed', 'Created'], true);
                foreach ($present[$hash] ?? [] as $k => $columnName) {
                    $target = $columnName;
                    foreach (array_keys($entry['specifics']) as $specName) {
                        if ($this->aspectKey((string) $specName) === $k) {
                            $target = (string) $specName;
                            break;
                        }
                    }
                    if (isset($skuColumns[$k])) {
                        $data[$i]['specifics'][$target] = (string) $entry['sku'];
                    } elseif (isset($alwaysColumns[$k])) {
                        $data[$i]['specifics'][$target] = $alwaysColumns[$k];
                    } elseif ($isMissingListing || $isBlank((string) ($entry['specifics'][$target] ?? ''))) {
                        $data[$i]['specifics'][$target] = $missingColumns[$k];
                    }
                }
            }

            // push_cols: the item specifics Auto Push sends for the SKU. Those edited by hand and not pushed yet,
            // plus those autofill changed (UPC is not an item specific, it is pushed from Technical Specifications).
            $autofillKeys = array_merge(array_keys($skuColumns), array_keys($alwaysColumns), array_keys($missingColumns));
            foreach ($data as $i => $entry) {
                $pendingKeys = $pendingByKey[$this->skuKey((string) $entry['sku'])] ?? [];
                $orig = $origByIndex[$i] ?? null;
                $cols = [];
                foreach ($entry['specifics'] as $specName => $specValue) {
                    $k = $this->aspectKey((string) $specName);
                    $val = is_scalar($specValue) ? trim((string) $specValue) : '';
                    if ($val === '' || $k === 'upc' || str_starts_with((string) $specName, '__')) {
                        continue;
                    }
                    $edited = isset($pendingKeys[$k]);
                    $autofilled = $orig !== null && in_array($k, $autofillKeys, true) && ($orig[$k] ?? '') !== $val;
                    if ($edited || $autofilled) {
                        $cols[] = (string) $specName;
                    }
                }
                $data[$i]['push_cols'] = $cols;
            }

            return response()->json([
                'status' => 200,
                'data' => $data,
                'auto_push' => $this->autoPushEnabled(),
                'cell_styles' => (object) $this->cellStylesMap(),
                'sources' => $this->columnSourceList(),
                'column_groups' => $this->columnGroups(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Ebay1Data: rows failed', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 500,
                'message' => 'Failed to load Ebay1 Data: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /ebay1-data/delete-specific
     * Removes one item specific from the live eBay 1 listing of each SKU, then from the stored copy.
     * The stored copy is only changed for SKUs where eBay accepted the change (or never had it), so a failed SKU can be retried.
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
            return response()->json(['success' => false, 'message' => 'Run php artisan migrate first (table ebay1_item_specifics is missing).'], 500);
        }
        @set_time_limit(300);

        // One or several columns: every SKU is changed once on eBay, whatever the number of columns.
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

        // Columns added by hand to this category tab are dropped as well (the live listings below are separate).
        if (array_key_exists('category', $validated) && Schema::hasTable($this->t('extra_columns'))) {
            $hash = $this->categoryHash((string) $validated['category']);
            DB::table($this->t('extra_columns'))->where('category_hash', $hash)->whereIn('name_key', $wantedKeys)->delete();
            if (Schema::hasTable($this->t('column_sources'))) {
                DB::table($this->t('column_sources'))->where('category_hash', $hash)->whereIn('column_key', $wantedKeys)->delete();
            }
            Cache::forget($this->cacheKey('column_options'));
        }
        $validated['items'] = $validated['items'] ?? [];
        $service = app($this->cfg()['service']);
        $results = [];

        // The app's own copy of the item specifics of a SKU loses the columns.
        $dropStored = function (string $key) use ($wantedKeys): void {
            if (Schema::hasTable($this->t('pending_specifics'))) {
                DB::table($this->t('pending_specifics'))->where('sku', $key)->whereIn('name_key', $wantedKeys)->delete();
            }
            $stored = DB::table($this->t('item_specifics'))->where('sku', $key)->first();
            if (! $stored) {
                return;
            }
            $decoded = json_decode((string) $stored->specifics, true);
            $decoded = is_array($decoded) ? $decoded : [];
            foreach (array_keys($decoded) as $storedName) {
                if (in_array($this->aspectKey((string) $storedName), $wantedKeys, true)) {
                    unset($decoded[$storedName]);
                }
            }
            DB::table($this->t('item_specifics'))->where('sku', $key)->update([
                'specifics' => json_encode($decoded, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
        };
        // eBay answers that mean the listing is simply not there (ended, deleted, wrong id).
        $listingGone = static fn (string $message): bool => (bool) preg_match('/\b(not found|no longer|has ended|ended|closed|does not exist|invalid item)\b/i', $message);

        foreach ($validated['items'] as $entry) {
            $sku = trim((string) $entry['sku']);
            $itemId = trim((string) ($entry['item_id'] ?? ''));
            $key = $this->skuKey($sku);

            // No live listing: the columns are still removed in the app.
            if (! preg_match('/^\d{9,15}$/', $itemId)) {
                $dropStored($key);
                $results[] = ['sku' => $sku, 'status' => 'app_only', 'message' => 'No eBay listing. Removed in the app.'];

                continue;
            }

            try {
                $item = $service->getItem($itemId);
                if (! is_array($item)) {
                    $why = trim((string) ($service->lastGetItemError ?? ''));
                    // The listing is not on eBay (any more): remove in the app anyway.
                    $dropStored($key);
                    $results[] = ['sku' => $sku, 'status' => 'app_only', 'message' => 'Listing not on eBay'.($why !== '' ? ' ('.$why.')' : '').'. Removed in the app.'];

                    continue;
                }
                $ctx = $service->tradingReviseContext();
                $result = EbayTradingReviseItem::removeItemSpecific(
                    $ctx['endpoint'],
                    $ctx['compatLevel'],
                    $ctx['devId'],
                    $ctx['appId'],
                    $ctx['certId'],
                    $ctx['siteId'],
                    $ctx['authToken'],
                    $item,
                    array_values($names)
                );
            } catch (\Throwable $e) {
                Log::warning('Ebay1Data: delete specific failed', ['sku' => $sku, 'names' => array_values($names), 'error' => $e->getMessage()]);
                $results[] = ['sku' => $sku, 'status' => 'failed', 'message' => $e->getMessage()];

                continue;
            }
            if (! ($result['success'] ?? false)) {
                $message = (string) ($result['message'] ?? 'eBay rejected the change.');
                if ($listingGone($message)) {
                    $dropStored($key);
                    $results[] = ['sku' => $sku, 'status' => 'app_only', 'message' => $message.' Removed in the app.'];
                } else {
                    $results[] = ['sku' => $sku, 'status' => 'failed', 'message' => $message];
                }

                continue;
            }

            $dropStored($key);
            $results[] = [
                'sku' => $sku,
                'status' => ($result['removed'] ?? false) ? 'deleted' : 'absent',
                'message' => ($result['removed'] ?? false) ? 'Removed from eBay.' : 'Was not on the eBay listing.',
            ];
        }

        return response()->json(['success' => true, 'results' => $results]);
    }

    /**
     * GET /ebay1-data/column-options
     * Every column name available on Technical Specifications, plus the item specifics already pulled here.
     */
    public function columnOptions()
    {
        $names = Cache::remember($this->cacheKey('column_options'), 600, function () {
            $found = [];
            try {
                $response = app(TechnicalSpecificationsController::class)->getData(request());
                $payload = $response->getData(true);
                foreach ((array) ($payload['ebay_aspects'] ?? []) as $name) {
                    $found[] = (string) $name;
                }
            } catch (\Throwable $e) {
                Log::warning('Ebay1Data: could not read Technical Specifications columns', ['error' => $e->getMessage()]);
            }
            if (Schema::hasTable($this->t('item_specifics'))) {
                foreach (DB::table($this->t('item_specifics'))->pluck('specifics') as $json) {
                    $decoded = json_decode((string) $json, true);
                    foreach (is_array($decoded) ? array_keys($decoded) : [] as $name) {
                        $found[] = (string) $name;
                    }
                }
            }
            if (Schema::hasTable($this->t('extra_columns'))) {
                foreach (DB::table($this->t('extra_columns'))->pluck('name') as $name) {
                    $found[] = (string) $name;
                }
            }

            $unique = [];
            foreach ($found as $name) {
                $name = trim($name);
                $key = $this->aspectKey($name);
                if ($name !== '' && $key !== '' && strlen($name) <= 120 && ! preg_match('/[{};<>]|text-decoration|^\./', $name) && ! isset($unique[$key])) {
                    $unique[$key] = $name;
                }
            }
            $list = array_values($unique);
            usort($list, static fn ($a, $b) => strcasecmp($a, $b));

            return $list;
        });

        return response()->json(['success' => true, 'columns' => $names]);
    }

    /**
     * POST /ebay1-data/add-column
     * Adds a column to one category tab. The name is also written as a blank specification on the category's SKUs,
     * so Technical Specifications shows the same column. An unknown name simply becomes a new column.
     */
    public function addColumn(Request $request)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:191',
            'names' => 'nullable|array|max:50',
            'names.*' => 'required|string|max:191',
            'category' => 'nullable|string|max:500',
            'skus' => 'required|array|min:1|max:3000',
            'skus.*' => 'required|string|max:191',
        ]);
        if (! Schema::hasTable($this->t('extra_columns'))) {
            return response()->json(['success' => false, 'message' => 'Run php artisan migrate first (table ebay1_extra_columns is missing).'], 500);
        }

        $requested = array_merge((array) ($validated['names'] ?? []), isset($validated['name']) ? [$validated['name']] : []);
        $names = [];
        foreach ($requested as $candidate) {
            $candidate = trim(preg_replace('/\s+/u', ' ', (string) $candidate) ?? '');
            $candidateKey = $this->aspectKey($candidate);
            if ($candidate === '' || $candidateKey === '' || preg_match('/[{}<>;]/', $candidate)) {
                continue;
            }
            $names[$candidateKey] = $candidate;
        }
        if ($names === []) {
            return response()->json(['success' => false, 'message' => 'Enter a valid column name.'], 422);
        }
        @set_time_limit(300);

        $category = trim((string) ($validated['category'] ?? ''));
        $hash = $this->categoryHash($category);
        $skus = array_values(array_unique(array_filter(array_map('trim', $validated['skus']), static fn ($s) => $s !== '' && stripos($s, 'PARENT') === false)));

        // Technical Specifications: a column exists once a SKU of the category carries its name.
        $stored = [];
        foreach ($names as $key => $name) {
            $existing = DB::table($this->t('extra_columns'))->where('category_hash', $hash)->where('name_key', $key)->first();
            if ($existing) {
                $names[$key] = (string) $existing->name;
            } else {
                DB::table($this->t('extra_columns'))->insert([
                    'category' => $category,
                    'category_hash' => $hash,
                    'name' => $name,
                    'name_key' => $key,
                    'added_by' => $request->user()?->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $stored[] = $names[$key];
        }

        $touched = 0;
        if (Schema::hasColumn('product_master', 'description_v2_specifications')) {
            foreach (array_chunk($skus, 200) as $chunk) {
                foreach (ProductMaster::query()->whereIn('sku', $chunk)->get() as $product) {
                    $specs = is_array($product->description_v2_specifications) ? $product->description_v2_specifications : [];
                    $have = [];
                    foreach ($specs as $spec) {
                        $have[$this->aspectKey((string) ($spec['key'] ?? ''))] = true;
                    }
                    $changed = false;
                    foreach ($names as $key => $name) {
                        if (! isset($have[$key])) {
                            $specs[] = ['key' => $name, 'value' => ''];
                            $changed = true;
                        }
                    }
                    if ($changed) {
                        $product->description_v2_specifications = $specs;
                        $product->save();
                        $touched++;
                    }
                }
            }
        }
        Cache::forget($this->cacheKey('column_options'));

        return response()->json([
            'success' => true,
            'names' => $stored,
            'skus_updated' => $touched,
        ]);
    }

    /**
     * POST /ebay1-data/push
     * Sends the item specifics shown in the table to the live eBay 1 listing of each SKU.
     * Blank values are never sent, and values that already match eBay are left alone.
     */
    public function pushSpecifics(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1|max:10',
            'items.*.sku' => 'required|string|max:191',
            // Blank on a mirrored push from the eBay 1 page: the listing of this account is found by SKU.
            'items.*.item_id' => 'nullable|string|max:64',
            'items.*.specifics' => 'nullable|array|max:300',
            'items.*.title' => 'nullable|string|max:80',
            'items.*.price' => 'nullable|numeric|min:0.01|max:100000',
        ]);
        @set_time_limit(300);

        $service = app($this->cfg()['service']);
        $results = [];
        foreach ($validated['items'] as $entry) {
            $sku = trim((string) $entry['sku']);
            $itemId = trim((string) ($entry['item_id'] ?? ''));
            if ($itemId === '') {
                $itemId = $this->liveItemId($sku);
                if ($itemId === '') {
                    $results[] = ['sku' => $sku, 'status' => 'skipped', 'message' => 'Not listed on '.$this->cfg()['label'].'.'];

                    continue;
                }
            }
            if (! preg_match('/^\d{9,15}$/', $itemId)) {
                $results[] = ['sku' => $sku, 'status' => 'failed', 'message' => 'No eBay item ID.'];

                continue;
            }

            $overrides = [];
            foreach ((array) ($entry['specifics'] ?? []) as $name => $value) {
                $name = trim((string) $name);
                $value = is_scalar($value) ? trim((string) $value) : '';
                // UPC is not an item specific. It is pushed from Technical Specifications.
                if ($name === '' || $value === '' || strcasecmp($name, 'UPC') === 0) {
                    continue;
                }
                $overrides[] = ['name' => $name, 'value' => $value];
            }
            $title = trim((string) ($entry['title'] ?? ''));
            $price = is_numeric($entry['price'] ?? null) ? round((float) $entry['price'], 2) : 0.0;
            if ($overrides === [] && $title === '' && $price <= 0) {
                $results[] = ['sku' => $sku, 'status' => 'skipped', 'message' => 'No values to send.'];

                continue;
            }

            $ctx = null;
            $result = ['success' => true, 'message' => ''];
            try {
                $ctx = $service->tradingReviseContext();
                if ($overrides !== []) {
                    $item = $service->getItem($itemId);
                    if (! is_array($item)) {
                        $why = trim((string) ($service->lastGetItemError ?? ''));
                        $results[] = ['sku' => $sku, 'status' => 'failed', 'message' => $why !== '' ? $why : 'eBay did not return the listing.'];

                        continue;
                    }
                    $result = EbayTradingReviseItem::reviseTechnicalSpecifications(
                        $ctx['endpoint'],
                        $ctx['compatLevel'],
                        $ctx['devId'],
                        $ctx['appId'],
                        $ctx['certId'],
                        $ctx['siteId'],
                        $ctx['authToken'],
                        $item,
                        $sku,
                        $overrides
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('Ebay1Data: push failed', ['sku' => $sku, 'error' => $e->getMessage()]);
                $results[] = ['sku' => $sku, 'status' => 'failed', 'message' => $e->getMessage()];

                continue;
            }

            if (! ($result['success'] ?? false)) {
                $results[] = ['sku' => $sku, 'status' => 'failed', 'message' => (string) ($result['message'] ?? 'eBay rejected the change.')];

                continue;
            }

            // These cells are on eBay now, so they are no longer waiting for Auto Push.
            $this->clearPendingSpecifics($sku, array_column($overrides, 'name'));

            // The stored copy follows eBay once eBay accepted the values.
            if ($overrides !== [] && Schema::hasTable($this->t('item_specifics'))) {
                $stored = DB::table($this->t('item_specifics'))->where('sku', $this->skuKey($sku))->first();
                if ($stored) {
                    $decoded = json_decode((string) $stored->specifics, true);
                    $decoded = is_array($decoded) ? $decoded : [];
                    foreach ($overrides as $row) {
                        $target = $row['name'];
                        foreach (array_keys($decoded) as $existing) {
                            if ($this->aspectKey((string) $existing) === $this->aspectKey($row['name'])) {
                                $target = (string) $existing;
                                break;
                            }
                        }
                        $decoded[$target] = $row['value'];
                    }
                    DB::table($this->t('item_specifics'))->where('sku', $this->skuKey($sku))->update([
                        'specifics' => json_encode($decoded, JSON_UNESCAPED_UNICODE),
                        'updated_at' => now(),
                    ]);
                }
            }

            $titleMessage = '';
            if ($title !== '') {
                try {
                    $titleResult = EbayTradingReviseItem::reviseListingTitle(
                        $ctx['endpoint'],
                        $ctx['compatLevel'],
                        $ctx['devId'],
                        $ctx['appId'],
                        $ctx['certId'],
                        $ctx['siteId'],
                        $ctx['authToken'],
                        $itemId,
                        $title,
                        $sku
                    );
                } catch (\Throwable $e) {
                    Log::warning('Ebay1Data: title push failed', ['sku' => $sku, 'error' => $e->getMessage()]);
                    $results[] = ['sku' => $sku, 'status' => 'failed', 'message' => $e->getMessage()];

                    continue;
                }
                if (! ($titleResult['success'] ?? false)) {
                    $results[] = ['sku' => $sku, 'status' => 'failed', 'message' => (string) ($titleResult['message'] ?? 'eBay rejected the title.')];

                    continue;
                }
                $this->clearPendingTitle($sku);
                if (Schema::hasTable($this->metricsTable()) && Schema::hasColumn($this->metricsTable(), 'ebay_title')) {
                    DB::table($this->metricsTable())->where('sku', $sku)->update(['ebay_title' => $title]);
                }
                $titleMessage = (string) ($titleResult['message'] ?? '');
            }

            $priceMessage = '';
            if ($price > 0) {
                try {
                    $priceResult = $service->reviseFixedPriceItem($itemId, $price, null, null);
                    if (! (is_array($priceResult) && ! empty($priceResult['success'])) && EbaySpricePush::looksMultiVariation($priceResult)) {
                        $priceResult = $service->reviseFixedPriceItem($itemId, $price, null, $sku);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Ebay1Data: price push failed', ['sku' => $sku, 'error' => $e->getMessage()]);
                    $results[] = ['sku' => $sku, 'status' => 'failed', 'message' => 'Price: '.$e->getMessage()];

                    continue;
                }
                if (! (is_array($priceResult) && ! empty($priceResult['success']))) {
                    $why = is_array($priceResult) ? (string) ($priceResult['message'] ?? 'eBay rejected the price.') : 'eBay rejected the price.';
                    if (is_array($priceResult) && ! empty($priceResult['errors'])) {
                        $why .= ': '.(is_string($priceResult['errors']) ? $priceResult['errors'] : json_encode($priceResult['errors']));
                    }
                    $results[] = ['sku' => $sku, 'status' => 'failed', 'message' => 'Price: '.$why];

                    continue;
                }
                $this->clearPendingPrice($sku);
                EbaySpricePush::storeAcceptedPriceFor($this->cfg()['key'], $sku, $price);
                $priceMessage = 'Price set to '.number_format($price, 2).'.';
            }

            $message = trim((string) ($result['message'] ?? '').($titleMessage !== '' ? ' '.$titleMessage : '').($priceMessage !== '' ? ' '.$priceMessage : ''));
            $unchanged = $title === '' && $price <= 0 && stripos((string) ($result['message'] ?? ''), 'No values changed') === 0;
            $results[] = ['sku' => $sku, 'status' => $unchanged ? 'unchanged' : 'pushed', 'message' => $message];
        }

        return response()->json(['success' => true, 'results' => $results]);
    }

    /**
     * POST /ebay1-data/nr-req
     * REQ / NRL of a SKU. It is stored exactly as the Listing eBay page stores it (ebay_data_view.value.NRL),
     * so both pages always show the same value.
     */
    public function saveNrReq(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:191',
            'nr_req' => 'required|string|in:REQ,NR,NRL',
        ]);
        $sku = trim((string) $validated['sku']);
        $isNr = in_array($validated['nr_req'], ['NR', 'NRL'], true);

        $record = $this->cfg()['dataView']::where('sku', $sku)->first();
        $existing = $record ? $record->value : [];
        if (! is_array($existing)) {
            $existing = json_decode((string) $existing, true) ?: [];
        }
        $existing['NRL'] = $isNr ? 'NRL' : 'REQ';

        $this->cfg()['dataView']::updateOrCreate(['sku' => $sku], ['value' => $existing]);

        return response()->json(['success' => true, 'sku' => $sku, 'nr_req' => $isNr ? 'NR' : 'REQ']);
    }

    /**
     * POST /ebay1-data/save-source
     * Saves (or, with a blank source, removes) the Technical Specifications column a tab column reads from.
     */
    public function saveSource(Request $request)
    {
        $validated = $request->validate([
            'category' => 'nullable|string|max:500',
            'column' => 'required|string|max:191',
            'source' => 'nullable|string|max:191',
        ]);
        if (! Schema::hasTable($this->t('column_sources'))) {
            return response()->json(['success' => false, 'message' => 'Run php artisan migrate first (table ebay1_column_sources is missing).'], 500);
        }

        $category = trim((string) ($validated['category'] ?? ''));
        $hash = $this->categoryHash($category);
        $column = trim((string) $validated['column']);
        $columnKey = $this->aspectKey($column);
        $source = trim(preg_replace('/\s+/u', ' ', (string) ($validated['source'] ?? '')) ?? '');
        $sourceKey = $this->aspectKey($source);
        if ($columnKey === '') {
            return response()->json(['success' => false, 'message' => 'Choose a column.'], 422);
        }

        $query = DB::table($this->t('column_sources'))->where('category_hash', $hash)->where('column_key', $columnKey);
        if ($source === '' || $sourceKey === '') {
            $query->delete();

            return response()->json(['success' => true, 'removed' => true]);
        }

        $payload = [
            'category' => $category,
            'column_name' => $column,
            'source_name' => $source,
            'source_key' => $sourceKey,
            'saved_by' => $request->user()?->id,
            'updated_at' => now(),
        ];
        if ($query->exists()) {
            DB::table($this->t('column_sources'))->where('category_hash', $hash)->where('column_key', $columnKey)->update($payload);
        } else {
            DB::table($this->t('column_sources'))->insert($payload + [
                'category_hash' => $hash,
                'column_key' => $columnKey,
                'created_at' => now(),
            ]);
        }

        return response()->json(['success' => true, 'removed' => false, 'source' => $source]);
    }

    /**
     * POST /ebay1-data/edit-column
     * Sets one item-specific column to the same value on the given SKUs. The value is saved as the SKU's
     * Technical Specifications value and in the stored eBay 1 copy this page shows. Nothing is sent to eBay until Push.
     */
    public function editColumn(Request $request)
    {
        $data = $request->validate([
            'column' => 'required|string|max:191',
            'value' => 'nullable|string|max:2000',
            'skus' => 'required|array|min:1|max:300',
            'skus.*' => 'required|string|max:191',
        ]);
        $column = trim(preg_replace('/\s+/u', ' ', (string) $data['column']) ?? '');
        $columnKey = $this->aspectKey($column);
        if ($columnKey === '') {
            return response()->json(['success' => false, 'message' => 'Choose a column.'], 422);
        }
        $value = trim((string) ($data['value'] ?? ''));
        @set_time_limit(300);

        $skus = array_values(array_unique(array_filter(
            array_map('trim', $data['skus']),
            static fn ($s) => $s !== '' && stripos($s, 'PARENT') === false
        )));
        if ($columnKey === 'ebaytitle') {
            return $this->saveEbayTitles($skus, $value);
        }
        if ($columnKey === 'price') {
            return $this->saveEbayPrices($skus, $value);
        }

        $specsUpdated = 0;
        if (Schema::hasColumn('product_master', 'description_v2_specifications')) {
            foreach (ProductMaster::query()->whereIn('sku', $skus)->get() as $product) {
                $specs = is_array($product->description_v2_specifications) ? $product->description_v2_specifications : [];
                $found = false;
                foreach ($specs as &$spec) {
                    if ($this->aspectKey((string) ($spec['key'] ?? '')) === $columnKey) {
                        $spec['value'] = $value;
                        $found = true;
                    }
                }
                unset($spec);
                if (! $found) {
                    $specs[] = ['key' => $column, 'value' => $value];
                }
                $product->description_v2_specifications = $specs;
                $product->save();
                $specsUpdated++;
            }
        }

        $copiesUpdated = 0;
        if (Schema::hasTable($this->t('item_specifics'))) {
            foreach ($skus as $sku) {
                $stored = DB::table($this->t('item_specifics'))->where('sku', $this->skuKey($sku))->first();
                $decoded = $stored ? json_decode((string) $stored->specifics, true) : [];
                $decoded = is_array($decoded) ? $decoded : [];
                $changed = false;
                foreach (array_keys($decoded) as $name) {
                    if ($this->aspectKey((string) $name) === $columnKey) {
                        $decoded[$name] = $value;
                        $changed = true;
                    }
                }
                if (! $changed) {
                    // The listing did not carry this item specific yet; it gets it so the cell shows and Push sends it.
                    $decoded[$column] = $value;
                    $changed = true;
                }
                if ($changed) {
                    $payload = [
                        'specifics' => json_encode($decoded, JSON_UNESCAPED_UNICODE),
                        'updated_at' => now(),
                    ];
                    if ($stored) {
                        DB::table($this->t('item_specifics'))->where('sku', $this->skuKey($sku))->update($payload);
                    } else {
                        // A SKU never pulled from eBay (for example a Missing L row) has no stored copy yet. Without one
                        // the pasted value only reached Technical Specifications and this page showed it blank again
                        // after a refresh, so the copy is created here.
                        DB::table($this->t('item_specifics'))->insert($payload + [
                            'sku' => $this->skuKey($sku),
                            'created_at' => now(),
                        ]);
                    }
                    $copiesUpdated++;
                    // An edited cell waits for Auto Push (or the Push button) until it is on eBay.
                    $this->markPendingSpecific($sku, $column);
                }
            }
        }

        return response()->json(['success' => true, 'skus' => count($skus), 'specs_updated' => $specsUpdated, 'copies_updated' => $copiesUpdated]);
    }

    /**
     * POST /ebay1-data/column-groups
     * Replaces the whole list of headers and the columns in each (a column can be in several headers).
     */
    public function saveColumnGroups(Request $request)
    {
        $data = $request->validate([
            'groups' => 'present|array|max:100',
            'groups.*.name' => 'required|string|max:100',
            'groups.*.columns' => 'nullable|array|max:2000',
            'groups.*.columns.*' => 'nullable|string|max:191',
        ]);
        if (! Schema::hasTable($this->t('column_groups'))) {
            return response()->json(['success' => false, 'message' => 'Run php artisan migrate first (table ebay1_column_groups is missing).'], 500);
        }

        $groups = [];
        foreach ($data['groups'] as $group) {
            $name = trim(preg_replace('/\s+/u', ' ', (string) ($group['name'] ?? '')) ?? '');
            if ($name === '' || isset($groups[strtolower($name)])) {
                continue;
            }
            $columns = [];
            foreach ((array) ($group['columns'] ?? []) as $column) {
                $column = trim((string) $column);
                if ($column !== '' && $this->aspectKey($column) !== '') {
                    $columns[$this->aspectKey($column)] = $column;
                }
            }
            $groups[strtolower($name)] = ['name' => $name, 'columns' => array_values($columns)];
        }

        DB::transaction(function () use ($groups, $request) {
            DB::table($this->t('column_groups'))->delete();
            $order = 0;
            foreach ($groups as $group) {
                DB::table($this->t('column_groups'))->insert([
                    'name' => $group['name'],
                    'columns' => json_encode($group['columns'], JSON_UNESCAPED_UNICODE),
                    'sort_order' => $order++,
                    'saved_by' => $request->user()?->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return response()->json(['success' => true, 'groups' => array_values($groups)]);
    }

    /**
     * @return list<array{name: string, columns: list<string>}>
     */
    private function columnGroups(): array
    {
        if (! Schema::hasTable($this->t('column_groups'))) {
            return [];
        }
        $out = [];
        foreach (DB::table($this->t('column_groups'))->orderBy('sort_order')->orderBy('id')->get(['name', 'columns']) as $row) {
            $decoded = json_decode((string) $row->columns, true);
            $out[] = [
                'name' => (string) $row->name,
                'columns' => array_values(array_filter(is_array($decoded) ? $decoded : [], 'is_string')),
            ];
        }

        return $out;
    }

    /**
     * Column sources: category hash => [column key => {column_name, source_name, source_key}].
     *
     * @return array<string, array<string, array{column_name: string, source_name: string, source_key: string}>>
     */
    private function columnSourcesByCategory(): array
    {
        if (! Schema::hasTable($this->t('column_sources'))) {
            return [];
        }
        $out = [];
        foreach (DB::table($this->t('column_sources'))->get(['category_hash', 'column_key', 'column_name', 'source_name', 'source_key']) as $row) {
            $out[(string) $row->category_hash][(string) $row->column_key] = [
                'column_name' => (string) $row->column_name,
                'source_name' => (string) $row->source_name,
                'source_key' => (string) $row->source_key,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{category: string, column: string, source: string}>
     */
    private function columnSourceList(): array
    {
        if (! Schema::hasTable($this->t('column_sources'))) {
            return [];
        }

        return DB::table($this->t('column_sources'))->get(['category', 'column_name', 'source_name'])
            ->map(static fn ($row) => ['category' => (string) $row->category, 'column' => (string) $row->column_name, 'source' => (string) $row->source_name])
            ->all();
    }

    /**
     * Hand-added columns: category hash => [name key => name].
     *
     * @return array<string, array<string, string>>
     */
    private function extraColumnsByCategory(): array
    {
        if (! Schema::hasTable($this->t('extra_columns'))) {
            return [];
        }
        $out = [];
        foreach (DB::table($this->t('extra_columns'))->orderBy('id')->get(['category_hash', 'name', 'name_key']) as $row) {
            $out[(string) $row->category_hash][(string) $row->name_key] = (string) $row->name;
        }

        return $out;
    }

    /**
     * Technical Specifications values: SKU key => [name key => value].
     *
     * @return array<string, array<string, string>>
     */
    private function technicalSpecValuesBySku(): array
    {
        if (! Schema::hasTable('product_master') || ! Schema::hasColumn('product_master', 'description_v2_specifications')) {
            return [];
        }
        $out = [];
        ProductMaster::query()
            ->whereNotNull('description_v2_specifications')
            ->select(['id', 'sku', 'description_v2_specifications'])
            ->chunkById(500, function ($products) use (&$out) {
                foreach ($products as $product) {
                    $specs = is_array($product->description_v2_specifications) ? $product->description_v2_specifications : [];
                    foreach ($specs as $spec) {
                        $value = trim((string) ($spec['value'] ?? ''));
                        $specKey = $this->aspectKey((string) ($spec['key'] ?? ''));
                        if ($value !== '' && $specKey !== '') {
                            $out[$this->skuKey((string) $product->sku)][$specKey] = $value;
                        }
                    }
                }
            });

        return $out;
    }

    /**
     * A parent row is a "PARENT ..." SKU, or a row whose SKU is the parent name itself.
     */
    private function isParentRow(string $sku, string $parent): bool
    {
        $sku = trim($sku);
        if ($sku === '' || stripos($sku, 'PARENT') !== false) {
            return true;
        }

        return $parent !== '' && $this->skuKey($sku) === $this->skuKey($parent);
    }

    private function categoryHash(string $category): string
    {
        return sha1(strtolower(trim($category)));
    }

    private function aspectKey(string $name): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', $name) ?? $name);
    }

    /**
     * Stored one-time pulls, by SKU key.
     *
     * @return array<string, array{category_path: string, specifics: array<string, string>}>
     */
    private function pulledSpecificsBySku(): array
    {
        if (! Schema::hasTable($this->t('item_specifics'))) {
            return [];
        }
        $out = [];
        foreach (DB::table($this->t('item_specifics'))->select(['sku', 'category_path', 'specifics'])->get() as $row) {
            $decoded = json_decode((string) $row->specifics, true);
            $out[$this->skuKey((string) $row->sku)] = [
                'category_path' => trim((string) ($row->category_path ?? '')),
                'specifics' => is_array($decoded) ? $decoded : [],
            ];
        }

        return $out;
    }

    /**
     * Latest ebay_metrics row per SKU.
     *
     * @return array<string, array<string, mixed>>
     */
    private function metricsBySku(): array
    {
        if (! Schema::hasTable($this->metricsTable())) {
            return [];
        }
        $wanted = [
            'sku', 'item_id', 'ebay_title', 'ebay_link', 'ebay_price', 'ebay_stock', 'ebay_l7', 'ebay_l30', 'ebay_l60',
            'views', 'l7_views', 'l1_views', 'organic_clicks', 'listing_status', 'inactive_reason', 'report_date',
        ];
        $columns = array_values(array_filter($wanted, fn ($column) => Schema::hasColumn($this->metricsTable(), $column)));
        if (! in_array('sku', $columns, true)) {
            return [];
        }

        $out = [];
        DB::table($this->metricsTable())
            ->whereNotNull('sku')
            ->orderBy('id')
            ->select($columns)
            ->chunk(1000, function ($rows) use (&$out) {
                foreach ($rows as $row) {
                    // Later rows replace earlier ones, so the newest row of a SKU wins.
                    $out[$this->skuKey((string) $row->sku)] = (array) $row;
                }
            });

        return $out;
    }

    /**
     * eBay category path per SKU. A SKU without one takes the category of a sibling under the same parent.
     *
     * @param  iterable<int, object>  $listing
     * @return array<string, string>
     */
    private function categoriesBySku(iterable $listing): array
    {
        // Most specific first: the eBay category from Category Master, then CAT-5C, then the Product Master category.
        $byKey = [];
        if (Schema::hasTable('category_masters')) {
            $columns = array_values(array_filter(['sku', 'category_ebay', 'category_5c'], static fn ($c) => Schema::hasColumn('category_masters', $c)));
            if (in_array('sku', $columns, true)) {
                foreach (DB::table('category_masters')->select($columns)->get() as $row) {
                    $path = trim((string) ($row->category_ebay ?? ''));
                    if ($path === '') {
                        $path = trim((string) ($row->category_5c ?? ''));
                    }
                    if ($path !== '') {
                        $byKey[$this->skuKey((string) $row->sku)] = $path;
                    }
                }
            }
        }

        if (Schema::hasTable('product_master')) {
            $hasName = Schema::hasColumn('product_master', 'category');
            $hasId = Schema::hasColumn('product_master', 'category_id') && Schema::hasTable('product_categories');
            if ($hasName || $hasId) {
                $query = DB::table('product_master')->select('product_master.sku');
                if ($hasName) {
                    $query->addSelect('product_master.category as category_name_text');
                }
                if ($hasId) {
                    $query->leftJoin('product_categories', 'product_categories.id', '=', 'product_master.category_id')
                        ->addSelect('product_categories.category_name as category_name_fk');
                }
                foreach ($query->get() as $row) {
                    $key = $this->skuKey((string) $row->sku);
                    if (isset($byKey[$key])) {
                        continue;
                    }
                    $name = trim((string) ($row->category_name_fk ?? ''));
                    if ($name === '') {
                        $name = trim((string) ($row->category_name_text ?? ''));
                    }
                    if ($name !== '') {
                        $byKey[$key] = $name;
                    }
                }
            }
        }

        $byParent = [];
        foreach ($listing as $item) {
            $key = $this->skuKey((string) ($item->sku ?? ''));
            $parent = $this->skuKey((string) ($item->parent ?? ''));
            if ($parent !== '' && isset($byKey[$key]) && ! isset($byParent[$parent])) {
                $byParent[$parent] = $byKey[$key];
            }
        }

        $out = $byKey;
        foreach ($listing as $item) {
            $key = $this->skuKey((string) ($item->sku ?? ''));
            if (isset($out[$key])) {
                continue;
            }
            $parent = $this->skuKey((string) ($item->parent ?? ''));
            if ($parent !== '' && isset($byParent[$parent])) {
                $out[$key] = $byParent[$parent];
            }
        }

        return $out;
    }

    /**
     * Keeps an edited eBay title on the SKU until Push sends it. eBay allows 80 characters.
     */
    private function saveEbayTitles(array $skus, string $value)
    {
        if ($value === '') {
            return response()->json(['success' => false, 'message' => 'eBay title cannot be blank.'], 422);
        }
        if (mb_strlen($value) > 80) {
            return response()->json(['success' => false, 'message' => 'eBay title can be at most 80 characters.'], 422);
        }

        if (Schema::hasTable($this->metricsTable()) && Schema::hasColumn($this->metricsTable(), 'ebay_title')) {
            DB::table($this->metricsTable())->whereIn('sku', $skus)->update(['ebay_title' => $value]);
        }

        return $this->storePending($skus, '__ebay_title', $value);
    }

    /**
     * Keeps an edited price on the SKU until Push sends it. The live price on the metrics row is not touched.
     */
    private function saveEbayPrices(array $skus, string $value)
    {
        $clean = str_replace([',', '$', ' '], '', $value);
        if (! is_numeric($clean) || (float) $clean < 0.01 || (float) $clean > 100000) {
            return response()->json(['success' => false, 'message' => 'Price must be a number above 0.'], 422);
        }

        return $this->storePending($skus, '__ebay_price', number_format((float) $clean, 2, '.', ''));
    }

    private function storePending(array $skus, string $field, string $value)
    {
        $copiesUpdated = 0;
        if (Schema::hasTable($this->t('item_specifics'))) {
            foreach ($skus as $sku) {
                $key = $this->skuKey($sku);
                $stored = DB::table($this->t('item_specifics'))->where('sku', $key)->first();
                $decoded = $stored ? json_decode((string) $stored->specifics, true) : [];
                $decoded = is_array($decoded) ? $decoded : [];
                $decoded[$field] = $value;
                $payload = [
                    'specifics' => json_encode($decoded, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ];
                if ($stored) {
                    DB::table($this->t('item_specifics'))->where('sku', $key)->update($payload);
                } else {
                    DB::table($this->t('item_specifics'))->insert($payload + [
                        'sku' => $key,
                        'created_at' => now(),
                    ]);
                }
                $copiesUpdated++;
            }
        }

        return response()->json(['success' => true, 'skus' => count($skus), 'specs_updated' => 0, 'copies_updated' => $copiesUpdated]);
    }

    // ---- Cell colours (text colour and background, like Excel), shared by every user. ----

    /**
     * @return array<string, array<string, array{0: ?string, 1: ?string}>> SKU key => field => [text colour, background]
     */
    private function cellStylesMap(): array
    {
        if (! Schema::hasTable($this->t('cell_styles'))) {
            return [];
        }
        $out = [];
        foreach (DB::table($this->t('cell_styles'))->select(['sku', 'field', 'color', 'bg'])->get() as $row) {
            $out[$this->skuKey((string) $row->sku)][(string) $row->field] = [$row->color, $row->bg];
        }

        return $out;
    }

    /**
     * POST /ebay1-data/cell-styles
     * Sets the text colour and/or background of the given cells. A key that is sent as null/blank clears it
     * (default colour); a key that is not sent stays as it is.
     */
    public function saveCellStyles(Request $request)
    {
        $data = $request->validate([
            'cells' => 'required|array|min:1|max:2000',
            'cells.*.sku' => 'required|string|max:191',
            'cells.*.field' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_]+$/'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'bg' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
        if (! Schema::hasTable($this->t('cell_styles'))) {
            return response()->json(['success' => false, 'message' => 'Run php artisan migrate first (table ebay1_cell_styles is missing).'], 500);
        }
        $setColor = $request->exists('color');
        $setBg = $request->exists('bg');
        if (! $setColor && ! $setBg) {
            return response()->json(['success' => false, 'message' => 'Nothing to change.'], 422);
        }
        $newColor = $setColor && ! empty($data['color']) ? strtolower((string) $data['color']) : null;
        $newBg = $setBg && ! empty($data['bg']) ? strtolower((string) $data['bg']) : null;

        $cells = [];
        foreach ($data['cells'] as $cell) {
            $cells[$this->skuKey((string) $cell['sku']).'|'.$cell['field']] = ['sku' => $this->skuKey((string) $cell['sku']), 'field' => (string) $cell['field']];
        }
        $existing = [];
        foreach (array_chunk(array_values(array_unique(array_column($cells, 'sku'))), 500) as $skuChunk) {
            foreach (DB::table($this->t('cell_styles'))->whereIn('sku', $skuChunk)->get() as $row) {
                $existing[$this->skuKey((string) $row->sku).'|'.$row->field] = $row;
            }
        }

        $now = now();
        $userId = $request->user()?->id;
        $inserts = [];
        $deletes = [];
        $updates = []; // "color|bg" => [ids]
        foreach ($cells as $key => $cell) {
            $row = $existing[$key] ?? null;
            $color = $setColor ? $newColor : ($row->color ?? null);
            $bg = $setBg ? $newBg : ($row->bg ?? null);
            if ($row) {
                if ($color === null && $bg === null) {
                    $deletes[] = $row->id;
                } elseif ($color !== ($row->color ?? null) || $bg !== ($row->bg ?? null)) {
                    $updates[($color ?? '').'|'.($bg ?? '')][] = $row->id;
                }
            } elseif ($color !== null || $bg !== null) {
                $inserts[] = $cell + ['color' => $color, 'bg' => $bg, 'updated_by' => $userId, 'created_at' => $now, 'updated_at' => $now];
            }
        }

        DB::transaction(function () use ($inserts, $deletes, $updates, $userId, $now) {
            foreach (array_chunk($deletes, 500) as $ids) {
                DB::table($this->t('cell_styles'))->whereIn('id', $ids)->delete();
            }
            foreach ($updates as $pair => $ids) {
                [$color, $bg] = explode('|', (string) $pair, 2);
                foreach (array_chunk($ids, 500) as $chunk) {
                    DB::table($this->t('cell_styles'))->whereIn('id', $chunk)->update([
                        'color' => $color !== '' ? $color : null,
                        'bg' => $bg !== '' ? $bg : null,
                        'updated_by' => $userId,
                        'updated_at' => $now,
                    ]);
                }
            }
            foreach (array_chunk($inserts, 300) as $chunk) {
                DB::table($this->t('cell_styles'))->insert($chunk);
            }
        });

        return response()->json(['success' => true, 'cells' => count($cells)]);
    }

    // ---- Change category: every leaf category of the eBay US tree, and a multi-SKU category change. ----

    /**
     * GET /ebay1-data/ebay-categories?q=&refresh=1
     * Searches the eBay category tree (fetched from eBay on first use, then kept in a file).
     */
    public function ebayCategories(Request $request)
    {
        @set_time_limit(300);
        @ini_set('memory_limit', '1024M');
        $result = EbayCategoryTree::search((string) $request->query('q', ''), 200, $request->boolean('refresh'));
        if ($result['total'] === 0 && $result['fetched_at'] === '') {
            return response()->json([
                'success' => false,
                'message' => EbayCategoryTree::$error !== '' ? EbayCategoryTree::$error : 'The eBay categories could not be loaded.',
                'results' => [],
                'total' => 0,
            ], 502);
        }

        return response()->json(['success' => true] + $result);
    }

    /**
     * POST /ebay1-data/change-category
     * Moves the given SKUs to one eBay category: on the live eBay 1 listing when there is one, and in this app
     * (Category Master, so Technical Specifications follows, and the stored eBay 1 pull of each SKU, which is what
     * puts a SKU under a category tab here). A SKU that eBay rejects is left unchanged everywhere.
     */
    public function changeCategory(Request $request)
    {
        $data = $request->validate([
            'skus' => 'required|array|min:1|max:5000',
            'skus.*' => 'required|string|max:191',
            'category_id' => 'required|string|max:64',
            'category_path' => 'nullable|string|max:500',
        ]);
        $categoryId = trim((string) $data['category_id']);
        $path = trim((string) ($data['category_path'] ?? ''));
        $known = EbayCategoryTree::byId($categoryId);
        if ($known === null && $path === '') {
            return response()->json(['success' => false, 'message' => 'That eBay category is not in the eBay category tree.'], 422);
        }
        if ($known !== null) {
            $path = $known['path']; // the path eBay has, whatever the browser sent
        }
        if (mb_strlen($path) > 255) {
            return response()->json(['success' => false, 'message' => 'The category path is longer than 255 characters.'], 422);
        }

        @set_time_limit(300);
        $skus = array_values(array_unique(array_filter(array_map('trim', $data['skus']))));
        $now = now();
        $userId = $request->user()?->id;
        $masters = 0;
        $pulls = 0;

        // eBay 1 first: a SKU with a live eBay 1 listing is moved on eBay (ReviseFixedPriceItem), and only moved
        // in the app when eBay accepted it, so the app and the marketplace never disagree. A SKU with no live
        // eBay 1 listing (Missing L) is changed in the app only.
        $results = [];
        $toSave = [];
        $ebay = null;
        foreach ($skus as $sku) {
            $metric = EbayListingEnded::preferredRow($this->cfg()['metric'], $sku);
            $itemId = trim((string) ($metric->item_id ?? ''));
            $live = $metric !== null
                && $itemId !== ''
                && ! str_starts_with($itemId, 'UNSOLD-')
                && strcasecmp($itemId, $sku) !== 0
                && ! EbayListingEnded::isEnded($metric->listing_status ?? null)
                && ! EbayListingEnded::isNotListed($metric->listing_status ?? null);
            if (! $live) {
                $results[] = ['sku' => $sku, 'ebay' => 'not_live', 'message' => 'No live '.$this->cfg()['label'].' listing: changed in the app only.'];
                $toSave[] = $sku;

                continue;
            }
            try {
                $ebay ??= app($this->cfg()['service']);
                $pushed = $ebay->updateCategory($itemId, $categoryId);
            } catch (\Throwable $e) {
                $pushed = ['success' => false, 'message' => $e->getMessage()];
            }
            if ($pushed['success'] ?? false) {
                $results[] = ['sku' => $sku, 'ebay' => 'pushed', 'item_id' => $itemId, 'message' => (string) ($pushed['message'] ?? 'Changed on eBay.')];
                $toSave[] = $sku;
            } else {
                $results[] = ['sku' => $sku, 'ebay' => 'failed', 'item_id' => $itemId, 'message' => (string) ($pushed['message'] ?? 'eBay rejected the category change.')];
            }
            Log::info('Ebay1 Data change category', [
                'user_id' => $userId, 'sku' => $sku, 'item_id' => $itemId, 'category_id' => $categoryId, 'ok' => (bool) ($pushed['success'] ?? false),
            ]);
        }
        $failed = count(array_filter($results, static fn ($r) => $r['ebay'] === 'failed'));
        $skus = $toSave;
        if ($skus === []) {
            return response()->json([
                'success' => false,
                'message' => 'eBay did not accept the change for any SKU, so nothing was changed in the app.',
                'skus' => 0,
                'failed' => $failed,
                'results' => $results,
                'category_id' => $categoryId,
                'category_path' => $path,
            ], 422);
        }

        DB::transaction(function () use ($skus, $categoryId, $path, $now, $userId, &$masters, &$pulls) {
            if (Schema::hasTable('category_masters') && Schema::hasColumn('category_masters', 'category_ebay')) {
                $hasId = Schema::hasColumn('category_masters', 'category_ebay_id');
                $parents = [];
                if (Schema::hasTable('product_master')) {
                    foreach (array_chunk($skus, 500) as $chunk) {
                        foreach (DB::table('product_master')->whereIn('sku', $chunk)->get(['sku', 'parent']) as $pm) {
                            $parents[$this->skuKey((string) $pm->sku)] = trim((string) ($pm->parent ?? ''));
                        }
                    }
                }
                $existing = [];
                foreach (array_chunk($skus, 500) as $chunk) {
                    foreach (DB::table('category_masters')->whereIn('sku', $chunk)->get() as $row) {
                        $existing[$this->skuKey((string) $row->sku)] = $row;
                    }
                }
                foreach ($skus as $sku) {
                    $key = $this->skuKey($sku);
                    $payload = ['category_ebay' => $path, 'updated_at' => $now];
                    if ($hasId) {
                        $payload['category_ebay_id'] = $categoryId;
                    }
                    if (Schema::hasColumn('category_masters', 'updated_by')) {
                        $payload['updated_by'] = $userId;
                    }
                    if (isset($existing[$key])) {
                        DB::table('category_masters')->where('id', $existing[$key]->id)->update($payload);
                    } else {
                        $parent = $parents[$key] ?? '';
                        DB::table('category_masters')->insert($payload + [
                            'sku' => $sku,
                            'parent' => $parent !== '' ? $parent : null,
                            'created_at' => $now,
                        ]);
                    }
                    $masters++;
                }
            }

            if (Schema::hasTable($this->t('item_specifics'))) {
                $hasId = Schema::hasColumn($this->t('item_specifics'), 'category_id');
                foreach (array_chunk($skus, 500) as $chunk) {
                    $payload = ['category_path' => $path, 'updated_at' => $now];
                    if ($hasId) {
                        $payload['category_id'] = $categoryId;
                    }
                    $pulls += DB::table($this->t('item_specifics'))->whereIn('sku', $chunk)->update($payload);
                }
            }
        });

        return response()->json([
            'success' => true,
            'skus' => count($skus),
            'failed' => $failed,
            'results' => $results,
            'category_master_rows' => $masters,
            'pull_rows' => $pulls,
            'category_id' => $categoryId,
            'category_path' => $path,
        ]);
    }

    // ---- Missing item specifics history: one figure per day, over every category. ----

    /**
     * POST /ebay2-item-data/sync-from-main (and eBay 3)
     * Brings this page in line with the eBay 1 page: item specifics and category of every SKU, and the columns
     * added by hand or filled from Technical Specifications. App only, nothing is sent to eBay. The eBay title,
     * the pending title and price, and the eBay item ID are never copied (each account has its own).
     */
    public function syncFromMain()
    {
        if ($this->cfg()['main']) {
            return response()->json(['success' => false, 'message' => 'This is the main page.'], 422);
        }
        foreach (['item_specifics'] as $name) {
            if (! Schema::hasTable($this->t($name)) || ! Schema::hasTable('ebay1_'.$name)) {
                return response()->json(['success' => false, 'message' => 'Run php artisan migrate first (eBay 2 / eBay 3 data tables are missing).'], 500);
            }
        }
        @set_time_limit(600);
        $own = $this->t('item_specifics');
        $skus = 0;
        $now = now();

        DB::table('ebay1_item_specifics')->orderBy('id')->chunkById(500, function ($chunk) use ($own, $now, &$skus) {
            $mine = DB::table($own)->whereIn('sku', $chunk->pluck('sku')->all())->get()->keyBy('sku');
            foreach ($chunk as $main) {
                $mainSpecs = json_decode((string) $main->specifics, true);
                $mainSpecs = is_array($mainSpecs) ? $mainSpecs : [];
                $row = $mine->get($main->sku);
                $specs = $row ? json_decode((string) $row->specifics, true) : [];
                $specs = is_array($specs) ? $specs : [];
                foreach ($mainSpecs as $name => $value) {
                    if (str_starts_with((string) $name, '__')) {
                        continue;
                    }
                    $target = (string) $name;
                    foreach (array_keys($specs) as $existing) {
                        if ($this->aspectKey((string) $existing) === $this->aspectKey((string) $name)) {
                            $target = (string) $existing;
                            break;
                        }
                    }
                    $specs[$target] = $value;
                }
                $payload = [
                    'category_id' => $main->category_id,
                    'category_path' => $main->category_path,
                    'specifics' => json_encode($specs, JSON_UNESCAPED_UNICODE),
                    'updated_at' => $now,
                ];
                if ($row) {
                    DB::table($own)->where('id', $row->id)->update($payload);
                } else {
                    DB::table($own)->insert($payload + ['sku' => $main->sku, 'item_id' => null, 'pulled_at' => $main->pulled_at, 'created_at' => $now]);
                }
                $skus++;
            }
        });

        $columns = 0;
        foreach ([
            'extra_columns' => ['category_hash', 'name_key'],
            'column_sources' => ['category_hash', 'column_key'],
        ] as $name => $keys) {
            if (! Schema::hasTable($this->t($name)) || ! Schema::hasTable('ebay1_'.$name)) {
                continue;
            }
            foreach (DB::table('ebay1_'.$name)->get() as $main) {
                $has = DB::table($this->t($name))->where($keys[0], $main->{$keys[0]})->where($keys[1], $main->{$keys[1]})->exists();
                if (! $has) {
                    $payload = (array) $main;
                    unset($payload['id']);
                    DB::table($this->t($name))->insert($payload);
                    $columns++;
                }
            }
        }
        Cache::forget($this->cacheKey('column_options'));

        return response()->json(['success' => true, 'skus' => $skus, 'columns' => $columns]);
    }

    /**
     * GET /ebay1-data/missing-history
     * The last 120 days, newest first.
     */
    public function missingHistory()
    {
        if (! Schema::hasTable($this->t('missing_history'))) {
            return response()->json(['success' => true, 'days' => [], 'message' => 'Run php artisan migrate to start the history.']);
        }
        $days = DB::table($this->t('missing_history'))
            ->orderByDesc('snapshot_date')
            ->limit(120)
            ->get(['snapshot_date', 'total_missing', 'total_cells', 'categories_count', 'categories', 'updated_at'])
            ->map(static fn ($row) => [
                'date' => (string) $row->snapshot_date,
                'missing' => (int) $row->total_missing,
                'cells' => (int) $row->total_cells,
                'categories_count' => (int) $row->categories_count,
                'categories' => json_decode((string) $row->categories, true) ?: [],
                'updated_at' => (string) $row->updated_at,
            ])
            ->values();

        return response()->json(['success' => true, 'days' => $days]);
    }

    /**
     * POST /ebay1-data/missing-history
     * Saves today's figure (the latest one sent today replaces the earlier one).
     */
    public function saveMissingHistory(Request $request)
    {
        $data = $request->validate([
            'missing' => 'required|integer|min:0|max:100000000',
            'cells' => 'required|integer|min:0|max:100000000',
            'categories' => 'nullable|array|max:500',
            'categories.*.name' => 'required|string|max:255',
            'categories.*.missing' => 'required|integer|min:0',
            'categories.*.cells' => 'nullable|integer|min:0',
        ]);
        if (! Schema::hasTable($this->t('missing_history'))) {
            return response()->json(['success' => false, 'message' => 'Run php artisan migrate first (table ebay1_missing_history is missing).'], 500);
        }
        $categories = array_values(array_map(static fn ($c) => [
            'name' => (string) $c['name'],
            'missing' => (int) $c['missing'],
            'cells' => (int) ($c['cells'] ?? 0),
        ], (array) ($data['categories'] ?? [])));
        $today = now()->toDateString();
        $payload = [
            'total_missing' => (int) $data['missing'],
            'total_cells' => (int) $data['cells'],
            'categories_count' => count($categories),
            'categories' => json_encode($categories),
            'updated_at' => now(),
        ];
        if (DB::table($this->t('missing_history'))->where('snapshot_date', $today)->exists()) {
            DB::table($this->t('missing_history'))->where('snapshot_date', $today)->update($payload);
        } else {
            DB::table($this->t('missing_history'))->insert($payload + ['snapshot_date' => $today, 'created_at' => now()]);
        }

        return response()->json(['success' => true, 'date' => $today]);
    }

    // ---- Auto Push: the on/off switch and the item specifics edited by hand that are not on eBay yet. ----

    private function autoPushEnabled(): bool
    {
        if (! Schema::hasTable($this->t('settings'))) {
            return false;
        }

        return (string) DB::table($this->t('settings'))->where('key', 'auto_push')->value('value') === '1';
    }

    /**
     * POST /ebay1-data/auto-push
     */
    public function setAutoPush(Request $request)
    {
        $data = $request->validate(['enabled' => 'required|boolean']);
        if (! Schema::hasTable($this->t('settings'))) {
            return response()->json(['success' => false, 'message' => 'Run php artisan migrate first (table ebay1_settings is missing).'], 500);
        }
        $enabled = (bool) $data['enabled'];
        $exists = DB::table($this->t('settings'))->where('key', 'auto_push')->exists();
        if ($exists) {
            DB::table($this->t('settings'))->where('key', 'auto_push')->update(['value' => $enabled ? '1' : '0', 'updated_at' => now()]);
        } else {
            DB::table($this->t('settings'))->insert(['key' => 'auto_push', 'value' => $enabled ? '1' : '0', 'created_at' => now(), 'updated_at' => now()]);
        }

        return response()->json(['success' => true, 'enabled' => $enabled]);
    }

    /**
     * @return array<string, array<string, string>> SKU key => [aspect key => column name]
     */
    private function pendingSpecificsBySku(): array
    {
        if (! Schema::hasTable($this->t('pending_specifics'))) {
            return [];
        }
        $out = [];
        foreach (DB::table($this->t('pending_specifics'))->select(['sku', 'name_key', 'name'])->get() as $row) {
            $out[$this->skuKey((string) $row->sku)][(string) $row->name_key] = (string) $row->name;
        }

        return $out;
    }

    private function markPendingSpecific(string $sku, string $name): void
    {
        $key = $this->aspectKey($name);
        if ($key === '' || ! Schema::hasTable($this->t('pending_specifics'))) {
            return;
        }
        $where = ['sku' => $this->skuKey($sku), 'name_key' => $key];
        if (DB::table($this->t('pending_specifics'))->where($where)->exists()) {
            DB::table($this->t('pending_specifics'))->where($where)->update(['name' => $name, 'updated_at' => now()]);
        } else {
            DB::table($this->t('pending_specifics'))->insert($where + ['name' => $name, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /**
     * @param  list<string>  $names
     */
    private function clearPendingSpecifics(string $sku, array $names): void
    {
        if ($names === [] || ! Schema::hasTable($this->t('pending_specifics'))) {
            return;
        }
        $keys = array_values(array_filter(array_map(fn ($n) => $this->aspectKey((string) $n), $names)));
        if ($keys !== []) {
            DB::table($this->t('pending_specifics'))->where('sku', $this->skuKey($sku))->whereIn('name_key', $keys)->delete();
        }
    }

    private function clearPending(string $sku, string $field): void
    {
        if (! Schema::hasTable($this->t('item_specifics'))) {
            return;
        }
        $key = $this->skuKey($sku);
        $stored = DB::table($this->t('item_specifics'))->where('sku', $key)->first();
        if (! $stored) {
            return;
        }
        $decoded = json_decode((string) $stored->specifics, true);
        if (! is_array($decoded) || ! array_key_exists($field, $decoded)) {
            return;
        }
        unset($decoded[$field]);
        DB::table($this->t('item_specifics'))->where('sku', $key)->update([
            'specifics' => json_encode($decoded, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    private function clearPendingTitle(string $sku): void
    {
        $this->clearPending($sku, '__ebay_title');
    }

    private function clearPendingPrice(string $sku): void
    {
        $this->clearPending($sku, '__ebay_price');
    }

    private function skuKey(string $sku): string
    {
        return strtoupper(trim(str_replace("\u{00a0}", ' ', $sku)));
    }

    private function num(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
