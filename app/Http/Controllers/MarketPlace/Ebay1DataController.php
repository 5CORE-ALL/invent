<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Services\CategoryMasters\CategoryChannelSyncService;
use App\Services\EbayApiService;
use App\Services\Support\EbayTradingReviseItem;
use App\Support\Marketplace\AutomatedListingPage;
use Illuminate\Http\Request;
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

            $data = [];
            foreach ($listing as $item) {
                $sku = trim((string) ($item->sku ?? ''));
                if ($sku === '' || stripos($sku, 'PARENT ') === 0) {
                    continue;
                }
                $key = $this->skuKey($sku);
                $metric = $metrics[$key] ?? [];
                $live = $pulled[$key] ?? null;
                // The live eBay category of a pulled listing wins over the sheet categories.
                $category = ($live['category_path'] ?? '') !== '' ? $live['category_path'] : ($categories[$key] ?? '');

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
                    'specifics' => $live['specifics'] ?? [],
                ];
            }

            return response()->json([
                'status' => 200,
                'data' => $data,
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
     * POST /ebay1-data/pull-specifics
     * One-time pull: reads the live eBay 1 listing of each SKU and stores its category and item specifics.
     * SKUs that were pulled before are skipped, so running it again never overwrites the stored data.
     */
    public function pullSpecifics(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1|max:20',
            'items.*.sku' => 'required|string|max:191',
            'items.*.item_id' => 'required|string|max:64',
        ]);
        if (! Schema::hasTable('ebay1_item_specifics')) {
            return response()->json(['success' => false, 'message' => 'Run php artisan migrate first (table ebay1_item_specifics is missing).'], 500);
        }
        @set_time_limit(300);

        $service = app(EbayApiService::class);
        $results = [];
        foreach ($validated['items'] as $entry) {
            $sku = trim((string) $entry['sku']);
            $itemId = trim((string) $entry['item_id']);
            $key = $this->skuKey($sku);
            if (! preg_match('/^\d{9,15}$/', $itemId)) {
                $results[] = ['sku' => $sku, 'status' => 'failed', 'message' => 'No eBay item ID.'];

                continue;
            }
            if (DB::table('ebay1_item_specifics')->where('sku', $key)->exists()) {
                $results[] = ['sku' => $sku, 'status' => 'skipped', 'message' => 'Already pulled.'];

                continue;
            }

            try {
                $item = $service->getItem($itemId);
            } catch (\Throwable $e) {
                $results[] = ['sku' => $sku, 'status' => 'failed', 'message' => $e->getMessage()];

                continue;
            }
            if (! is_array($item)) {
                $why = trim((string) ($service->lastGetItemError ?? ''));
                $results[] = ['sku' => $sku, 'status' => 'failed', 'message' => $why !== '' ? $why : 'eBay did not return the listing.'];

                continue;
            }

            $node = is_array($item['Item'] ?? null) ? $item['Item'] : $item;
            $specifics = [];
            foreach (EbayTradingReviseItem::normalizeItemSpecificsNameValueLists($node['ItemSpecifics'] ?? null) as $row) {
                $name = trim((string) ($row['name'] ?? ''));
                $value = trim(implode(', ', (array) ($row['values'] ?? [])));
                if ($name !== '' && $value !== '' && ! array_key_exists($name, $specifics)) {
                    $specifics[$name] = $value;
                }
            }
            $category = $node['PrimaryCategory'] ?? [];
            $categoryId = is_array($category) ? trim((string) ($category['CategoryID'] ?? '')) : '';
            $categoryPath = is_array($category)
                ? CategoryChannelSyncService::normalizePath((string) ($category['CategoryName'] ?? ''), ':')
                : '';
            if ($categoryPath === '' && $categoryId !== '') {
                $categoryPath = 'eBay category '.$categoryId;
            }

            DB::table('ebay1_item_specifics')->insert([
                'sku' => $key,
                'item_id' => $itemId,
                'category_id' => $categoryId !== '' ? $categoryId : null,
                'category_path' => $categoryPath !== '' ? $categoryPath : null,
                'specifics' => json_encode($specifics, JSON_UNESCAPED_UNICODE),
                'pulled_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $count = count($specifics);
            $results[] = ['sku' => $sku, 'status' => 'pulled', 'message' => $count.' item specific'.($count === 1 ? '' : 's').'.'];
        }

        return response()->json(['success' => true, 'results' => $results]);
    }

    /**
     * POST /ebay1-data/delete-specific
     * Removes one item specific from the live eBay 1 listing of each SKU, then from the stored copy.
     * The stored copy is only changed for SKUs where eBay accepted the change (or never had it), so a failed SKU can be retried.
     */
    public function deleteSpecific(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'items' => 'required|array|min:1|max:20',
            'items.*.sku' => 'required|string|max:191',
            'items.*.item_id' => 'required|string|max:64',
        ]);
        if (! Schema::hasTable('ebay1_item_specifics')) {
            return response()->json(['success' => false, 'message' => 'Run php artisan migrate first (table ebay1_item_specifics is missing).'], 500);
        }
        @set_time_limit(300);

        $name = trim((string) $validated['name']);
        $wanted = $this->aspectKey($name);
        $service = app(EbayApiService::class);
        $results = [];
        foreach ($validated['items'] as $entry) {
            $sku = trim((string) $entry['sku']);
            $itemId = trim((string) $entry['item_id']);
            $key = $this->skuKey($sku);
            if (! preg_match('/^\d{9,15}$/', $itemId)) {
                $results[] = ['sku' => $sku, 'status' => 'failed', 'message' => 'No eBay item ID.'];

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
                $result = EbayTradingReviseItem::removeItemSpecific(
                    $ctx['endpoint'],
                    $ctx['compatLevel'],
                    $ctx['devId'],
                    $ctx['appId'],
                    $ctx['certId'],
                    $ctx['siteId'],
                    $ctx['authToken'],
                    $item,
                    $name
                );
            } catch (\Throwable $e) {
                Log::warning('Ebay1Data: delete specific failed', ['sku' => $sku, 'name' => $name, 'error' => $e->getMessage()]);
                $results[] = ['sku' => $sku, 'status' => 'failed', 'message' => $e->getMessage()];

                continue;
            }
            if (! ($result['success'] ?? false)) {
                $results[] = ['sku' => $sku, 'status' => 'failed', 'message' => (string) ($result['message'] ?? 'eBay rejected the change.')];

                continue;
            }

            $stored = DB::table('ebay1_item_specifics')->where('sku', $key)->first();
            if ($stored) {
                $decoded = json_decode((string) $stored->specifics, true);
                $decoded = is_array($decoded) ? $decoded : [];
                foreach (array_keys($decoded) as $storedName) {
                    if ($this->aspectKey((string) $storedName) === $wanted) {
                        unset($decoded[$storedName]);
                    }
                }
                DB::table('ebay1_item_specifics')->where('sku', $key)->update([
                    'specifics' => json_encode($decoded, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
            }
            $results[] = [
                'sku' => $sku,
                'status' => ($result['removed'] ?? false) ? 'deleted' : 'absent',
                'message' => ($result['removed'] ?? false) ? 'Removed from eBay.' : 'Was not on the eBay listing.',
            ];
        }

        return response()->json(['success' => true, 'results' => $results]);
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
