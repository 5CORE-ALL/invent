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

        $rows = $this->applyPolicyRemovals($channel, $rows, $catalog, $activeNorms);

        return response()->json([
            'success' => true, 'channel' => $channel, 'rows' => $rows, 'count' => count($rows),
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
        $removed = ['count' => 0, 'note' => ''];
        if ($result['ok'] ?? false) {
            try {
                $removed = $this->pullPolicyRemovals($channel);
            } catch (\Throwable $e) {
                $removed = ['count' => 0, 'note' => 'Policy check failed: '.$e->getMessage()];
            }
        }

        $message = ($result['ok'] ?? false)
            ? 'eBay '.substr($channel, 4).': '.($result['inactive'] ?? 0).' inactive, '.($result['active'] ?? 0).' active.'
                .($removed['count'] > 0 ? ' '.$removed['count'].' listing(s) eBay removed for a policy violation.' : '')
                .($removed['note'] !== '' ? ' '.$removed['note'] : '')
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
        } elseif ($ending === 'CustomCode' || strtolower((string) $this->text($node['SellingStatus']['AdminEnded'] ?? '')) === 'true') {
            $reason = 'This listing was removed for violating an eBay policy.';
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
     * Unsold listings eBay itself ended (policy removal). Seller Hub shows the policy name,
     * for example "product safety policy"; the Trading API only reports that eBay ended it.
     *
     * @return array{count: int, note: string}
     */
    private function pullPolicyRemovals(string $channel): array
    {
        $service = app(self::CHANNELS[$channel]['service']);
        $ctx = $service->tradingReviseContext();
        if (($ctx['authToken'] ?? '') === '') {
            return ['count' => 0, 'note' => ''];
        }

        $unsold = $this->unsoldListings($ctx);
        $cached = Cache::get('ebay_policy_removed:'.$channel, ['items' => []]);
        $previous = (array) ($cached['items'] ?? []);
        $need = [];
        foreach ($unsold as $id => $item) {
            if (! isset($previous[$id]['error'])) {
                $need[] = $id;
            }
        }

        $reasons = [];
        foreach (array_chunk($need, 6) as $chunk) {
            $responses = Http::pool(function ($pool) use ($chunk, $ctx) {
                foreach ($chunk as $id) {
                    $xml = '<?xml version="1.0" encoding="utf-8"?><GetItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">'
                        .'<RequesterCredentials><eBayAuthToken>'.htmlspecialchars((string) $ctx['authToken'], ENT_XML1).'</eBayAuthToken></RequesterCredentials>'
                        .'<ErrorLanguage>en_US</ErrorLanguage><ItemID>'.$id.'</ItemID>'
                        .'<OutputSelector>Item.ListingDetails.EndingReason</OutputSelector>'
                        .'<OutputSelector>Item.SellingStatus.AdminEnded</OutputSelector>'
                        .'<OutputSelector>Item.ItemPolicyViolation.PolicyText</OutputSelector>'
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
                if ($xml === false || ! isset($xml->Item)) {
                    continue;
                }
                $kids = [];
                foreach ($xml->Item->Variations->Variation ?? [] as $variation) {
                    $sku = trim((string) ($variation->SKU ?? ''));
                    if ($sku !== '' && stripos($sku, 'PARENT') === false) {
                        $kids[] = $sku;
                    }
                }
                $reasons[$id] = [
                    'ending' => trim((string) ($xml->Item->ListingDetails->EndingReason ?? '')),
                    'policy' => trim((string) ($xml->Item->ItemPolicyViolation->PolicyText ?? '')),
                    'admin' => strtolower(trim((string) ($xml->Item->SellingStatus->AdminEnded ?? ''))) === 'true',
                    'skus' => $kids,
                ];
            }
        }

        $items = [];
        foreach ($unsold as $id => $item) {
            $reason = $reasons[$id] ?? (isset($previous[$id]['error']) ? [
                'ending' => (string) ($previous[$id]['ending'] ?? ''),
                'policy' => (string) ($previous[$id]['policy'] ?? ''),
                'admin' => (bool) ($previous[$id]['admin'] ?? false),
                'skus' => (array) ($previous[$id]['skus'] ?? []),
            ] : null);
            if ($reason === null) {
                continue;
            }
            $text = $this->policyText($reason);
            if ($text === '') {
                continue;
            }
            $skus = $item['skus'] !== [] ? $item['skus'] : (array) $reason['skus'];
            $items[$id] = [
                'parent_sku' => $item['parent_sku'],
                'title' => $item['title'],
                'skus' => array_values(array_unique($skus)),
                'error' => $text,
                'ending' => (string) ($reason['ending'] ?? ''),
                'policy' => (string) ($reason['policy'] ?? ''),
                'admin' => (bool) ($reason['admin'] ?? false),
            ];
        }

        Cache::put('ebay_policy_removed:'.$channel, ['items' => $items], now()->addDays(7));
        $this->storePolicyReason(self::CHANNELS[$channel]['table'], $items);

        return ['count' => count($items), 'note' => ''];
    }

    /** @param array{ending: string, policy: string, admin: bool} $reason */
    private function policyText(array $reason): string
    {
        if (($reason['policy'] ?? '') !== '') {
            return (string) $reason['policy'];
        }
        if (($reason['ending'] ?? '') === 'ProductDeleted') {
            return 'eBay removed this listing because its catalog product was deleted.';
        }
        if (($reason['ending'] ?? '') === 'CustomCode' || ! empty($reason['admin'])) {
            return 'This listing was removed for violating an eBay policy.';
        }

        return '';
    }

    /** @param array<string, array{skus: list<string>, error: string}> $items */
    private function storePolicyReason(string $table, array $items): void
    {
        if ($items === [] || ! Schema::hasTable($table) || ! Schema::hasColumn($table, 'inactive_reason')) {
            return;
        }
        foreach ($items as $item) {
            $skus = array_values(array_filter($item['skus']));
            foreach (array_chunk($skus, 200) as $chunk) {
                DB::table($table)->whereIn('sku', $chunk)
                    ->whereRaw("UPPER(TRIM(COALESCE(listing_status, ''))) <> 'ACTIVE'")
                    ->update(['inactive_reason' => $item['error']]);
            }
        }
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
                .'<OutputSelector>UnsoldList.ItemArray.Item.Variations.Variation.SKU</OutputSelector>'
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
                $skus = [];
                foreach ($item->Variations->Variation ?? [] as $variation) {
                    $sku = trim((string) ($variation->SKU ?? ''));
                    if ($sku !== '' && stripos($sku, 'PARENT') === false) {
                        $skus[] = $sku;
                    }
                }
                $out[$id] = [
                    'parent_sku' => trim((string) ($item->SKU ?? '')),
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

    /**
     * Add the SKUs of listings eBay removed, and use the policy text as their error.
     *
     * @param list<array<string, mixed>> $rows
     * @param array{name: array<string, string>, image: array<string, string>, parent: array<string, string>} $catalog
     * @param array<string, bool> $activeNorms
     * @return list<array<string, mixed>>
     */
    private function applyPolicyRemovals(string $channel, array $rows, array $catalog, array $activeNorms): array
    {
        $items = (array) (Cache::get('ebay_policy_removed:'.$channel, [])['items'] ?? []);
        if ($items === []) {
            return $rows;
        }

        $bySku = [];
        foreach ($items as $itemId => $item) {
            foreach ((array) ($item['skus'] ?? []) as $sku) {
                $norm = ShopifySku::normalizeSkuForShopifyLookup((string) $sku);
                if ($norm !== '') {
                    $bySku[$norm] = $item + ['item_id' => (string) $itemId];
                }
            }
        }

        $seen = [];
        foreach ($rows as &$row) {
            $norm = ShopifySku::normalizeSkuForShopifyLookup((string) ($row['sku'] ?? ''));
            $seen[$norm] = true;
            $item = $bySku[$norm] ?? ($items[$row['item_id']] ?? null);
            if (! is_array($item) || trim((string) ($item['error'] ?? '')) === '') {
                continue;
            }
            $text = (string) $item['error'];
            $row['error'] = $text;
            $row['issues'] = [['code' => 'inactive', 'text' => $text]];
            $row['codes'] = ['inactive'];
            $row['status'] = 'INACTIVE';
            if (($row['parent'] ?? '') === '' && ! empty($item['parent_sku'])) {
                $row['parent'] = (string) $item['parent_sku'];
            }
        }
        unset($row);

        foreach ($items as $itemId => $item) {
            $text = trim((string) ($item['error'] ?? ''));
            if ($text === '') {
                continue;
            }
            $matched = false;
            foreach ((array) ($item['skus'] ?? []) as $sku) {
                $norm = ShopifySku::normalizeSkuForShopifyLookup((string) $sku);
                if ($norm === '' || ! isset($catalog['name'][$norm])) {
                    continue;
                }
                $matched = true;
                if (isset($seen[$norm]) || isset($activeNorms[$norm])) {
                    continue;
                }
                $seen[$norm] = true;
                $rows[] = $this->policyRow((string) $itemId, $catalog['name'][$norm], $catalog['parent'][$norm] ?: (string) ($item['parent_sku'] ?? ''), $catalog['image'][$norm] ?? '', (string) ($item['title'] ?? ''), $text);
            }
            $parent = trim((string) ($item['parent_sku'] ?? ''));
            if (! $matched && $parent !== '' && ! isset($seen['item:'.$itemId])) {
                $rows[] = $this->policyRow((string) $itemId, $parent, '', '', (string) ($item['title'] ?? ''), $text);
            }
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    private function policyRow(string $itemId, string $sku, string $parent, string $image, string $title, string $error): array
    {
        return [
            'id' => 'rm-'.substr(md5($itemId.'|'.$sku), 0, 16),
            'sku' => $sku,
            'parent' => $parent,
            'image' => $image,
            'item_id' => $itemId,
            'title' => $title,
            'link' => 'https://www.ebay.com/itm/'.$itemId,
            'status' => 'INACTIVE',
            'stock' => null,
            'inv' => null,
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
