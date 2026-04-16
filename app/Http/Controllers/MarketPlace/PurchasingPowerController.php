<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\AmazonDatasheet;
use App\Models\MarketplacePercentage;
use App\Models\ProductMaster;
use App\Models\PurchasingPowerDataView;
use App\Models\PurchasingPowerProduct;
use App\Models\PurchasingPowerSale;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PurchasingPowerController extends Controller
{
    public function pricingView(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        $marketplaceData = MarketplacePercentage::where('marketplace', 'Purchase')->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 65;

        return view('market-places.purchasing_power_tabulator_view', [
            'mode'         => $mode,
            'demo'         => $demo,
            'ppPercentage' => $percentage,
        ]);
    }

    public function dataJson(Request $request)
    {
        try {
            $response = $this->getViewData($request);
            $data = json_decode($response->getContent(), true);
            return response()->json($data['data'] ?? []);
        } catch (\Exception $e) {
            Log::error('Error fetching Purchasing Power data: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch data'], 500);
        }
    }

    public function getViewData(Request $request)
    {
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $productMasters = $productMasters->filter(function ($item) {
            return stripos($item->sku, 'PARENT') === false;
        })->values();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        $shopifyData = ShopifySku::mapByProductSkus($skus);
        $ppMetrics    = PurchasingPowerProduct::whereIn('sku', $skus)->get()->keyBy('sku');
        $dataViews    = PurchasingPowerDataView::whereIn('sku', $skus)->pluck('value', 'sku');
        $amazonData   = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy(fn($i) => strtoupper($i->sku));

        // Sales qty from uploaded purchasing_power_sales (excluding Canceled)
        // Match by offer_sku (= product_masters.sku), NOT product_sku (which is Mirakl internal numeric ID)
        $salesQty = PurchasingPowerSale::whereNotIn('status', ['Canceled', 'canceled'])
            ->selectRaw('UPPER(offer_sku) as sku_upper, SUM(quantity) as total_qty')
            ->groupBy('sku_upper')
            ->pluck('total_qty', 'sku_upper');

        $marketplaceData = MarketplacePercentage::where('marketplace', 'Purchase')->first();
        $percentage = $marketplaceData ? ($marketplaceData->percentage / 100) : 0.65;

        $result = [];

        foreach ($productMasters as $pm) {
            $sku     = strtoupper($pm->sku);
            $parent  = $pm->parent;

            $shopify   = $shopifyData->get($pm->sku);
            $ppMetric  = $ppMetrics[$pm->sku] ?? null;
            $amazon    = $amazonData[strtoupper($pm->sku)] ?? null;

            $row = [];
            $row['Parent']      = $parent;
            $row['(Child) sku'] = $pm->sku;

            $row['INV']  = $shopify ? (int) ($shopify->inv ?? 0) : 0;
            $row['L30']  = $shopify ? (int) ($shopify->quantity ?? 0) : 0;

            $row['PP L30']   = $salesQty[strtoupper($pm->sku)] ?? $ppMetric->m_l30 ?? 0;
            $row['PP Price'] = $ppMetric->price ?? 0;
            $row['PP INV']   = $ppMetric->stock ?? 0;

            $row['A Price'] = $amazon ? floatval($amazon->price ?? 0) : null;

            // NR/REQ + SPRICE from PurchasingPowerDataView
            $row['nr_req']          = 'REQ';
            $row['NR']              = '';
            $row['Listed']          = null;
            $row['Live']            = null;
            $row['SPRICE']          = null;
            $row['has_custom_sprice'] = false;
            $row['SPRICE_STATUS']   = null;

            if (isset($dataViews[$pm->sku])) {
                $raw = $dataViews[$pm->sku];
                if (!is_array($raw)) $raw = json_decode($raw, true);
                if (is_array($raw)) {
                    $row['nr_req'] = $raw['nr_req'] ?? 'REQ';
                    $row['NR']     = $raw['NR']     ?? '';
                    $row['Listed'] = isset($raw['Listed']) ? filter_var($raw['Listed'], FILTER_VALIDATE_BOOLEAN) : null;
                    $row['Live']   = isset($raw['Live'])   ? filter_var($raw['Live'],   FILTER_VALIDATE_BOOLEAN) : null;

                    if (isset($raw['SPRICE'])) {
                        $row['SPRICE']           = floatval($raw['SPRICE']);
                        $row['has_custom_sprice'] = true;
                        $row['SPRICE_STATUS']     = $raw['SPRICE_STATUS'] ?? 'saved';
                    } else {
                        $row['SPRICE'] = isset($dataViews[$pm->sku]) ? 0 : null;
                    }
                }
            }

            // LP / Ship from ProductMaster
            $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
            $lp = 0;
            foreach ($values as $k => $v) {
                if (strtolower($k) === 'lp') { $lp = floatval($v); break; }
            }
            if ($lp === 0 && isset($pm->lp)) $lp = floatval($pm->lp);
            $ship = isset($values['ship']) ? floatval($values['ship']) : (isset($pm->ship) ? floatval($pm->ship) : 0);

            $price           = floatval($row['PP Price'] ?? 0);
            $units_l30       = floatval($row['PP L30']   ?? 0);

            $row['PP Dil%']    = ($units_l30 && $row['INV'] > 0) ? round($units_l30 / $row['INV'], 2) : 0;
            $row['Total_pft']  = round(($price * $percentage - $lp - $ship) * $units_l30, 2);
            $row['Profit']     = $row['Total_pft'];
            $row['T_Sale_l30'] = round($price * $units_l30, 2);
            $row['Sales L30']  = $row['T_Sale_l30'];

            $gpft = $price > 0 ? (($price * $percentage - $lp - $ship) / $price) * 100 : 0;
            $row['GPFT%']  = round($gpft, 2);
            $row['PFT %']  = round($gpft, 2);
            $row['ROI%']   = round($lp > 0 ? (($price * $percentage - $lp - $ship) / $lp) * 100 : 0, 2);

            $row['percentage']          = $percentage;
            $row['LP_productmaster']    = $lp;
            $row['Ship_productmaster']  = $ship;

            // SPRICE metrics
            $sprice = $row['SPRICE'] ?? 0;
            $sgpft  = round($sprice > 0 ? (($sprice * $percentage - $lp - $ship) / $sprice) * 100 : 0, 2);
            $row['SGPFT'] = $sgpft;
            $row['SPFT']  = $sgpft;
            $row['SROI']  = round($lp > 0 ? (($sprice * $percentage - $lp - $ship) / $lp) * 100 : 0, 2);

            $row['image_path'] = $shopify?->image_src ?? ($values['image_path'] ?? ($pm->image_path ?? null));

            $result[] = (object) $row;
        }

        return response()->json([
            'message' => 'Purchasing Power Data Fetched Successfully',
            'data'    => $result,
            'status'  => 200,
        ]);
    }

    public function updateNrReq(Request $request)
    {
        $sku    = trim($request->input('sku'));
        $nrReq  = $request->input('nr_req');

        $dv       = PurchasingPowerDataView::firstOrNew(['sku' => $sku]);
        $existing = is_array($dv->value) ? $dv->value : (json_decode($dv->value, true) ?? []);
        $existing['nr_req'] = $nrReq;
        $dv->value = $existing;
        $dv->save();

        return response()->json(['success' => true, 'message' => 'NR/REQ updated']);
    }

    public function saveSpriceTabulator(Request $request)
    {
        try {
            $sku    = trim($request->input('sku'));
            $sprice = (float) $request->input('sprice');

            if (!$sku || $sprice === null) {
                return response()->json(['error' => 'SKU and SPRICE are required'], 400);
            }

            $marketplaceData = MarketplacePercentage::where('marketplace', 'Purchase')->first();
            $percentage = $marketplaceData ? ((float) ($marketplaceData->percentage ?? 65)) : 65;
            $margin     = $percentage / 100;

            $pm = ProductMaster::where('sku', $sku)->first();
            $lp = 0;
            $ship = 0;
            if ($pm) {
                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                foreach ($values as $k => $v) {
                    if (strtolower($k) === 'lp') { $lp = (float) $v; break; }
                }
                if ($lp === 0 && isset($pm->lp)) $lp = (float) $pm->lp;
                $ship = isset($values['ship']) ? (float) $values['ship'] : (isset($pm->ship) ? (float) $pm->ship : 0);
            }

            $sgpft = $sprice > 0 ? round((($sprice * $margin - $lp - $ship) / $sprice) * 100, 2) : 0;
            $sroi  = $lp     > 0 ? round((($sprice * $margin - $lp - $ship) / $lp)     * 100, 2) : 0;

            // Same pattern as AliExpress
            $view   = PurchasingPowerDataView::firstOrNew(['sku' => $sku]);
            $stored = is_array($view->value) ? $view->value
                    : (json_decode($view->value, true) ?: []);

            $stored['SPRICE'] = $sprice;
            $stored['SGPFT']  = $sgpft;
            $stored['SPFT']   = $sgpft;
            $stored['SROI']   = $sroi;

            $view->value = $stored;
            $view->save();

            Log::info('PP SPRICE saved', ['sku' => $sku, 'sprice' => $sprice]);

            return response()->json([
                'success'            => true,
                'spft_percent'       => $sgpft,
                'sroi_percent'       => $sroi,
                'sgpft_percent'      => $sgpft,
                'price_push_success' => false,
                'price_push_message' => 'No API push configured for Purchasing Power',
            ]);
        } catch (\Exception $e) {
            Log::error('PP SPRICE tabulator save failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function saveSpriceUpdates(Request $request)
    {
        try {
            $updates = $request->input('updates', []);
            if (empty($updates) && $request->has('sku')) {
                $updates = [['sku' => $request->input('sku'), 'sprice' => $request->input('sprice')]];
            }

            if (empty($updates)) return response()->json(['error' => 'No updates provided'], 400);

            $marketplaceData = MarketplacePercentage::where('marketplace', 'Purchase')->first();
            $percentage = $marketplaceData ? ((float) ($marketplaceData->percentage ?? 65)) : 65;
            $margin     = $percentage / 100;

            $updatedCount = 0;
            foreach ($updates as $update) {
                $sku    = $update['sku']    ?? null;
                $sprice = $update['sprice'] ?? null;
                if (!$sku || $sprice === null) continue;

                $sprice = (float) $sprice;

                $pm = ProductMaster::where('sku', $sku)->first();
                $lp = 0;
                $ship = 0;
                if ($pm) {
                    $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                    foreach ($values as $k => $v) {
                        if (strtolower($k) === 'lp') { $lp = (float) $v; break; }
                    }
                    if ($lp === 0 && isset($pm->lp)) $lp = (float) $pm->lp;
                    $ship = isset($values['ship']) ? (float) $values['ship'] : (isset($pm->ship) ? (float) $pm->ship : 0);
                }

                // Same pattern as AliExpress
                $view   = PurchasingPowerDataView::firstOrNew(['sku' => $sku]);
                $stored = is_array($view->value) ? $view->value
                        : (json_decode($view->value, true) ?: []);

                if ($sprice == 0) {
                    unset($stored['SPRICE'], $stored['SPFT'], $stored['SROI'], $stored['SGPFT']);
                } else {
                    $sgpft = $sprice > 0 ? round((($sprice * $margin - $lp - $ship) / $sprice) * 100, 2) : 0;
                    $sroi  = $lp     > 0 ? round((($sprice * $margin - $lp - $ship) / $lp)     * 100, 2) : 0;

                    $stored['SPRICE'] = $sprice;
                    $stored['SGPFT']  = $sgpft;
                    $stored['SPFT']   = $sgpft;
                    $stored['SROI']   = $sroi;
                }

                $view->value = $stored;
                $view->save();
                $updatedCount++;
            }

            return response()->json([
                'success'                  => true,
                'updated'                  => $updatedCount,
                'message'                  => "Successfully saved {$updatedCount} SPRICE update(s)",
                'price_push_success_count' => 0,
                'price_push_failed_count'  => 0,
            ]);
        } catch (\Exception $e) {
            Log::error('PP SPRICE batch save failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function updatePercentage(Request $request)
    {
        $percent = $request->input('percent');
        if (!is_numeric($percent) || $percent < 0 || $percent > 100) {
            return response()->json(['status' => 400, 'message' => 'Invalid percentage'], 400);
        }

        MarketplacePercentage::updateOrCreate(
            ['marketplace' => 'Purchase'],
            ['percentage'  => $percent]
        );
        Cache::put('pp_marketplace_percentage', $percent, now()->addDays(30));

        return response()->json(['status' => 200, 'message' => 'Percentage updated', 'data' => ['percentage' => $percent]]);
    }

    public function getColumnVisibility(Request $request)
    {
        $key = 'pp_tabulator_column_visibility_' . (auth()->id() ?? 'guest');
        return response()->json(Cache::get($key, []));
    }

    public function setColumnVisibility(Request $request)
    {
        $key = 'pp_tabulator_column_visibility_' . (auth()->id() ?? 'guest');
        Cache::put($key, $request->input('visibility', []), now()->addDays(365));
        return response()->json(['success' => true]);
    }

    // ==================== SALES PAGE ====================

    public function salesView(Request $request)
    {
        $rawPct = MarketplacePercentage::where('marketplace', 'Purchase')->value('percentage');
        $ppMargin = ($rawPct !== null && (float) $rawPct > 0) ? (float) $rawPct : 65.0;

        return view('market-places.purchasing_power_sales_view', [
            'mode' => $request->query('mode'),
            'demo' => $request->query('demo'),
            'ppMargin' => $ppMargin,
        ]);
    }

    public function salesDataJson(Request $request)
    {
        $sales = PurchasingPowerSale::orderBy('date_created', 'desc')->get();

        $rawPct = MarketplacePercentage::where('marketplace', 'Purchase')->value('percentage');
        $percentage = ($rawPct !== null && (float) $rawPct > 0) ? (float) $rawPct : 65.0;
        $pct = $percentage / 100;

        $skus = $sales->pluck('offer_sku')->filter()->map(fn ($sku) => trim((string) $sku))->unique()->values()->all();
        $productMasters = collect();
        if (!empty($skus)) {
            $productMasters = ProductMaster::whereIn('sku', $skus)->get()->keyBy(fn ($pm) => strtoupper(trim((string) $pm->sku)));
        }

        $data = $sales->map(function ($s) use ($pct, $percentage, $productMasters) {
            $skuKey = strtoupper(trim((string) ($s->offer_sku ?? '')));
            $pm = $skuKey !== '' ? ($productMasters[$skuKey] ?? null) : null;

            $lp = 0;
            $ship = 0;
            if ($pm) {
                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                foreach ($values as $k => $v) {
                    if (strtolower((string) $k) === 'lp') {
                        $lp = (float) $v;
                        break;
                    }
                }
                if ($lp === 0.0 && isset($pm->lp)) {
                    $lp = (float) $pm->lp;
                }
                $ship = isset($values['ship']) ? (float) $values['ship'] : (isset($pm->ship) ? (float) $pm->ship : 0);
            }

            $unitPrice = floatval($s->unit_price ?? 0);
            $qty       = max(0, (int) ($s->quantity ?? 0));
            $pftEach   = ($unitPrice * $pct) - $lp - $ship;
            $pft       = round($pftEach * $qty, 2);
            $gpft      = $unitPrice > 0 ? round(($pftEach / $unitPrice) * 100, 2) : 0;
            $cogs      = round($lp * $qty, 2);
            $groi      = $lp > 0 ? round(($pftEach / $lp) * 100, 2) : 0;

            return [
                'id'                   => $s->id,
                'date_created'         => $s->date_created ? $s->date_created->format('m/d/Y') : '',
                'order_number'         => $s->order_number,
                'order_id'             => $s->order_id,
                'status'               => $s->status,
                'product_sku'          => $s->offer_sku,   // offer_sku matches product_masters.sku
                'mirakl_product_sku'   => $s->product_sku, // Mirakl internal numeric ID
                'offer_sku'            => $s->offer_sku,
                'product_name'         => $s->product_name,
                'quantity'             => $s->quantity,
                'unit_price'           => $unitPrice,
                'amount'               => floatval($s->amount ?? 0),
                'commission_rule'      => $s->commission_rule_name,
                'commission'           => floatval($s->commission_incl_tax ?? 0),
                'amount_transferred'   => floatval($s->amount_transferred ?? 0),
                'shipping_company'     => $s->shipping_company,
                'tracking_number'      => $s->tracking_number,
                'tracking_url'         => $s->tracking_url,
                'customer'             => trim(($s->customer_first_name ?? '') . ' ' . ($s->customer_last_name ?? '')),
                'city'                 => $s->customer_city,
                'state'                => $s->customer_state,
                'country'              => $s->customer_country,
                'category_label'       => $s->category_label,
                'lp'                   => round($lp, 2),
                'ship'                 => round($ship, 2),
                'cogs'                 => $cogs,
                'pft'                  => $pft,
                'gpft_pct'             => $gpft,
                'groi_pct'             => $groi,
                'margin_pct'           => $percentage,
            ];
        });

        return response()->json($data);
    }

    public function uploadSales(Request $request)
    {
        try {
            $request->validate(['file' => 'required|file']);

            $file      = $request->file('file');
            $extension = strtolower($file->getClientOriginalExtension());

            // Parse TSV / TXT / CSV / Excel
            if (in_array($extension, ['txt', 'tsv'])) {
                $rows = [];
                if (($handle = fopen($file->getRealPath(), 'r')) !== false) {
                    while (($line = fgetcsv($handle, 0, "\t")) !== false) {
                        $rows[] = $line;
                    }
                    fclose($handle);
                }
            } elseif ($extension === 'csv') {
                $rows = [];
                if (($handle = fopen($file->getRealPath(), 'r')) !== false) {
                    while (($line = fgetcsv($handle, 0, ',')) !== false) {
                        $rows[] = $line;
                    }
                    fclose($handle);
                }
            } else {
                $spreadsheet = IOFactory::load($file->getRealPath());
                $rows = $spreadsheet->getActiveSheet()->toArray();
            }

            if (empty($rows)) {
                return response()->json(['error' => 'File is empty'], 400);
            }

            // Build header index
            $rawHeaders = array_shift($rows);
            $headerIndex = [];
            foreach ($rawHeaders as $i => $h) {
                $headerIndex[strtolower(trim($h ?? ''))] = $i;
            }

            $col = function (string $name) use ($headerIndex, &$rowArr): ?string {
                $key = strtolower(trim($name));
                return isset($headerIndex[$key]) ? ($rowArr[$headerIndex[$key]] ?? null) : null;
            };

            // Truncate before fresh import
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            PurchasingPowerSale::truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            $imported = 0;
            $skipped  = 0;
            $batch    = [];

            foreach ($rows as $rowArr) {
                if (count(array_filter($rowArr, fn($v) => $v !== null && $v !== '')) === 0) {
                    $skipped++;
                    continue;
                }

                $orderNumber = $col('Order number');
                if (empty($orderNumber)) { $skipped++; continue; }

                // Parse date
                $rawDate = $col('Date created');
                $dateCreated = null;
                if ($rawDate) {
                    try { $dateCreated = \Carbon\Carbon::parse($rawDate)->toDateTimeString(); } catch (\Exception $e) {}
                }

                $batch[] = [
                    'order_number'         => $orderNumber,
                    'date_created'         => $dateCreated,
                    'quantity'             => intval($col('Quantity') ?? 0),
                    'product_name'         => $col('Details'),
                    'status'               => $col('Status'),
                    'amount'               => is_numeric($col('Amount')) ? floatval($col('Amount')) : null,
                    'currency'             => $col('Currency'),
                    'product_sku'          => $col('Product SKU'),
                    'offer_sku'            => $col('Offer SKU') ?? $col('Supplier SKU'),
                    'brand'                => $col('Brand'),
                    'category_code'        => $col('Category code'),
                    'category_label'       => $col('Category label'),
                    'unit_price'           => is_numeric($col('Unit price')) ? floatval($col('Unit price')) : null,
                    'shipping_price'       => is_numeric($col('Shipping price')) ? floatval($col('Shipping price')) : null,
                    'commission_rule_name' => $col('Commission rule name'),
                    'commission_excl_tax'  => is_numeric($col('Commission (excluding taxes)')) ? floatval($col('Commission (excluding taxes)')) : null,
                    'commission_incl_tax'  => is_numeric($col('Commission value (including taxes)')) ? floatval($col('Commission value (including taxes)')) : null,
                    'amount_transferred'   => is_numeric($col('Amount transferred to supplier (including taxes)')) ? floatval($col('Amount transferred to supplier (including taxes)')) : null,
                    'shipping_company'     => $col('Shipping company'),
                    'tracking_number'      => $col('Tracking number'),
                    'tracking_url'         => $col('Tracking URL'),
                    'customer_first_name'  => $col('Shipping address first name'),
                    'customer_last_name'   => $col('Shipping address last name'),
                    'customer_city'        => $col('Shipping address city'),
                    'customer_state'       => $col('Shipping address state'),
                    'customer_country'     => $col('Shipping address country'),
                    'order_id'             => $col('OrderID'),
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ];
                $imported++;

                if (count($batch) >= 100) {
                    DB::table('purchasing_power_sales')->insert($batch);
                    $batch = [];
                }
            }

            if (!empty($batch)) {
                DB::table('purchasing_power_sales')->insert($batch);
            }

            Log::info("Purchasing Power sales uploaded: {$imported} rows, {$skipped} skipped");

            return response()->json([
                'success'  => true,
                'imported' => $imported,
                'skipped'  => $skipped,
                'message'  => "Successfully imported {$imported} orders ({$skipped} skipped)",
            ]);

        } catch (\Exception $e) {
            Log::error('PP sales upload error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
