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

        $catalog = $this->catalog();
        $skuByItem = $this->skuByItemId($channel);
        $raw = DB::table($table)->select($cols)
            ->whereRaw("UPPER(TRIM(COALESCE(listing_status, ''))) IN ('INACTIVE', 'ENDED', 'UNSOLD', 'COMPLETED', 'SOLD')")
            ->orderBy('sku')
            ->limit(8000)
            ->get();

        $realByItem = [];
        foreach ($raw as $row) {
            $itemId = trim((string) $row->item_id);
            $sku = trim((string) $row->sku);
            if ($itemId !== '' && $sku !== '' && ! ctype_digit($sku) && stripos($sku, 'PARENT') === false) {
                $realByItem[$itemId] = $sku;
            }
        }

        $rows = [];
        foreach ($raw as $row) {
            $status = strtoupper(trim((string) $row->listing_status));
            $itemId = trim((string) $row->item_id);
            if (str_starts_with($itemId, 'UNSOLD-')) {
                continue;
            }
            $ebaySku = trim((string) $row->sku);
            if ($ebaySku === '' && $itemId === '') {
                continue;
            }
            if (stripos($ebaySku, 'PARENT') !== false) {
                continue;
            }
            $display = $skuByItem[$itemId] ?? (($ebaySku !== '' && ctype_digit($ebaySku)) ? ($realByItem[$itemId] ?? $ebaySku) : $ebaySku);
            $norm = ShopifySku::normalizeSkuForShopifyLookup($display);
            if (isset($catalog['name'][$norm])) {
                $display = $catalog['name'][$norm];
                $norm = ShopifySku::normalizeSkuForShopifyLookup($display);
            }
            $storedImage = property_exists($row, 'image_urls') ? $row->image_urls : null;
            $image = $catalog['image'][$norm] ?? $this->firstImage($storedImage);
            $rows[] = [
                'id' => (int) $row->id,
                'sku' => $display,
                'ebay_sku' => $ebaySku,
                'image' => $image,
                'item_id' => $itemId,
                'title' => trim((string) ($row->ebay_title ?? '')),
                'link' => trim((string) ($row->ebay_link ?? '')),
                'status' => $status,
                'stock' => isset($row->ebay_stock) ? (int) $row->ebay_stock : null,
                'price' => isset($row->ebay_price) && $row->ebay_price !== null ? (float) $row->ebay_price : null,
                'error' => trim((string) ($row->inactive_reason ?? '')) ?: 'Listed, but not active ('.$status.').',
            ];
        }

        return response()->json(['success' => true, 'channel' => $channel, 'rows' => $rows, 'count' => count($rows)]);
    }

    /** Pull Active / Unsold from eBay and store the statuses, then the page reloads. */
    public function refresh(Request $request): JsonResponse
    {
        @set_time_limit(300);
        $channel = $this->channel($request->input('channel'));
        $store = ['ebay1' => 1, 'ebay2' => 2, 'ebay3' => 3][$channel];
        $result = app(EbayPortalListingStatusSync::class)->sync($store);

        return response()->json([
            'success' => (bool) ($result['ok'] ?? false),
            'message' => ($result['ok'] ?? false)
                ? 'eBay '.substr($channel, 4).': '.($result['inactive'] ?? 0).' inactive, '.($result['active'] ?? 0).' active.'
                : (string) ($result['error'] ?? 'eBay did not return the listing list.'),
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
        $parts = array_filter([
            $status !== '' ? 'eBay status: '.$status : '',
            $ending !== '' ? 'Ending reason: '.$ending : '',
            is_numeric($qty) ? 'Quantity '.(int) $qty.(is_numeric($sold) ? ', sold '.(int) $sold : '') : '',
        ]);
        $reason = $parts !== [] ? implode('. ', $parts).'.' : 'eBay returned the listing with no error text.';
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
     * @return array{name: array<string, string>, image: array<string, string>}
     */
    private function catalog(): array
    {
        $name = [];
        $image = [];
        if (Schema::hasTable('product_master')) {
            $select = ['sku'];
            foreach (['image1', 'main_image'] as $col) {
                if (Schema::hasColumn('product_master', $col)) {
                    $select[] = $col;
                }
            }
            foreach (DB::table('product_master')->whereNull('deleted_at')->whereNotNull('sku')->select($select)->get() as $p) {
                $sku = trim((string) $p->sku);
                $key = ShopifySku::normalizeSkuForShopifyLookup($sku);
                if ($key === '' || stripos($sku, 'PARENT') !== false) {
                    continue;
                }
                $name[$key] = $sku;
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

        return ['name' => $name, 'image' => $image];
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

    private function text(mixed $value): string
    {
        if (is_array($value)) {
            $value = $value[0] ?? '';
        }

        return trim((string) $value);
    }
}
