<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Services\Ebay2ApiService;
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
        foreach (['ebay_title', 'ebay_link', 'ebay_price', 'ebay_stock', 'inactive_reason'] as $col) {
            if (Schema::hasColumn($table, $col)) {
                $cols[] = $col;
            }
        }

        $rows = [];
        $query = DB::table($table)->select($cols)
            ->whereNotNull('item_id')->where('item_id', '!=', '')
            ->whereRaw("UPPER(TRIM(COALESCE(listing_status, ''))) NOT IN ('', 'ACTIVE', 'MISSING', 'NOT_LISTED')")
            ->orderBy('sku');
        foreach ($query->limit(8000)->get() as $row) {
            $status = strtoupper(trim((string) $row->listing_status));
            $itemId = trim((string) $row->item_id);
            if ($itemId === '' || str_starts_with($itemId, 'UNSOLD-')) {
                continue;
            }
            $rows[] = [
                'id' => (int) $row->id,
                'sku' => trim((string) $row->sku),
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
