<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProductMaster\TechnicalSpecificationsController;
use App\Models\EbayDataView;
use App\Models\ProductMaster;
use App\Services\EbayApiService;
use App\Services\Support\EbayTradingReviseItem;
use App\Support\Marketplace\AutomatedListingPage;
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
    public function index(Request $request)
    {
        return view('market-places.ebay1-data', [
            'mode' => $request->query('mode', ''),
            'demo' => $request->query('demo', ''),
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

            $listing = AutomatedListingPage::rows('ebay', false, false);
            $metrics = $this->metricsBySku();
            $pulled = $this->pulledSpecificsBySku();
            $categories = $this->categoriesBySku($listing);
            $extras = $this->extraColumnsByCategory();
            $sources = $this->columnSourcesByCategory();
            $techSpecs = ($extras === [] && $sources === []) ? [] : $this->technicalSpecValuesBySku();

            // UPC column: the code held in CP Master (product_master), as on Technical Specifications.
            $cpUpc = ProductUpc::forSkus($listing->map(static fn ($item) => trim((string) ($item->sku ?? '')))->filter()->values()->all());

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

                // Brand is always 5 Core, as on Technical Specifications.
                foreach (array_keys($specifics) as $specName) {
                    if ($this->aspectKey((string) $specName) === 'brand') {
                        $specifics[$specName] = '5 Core';
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

                $data[] = [
                    'parent' => (string) ($item->parent ?? ''),
                    'sku' => $sku,
                    'inv' => (float) ($item->INV ?? 0),
                    'sold_l30' => (float) ($item->L30 ?? 0),
                    'nr_req' => (string) ($item->nr_req ?? 'REQ'),
                    'listed' => (string) ($item->listed ?? 'Pending'),
                    'item_id' => $itemId,
                    'buyer_link' => (string) ($item->buyer_link ?? ($metric['ebay_link'] ?? '')),
                    'ebay_title' => (string) ($metric['ebay_title'] ?? ''),
                    'ebay_price' => $this->num($metric['ebay_price'] ?? null),
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

            return response()->json([
                'status' => 200,
                'data' => $data,
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
        if (! Schema::hasTable('ebay1_item_specifics')) {
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
        if (array_key_exists('category', $validated) && Schema::hasTable('ebay1_extra_columns')) {
            $hash = $this->categoryHash((string) $validated['category']);
            DB::table('ebay1_extra_columns')->where('category_hash', $hash)->whereIn('name_key', $wantedKeys)->delete();
            if (Schema::hasTable('ebay1_column_sources')) {
                DB::table('ebay1_column_sources')->where('category_hash', $hash)->whereIn('column_key', $wantedKeys)->delete();
            }
            Cache::forget('ebay1data.column_options');
        }
        $validated['items'] = $validated['items'] ?? [];
        $service = app(EbayApiService::class);
        $results = [];

        // The app's own copy of the item specifics of a SKU loses the columns.
        $dropStored = function (string $key) use ($wantedKeys): void {
            $stored = DB::table('ebay1_item_specifics')->where('sku', $key)->first();
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
            DB::table('ebay1_item_specifics')->where('sku', $key)->update([
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
        $names = Cache::remember('ebay1data.column_options', 600, function () {
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
            if (Schema::hasTable('ebay1_item_specifics')) {
                foreach (DB::table('ebay1_item_specifics')->pluck('specifics') as $json) {
                    $decoded = json_decode((string) $json, true);
                    foreach (is_array($decoded) ? array_keys($decoded) : [] as $name) {
                        $found[] = (string) $name;
                    }
                }
            }
            if (Schema::hasTable('ebay1_extra_columns')) {
                foreach (DB::table('ebay1_extra_columns')->pluck('name') as $name) {
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
        if (! Schema::hasTable('ebay1_extra_columns')) {
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
            $existing = DB::table('ebay1_extra_columns')->where('category_hash', $hash)->where('name_key', $key)->first();
            if ($existing) {
                $names[$key] = (string) $existing->name;
            } else {
                DB::table('ebay1_extra_columns')->insert([
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
        Cache::forget('ebay1data.column_options');

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
            'items.*.item_id' => 'required|string|max:64',
            'items.*.specifics' => 'nullable|array|max:300',
        ]);
        @set_time_limit(300);

        $service = app(EbayApiService::class);
        $results = [];
        foreach ($validated['items'] as $entry) {
            $sku = trim((string) $entry['sku']);
            $itemId = trim((string) $entry['item_id']);
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
            if ($overrides === []) {
                $results[] = ['sku' => $sku, 'status' => 'skipped', 'message' => 'No values to send.'];

                continue;
            }

            try {
                $item = $service->getItem($itemId);
                if (! is_array($item)) {
                    $why = trim((string) ($service->lastGetItemError ?? ''));
                    $results[] = ['sku' => $sku, 'status' => 'failed', 'message' => $why !== '' ? $why : 'eBay did not return the listing.'];

                    continue;
                }
                $ctx = $service->tradingReviseContext();
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
            } catch (\Throwable $e) {
                Log::warning('Ebay1Data: push failed', ['sku' => $sku, 'error' => $e->getMessage()]);
                $results[] = ['sku' => $sku, 'status' => 'failed', 'message' => $e->getMessage()];

                continue;
            }

            if (! ($result['success'] ?? false)) {
                $results[] = ['sku' => $sku, 'status' => 'failed', 'message' => (string) ($result['message'] ?? 'eBay rejected the change.')];

                continue;
            }

            // The stored copy follows eBay once eBay accepted the values.
            if (Schema::hasTable('ebay1_item_specifics')) {
                $stored = DB::table('ebay1_item_specifics')->where('sku', $this->skuKey($sku))->first();
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
                    DB::table('ebay1_item_specifics')->where('sku', $this->skuKey($sku))->update([
                        'specifics' => json_encode($decoded, JSON_UNESCAPED_UNICODE),
                        'updated_at' => now(),
                    ]);
                }
            }

            $message = (string) ($result['message'] ?? '');
            $unchanged = stripos($message, 'No values changed') === 0;
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

        $record = EbayDataView::where('sku', $sku)->first();
        $existing = $record ? $record->value : [];
        if (! is_array($existing)) {
            $existing = json_decode((string) $existing, true) ?: [];
        }
        $existing['NRL'] = $isNr ? 'NRL' : 'REQ';

        EbayDataView::updateOrCreate(['sku' => $sku], ['value' => $existing]);

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
        if (! Schema::hasTable('ebay1_column_sources')) {
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

        $query = DB::table('ebay1_column_sources')->where('category_hash', $hash)->where('column_key', $columnKey);
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
            DB::table('ebay1_column_sources')->where('category_hash', $hash)->where('column_key', $columnKey)->update($payload);
        } else {
            DB::table('ebay1_column_sources')->insert($payload + [
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
        if (Schema::hasTable('ebay1_item_specifics')) {
            foreach ($skus as $sku) {
                $stored = DB::table('ebay1_item_specifics')->where('sku', $this->skuKey($sku))->first();
                if (! $stored) {
                    continue;
                }
                $decoded = json_decode((string) $stored->specifics, true);
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
                    DB::table('ebay1_item_specifics')->where('sku', $this->skuKey($sku))->update([
                        'specifics' => json_encode($decoded, JSON_UNESCAPED_UNICODE),
                        'updated_at' => now(),
                    ]);
                    $copiesUpdated++;
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
        if (! Schema::hasTable('ebay1_column_groups')) {
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
            DB::table('ebay1_column_groups')->delete();
            $order = 0;
            foreach ($groups as $group) {
                DB::table('ebay1_column_groups')->insert([
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
        if (! Schema::hasTable('ebay1_column_groups')) {
            return [];
        }
        $out = [];
        foreach (DB::table('ebay1_column_groups')->orderBy('sort_order')->orderBy('id')->get(['name', 'columns']) as $row) {
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
        if (! Schema::hasTable('ebay1_column_sources')) {
            return [];
        }
        $out = [];
        foreach (DB::table('ebay1_column_sources')->get(['category_hash', 'column_key', 'column_name', 'source_name', 'source_key']) as $row) {
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
        if (! Schema::hasTable('ebay1_column_sources')) {
            return [];
        }

        return DB::table('ebay1_column_sources')->get(['category', 'column_name', 'source_name'])
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
        if (! Schema::hasTable('ebay1_extra_columns')) {
            return [];
        }
        $out = [];
        foreach (DB::table('ebay1_extra_columns')->orderBy('id')->get(['category_hash', 'name', 'name_key']) as $row) {
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
        if (! Schema::hasTable('ebay1_item_specifics')) {
            return [];
        }
        $out = [];
        foreach (DB::table('ebay1_item_specifics')->select(['sku', 'category_path', 'specifics'])->get() as $row) {
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
        if (! Schema::hasTable('ebay_metrics')) {
            return [];
        }
        $wanted = [
            'sku', 'item_id', 'ebay_title', 'ebay_link', 'ebay_price', 'ebay_stock', 'ebay_l7', 'ebay_l30', 'ebay_l60',
            'views', 'l7_views', 'l1_views', 'organic_clicks', 'listing_status', 'inactive_reason', 'report_date',
        ];
        $columns = array_values(array_filter($wanted, static fn ($column) => Schema::hasColumn('ebay_metrics', $column)));
        if (! in_array('sku', $columns, true)) {
            return [];
        }

        $out = [];
        DB::table('ebay_metrics')
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
