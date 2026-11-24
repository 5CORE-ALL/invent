<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ShopifySku;
use App\Services\Ebay2ApiService;
use App\Services\MarketplaceManager\EbayPortalListingStatusSync;
use App\Services\EbayApiService;
use App\Services\EbayThreeApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Listings that exist on eBay (they have an item id) but are not Active, with the reason eBay gave,
 * and a way to correct quantity, price or relist them from the page.
 */
class EbayListingErrorsController extends Controller
{
    private const CHANNELS = [
        'ebay1' => ['table' => 'ebay_metrics', 'label' => 'eBay 1', 'service' => EbayApiService::class],
        'ebay2' => ['table' => 'ebay_2_metrics', 'label' => 'eBay 2', 'service' => Ebay2ApiService::class],
        'ebay3' => ['table' => 'ebay_3_metrics', 'label' => 'eBay 3', 'service' => EbayThreeApiService::class],
    ];

    public function index(): View
    {
        return view('market-places.ebay-listing-errors');
    }

    /** Listed (has an item id) but not Active. */
    public function data(Request $request): JsonResponse
    {
        $channel = $this->channel($request->query('channel'));
        $table = self::CHANNELS[$channel]['table'];
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'listing_status')) {
            return response()->json(['success' => true, 'rows' => [], 'message' => $table.' has no listing status.']);
        }

        $cols = ['id', 'sku', 'item_id', 'listing_status'];
        foreach (['ebay_title', 'ebay_link', 'ebay_price', 'ebay_stock', 'inactive_reason', 'image_urls'] as $col) {
            if (Schema::hasColumn($table, $col)) {
                $cols[] = $col;
            }
        }

        // Listings of CP Master SKUs that are on eBay (they have an item id) and have an error, active or not.
        $catalog = $this->catalog();
        $skuByItem = $this->skuByItemId($channel);
        $inv = $this->invBySku();
        $raw = DB::table($table)->select($cols)
            ->whereRaw("UPPER(TRIM(COALESCE(listing_status, ''))) IN ('ACTIVE', 'INACTIVE', 'ENDED', 'UNSOLD', 'COMPLETED', 'SOLD')")
            ->orderBy('sku')
            ->limit(20000)
            ->get();

        // Real SKU per item, from any row of the table, for rows that only carry the item id as SKU.
        $realByItem = [];
        foreach (DB::table($table)->select('item_id', 'sku')->whereNotNull('sku')->get() as $r) {
            $sku = trim((string) $r->sku);
            $id = trim((string) $r->item_id);
            if ($id !== '' && $sku !== '' && ! ctype_digit($sku) && stripos($sku, 'PARENT') === false) {
                $realByItem[$id] = $sku;
            }
        }

        $rowsPerItem = [];
        foreach (DB::table($table)->selectRaw('item_id, COUNT(*) AS n')->groupBy('item_id')->get() as $c) {
            $rowsPerItem[trim((string) $c->item_id)] = (int) $c->n;
        }

        $rows = [];
        $activeNorms = [];
        foreach ($raw as $row) {
            $status = strtoupper(trim((string) $row->listing_status));
            $itemId = trim((string) $row->item_id);
            if (! ctype_digit($itemId)) {
                continue; // not a real eBay listing (UNSOLD-… placeholders, blanks)
            }
            $ebaySku = trim((string) $row->sku);
            if (stripos($ebaySku, 'PARENT') !== false) {
                continue;
            }
            $hasSku = $ebaySku !== '' && ! ctype_digit($ebaySku);
            // A variation row keeps its own SKU; only rows that carry just the item id borrow a SKU.
            $display = $hasSku ? $ebaySku : ($skuByItem[$itemId] ?? $realByItem[$itemId] ?? $ebaySku);
            $norm = ShopifySku::normalizeSkuForShopifyLookup($display);
            if (! isset($catalog['name'][$norm])) {
                continue; // not a CP Master SKU
            }
            $display = $catalog['name'][$norm];
            $parent = $catalog['parent'][$norm] ?? '';
            if ($status === 'ACTIVE') {
                $activeNorms[$norm] = true;
            }
            $title = trim((string) ($row->ebay_title ?? ''));
            $storedImage = property_exists($row, 'image_urls') ? $row->image_urls : null;
            // The listing photo belongs to the whole item, so it is only a fallback when the item has a single SKU.
            $image = $catalog['image'][$norm] ?? (($rowsPerItem[$itemId] ?? 0) > 1 ? '' : $this->firstImage($storedImage));
            $stock = isset($row->ebay_stock) ? (int) $row->ebay_stock : null;
            $cpInv = $inv[$norm] ?? null;

            // Each problem carries a code so the Fix button can offer the matching action.
            $issues = [];
            if ($status !== 'ACTIVE') {
                $issues[] = ['code' => 'inactive', 'text' => trim((string) ($row->inactive_reason ?? '')) ?: 'Listed, but not active ('.$status.').'];
            }
            if ($issues === []) {
                continue;
            }

            $rows[] = [
                'id' => (int) $row->id,
                'sku' => $display,
                'parent' => $parent,
                'image' => $image,
                'item_id' => $itemId,
                'title' => $title,
                'link' => trim((string) ($row->ebay_link ?? '')),
                'status' => $status,
                'stock' => $stock,
                'inv' => $cpInv,
                'price' => isset($row->ebay_price) && $row->ebay_price !== null ? (float) $row->ebay_price : null,
                'issues' => $issues,
                'codes' => array_values(array_unique(array_column($issues, 'code'))),
                'error' => implode(' • ', array_column($issues, 'text')),
            ];
        }

        $added = $this->addLiveUnsold($channel, $rows, $catalog, $activeNorms, $inv);
        $rows = $added['rows'];

        return response()->json([
            'success' => true, 'channel' => $channel, 'rows' => $rows, 'count' => count($rows), 'hidden' => $added['hidden'],
        ]);
    }

    /** CP Master inventory by normalized SKU (shopify_skus.inv). @return array<string, int> */
    private function invBySku(): array
    {
        $out = [];
        if (! Schema::hasTable('shopify_skus') || ! Schema::hasColumn('shopify_skus', 'inv')) {
            return $out;
        }
        foreach (DB::table('shopify_skus')->select('sku', 'inv')->get() as $r) {
            $key = ShopifySku::normalizeSkuForShopifyLookup((string) $r->sku);
            if ($key !== '' && is_numeric($r->inv)) {
                $out[$key] = (int) $r->inv;
            }
        }

        return $out;
    }

    /** Pull Active / Unsold from eBay and store the statuses, then the page reloads. */
    public function refresh(Request $request): JsonResponse
    {
        @set_time_limit(300);
        $channel = $this->channel($request->input('channel'));
        $store = ['ebay1' => 1, 'ebay2' => 2, 'ebay3' => 3][$channel];
        $result = app(EbayPortalListingStatusSync::class)->sync($store);
        $live = ($result['ok'] ?? false) ? count($this->liveUnsold($channel, true)) : 0;

        $message = ($result['ok'] ?? false)
            ? 'eBay '.substr($channel, 4).': '.($result['inactive'] ?? 0).' inactive, '.($result['active'] ?? 0).' active, '.$live.' ended listing(s) read live from eBay.'
            : (string) ($result['error'] ?? 'eBay did not return the listing list.');

        return response()->json([
            'success' => (bool) ($result['ok'] ?? false),
            'message' => $message,
            'inactive' => (int) ($result['inactive'] ?? 0),
        ]);
    }

    /** Ask eBay (GetItem) what is wrong with this listing and store the answer. */
    public function check(Request $request): JsonResponse
    {
        $data = $request->validate(['channel' => 'required|string', 'item_id' => 'required|string|max:40', 'sku' => 'nullable|string|max:191']);
        $channel = $this->channel($data['channel']);
        $service = app(self::CHANNELS[$channel]['service']);
        $item = $service->getItem(trim($data['item_id']));

        if (! is_array($item)) {
            $why = trim((string) ($service->lastGetItemError ?? ''));

            return response()->json(['success' => false, 'message' => $why !== '' ? $why : 'eBay did not return this listing.']);
        }

        $node = is_array($item['Item'] ?? null) ? $item['Item'] : $item;
        $status = strtoupper(trim((string) ($this->text($node['SellingStatus']['ListingStatus'] ?? '') ?: '')));
        $ending = trim((string) $this->text($node['ListingDetails']['EndingReason'] ?? ''));
        $qty = $this->text($node['Quantity'] ?? '');
        $sold = $this->text($node['SellingStatus']['QuantitySold'] ?? '');
        $policy = trim((string) $this->text($node['ItemPolicyViolation']['PolicyText'] ?? ''));
        if ($policy !== '') {
            $reason = $policy;
        } elseif ($ending === 'ProductDeleted') {
            $reason = 'eBay removed this listing because its catalog product was deleted.';
        } elseif (strtolower((string) $this->text($node['SellingStatus']['AdminEnded'] ?? '')) === 'true') {
            $reason = 'eBay ended this listing (administratively). Open it in Seller Hub > Inactive to see why.';
        } else {
            $parts = array_filter([
                $status !== '' ? 'eBay status: '.$status : '',
                $ending !== '' ? 'Ending reason: '.$ending : '',
                is_numeric($qty) ? 'Quantity '.(int) $qty.(is_numeric($sold) ? ', sold '.(int) $sold : '') : '',
            ]);
            $reason = $parts !== [] ? implode('. ', $parts).'.' : 'eBay returned the listing with no error text.';
        }
        $mapped = $status === 'ACTIVE' ? 'ACTIVE' : ($status !== '' ? $status : null);
        $this->store($channel, trim($data['item_id']), trim((string) ($data['sku'] ?? '')), $mapped, $status === 'ACTIVE' ? null : $reason);

        return response()->json(['success' => true, 'status' => $mapped ?: $status, 'error' => $status === 'ACTIVE' ? '' : $reason, 'message' => $reason]);
    }

    /** Push a manual correction: quantity, price, or relist the ended listing. */
    public function push(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel' => 'required|string',
            'item_id' => 'required|string|max:40',
            'sku' => 'nullable|string|max:191',
            'action' => 'required|in:qty,price,relist',
            'quantity' => 'nullable|integer|min:0|max:99999',
            'price' => 'nullable|numeric|min:0.01|max:99999',
        ]);
        @set_time_limit(120);
        $channel = $this->channel($data['channel']);
        $itemId = trim($data['item_id']);
        $sku = trim((string) ($data['sku'] ?? ''));
        $service = app(self::CHANNELS[$channel]['service']);
        if ($data['action'] === 'relist') {
            $result = $channel === 'ebay2'
                ? $service->relistFixedPriceItem($itemId, $sku !== '' ? $sku : null, isset($data['quantity']) ? (int) $data['quantity'] : null)
                : $this->relist($service, $itemId, $sku, isset($data['quantity']) ? (int) $data['quantity'] : null);
            if (! empty($result['success']) && ! empty($result['item_id']) && $result['item_id'] !== $itemId) {
                $this->store($channel, $itemId, $sku, 'ACTIVE', null, (string) $result['item_id']);
            }

            return response()->json(['success' => (bool) ($result['success'] ?? false), 'message' => (string) ($result['message'] ?? 'Relist failed.'), 'item_id' => $result['item_id'] ?? $itemId]);
        }

        $qty = array_key_exists('quantity', $data) && $data['quantity'] !== null ? (int) $data['quantity'] : null;
        $price = $data['action'] === 'price' ? (float) ($data['price'] ?? 0) : null;
        if ($data['action'] === 'qty' && $qty === null) {
            return response()->json(['success' => false, 'message' => 'Enter the quantity to push.'], 422);
        }
        if ($data['action'] === 'price' && ($price === null || $price <= 0)) {
            return response()->json(['success' => false, 'message' => 'Enter the price to push.'], 422);
        }
        // ReviseInventoryStatus needs a quantity; a price-only fix keeps the quantity already on the row.
        if ($qty === null) {
            $qty = $this->currentQty($channel, $itemId, $sku);
        }
        $result = $service->reviseInventoryStatus($itemId, max(0, (int) $qty), $sku !== '' ? $sku : null, $price);
        if (! empty($result['success'])) {
            $note = $data['action'] === 'price'
                ? 'Price pushed: $'.number_format($price, 2).'.'
                : 'Quantity pushed: '.$qty.'.';
            $this->store($channel, $itemId, $sku, null, $note);
        }

        return response()->json(['success' => (bool) ($result['success'] ?? false), 'message' => (string) ($result['message'] ?? ($result['success'] ?? false ? 'Pushed.' : 'eBay refused the update.'))]);
    }

    /**
     * CP Master SKU (the name) and its first photo, keyed by the normalized SKU.
     *
     * @return array{name: array<string, string>, image: array<string, string>, parent: array<string, string>}
     */
    private function catalog(): array
    {
        $name = [];
        $image = [];
        $parent = [];
        if (Schema::hasTable('product_master')) {
            $select = ['sku', 'Values'];
            if (Schema::hasColumn('product_master', 'parent')) {
                $select[] = 'parent';
            }
            foreach (['image1', 'main_image'] as $col) {
                if (Schema::hasColumn('product_master', $col)) {
                    $select[] = $col;
                }
            }
            foreach (DB::table('product_master')->whereNull('deleted_at')->whereNotNull('sku')->select($select)->get() as $p) {
                // Only the SKUs CP Master lists (status Active, DC or Coming).
                $values = is_string($p->Values ?? null) ? (json_decode($p->Values, true) ?: []) : (array) ($p->Values ?? []);
                if (! in_array(strtolower(trim((string) ($values['status'] ?? ''))), ['active', 'dc', 'upcoming'], true)) {
                    continue;
                }
                $sku = trim((string) $p->sku);
                $key = ShopifySku::normalizeSkuForShopifyLookup($sku);
                if ($key === '' || stripos($sku, 'PARENT') !== false) {
                    continue;
                }
                $name[$key] = $sku;
                $parent[$key] = trim((string) ($p->parent ?? ''));
                $photo = trim((string) ($p->image1 ?? ''));
                if ($photo === '') {
                    $photo = trim((string) ($p->main_image ?? ''));
                }
                if ($photo !== '') {
                    $image[$key] = $photo;
                }
            }
        }
        if (Schema::hasTable('shopify_skus') && Schema::hasColumn('shopify_skus', 'image_src')) {
            foreach (DB::table('shopify_skus')->whereNotNull('image_src')->select('sku', 'image_src')->get() as $s) {
                $key = ShopifySku::normalizeSkuForShopifyLookup((string) $s->sku);
                $src = trim((string) $s->image_src);
                if ($key !== '' && $src !== '' && ! isset($image[$key])) {
                    $image[$key] = $src;
                }
            }
        }

        return ['name' => $name, 'image' => $image, 'parent' => $parent];
    }

    /** Real SKU saved on the campaign ad for an item whose metric row only has the item id. @return array<string, string> */
    private function skuByItemId(string $channel): array
    {
        $table = ['ebay1' => 'ebay_campaign_ads', 'ebay2' => 'ebay2_campaign_ads', 'ebay3' => 'ebay3_campaign_ads'][$channel] ?? '';
        $out = [];
        if ($table === '' || ! Schema::hasTable($table) || ! Schema::hasColumn($table, 'listing_id') || ! Schema::hasColumn($table, 'sku')) {
            return $out;
        }
        foreach (DB::table($table)->select('listing_id', 'sku')->whereNotNull('sku')->where('sku', '!=', '')->get() as $row) {
            $sku = trim((string) $row->sku);
            $id = trim((string) $row->listing_id);
            if ($id !== '' && $sku !== '' && ! ctype_digit($sku) && stripos($sku, 'PARENT') === false) {
                $out[$id] = $sku;
            }
        }

        return $out;
    }

    private function firstImage(mixed $stored): string
    {
        if (is_string($stored) && $stored !== '') {
            $decoded = json_decode($stored, true);
            $stored = is_array($decoded) ? $decoded : [$stored];
        }
        if (! is_array($stored)) {
            return '';
        }
        $flat = $stored['urls'] ?? $stored['images'] ?? $stored;
        foreach ((array) $flat as $url) {
            $url = trim((string) (is_array($url) ? ($url['url'] ?? $url['src'] ?? '') : $url));
            if (str_starts_with($url, 'http') || str_starts_with($url, '/')) {
                return $url;
            }
        }

        return '';
    }

    private function channel(mixed $value): string
    {
        $value = (string) $value;

        return isset(self::CHANNELS[$value]) ? $value : 'ebay1';
    }

    private function currentQty(string $channel, string $itemId, string $sku): int
    {
        $table = self::CHANNELS[$channel]['table'];
        if (! Schema::hasColumn($table, 'ebay_stock')) {
            return 0;
        }
        $q = DB::table($table)->where('item_id', $itemId);
        if ($sku !== '') {
            $q->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)]);
        }
        $stock = $q->value('ebay_stock');

        return is_numeric($stock) ? max(0, (int) $stock) : 0;
    }

    private function store(string $channel, string $itemId, string $sku, ?string $status, ?string $reason, ?string $newItemId = null): void
    {
        $table = self::CHANNELS[$channel]['table'];
        $payload = [];
        if ($status !== null && Schema::hasColumn($table, 'listing_status')) {
            $payload['listing_status'] = $status;
        }
        if (Schema::hasColumn($table, 'inactive_reason')) {
            $payload['inactive_reason'] = $reason;
        }
        if ($newItemId !== null) {
            $payload['item_id'] = $newItemId;
        }
        if ($payload === []) {
            return;
        }
        $q = DB::table($table)->where('item_id', $itemId);
        if ($sku !== '') {
            $q->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)]);
        }
        $q->update($payload);
    }

    /**
     * @return array{success: bool, message: string, item_id?: string}
     */
    private function relist(object $service, string $itemId, string $sku, ?int $quantity): array
    {
        $ctx = $service->tradingReviseContext();
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><RelistFixedPriceItemRequest xmlns="urn:ebay:apis:eBLBaseComponents"/>');
        $xml->addChild('RequesterCredentials')->addChild('eBayAuthToken', (string) $ctx['authToken']);
        $xml->addChild('ErrorLanguage', 'en_US');
        $item = $xml->addChild('Item');
        $item->addChild('ItemID', $itemId);
        if ($quantity !== null && $sku === '') {
            $item->addChild('Quantity', (string) max(0, $quantity));
        }
        try {
            $res = Http::timeout(90)->withHeaders([
                'X-EBAY-API-COMPATIBILITY-LEVEL' => $ctx['compatLevel'],
                'X-EBAY-API-DEV-NAME' => $ctx['devId'],
                'X-EBAY-API-APP-NAME' => $ctx['appId'],
                'X-EBAY-API-CERT-NAME' => $ctx['certId'],
                'X-EBAY-API-CALL-NAME' => 'RelistFixedPriceItem',
                'X-EBAY-API-SITEID' => $ctx['siteId'],
                'Content-Type' => 'text/xml',
            ])->withBody($xml->asXML(), 'text/xml')->post($ctx['endpoint']);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
        $parsed = json_decode(json_encode(simplexml_load_string($res->body())), true) ?: [];
        $ack = (string) ($parsed['Ack'] ?? 'Failure');
        if ($ack === 'Success' || $ack === 'Warning') {
            $newId = trim((string) ($this->text($parsed['ItemID'] ?? '') ?: $itemId));

            return ['success' => true, 'message' => 'Listing relisted'.($newId !== $itemId ? ' as item '.$newId : '').'.', 'item_id' => $newId];
        }
        $errors = $parsed['Errors'] ?? [];
        $errors = isset($errors['LongMessage']) || isset($errors['ShortMessage']) ? [$errors] : (array) $errors;
        $msg = [];
        foreach ($errors as $err) {
            if (is_array($err)) {
                $msg[] = trim((string) ($this->text($err['LongMessage'] ?? '') ?: $this->text($err['ShortMessage'] ?? '')));
            }
        }

        return ['success' => false, 'message' => implode(' ', array_filter($msg)) ?: 'eBay refused to relist this item.'];
    }

    /**
     * Live list of the listings that are ended and not relisted on eBay (GetMyeBaySelling Unsold list), with
     * the SKUs of their variations. Kept for 15 minutes so the page can add listings the status sync missed.
     * eBay's API does not say WHY a listing ended (the Seller Hub "removed for violating a policy" text is not
     * returned by GetItem, GetSellerList or the Compliance API), so no reason is guessed here.
     *
     * @return array<string, array{parent_sku: string, title: string, skus: list<string>}>
     */
    private function liveUnsold(string $channel, bool $force = false): array
    {
        $key = 'ebay_live_unsold:'.$channel;
        $cached = Cache::get($key);
        if (! $force && is_array($cached) && (int) ($cached['at'] ?? 0) > time() - 900) {
            return (array) ($cached['items'] ?? []);
        }

        try {
            $ctx = app(self::CHANNELS[$channel]['service'])->tradingReviseContext();
            if (($ctx['authToken'] ?? '') === '') {
                return (array) ($cached['items'] ?? []);
            }
            $items = $this->unsoldListings($ctx);
            $items = $this->fillVariationSkus($channel, $ctx, $items);
        } catch (\Throwable $e) {
            return (array) ($cached['items'] ?? []);
        }
        Cache::put($key, ['at' => time(), 'items' => $items], now()->addHours(6));

        return $items;
    }

    /**
     * The Unsold list does not carry the SKU of each variation, so a variation listing comes back with no SKUs.
     * Read them with GetItem, once per listing (the SKUs of an ended listing do not change, so they are kept).
     *
     * @param array{authToken: string, endpoint: string, compatLevel: string, devId: string, appId: string, certId: string, siteId: string} $ctx
     * @param array<string, array{parent_sku: string, title: string, skus: list<string>}> $items
     * @return array<string, array{parent_sku: string, title: string, skus: list<string>}>
     */
    private function fillVariationSkus(string $channel, array $ctx, array $items): array
    {
        $need = [];
        foreach ($items as $id => $item) {
            $items[$id]['reason'] = '';
            $known = Cache::get('ebay_item_info:'.$channel.':'.$id);
            if (is_array($known)) {
                if ($item['skus'] === []) {
                    $items[$id]['skus'] = (array) ($known['skus'] ?? []);
                }
                $items[$id]['reason'] = (string) ($known['reason'] ?? '');
            } else {
                $need[] = (string) $id;
            }
        }

        foreach (array_chunk(array_slice($need, 0, 120), 8) as $chunk) {
            $responses = Http::pool(function ($pool) use ($chunk, $ctx) {
                foreach ($chunk as $id) {
                    $xml = '<?xml version="1.0" encoding="utf-8"?><GetItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">'
                        .'<RequesterCredentials><eBayAuthToken>'.htmlspecialchars((string) $ctx['authToken'], ENT_XML1).'</eBayAuthToken></RequesterCredentials>'
                        .'<ErrorLanguage>en_US</ErrorLanguage><ItemID>'.$id.'</ItemID>'
                        .'<OutputSelector>Item.Variations.Variation.SKU</OutputSelector>'
                        .'</GetItemRequest>';
                    $pool->as($id)->timeout(40)->withHeaders($this->tradingHeaders($ctx, 'GetItem'))->withBody($xml, 'text/xml')->post($ctx['endpoint']);
                }
            });
            foreach ($chunk as $id) {
                $response = $responses[$id] ?? null;
                if (! $response instanceof \Illuminate\Http\Client\Response || ! $response->successful()) {
                    continue;
                }
                $xml = @simplexml_load_string($response->body());
                if ($xml === false) {
                    continue;
                }
                if (strtoupper(trim((string) ($xml->Ack ?? ''))) === 'FAILURE') {
                    // A listing eBay removed answers GetItem with the policy it broke, e.g.
                    // "This listing was removed for violating our Electronic equipment policy."
                    $message = '';
                    foreach ($xml->Errors ?? [] as $err) {
                        $message = trim((string) ($err->LongMessage ?: $err->ShortMessage));
                        if ($message !== '') {
                            break;
                        }
                    }
                    if ($message !== '' && (stripos($message, 'removed') !== false || stripos($message, 'violat') !== false || stripos($message, 'policy') !== false)) {
                        $items[$id]['reason'] = $message;
                        Cache::put('ebay_item_info:'.$channel.':'.$id, ['skus' => $items[$id]['skus'], 'reason' => $message], now()->addDays(14));
                    }
                    continue;
                }
                $skus = [];
                foreach ($xml->Item->Variations->Variation ?? [] as $variation) {
                    $sku = trim((string) ($variation->SKU ?? ''));
                    if ($sku !== '' && stripos($sku, 'PARENT') === false) {
                        $skus[] = $sku;
                    }
                }
                $skus = array_values(array_unique($skus));
                if ($items[$id]['skus'] === []) {
                    $items[$id]['skus'] = $skus;
                }
                Cache::put('ebay_item_info:'.$channel.':'.$id, ['skus' => $items[$id]['skus'], 'reason' => ''], now()->addDays(14));
            }
        }

        return $items;
    }

    /**
     * @param array{authToken: string, endpoint: string, compatLevel: string, devId: string, appId: string, certId: string, siteId: string} $ctx
     * @return array<string, array{parent_sku: string, title: string, skus: list<string>}>
     */
    private function unsoldListings(array $ctx): array
    {
        $out = [];
        $page = 1;
        $pages = 1;
        do {
            $xml = '<?xml version="1.0" encoding="utf-8"?><GetMyeBaySellingRequest xmlns="urn:ebay:apis:eBLBaseComponents">'
                .'<RequesterCredentials><eBayAuthToken>'.htmlspecialchars((string) $ctx['authToken'], ENT_XML1).'</eBayAuthToken></RequesterCredentials>'
                .'<UnsoldList><Include>true</Include><DurationInDays>60</DurationInDays>'
                .'<Pagination><EntriesPerPage>200</EntriesPerPage><PageNumber>'.$page.'</PageNumber></Pagination></UnsoldList>'
                .'<OutputSelector>UnsoldList.ItemArray.Item.ItemID</OutputSelector>'
                .'<OutputSelector>UnsoldList.ItemArray.Item.SKU</OutputSelector>'
                .'<OutputSelector>UnsoldList.ItemArray.Item.Title</OutputSelector>'
                .'<OutputSelector>UnsoldList.ItemArray.Item.Variations</OutputSelector>'
                .'<OutputSelector>UnsoldList.PaginationResult</OutputSelector>'
                .'</GetMyeBaySellingRequest>';
            $response = Http::timeout(60)->withHeaders($this->tradingHeaders($ctx, 'GetMyeBaySelling'))->withBody($xml, 'text/xml')->post($ctx['endpoint']);
            if (! $response->successful()) {
                break;
            }
            $parsed = @simplexml_load_string($response->body());
            if ($parsed === false || strtoupper(trim((string) ($parsed->Ack ?? ''))) === 'FAILURE') {
                break;
            }
            if ($page === 1) {
                $pages = max(1, (int) ($parsed->UnsoldList->PaginationResult->TotalNumberOfPages ?? 1));
            }
            foreach ($parsed->UnsoldList->ItemArray->Item ?? [] as $item) {
                $id = trim((string) ($item->ItemID ?? ''));
                if ($id === '') {
                    continue;
                }
                $parent = trim((string) ($item->SKU ?? ''));
                $skus = [];
                foreach ($item->Variations->Variation ?? [] as $variation) {
                    $sku = trim((string) ($variation->SKU ?? ''));
                    if ($sku !== '' && stripos($sku, 'PARENT') === false) {
                        $skus[] = $sku;
                    }
                }
                if ($skus === [] && $parent !== '' && stripos($parent, 'PARENT') === false) {
                    $skus[] = $parent;
                }
                $out[$id] = [
                    'parent_sku' => $parent,
                    'title' => trim((string) ($item->Title ?? '')),
                    'skus' => array_values(array_unique($skus)),
                ];
            }
            $page++;
        } while ($page <= $pages && $page <= 20);

        return $out;
    }

    /** @param array{compatLevel: string, devId: string, appId: string, certId: string, siteId: string} $ctx
     *  @return array<string, string> */
    private function tradingHeaders(array $ctx, string $call): array
    {
        return [
            'X-EBAY-API-COMPATIBILITY-LEVEL' => (string) $ctx['compatLevel'],
            'X-EBAY-API-DEV-NAME' => (string) $ctx['devId'],
            'X-EBAY-API-APP-NAME' => (string) $ctx['appId'],
            'X-EBAY-API-CERT-NAME' => (string) $ctx['certId'],
            'X-EBAY-API-CALL-NAME' => $call,
            'X-EBAY-API-SITEID' => (string) $ctx['siteId'],
            'Content-Type' => 'text/xml',
        ];
    }

    private const ENDED_TEXT = 'Ended on eBay and not relisted. eBay gives no reason for this one through its API: open the listing in Seller Hub > Inactive to see it.';

    /**
     * Add the CP Master SKUs of listings that are ended on eBay but missing from the rows built from the
     * stored statuses, and use the reason eBay gives (GetItem answers a removed listing with the policy it broke).
     * A SKU that is Active somewhere is not added. Everything left out is returned with the reason, so the page
     * can say why a listing eBay shows as ended is not in the table.
     *
     * @param list<array<string, mixed>> $rows
     * @param array{name: array<string, string>, image: array<string, string>, parent: array<string, string>} $catalog
     * @param array<string, bool> $activeNorms
     * @param array<string, int> $inv
     * @return array{rows: list<array<string, mixed>>, hidden: list<array{item_id: string, sku: string, why: string}>}
     */
    private function addLiveUnsold(string $channel, array $rows, array $catalog, array $activeNorms, array $inv = []): array
    {
        $seen = [];
        $liveByItem = $this->liveUnsold($channel);
        foreach ($rows as &$row) {
            $seen[ShopifySku::normalizeSkuForShopifyLookup((string) ($row['sku'] ?? ''))] = true;
            $reason = trim((string) ($liveByItem[$row['item_id'] ?? '']['reason'] ?? ''));
            if ($reason !== '') {
                $row['error'] = $reason;
                $row['issues'] = [['code' => 'inactive', 'text' => $reason]];
                $row['codes'] = ['inactive'];
            }
        }
        unset($row);

        $hidden = [];
        foreach ($liveByItem as $itemId => $item) {
            $itemId = (string) $itemId;
            $text = trim((string) ($item['reason'] ?? '')) ?: self::ENDED_TEXT;
            $shown = false;
            foreach ($item['skus'] as $sku) {
                $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
                if ($norm === '') {
                    continue;
                }
                if (! isset($catalog['name'][$norm])) {
                    $hidden[] = ['item_id' => $itemId, 'sku' => $sku, 'why' => 'not a CP Master SKU (status must be Active, DC or Coming)'];
                    continue;
                }
                if (isset($activeNorms[$norm])) {
                    $hidden[] = ['item_id' => $itemId, 'sku' => $sku, 'why' => 'the SKU is active in another listing'];
                    continue;
                }
                $shown = true;
                if (isset($seen[$norm])) {
                    continue;
                }
                $seen[$norm] = true;
                $rows[] = $this->liveRow($itemId, $catalog['name'][$norm], $catalog['parent'][$norm] ?: (string) $item['parent_sku'], $catalog['image'][$norm] ?? '', (string) $item['title'], $text, $inv[$norm] ?? null);
            }
            // eBay removed the listing and would not show its variations: list the listing itself.
            $parentKey = 'item:'.$itemId;
            if (! $shown && $item['skus'] === [] && trim((string) $item['parent_sku']) !== '' && ! isset($seen[$parentKey])) {
                $seen[$parentKey] = true;
                $rows[] = $this->liveRow($itemId, (string) $item['parent_sku'], '', '', (string) $item['title'], $text, null);
            }
        }

        return ['rows' => $rows, 'hidden' => $hidden];
    }

    /** @return array<string, mixed> */
    private function liveRow(string $itemId, string $sku, string $parent, string $image, string $title, string $error, ?int $inv): array
    {
        return [
            'id' => 'u-'.substr(md5($itemId.'|'.$sku), 0, 16),
            'sku' => $sku,
            'parent' => $parent,
            'image' => $image,
            'item_id' => $itemId,
            'title' => $title,
            'link' => 'https://www.ebay.com/itm/'.$itemId,
            'status' => 'INACTIVE',
            'stock' => null,
            'inv' => $inv,
            'price' => null,
            'issues' => [['code' => 'inactive', 'text' => $error]],
            'codes' => ['inactive'],
            'error' => $error,
        ];
    }

    private function text(mixed $value): string
    {
        if (is_array($value)) {
            $value = $value[0] ?? '';
        }

        return trim((string) $value);
    }
}
