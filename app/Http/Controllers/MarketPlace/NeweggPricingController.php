<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\AmazonDatasheet;
use App\Models\MarketplacePercentage;
use App\Models\NeweggDataView;
use App\Models\NeweggItem;
use App\Models\NeweggPricing;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Services\NeweggApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NeweggPricingController extends Controller
{
    public function index()
    {
        return view('market-places.newegg_pricing_tabulator_view');
    }

    public function getData(Request $request)
    {
        // Margin from marketplace_percentages (Neweggb2c): factor = (percentage - ad_updates) / 100.
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Neweggb2c')->first();
        $percentage = $marketplaceData ? (float) $marketplaceData->percentage : 80;
        $adUpdates  = $marketplaceData ? (float) $marketplaceData->ad_updates : 0;
        $margin     = $percentage - $adUpdates;
        $factor     = $margin > 0 ? $margin / 100 : 0.80;

        // 1) Fetch ALL SKUs from product master (base row set — same as Reverb/Amazon pages).
        $productMasterRows = ProductMaster::all();
        $skus = $productMasterRows->pluck('sku')->filter()->values()->all();

        // 2) Shopify data (INV + overall L30) keyed by the exact PM SKU.
        $shopifyData = ShopifySku::mapByProductSkus($skus);

        // 3) Newegg pricing keyed by a normalized SKU (special-char-insensitive match).
        $neweggByNorm = [];
        foreach (NeweggPricing::all() as $p) {
            $norm = $this->normalizeSkuKey($p->seller_part_number);
            if ($norm !== '' && !isset($neweggByNorm[$norm])) {
                $neweggByNorm[$norm] = $p;
            }
        }

        // 4) Newegg catalog titles keyed by normalized SKU.
        $titleByNorm = [];
        foreach (NeweggItem::query()->select('seller_part_number', 'title')->get() as $it) {
            $norm = $this->normalizeSkuKey($it->seller_part_number);
            if ($norm !== '' && !isset($titleByNorm[$norm])) {
                $titleByNorm[$norm] = $it->title;
            }
        }

        // 5) Newegg L30 units sold (last 30 days, excl. voided) keyed by normalized SKU.
        $neweggL30Raw = DB::table('newegg_order_items as i')
            ->join('newegg_orders as o', 'o.order_number', '=', 'i.order_number')
            ->where('o.order_date', '>=', now()->subDays(30))
            ->where(function ($q) {
                $q->whereNull('o.order_status_description')
                  ->orWhere('o.order_status_description', 'not like', '%void%');
            })
            ->whereNotNull('i.seller_part_number')
            ->groupBy('i.seller_part_number')
            ->select('i.seller_part_number', DB::raw('SUM(i.ordered_qty) as qty'))
            ->pluck('qty', 'seller_part_number');

        $neweggL30ByNorm = [];
        foreach ($neweggL30Raw as $spn => $qty) {
            $norm = $this->normalizeSkuKey($spn);
            if ($norm === '') {
                continue;
            }
            $neweggL30ByNorm[$norm] = ($neweggL30ByNorm[$norm] ?? 0) + (int) $qty;
        }

        // 6) User-entered SPRICE / SPFT / SROI overlay (newegg_data_views), keyed by exact SKU.
        $dataViews = NeweggDataView::all()->keyBy('sku');

        // 7) Amazon live selling price keyed by uppercased SKU (same source the
        //    Purchasing-Power / Macys / etc. pages use for the "A Price" column).
        $amazonBySku = AmazonDatasheet::whereIn('sku', $skus)
            ->get()
            ->keyBy(fn ($r) => strtoupper((string) $r->sku));

        $data = [];
        foreach ($productMasterRows as $pm) {
            $sku = $pm->sku;
            if ($sku === null || $sku === '' || stripos($sku, 'PARENT') !== false) {
                continue;
            }

            $norm    = $this->normalizeSkuKey($sku);
            $newegg  = $neweggByNorm[$norm] ?? null;
            $shopify = $shopifyData[$sku] ?? null;

            $inv   = (float) ($shopify->inv ?? 0);
            $ovl30 = (float) ($shopify->quantity ?? 0);

            $price = $newegg && $newegg->selling_price !== null ? (float) $newegg->selling_price : null;
            $l30   = (int) ($neweggL30ByNorm[$norm] ?? 0);

            // DIL% = overall sell-through = OVL30 / INV * 100 (same as "OV DIL" elsewhere).
            $dil = $inv > 0 ? round(($ovl30 / $inv) * 100, 0) : 0;

            // Profit per unit & ROI using the Newegg margin factor + ProductMaster costs.
            [$lp, $ship] = $this->extractCosts($pm);
            $priceVal = $price ?? 0;
            $pftEach  = ($priceVal * $factor) - $lp - $ship;
            $pftPct   = $priceVal > 0 ? round(($pftEach / $priceVal) * 100, 1) : 0;
            $roi      = $lp > 0 ? round(($pftEach / $lp) * 100, 0) : 0;

            // SPRICE / SPFT / SROI overlay (user-entered selling price + computed margin/roi).
            $dv      = $dataViews[$sku] ?? null;
            $dvValue = $dv ? (is_array($dv->value) ? $dv->value : []) : [];
            $sprice  = isset($dvValue['SPRICE']) ? (float) $dvValue['SPRICE'] : null;
            $spft    = isset($dvValue['SPFT']) ? (float) $dvValue['SPFT'] : null;
            $sroi    = isset($dvValue['SROI']) ? (float) $dvValue['SROI'] : null;
            $nr      = $dvValue['NR'] ?? 'REQ';
            $buyerLink  = $dvValue['BUYER_LINK'] ?? null;
            $sellerLink = $dvValue['SELLER_LINK'] ?? null;

            $image = $pm->main_image
                ?: ($pm->image1 ?? null)
                ?: ($pm->getAttribute('image_path') ?? null)
                ?: ($shopify->image_src ?? null);

            $amazon = $amazonBySku[strtoupper((string) $sku)] ?? null;
            $aPrice = $amazon && (float) ($amazon->price ?? 0) > 0
                ? round((float) $amazon->price, 2)
                : null;

            $data[] = [
                'sku'                => $sku,
                'image'              => $image ?: null,
                'title'              => $titleByNorm[$norm] ?? null,
                'inv'                => (int) $inv,
                'ovl30'              => (int) $ovl30,
                'dil'                => $dil,
                'price'              => $price !== null ? round($price, 2) : null,
                'a_price'            => $aPrice,
                'l30'                => $l30,
                'lp'                 => round($lp, 2),
                'ship'               => round($ship, 2),
                'pft'                => $price !== null ? round($pftEach, 2) : null,
                'pft_pct'            => $price !== null ? $pftPct : null,
                'roi'                => $price !== null ? $roi : null,
                'map'                => $newegg && $newegg->map !== null ? round((float) $newegg->map, 2) : null,
                'available_quantity' => $newegg->available_quantity ?? null,
                'currency'           => $newegg->currency ?? null,
                'status'             => $newegg
                    ? ($newegg->active === null ? null : ((int) $newegg->active === 1 ? 'Active' : 'Inactive'))
                    : null,
                'on_newegg'          => $newegg ? true : false,
                'sprice'             => $sprice,
                'spft'               => $spft,
                'sroi'               => $sroi,
                'nr'                 => $nr,
                'buyer_link'         => $buyerLink,
                'seller_link'        => $sellerLink,
                // Used by client-side bulk SPRICE tools (Increase / Decrease / Same Price)
                // to compute SPFT/SROI optimistically before the server response lands.
                'factor'             => round($factor, 4),
            ];
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Save a user-entered SPRICE for a SKU and (re)compute SPFT / SROI using the
     * Newegg margin + ProductMaster costs. Stored as JSON in newegg_data_views.value.
     */
    public function saveSprice(Request $request)
    {
        try {
            $sku    = $request->input('sku');
            $sprice = $request->input('sprice');

            if (!$sku) {
                return response()->json(['success' => false, 'error' => 'SKU is required'], 422);
            }

            $marketplaceData = MarketplacePercentage::where('marketplace', 'Neweggb2c')->first();
            $margin = ($marketplaceData ? (float) $marketplaceData->percentage : 80)
                    - ($marketplaceData ? (float) $marketplaceData->ad_updates : 0);
            $factor = $margin > 0 ? $margin / 100 : 0.80;

            $pm = ProductMaster::where('sku', $sku)->first();
            [$lp, $ship] = $this->extractCosts($pm);

            $dv     = NeweggDataView::firstOrNew(['sku' => $sku]);
            $values = is_array($dv->value) ? $dv->value : [];

            if ($sprice === null || $sprice === '') {
                unset($values['SPRICE'], $values['SPFT'], $values['SROI']);
            } else {
                $sprice = (float) $sprice;
                $profit = ($sprice * $factor) - $lp - $ship;
                $values['SPRICE'] = round($sprice, 2);
                $values['SPFT']   = $sprice > 0 ? round(($profit / $sprice) * 100, 1) : 0;
                $values['SROI']   = $lp > 0 ? round(($profit / $lp) * 100, 0) : 0;
            }

            $dv->value = $values;
            $dv->save();

            return response()->json([
                'success' => true,
                'sprice'  => $values['SPRICE'] ?? null,
                'spft'    => $values['SPFT'] ?? null,
                'sroi'    => $values['SROI'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving Newegg SPRICE: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to save'], 500);
        }
    }

    /**
     * Bulk-save SPRICE for many SKUs at once. Powers the "Increase / Decrease /
     * Same Price" tools on the pricing page (one HTTP request per click instead
     * of N). Re-uses saveSprice's per-SKU calc so SPFT/SROI stay authoritative.
     *
     * Request body: { updates: [ { sku: "...", sprice: 19.99 }, ... ] }
     */
    public function saveSpriceBulk(Request $request)
    {
        try {
            $updates = $request->input('updates', []);
            if (!is_array($updates) || count($updates) === 0) {
                return response()->json(['success' => false, 'error' => 'No updates provided'], 422);
            }

            $marketplaceData = MarketplacePercentage::where('marketplace', 'Neweggb2c')->first();
            $margin = ($marketplaceData ? (float) $marketplaceData->percentage : 80)
                    - ($marketplaceData ? (float) $marketplaceData->ad_updates : 0);
            $factor = $margin > 0 ? $margin / 100 : 0.80;

            $savedCount = 0;
            $errors     = [];
            $results    = [];

            foreach ($updates as $u) {
                $sku    = $u['sku']    ?? null;
                $sprice = $u['sprice'] ?? null;
                if (!$sku) {
                    $errors[] = ['sku' => null, 'error' => 'Missing SKU'];
                    continue;
                }

                try {
                    $pm = ProductMaster::where('sku', $sku)->first();
                    [$lp, $ship] = $this->extractCosts($pm);

                    $dv     = NeweggDataView::firstOrNew(['sku' => $sku]);
                    $values = is_array($dv->value) ? $dv->value : [];

                    if ($sprice === null || $sprice === '' || (float) $sprice === 0.0) {
                        unset($values['SPRICE'], $values['SPFT'], $values['SROI']);
                        $values['SPRICE'] = $sprice === null || $sprice === '' ? null : 0;
                        if ($values['SPRICE'] === null) {
                            unset($values['SPRICE']);
                        }
                    } else {
                        $spriceF = (float) $sprice;
                        $profit  = ($spriceF * $factor) - $lp - $ship;
                        $values['SPRICE'] = round($spriceF, 2);
                        $values['SPFT']   = $spriceF > 0 ? round(($profit / $spriceF) * 100, 1) : 0;
                        $values['SROI']   = $lp > 0 ? round(($profit / $lp) * 100, 0) : 0;
                    }

                    $dv->value = $values;
                    $dv->save();

                    $results[] = [
                        'sku'    => $sku,
                        'sprice' => $values['SPRICE'] ?? null,
                        'spft'   => $values['SPFT']   ?? null,
                        'sroi'   => $values['SROI']   ?? null,
                    ];
                    $savedCount++;
                } catch (\Throwable $rowEx) {
                    $errors[] = ['sku' => $sku, 'error' => $rowEx->getMessage()];
                }
            }

            return response()->json([
                'success' => true,
                'updated' => $savedCount,
                'results' => $results,
                'errors'  => $errors,
            ]);
        } catch (\Exception $e) {
            Log::error('Error bulk-saving Newegg SPRICE: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to save'], 500);
        }
    }

    /**
     * Push selected SKU prices to the live Newegg Marketplace API. Mirrors the
     * Reverb push pattern (uses a service helper; returns per-SKU results so
     * the UI can show successes vs failures).
     *
     * Request body:
     *   { updates: [ { sku: "<local SKU>", price: 19.99 }, ... ] }
     *
     * The local SKU is resolved to its Newegg SellerPartNumber via the same
     * special-char-insensitive normalization used elsewhere on the page.
     */
    public function pushPriceToNewegg(Request $request, NeweggApiService $newegg)
    {
        try {
            $updates = $request->input('updates', []);
            if (!is_array($updates) || count($updates) === 0) {
                return response()->json(['success' => false, 'error' => 'No updates provided'], 422);
            }

            // Build a SKU → SellerPartNumber index once (avoids N queries).
            $spnByNorm = [];
            foreach (NeweggPricing::query()->select('seller_part_number')->get() as $row) {
                $norm = $this->normalizeSkuKey((string) $row->seller_part_number);
                if ($norm !== '' && !isset($spnByNorm[$norm])) {
                    $spnByNorm[$norm] = (string) $row->seller_part_number;
                }
            }

            $items = [];   // Newegg payload rows (one PUT covers them all)
            $skuBySpn = []; // SPN → local sku, for mapping results back
            $errors = [];
            foreach ($updates as $u) {
                $sku   = trim((string) ($u['sku'] ?? ''));
                $price = isset($u['price']) ? (float) $u['price'] : 0.0;
                if ($sku === '') {
                    $errors[] = ['sku' => $sku, 'success' => false, 'error' => 'Missing SKU'];
                    continue;
                }
                if ($price <= 0) {
                    $errors[] = ['sku' => $sku, 'success' => false, 'error' => 'Price must be > 0'];
                    continue;
                }
                $norm = $this->normalizeSkuKey($sku);
                $spn = $spnByNorm[$norm] ?? null;
                if (!$spn) {
                    $errors[] = ['sku' => $sku, 'success' => false, 'error' => 'No Newegg listing (SPN) found for SKU'];
                    continue;
                }
                $items[] = ['seller_part_number' => $spn, 'price' => round($price, 2), 'currency' => 'USD'];
                $skuBySpn[$spn] = $sku;
            }

            if ($items === []) {
                return response()->json([
                    'success' => false,
                    'pushed'  => 0,
                    'results' => array_values($errors),
                    'error'   => 'No valid SKU/price pairs to push.',
                ], 422);
            }

            // Service loops the per-SKU Newegg endpoint internally and returns
            // a per-item results list — map it back to local SKUs for the UI.
            $bulk = $newegg->updateItemPriceBulk($items, 'USA');

            $priceBySpn = [];
            foreach ($items as $row) {
                $priceBySpn[$row['seller_part_number']] = $row['price'];
            }

            $results = $errors; // pre-flight failures first
            $pushed = 0;
            foreach ($bulk['results'] as $r) {
                $spn = (string) ($r['seller_part_number'] ?? '');
                $localSku = $skuBySpn[$spn] ?? $spn;
                $success = (bool) ($r['success'] ?? false);
                if ($success) {
                    $pushed++;
                    NeweggPricing::where('seller_part_number', $spn)->update([
                        'selling_price' => $priceBySpn[$spn] ?? null,
                    ]);
                }
                $results[] = [
                    'sku'     => $localSku,
                    'spn'     => $spn,
                    'success' => $success,
                    'price'   => $priceBySpn[$spn] ?? null,
                    'error'   => $success ? null : ($r['error'] ?? 'Rejected'),
                ];
            }

            // Whole-batch blocked-by-cloudflare gets a clearer HTTP status so
            // the UI can fail-fast instead of treating it as a normal error.
            if ($bulk['blocked_by_cloudflare'] && $pushed === 0) {
                return response()->json([
                    'success' => false,
                    'pushed'  => 0,
                    'failed'  => count($items),
                    'results' => array_values($results),
                    'error'   => 'Blocked by Cloudflare. Whitelist this server IP in the Newegg Seller Portal.',
                ], 502);
            }

            return response()->json([
                'success' => $pushed > 0,
                'pushed'  => $pushed,
                'failed'  => (count($items) - $pushed) + count($errors),
                'results' => array_values($results),
                'error'   => ($pushed === 0 ? ($bulk['error_message'] ?? 'No prices pushed') : null),
            ]);
        } catch (\Throwable $e) {
            Log::error('Error pushing Newegg prices: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Push failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Save the NR/REQ flag for a SKU into newegg_data_views.value.
     */
    public function saveNr(Request $request)
    {
        try {
            $sku = $request->input('sku');
            $nr  = $request->input('nr');

            if (!$sku) {
                return response()->json(['success' => false, 'error' => 'SKU is required'], 422);
            }

            $nr = strtoupper((string) $nr) === 'NR' ? 'NR' : 'REQ';

            $dv     = NeweggDataView::firstOrNew(['sku' => $sku]);
            $values = is_array($dv->value) ? $dv->value : [];
            $values['NR'] = $nr;
            $dv->value = $values;
            $dv->save();

            return response()->json(['success' => true, 'nr' => $nr]);
        } catch (\Exception $e) {
            Log::error('Error saving Newegg NR: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to save'], 500);
        }
    }

    /**
     * Save Buyer / Seller links for a SKU into newegg_data_views.value.
     */
    public function saveLinks(Request $request)
    {
        try {
            $sku = $request->input('sku');
            if (!$sku) {
                return response()->json(['success' => false, 'error' => 'SKU is required'], 422);
            }

            $buyer  = trim((string) $request->input('buyer_link', ''));
            $seller = trim((string) $request->input('seller_link', ''));

            $dv     = NeweggDataView::firstOrNew(['sku' => $sku]);
            $values = is_array($dv->value) ? $dv->value : [];

            if ($buyer === '') {
                unset($values['BUYER_LINK']);
            } else {
                $values['BUYER_LINK'] = $buyer;
            }
            if ($seller === '') {
                unset($values['SELLER_LINK']);
            } else {
                $values['SELLER_LINK'] = $seller;
            }

            $dv->value = $values;
            $dv->save();

            return response()->json([
                'success'     => true,
                'buyer_link'  => $values['BUYER_LINK'] ?? null,
                'seller_link' => $values['SELLER_LINK'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving Newegg B/S links: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to save'], 500);
        }
    }

    /**
     * Normalize a SKU for special-char-insensitive matching: drop everything
     * that isn't a letter or digit (spaces, slashes, dashes, etc.) and uppercase.
     * e.g. "1/4M-3/8M Camera Screw 5Pcs" => "14M38MCAMERASCREW5PCS".
     */
    private function normalizeSkuKey(?string $sku): string
    {
        if ($sku === null || $sku === '') {
            return '';
        }

        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $sku));
    }

    /**
     * Pull LP and Ship from a ProductMaster row (Values JSON or direct columns).
     *
     * @return array{0:float,1:float}
     */
    private function extractCosts(?ProductMaster $pm): array
    {
        if (!$pm) {
            return [0.0, 0.0];
        }

        $values = is_array($pm->Values)
            ? $pm->Values
            : (is_string($pm->Values) ? (json_decode($pm->Values, true) ?: []) : []);

        $lp = 0.0;
        foreach ($values as $k => $v) {
            if (strtolower((string) $k) === 'lp') {
                $lp = (float) $v;
                break;
            }
        }
        if ($lp === 0.0 && isset($pm->lp)) {
            $lp = (float) $pm->lp;
        }

        $ship = isset($values['ship']) ? (float) $values['ship'] : (isset($pm->ship) ? (float) $pm->ship : 0.0);

        return [$lp, $ship];
    }

    public function getColumnVisibility()
    {
        try {
            $filePath = storage_path('app/newegg_pricing_column_visibility.json');

            $default = [
                // _select is a toolbar-controlled column (Increase/Decrease/Same Price modes);
                // hide it from the Columns dropdown so users don't toggle it manually.
                '_select' => false,
                'sku' => true, 'title' => false, 'inv' => true, 'ovl30' => true,
                'dil' => true, 'price' => true, 'a_price' => true, 'l30' => true,
                'lp' => false, 'ship' => false, 'pft' => true, 'pft_pct' => true, 'roi' => true,
                'sprice' => true, 'spft' => true, 'sroi' => true, 'nr' => true, 'bs' => true,
                'map' => true, 'missing_l' => true, 'map_status' => true, 'available_quantity' => true, 'currency' => false, 'status' => true,
            ];

            if (file_exists($filePath)) {
                $saved = json_decode(file_get_contents($filePath), true);
                if (is_array($saved)) {
                    return response()->json($saved);
                }
            }

            return response()->json($default);
        } catch (\Exception $e) {
            Log::error('Error getting Newegg pricing column visibility: ' . $e->getMessage());
            return response()->json([], 500);
        }
    }

    public function saveColumnVisibility(Request $request)
    {
        try {
            $filePath = storage_path('app/newegg_pricing_column_visibility.json');
            file_put_contents($filePath, json_encode($request->input('visibility', []), JSON_PRETTY_PRINT));
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Error saving Newegg pricing column visibility: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to save preferences'], 500);
        }
    }
}
